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
                $expiresOn = filled($data['expires_on'] ?? null)
                    ? Carbon::parse($data['expires_on'])->toDateString()
                    : null;

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
                        'expires_on' => $expiresOn,
                        'quantity_received' => '0.00',
                        'quantity_available' => '0.00',
                        'status' => 'available',
                    ],
                );
                if ($batch->status !== 'available') {
                    throw ValidationException::withMessages(['batch_number' => 'Stock cannot be added to a batch that is not available.']);
                }

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

    private function existingMovement(?string $key): ?InventoryMovement
    {
        if (blank($key)) {
            return null;
        }

        return InventoryMovement::query()->with('batch')->where('idempotency_key', $key)->first();
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
            && optional($batch?->expires_on)->toDateString() === (filled($data['expires_on'] ?? null) ? Carbon::parse($data['expires_on'])->toDateString() : null)
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
