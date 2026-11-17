<?php

namespace App\Services;

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\SupplyBatch;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function receive(array $data, int $userId): InventoryMovement
    {
        try {
            return DB::transaction(function () use ($data, $userId) {
                $existing = $this->existingMovement($data['idempotency_key'] ?? null);
                if ($existing) {
                    return $this->assertSameReceipt($existing, $data, $userId);
                }

                $receivedOn = Carbon::parse($data['received_on'])->toDateString();
                $manufacturedOn = filled($data['manufactured_on'] ?? null)
                    ? Carbon::parse($data['manufactured_on'])->toDateString()
                    : null;
                $expiresOn = filled($data['expires_on'] ?? null)
                    ? Carbon::parse($data['expires_on'])->toDateString()
                    : null;

                if ($manufacturedOn && $manufacturedOn > $receivedOn) {
                    throw ValidationException::withMessages([
                        'manufactured_on' => 'The manufacturing date cannot be later than the receipt date.',
                    ]);
                }

                if ($expiresOn && $expiresOn < $receivedOn) {
                    throw ValidationException::withMessages([
                        'expires_on' => 'The expiration date cannot be earlier than the receipt date.',
                    ]);
                }

                $locationIsActive = InventoryLocation::query()
                    ->whereKey($data['location_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->exists();

                if (! $locationIsActive) {
                    throw ValidationException::withMessages([
                        'location_id' => 'The selected inventory location is inactive.',
                    ]);
                }

                $quantityCents = $this->decimalCents($data['quantity'] ?? null);
                if ($quantityCents <= 0) {
                    throw ValidationException::withMessages(['quantity' => 'Receipt quantity must be greater than zero.']);
                }

                $batch = SupplyBatch::query()->lockForUpdate()->firstOrCreate(
                    [
                        'supply_item_id' => $data['supply_item_id'],
                        'location_id' => $data['location_id'],
                        'batch_number' => $data['batch_number'],
                    ],
                    [
                        'received_on' => $receivedOn,
                        'manufactured_on' => $manufacturedOn,
                        'expires_on' => $expiresOn,
                        'quantity_received' => '0.00',
                        'quantity_available' => '0.00',
                        'supplier_id' => $data['supplier_id'] ?? null,
                        'unit_cost' => filled($data['unit_cost'] ?? null)
                            ? $this->money($this->decimalCents($data['unit_cost']))
                            : null,
                        'status' => 'available',
                    ],
                );
                if ($batch->status !== 'available') {
                    throw ValidationException::withMessages(['batch_number' => 'Stock cannot be added to a batch that is not available.']);
                }

                $this->mergeReceiptMetadata($batch, $data, $manufacturedOn, $expiresOn);

                $batch->update([
                    'quantity_received' => $this->money($this->decimalCents($batch->quantity_received) + $quantityCents),
                    'quantity_available' => $this->money($this->decimalCents($batch->quantity_available) + $quantityCents),
                ]);

                return InventoryMovement::create([
                    'reference' => 'RCV-'.str()->upper(str()->random(16)),
                    'supply_batch_id' => $batch->id,
                    'user_id' => $userId,
                    'movement_type' => 'receipt',
                    'quantity' => $this->money($quantityCents),
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverDuplicate($exception, $data['idempotency_key'] ?? null, fn (InventoryMovement $movement) => $this->assertSameReceipt($movement, $data, $userId));
        }
    }

    public function release(array $data, int $userId): InventoryMovement
    {
        try {
            return DB::transaction(function () use ($data, $userId) {
                $existing = $this->existingMovement($data['idempotency_key'] ?? null);
                if ($existing) {
                    return $this->assertSameRelease($existing, $data, $userId);
                }

                $batch = SupplyBatch::query()->lockForUpdate()->findOrFail($data['supply_batch_id']);
                if (isset($data['expected_supply_item_id'])
                    && (int) $batch->supply_item_id !== (int) $data['expected_supply_item_id']) {
                    throw ValidationException::withMessages(['supply_batch_id' => 'The selected batch does not match the approved supply item.']);
                }
                if ($batch->status !== 'available') {
                    throw ValidationException::withMessages(['supply_batch_id' => 'This inventory batch is not available for release.']);
                }
                if ($batch->expires_on?->lt(today())) {
                    throw ValidationException::withMessages(['supply_batch_id' => 'Expired batches cannot be released.']);
                }

                $quantityCents = $this->decimalCents($data['quantity'] ?? null);
                $availableCents = $this->decimalCents($batch->quantity_available);
                if ($quantityCents <= 0 || $availableCents < $quantityCents) {
                    throw ValidationException::withMessages(['quantity' => 'Insufficient available stock.']);
                }

                $batch->update(['quantity_available' => $this->money($availableCents - $quantityCents)]);

                return InventoryMovement::create([
                    'reference' => 'REL-'.str()->upper(str()->random(16)),
                    'supply_batch_id' => $batch->id,
                    'user_id' => $userId,
                    'movement_type' => 'release',
                    'quantity' => $this->money($quantityCents),
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'recipient' => $data['recipient'],
                    'purpose' => $data['purpose'],
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverDuplicate($exception, $data['idempotency_key'] ?? null, fn (InventoryMovement $movement) => $this->assertSameRelease($movement, $data, $userId));
        }
    }

    public function adjust(array $data, int $userId): InventoryMovement
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An adjustment retry key is required.']);
        }

        if (blank(trim((string) ($data['adjustment_reason'] ?? '')))) {
            throw ValidationException::withMessages(['adjustment_reason' => 'An adjustment reason is required.']);
        }

        try {
            return DB::transaction(function () use ($data, $userId, $key) {
                $existing = $this->existingMovement($key);
                if ($existing) {
                    return $this->assertSameAdjustment($existing, $data, $userId);
                }

                $batch = SupplyBatch::query()->lockForUpdate()->findOrFail($data['supply_batch_id']);
                $quantityCents = $this->decimalCents($data['quantity'] ?? null);
                $newAvailableCents = $this->decimalCents($batch->quantity_available) + $quantityCents;

                if ($quantityCents === 0) {
                    throw ValidationException::withMessages(['quantity' => 'Adjustment quantity cannot be zero.']);
                }

                if ($newAvailableCents < 0) {
                    throw ValidationException::withMessages(['quantity' => 'Adjustment would create negative stock.']);
                }

                $batch->update(['quantity_available' => $this->money($newAvailableCents)]);

                return InventoryMovement::create([
                    'reference' => 'ADJ-'.str()->upper(str()->random(16)),
                    'supply_batch_id' => $batch->id,
                    'user_id' => $userId,
                    'movement_type' => 'adjustment',
                    'quantity' => $this->money($quantityCents),
                    'idempotency_key' => $key,
                    'adjustment_reason' => trim($data['adjustment_reason']),
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverDuplicate($exception, $key, fn (InventoryMovement $movement) => $this->assertSameAdjustment($movement, $data, $userId));
        }
    }

    public function transfer(array $data, int $userId): InventoryMovement
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'A transfer retry key is required.']);
        }

        try {
            return DB::transaction(function () use ($data, $userId, $key) {
                $existing = $this->existingMovement($key);
                if ($existing) {
                    return $this->assertSameTransfer($existing, $data, $userId);
                }

                $source = SupplyBatch::query()->lockForUpdate()->findOrFail($data['supply_batch_id']);
                $destination = InventoryLocation::query()->whereKey($data['destination_location_id'])
                    ->where('is_active', true)->lockForUpdate()->first();

                if (! $destination) {
                    throw ValidationException::withMessages(['destination_location_id' => 'The destination location is inactive.']);
                }
                if ((int) $source->location_id === (int) $destination->id) {
                    throw ValidationException::withMessages(['destination_location_id' => 'Choose a different destination location.']);
                }
                if ($source->status !== 'available') {
                    throw ValidationException::withMessages(['supply_batch_id' => 'Only available batches can be transferred.']);
                }
                if ($source->expires_on?->lt(today())) {
                    throw ValidationException::withMessages(['supply_batch_id' => 'Expired batches cannot be transferred.']);
                }

                $quantityCents = $this->decimalCents($data['quantity'] ?? null);
                $availableCents = $this->decimalCents($source->quantity_available);
                if ($quantityCents <= 0 || $availableCents < $quantityCents) {
                    throw ValidationException::withMessages(['quantity' => 'Insufficient available stock for this transfer.']);
                }

                $target = SupplyBatch::query()->lockForUpdate()->firstOrCreate(
                    [
                        'supply_item_id' => $source->supply_item_id,
                        'location_id' => $destination->id,
                        'batch_number' => $source->batch_number,
                    ],
                    [
                        'supplier_id' => $source->supplier_id,
                        'received_on' => today()->toDateString(),
                        'manufactured_on' => $source->manufactured_on?->toDateString(),
                        'expires_on' => $source->expires_on?->toDateString(),
                        'quantity_received' => '0.00',
                        'quantity_available' => '0.00',
                        'unit_cost' => $source->unit_cost,
                        'status' => 'available',
                    ],
                );

                if ($target->status !== 'available') {
                    throw ValidationException::withMessages(['destination_location_id' => 'The matching destination batch is not available.']);
                }

                $this->mergeReceiptMetadata($target, [
                    'supplier_id' => $source->supplier_id,
                    'unit_cost' => $source->unit_cost,
                ], $source->manufactured_on?->toDateString(), $source->expires_on?->toDateString());

                $source->update(['quantity_available' => $this->money($availableCents - $quantityCents)]);
                $target->update([
                    'quantity_received' => $this->money($this->decimalCents($target->quantity_received) + $quantityCents),
                    'quantity_available' => $this->money($this->decimalCents($target->quantity_available) + $quantityCents),
                ]);

                return InventoryMovement::create([
                    'reference' => 'TRF-'.str()->upper(str()->random(16)),
                    'supply_batch_id' => $source->id,
                    'related_batch_id' => $target->id,
                    'user_id' => $userId,
                    'movement_type' => 'transfer',
                    'quantity' => $this->money($quantityCents),
                    'idempotency_key' => $key,
                    'recipient' => $destination->name,
                    'purpose' => 'Warehouse transfer',
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverDuplicate($exception, $key, fn (InventoryMovement $movement) => $this->assertSameTransfer($movement, $data, $userId));
        }
    }

    public function dispose(array $data, int $userId): InventoryMovement
    {
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'A disposal retry key is required.']);
        }
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A disposal reason is required.']);
        }

        try {
            return DB::transaction(function () use ($data, $userId, $key, $reason) {
                $existing = $this->existingMovement($key);
                if ($existing) {
                    return $this->assertSameDisposal($existing, $data, $userId, $reason);
                }

                $batch = SupplyBatch::query()->lockForUpdate()->findOrFail($data['supply_batch_id']);
                $availableCents = $this->decimalCents($batch->quantity_available);
                if ($availableCents <= 0) {
                    throw ValidationException::withMessages(['supply_batch_id' => 'This batch has no stock left to dispose.']);
                }

                $batch->update(['quantity_available' => '0.00', 'status' => 'disposed']);

                return InventoryMovement::create([
                    'reference' => 'DSP-'.str()->upper(str()->random(16)),
                    'supply_batch_id' => $batch->id,
                    'user_id' => $userId,
                    'movement_type' => 'adjustment',
                    'quantity' => $this->money(-$availableCents),
                    'idempotency_key' => $key,
                    'adjustment_reason' => 'Disposal: '.$reason,
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverDuplicate($exception, $key, fn (InventoryMovement $movement) => $this->assertSameDisposal($movement, $data, $userId, $reason));
        }
    }

    private function existingMovement(?string $key): ?InventoryMovement
    {
        if (blank($key)) {
            return null;
        }

        return InventoryMovement::query()->with(['batch', 'relatedBatch'])->where('idempotency_key', $key)->first();
    }

    private function recoverDuplicate(UniqueConstraintViolationException $exception, ?string $key, callable $assertSame): InventoryMovement
    {
        $existing = filled($key) ? $this->existingMovement($key) : null;
        if (! $existing) {
            throw $exception;
        }

        return $assertSame($existing);
    }

    private function assertSameReceipt(InventoryMovement $movement, array $data, int $userId): InventoryMovement
    {
        $batch = $movement->batch;
        $same = $movement->movement_type === 'receipt'
            && (int) $movement->user_id === $userId
            && (int) $batch?->supply_item_id === (int) ($data['supply_item_id'] ?? 0)
            && (int) $batch?->location_id === (int) ($data['location_id'] ?? 0)
            && (string) $batch?->batch_number === (string) ($data['batch_number'] ?? '')
            && $this->decimalCents($movement->quantity) === $this->decimalCents($data['quantity'] ?? null)
            && optional($batch?->received_on)->toDateString() === Carbon::parse($data['received_on'])->toDateString()
            && optional($batch?->manufactured_on)->toDateString() === (filled($data['manufactured_on'] ?? null) ? Carbon::parse($data['manufactured_on'])->toDateString() : null)
            && optional($batch?->expires_on)->toDateString() === (filled($data['expires_on'] ?? null) ? Carbon::parse($data['expires_on'])->toDateString() : null)
            && (int) ($batch?->supplier_id ?? 0) === (int) ($data['supplier_id'] ?? 0)
            && $this->nullableMoney($batch?->unit_cost) === $this->nullableMoney($data['unit_cost'] ?? null)
            && $this->normalizedText($movement->notes) === $this->normalizedText($data['notes'] ?? null);

        return $this->returnOrReject($movement, $same);
    }

    private function assertSameRelease(InventoryMovement $movement, array $data, int $userId): InventoryMovement
    {
        $same = $movement->movement_type === 'release'
            && (int) $movement->user_id === $userId
            && (int) $movement->supply_batch_id === (int) ($data['supply_batch_id'] ?? 0)
            && $this->decimalCents($movement->quantity) === $this->decimalCents($data['quantity'] ?? null)
            && $this->normalizedText($movement->recipient) === $this->normalizedText($data['recipient'] ?? null)
            && $this->normalizedText($movement->purpose) === $this->normalizedText($data['purpose'] ?? null)
            && $this->normalizedText($movement->notes) === $this->normalizedText($data['notes'] ?? null);

        return $this->returnOrReject($movement, $same);
    }

    private function assertSameAdjustment(InventoryMovement $movement, array $data, int $userId): InventoryMovement
    {
        $same = $movement->movement_type === 'adjustment'
            && (int) $movement->user_id === $userId
            && (int) $movement->supply_batch_id === (int) ($data['supply_batch_id'] ?? 0)
            && $this->decimalCents($movement->quantity) === $this->decimalCents($data['quantity'] ?? null)
            && trim((string) $movement->adjustment_reason) === trim((string) ($data['adjustment_reason'] ?? ''))
            && $this->normalizedText($movement->notes) === $this->normalizedText($data['notes'] ?? null);

        return $this->returnOrReject($movement, $same);
    }

    private function assertSameTransfer(InventoryMovement $movement, array $data, int $userId): InventoryMovement
    {
        $same = $movement->movement_type === 'transfer'
            && (int) $movement->user_id === $userId
            && (int) $movement->supply_batch_id === (int) ($data['supply_batch_id'] ?? 0)
            && (int) $movement->relatedBatch?->location_id === (int) ($data['destination_location_id'] ?? 0)
            && $this->decimalCents($movement->quantity) === $this->decimalCents($data['quantity'] ?? null)
            && $this->normalizedText($movement->notes) === $this->normalizedText($data['notes'] ?? null);

        return $this->returnOrReject($movement, $same);
    }

    private function assertSameDisposal(InventoryMovement $movement, array $data, int $userId, string $reason): InventoryMovement
    {
        $same = $movement->movement_type === 'adjustment'
            && (int) $movement->user_id === $userId
            && (int) $movement->supply_batch_id === (int) ($data['supply_batch_id'] ?? 0)
            && (string) $movement->adjustment_reason === 'Disposal: '.$reason
            && $this->normalizedText($movement->notes) === $this->normalizedText($data['notes'] ?? null);

        return $this->returnOrReject($movement, $same);
    }

    private function mergeReceiptMetadata(SupplyBatch $batch, array $data, ?string $manufacturedOn, ?string $expiresOn): void
    {
        $incoming = [
            'supplier_id' => $data['supplier_id'] ?? null,
            'manufactured_on' => $manufacturedOn,
            'expires_on' => $expiresOn,
            'unit_cost' => filled($data['unit_cost'] ?? null) ? $this->money($this->decimalCents($data['unit_cost'])) : null,
        ];
        $updates = [];

        foreach ($incoming as $field => $value) {
            if ($value === null) {
                continue;
            }
            $current = $field === 'manufactured_on' || $field === 'expires_on'
                ? $batch->{$field}?->toDateString()
                : ($field === 'unit_cost' ? $this->nullableMoney($batch->{$field}) : $batch->{$field});
            $comparable = $field === 'unit_cost' ? $this->nullableMoney($value) : $value;

            if ($current !== null && (string) $current !== (string) $comparable) {
                throw ValidationException::withMessages([
                    'batch_number' => 'This batch already exists with different supplier, date, or cost details.',
                ]);
            }
            if ($current === null) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $batch->update($updates);
        }
    }

    private function returnOrReject(InventoryMovement $movement, bool $same): InventoryMovement
    {
        if (! $same) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This retry key was already used for a different inventory operation.',
            ]);
        }

        return $movement;
    }

    private function normalizedText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function nullableMoney(mixed $value): ?string
    {
        return filled($value) ? $this->money($this->decimalCents($value)) : null;
    }

    private function decimalCents(mixed $value): int
    {
        $text = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be a valid amount with up to two decimal places.']);
        }

        $negative = str_starts_with($text, '-');
        $absolute = ltrim($text, '-');
        [$whole, $fraction] = array_pad(explode('.', $absolute, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
