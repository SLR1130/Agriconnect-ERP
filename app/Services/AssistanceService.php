<?php

namespace App\Services;

use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceStatusHistory;
use App\Models\SupplyBatch;
use App\Notifications\AssistanceStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistanceService
{
    public function status(AssistanceApplication $application, string $status, int $userId, ?string $notes = null, ?float $quantity = null, ?int $supplyItemId = null): AssistanceApplication
    {
        return DB::transaction(function () use ($application, $status, $userId, $notes, $quantity, $supplyItemId) {
            $lockedApplication = AssistanceApplication::query()
                ->with('program')
                ->lockForUpdate()
                ->findOrFail($application->id);
            $allowed = ['submitted' => ['approved', 'rejected', 'returned'], 'returned' => ['approved', 'rejected']];
            if (! in_array($status, $allowed[$lockedApplication->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Invalid application transition.']);
            }
            if (in_array($status, ['rejected', 'returned'], true) && blank($notes)) {
                throw ValidationException::withMessages(['review_notes' => 'A reason is required.']);
            }
            if ($status === 'approved' && ($quantity === null || $quantity <= 0 || ! $supplyItemId) && $lockedApplication->program->supply_item_id) {
                throw ValidationException::withMessages(['approved_quantity' => 'Approve with a positive quantity and an approved supply item.']);
            }
            if ($status === 'approved' && $lockedApplication->program->allocation_limit !== null && $quantity > (float) $lockedApplication->program->allocation_limit) {
                throw ValidationException::withMessages(['approved_quantity' => 'Approved quantity exceeds the program beneficiary limit.']);
            }

            $old = $lockedApplication->status;
            $attributes = ['status' => $status, 'reviewed_by' => $userId, 'review_notes' => $notes];
            if ($status === 'approved') {
                $attributes += ['approved_quantity' => $quantity, 'approved_supply_item_id' => $supplyItemId, 'allocation_confirmed_at' => now()];
            }
            $lockedApplication->update($attributes);
            AssistanceStatusHistory::create(['application_id' => $lockedApplication->id, 'from_status' => $old, 'to_status' => $status, 'changed_by' => $userId, 'notes' => $notes]);
            $lockedApplication->farmer?->user?->notify(new AssistanceStatusNotification($lockedApplication->id, $status, $notes));

            return $lockedApplication->fresh();
        });
    }

    public function distribute(AssistanceApplication $application, array $data, int $userId, InventoryService $inventory): AssistanceDistribution
    {
        return DB::transaction(function () use ($application, $data, $userId, $inventory) {
            $lockedApplication = AssistanceApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);
            $key = $data['idempotency_key'] ?? ('aasms-'.$application->id.'-'.$data['supply_batch_id']);
            if ($existing = AssistanceDistribution::where('operation_key', $key)->first()) {
                if ((int) $existing->application_id !== (int) $lockedApplication->id
                    || (int) $existing->supply_batch_id !== (int) $data['supply_batch_id']
                    || (float) $existing->quantity !== (float) $data['quantity']) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This retry reference was already used for a different distribution.']);
                }

                return $existing;
            }
            if ($lockedApplication->status !== 'approved') {
                throw ValidationException::withMessages(['application_id' => 'Only approved applications may be distributed.']);
            }
            if ($lockedApplication->distributions()->exists()) {
                throw ValidationException::withMessages(['application_id' => 'This application already has a committed single distribution.']);
            }
            if (($lockedApplication->approved_quantity === null || ! $lockedApplication->approved_supply_item_id) && $lockedApplication->eligibility_passed !== null) {
                throw ValidationException::withMessages(['application_id' => 'An authorized allocation confirmation is required before distribution.']);
            }
            if ($lockedApplication->approved_quantity !== null && (float) $data['quantity'] !== (float) $lockedApplication->approved_quantity) {
                throw ValidationException::withMessages(['quantity' => 'This workflow requires the complete approved allocation in one distribution.']);
            }
            $batch = SupplyBatch::findOrFail($data['supply_batch_id']);
            if ($lockedApplication->approved_supply_item_id && (int) $batch->supply_item_id !== (int) $lockedApplication->approved_supply_item_id) {
                throw ValidationException::withMessages(['supply_batch_id' => 'Selected batch does not match the approved supply item.']);
            }
            $movement = $inventory->release(['supply_batch_id' => $batch->id, 'quantity' => $data['quantity'], 'recipient' => $lockedApplication->farmer_id, 'purpose' => 'AASMS application '.$lockedApplication->id, 'idempotency_key' => $key], $userId);
            $distribution = AssistanceDistribution::create(['application_id' => $lockedApplication->id, 'supply_batch_id' => $batch->id, 'reference' => $movement->reference, 'operation_key' => $key, 'quantity' => $data['quantity'], 'status' => 'distributed', 'processed_by' => $userId, 'distributed_at' => now()]);
            $previousStatus = $lockedApplication->status;
            $lockedApplication->update(['status' => 'completed']);
            AssistanceStatusHistory::create([
                'application_id' => $lockedApplication->id,
                'from_status' => $previousStatus,
                'to_status' => 'completed',
                'changed_by' => $userId,
                'notes' => 'Distribution '.$distribution->reference.' completed.',
            ]);
            $lockedApplication->farmer?->user?->notify(new AssistanceStatusNotification($lockedApplication->id, 'completed', null, $distribution->reference));

            return $distribution;
        });
    }
}
