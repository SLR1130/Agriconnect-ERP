<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    public function createPeriod(array $data, User $actor): BudgetPeriod
    {
        return BudgetPeriod::create($data);
    }

    public function createAllocation(array $data, User $actor): BudgetAllocation
    {
        return DB::transaction(function () use ($data, $actor) {
            return BudgetAllocation::create([
                'reference' => $this->reference('BUD'),
                'budget_period_id' => $data['budget_period_id'],
                'program_code' => $data['program_code'],
                'funding_category' => $data['funding_category'],
                'description' => $data['description'],
                'authorized_amount' => $this->money($this->cents($data['authorized_amount'])),
                'status' => 'active',
                'created_by' => $actor->id,
            ]);
        });
    }

    public function adjust(BudgetAllocation $allocation, array $data, User $actor): BudgetTransaction
    {
        $amount = $this->cents($data['amount']);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Adjustment amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($allocation, $data, $actor, $amount) {
            $locked = BudgetAllocation::query()->lockForUpdate()->findOrFail($allocation->id);
            $direction = $data['direction'];

            if ($direction === 'debit') {
                $this->assertAvailable($locked, $amount);
            }

            return $this->ledger($locked, 'adjustment', $direction, $amount, $this->reference('ADJ'), $data['description'], $actor);
        });
    }

    public function assertAvailable(BudgetAllocation $allocation, int $amountCents): void
    {
        $available = $this->summary($allocation)['available_cents'];

        if ($amountCents > $available) {
            throw ValidationException::withMessages([
                'budget_allocation_id' => 'The selected budget allocation does not have enough available funds.',
            ]);
        }
    }

    public function commit(BudgetAllocation $allocation, int $amountCents, PurchaseOrder $purchaseOrder, User $actor): BudgetTransaction
    {
        $locked = BudgetAllocation::query()->lockForUpdate()->findOrFail($allocation->id);
        $this->assertAvailable($locked, $amountCents);

        return $this->ledger(
            $locked,
            'commitment',
            'debit',
            $amountCents,
            'PO-COMMIT-'.$purchaseOrder->purchase_order_number,
            'Purchase order commitment.',
            $actor,
            $purchaseOrder,
        );
    }

    public function releaseCommitment(BudgetAllocation $allocation, int $amountCents, PurchaseOrder $purchaseOrder, User $actor): BudgetTransaction
    {
        return DB::transaction(function () use ($allocation, $amountCents, $purchaseOrder, $actor) {
            $locked = BudgetAllocation::query()->lockForUpdate()->findOrFail($allocation->id);

            return $this->ledger(
                $locked,
                'commitment_release',
                'credit',
                $amountCents,
                'PO-RELEASE-'.$purchaseOrder->purchase_order_number,
                'Cancelled purchase order commitment released.',
                $actor,
                $purchaseOrder,
            );
        });
    }

    public function postExpenditure(BudgetAllocation $allocation, array $data, User $actor): BudgetTransaction
    {
        $amount = $this->cents($data['amount']);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Expenditure amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($allocation, $data, $actor, $amount) {
            $existing = BudgetTransaction::where('reference', $data['reference'])->first();
            if ($existing) {
                if ((int) $existing->budget_allocation_id !== (int) $allocation->id || $existing->entry_type !== 'expenditure') {
                    throw ValidationException::withMessages(['reference' => 'This transaction reference is already in use.']);
                }

                return $existing;
            }

            $locked = BudgetAllocation::query()->lockForUpdate()->findOrFail($allocation->id);
            $this->assertAvailable($locked, $amount);

            return $this->ledger($locked, 'expenditure', 'debit', $amount, $data['reference'], $data['description'], $actor);
        });
    }

    public function summary(BudgetAllocation $allocation): array
    {
        $authorized = $this->cents($allocation->authorized_amount);
        $credits = $this->sum($allocation, 'credit');
        $debits = $this->sum($allocation, 'debit');
        $commitments = $this->sum($allocation, 'debit', 'commitment') - $this->sum($allocation, 'credit', 'commitment_release');
        $actual = $this->sum($allocation, 'debit', 'expenditure');
        $adjustments = $this->sum($allocation, 'credit', 'adjustment') - $this->sum($allocation, 'debit', 'adjustment');

        return [
            'authorized_cents' => $authorized,
            'authorized' => $this->money($authorized),
            'adjustments' => $this->money($adjustments),
            'committed' => $this->money($commitments),
            'actual' => $this->money($actual),
            'available_cents' => $authorized + $credits - $debits,
            'available' => $this->money($authorized + $credits - $debits),
        ];
    }

    /**
     * Calculate the same ledger summary for several allocations with one grouped
     * query, avoiding four or more ledger scans for every dashboard row.
     *
     * @param  Collection<int, BudgetAllocation>  $allocations
     * @return array<int, array<string, int|string>>
     */
    public function summaries(Collection $allocations): array
    {
        if ($allocations->isEmpty()) {
            return [];
        }

        $ledger = BudgetTransaction::query()
            ->whereIn('budget_allocation_id', $allocations->pluck('id'))
            ->select('budget_allocation_id', 'direction', 'entry_type', DB::raw('SUM(amount) as total'))
            ->groupBy('budget_allocation_id', 'direction', 'entry_type')
            ->get()
            ->groupBy('budget_allocation_id');
        $summaries = [];

        foreach ($allocations as $allocation) {
            $entries = $ledger->get($allocation->id, collect());
            $sum = fn (string $direction, ?string $entryType = null): int => $entries
                ->filter(fn (BudgetTransaction $entry) => $entry->direction === $direction
                    && ($entryType === null || $entry->entry_type === $entryType))
                ->sum(fn (BudgetTransaction $entry) => $this->cents((string) $entry->total));

            $authorized = $this->cents((string) $allocation->authorized_amount);
            $credits = $sum('credit');
            $debits = $sum('debit');
            $commitments = $sum('debit', 'commitment') - $sum('credit', 'commitment_release');
            $actual = $sum('debit', 'expenditure');
            $adjustments = $sum('credit', 'adjustment') - $sum('debit', 'adjustment');

            $summaries[$allocation->id] = [
                'authorized_cents' => $authorized,
                'authorized' => $this->money($authorized),
                'adjustments' => $this->money($adjustments),
                'committed' => $this->money($commitments),
                'actual' => $this->money($actual),
                'available_cents' => $authorized + $credits - $debits,
                'available' => $this->money($authorized + $credits - $debits),
            ];
        }

        return $summaries;
    }

    public function cents(string|int|float $amount): int
    {
        $value = trim((string) $amount);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) ($whole ?: 0) * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function lineTotal(string|int|float $quantity, string|int|float $unitPrice): string
    {
        $quantityScaled = $this->cents($quantity);
        $unitPriceCents = $this->cents($unitPrice);
        $totalCents = intdiv(($quantityScaled * $unitPriceCents) + 50, 100);

        return $this->money($totalCents);
    }

    private function sum(BudgetAllocation $allocation, string $direction, ?string $entryType = null): int
    {
        $query = $allocation->transactions()->where('direction', $direction);
        if ($entryType) {
            $query->where('entry_type', $entryType);
        }

        return $this->cents($query->sum('amount'));
    }

    private function ledger(BudgetAllocation $allocation, string $entryType, string $direction, int $amount, string $reference, string $description, User $actor, ?PurchaseOrder $purchaseOrder = null): BudgetTransaction
    {
        return BudgetTransaction::create([
            'budget_allocation_id' => $allocation->id,
            'entry_type' => $entryType,
            'direction' => $direction,
            'amount' => $this->money($amount),
            'reference' => $reference,
            'description' => $description,
            'performed_by' => $actor->id,
            'procurement_request_id' => $purchaseOrder?->procurement_request_id,
            'purchase_order_id' => $purchaseOrder?->id,
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
