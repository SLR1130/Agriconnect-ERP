<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\EquipmentAsset;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentRequest;
use App\Models\EquipmentTransaction;
use App\Models\Farmer;
use App\Models\User;
use App\Notifications\EquipmentRequestStatusChanged;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    public function registerAsset(array $data, User $actor): EquipmentAsset
    {
        return DB::transaction(function () use ($data, $actor) {
            $asset = EquipmentAsset::create($data);
            $this->record($asset, null, 'registered', $actor, $asset->condition, 'Equipment asset registered.');
            ActivityLog::log('created', 'Equipment asset '.$asset->asset_code.' registered.', $asset->asset_code, $actor->id, 'aeams');

            return $asset->load('category');
        });
    }

    public function updateAsset(EquipmentAsset $asset, array $data, User $actor): EquipmentAsset
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $locked = EquipmentAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if ($locked->availability_status === 'issued') {
                throw ValidationException::withMessages(['asset' => 'Issued equipment must be returned before its master record can be edited.']);
            }
            $old = $locked->only(array_keys($data));
            $locked->update($data);
            $this->record($locked, null, 'updated', $actor, $locked->condition, 'Equipment asset details updated.');
            ActivityLog::log('updated', 'Equipment asset '.$locked->asset_code.' updated.', $locked->asset_code, $actor->id, 'aeams', $old, $locked->fresh()->only(array_keys($data)));

            return $locked->refresh()->load('category');
        });
    }

    public function archiveAsset(EquipmentAsset $asset, User $actor): EquipmentAsset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $locked = EquipmentAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if ($locked->availability_status === 'issued') {
                throw ValidationException::withMessages(['asset' => 'Issued equipment cannot be archived.']);
            }
            if ($locked->maintenances()->whereIn('status', ['open', 'in_progress'])->exists()) {
                throw ValidationException::withMessages(['asset' => 'Complete open maintenance before archiving this asset.']);
            }
            if ($locked->requests()->whereIn('status', ['pending', 'approved', 'issued'])->exists()) {
                throw ValidationException::withMessages(['asset' => 'An asset with active requests cannot be archived.']);
            }
            $locked->update(['availability_status' => 'archived']);
            $this->record($locked, null, 'archived', $actor, $locked->condition, 'Equipment asset archived.');
            ActivityLog::log('archived', 'Equipment asset '.$locked->asset_code.' archived.', $locked->asset_code, $actor->id, 'aeams');

            return $locked->refresh();
        });
    }

    public function restoreAsset(EquipmentAsset $asset, User $actor): EquipmentAsset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $locked = EquipmentAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if ($locked->availability_status !== 'archived') {
                throw ValidationException::withMessages(['asset' => 'Only archived assets can be restored.']);
            }
            $locked->update(['availability_status' => 'available']);
            $this->record($locked, null, 'restored', $actor, $locked->condition, 'Equipment asset restored to available inventory.');
            ActivityLog::log('updated', 'Equipment asset '.$locked->asset_code.' restored.', $locked->asset_code, $actor->id, 'aeams');

            return $locked->refresh();
        });
    }

    public function requestForUser(User $actor, array $data): EquipmentRequest
    {
        $farmer = $actor->farmer;

        if (! $farmer || ! $farmer->isVerified()) {
            throw ValidationException::withMessages([
                'farmer' => 'Only verified farmer records may submit equipment requests.',
            ]);
        }

        return $this->requestForFarmer($farmer, $actor, $data);
    }

    public function requestForFarmer(Farmer $farmer, User $actor, array $data): EquipmentRequest
    {
        if (! $farmer->isVerified()) {
            throw ValidationException::withMessages([
                'farmer' => 'Only verified farmer records may submit equipment requests.',
            ]);
        }

        $start = Carbon::parse($data['schedule_start']);
        $end = Carbon::parse($data['schedule_end']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'schedule_end' => 'The requested end time must be after the start time.',
            ]);
        }

        return DB::transaction(function () use ($farmer, $actor, $data, $start, $end) {
            if (! empty($data['idempotency_key'])) {
                $existing = EquipmentRequest::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();

                if ($existing) {
                    if ((int) $existing->requested_by !== (int) $actor->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This request key belongs to another account.',
                        ]);
                    }

                    return $existing;
                }
            }

            $asset = EquipmentAsset::query()->lockForUpdate()->findOrFail($data['equipment_asset_id']);
            $this->ensureServiceable($asset);
            $this->ensureNoConflict($asset->id, $start, $end);

            $request = EquipmentRequest::create([
                'reference' => $this->reference(),
                'farmer_id' => $farmer->id,
                'requested_by' => $actor->id,
                'equipment_asset_id' => $asset->id,
                'intended_use' => $data['intended_use'],
                'schedule_start' => $start,
                'schedule_end' => $end,
                'status' => 'pending',
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $this->record($asset, $request, 'requested', $actor, null, 'Equipment request submitted.');
            ActivityLog::log('created', 'Equipment request '.$request->reference.' submitted.', $request->reference, $actor->id, 'aeams');

            return $request->load('asset');
        });
    }

    public function review(EquipmentRequest $equipmentRequest, string $status, ?string $notes, User $actor): EquipmentRequest
    {
        if (! in_array($status, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported equipment request review status.']);
        }

        if ($status === 'rejected' && blank($notes)) {
            throw ValidationException::withMessages(['review_notes' => 'A rejection reason is required.']);
        }

        return DB::transaction(function () use ($equipmentRequest, $status, $notes, $actor) {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->id);

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Only pending equipment requests can be reviewed.',
                ]);
            }

            $asset = EquipmentAsset::query()->lockForUpdate()->findOrFail($request->equipment_asset_id);

            if ($status === 'approved') {
                $this->ensureServiceable($asset);
                $this->ensureNoConflict($asset->id, $request->schedule_start, $request->schedule_end, $request->id);
            }

            $request->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->record($asset, $request, $status, $actor, null, $notes);
            ActivityLog::log($status, 'Equipment request '.$request->reference.' '.$status.'.', $request->reference, $actor->id, 'aeams');
            $request->farmer->user->notify(new EquipmentRequestStatusChanged($request->reference, $status));

            return $request->refresh();
        });
    }

    public function cancel(EquipmentRequest $equipmentRequest, User $actor): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest, $actor) {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->id);

            if ((int) $request->requested_by !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'request' => 'You may only cancel your own equipment requests.',
                ]);
            }
            if (! in_array($request->status, ['pending', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending or approved equipment requests can be cancelled.',
                ]);
            }

            $request->update([
                'status' => 'cancelled',
                'review_notes' => 'Cancelled by the requesting farmer.',
            ]);
            $asset = EquipmentAsset::findOrFail($request->equipment_asset_id);
            $this->record($asset, $request, 'cancelled', $actor, null, 'Request cancelled by the requesting farmer.');
            ActivityLog::log('updated', 'Equipment request '.$request->reference.' cancelled.', $request->reference, $actor->id, 'aeams');

            return $request->refresh();
        });
    }

    public function issue(EquipmentRequest $equipmentRequest, string $condition, User $actor): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest, $condition, $actor) {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->id);

            if ($request->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Only approved equipment requests can be issued.',
                ]);
            }

            $asset = EquipmentAsset::query()->lockForUpdate()->findOrFail($request->equipment_asset_id);
            $this->ensureServiceable($asset);
            $this->ensureNoConflict($asset->id, $request->schedule_start, $request->schedule_end, $request->id);

            $request->update([
                'status' => 'issued',
                'issued_at' => now(),
                'checkout_condition' => $condition,
            ]);
            $asset->update([
                'condition' => $condition,
                'availability_status' => 'issued',
            ]);

            $this->record($asset, $request, 'issued', $actor, $condition, 'Equipment issued to the verified farmer.');
            ActivityLog::log('updated', 'Equipment request '.$request->reference.' issued.', $request->reference, $actor->id, 'aeams');
            $request->farmer->user->notify(new EquipmentRequestStatusChanged($request->reference, 'issued'));

            return $request->refresh();
        });
    }

    public function return(EquipmentRequest $equipmentRequest, string $condition, bool $needsMaintenance, ?string $notes, User $actor): EquipmentRequest
    {
        return DB::transaction(function () use ($equipmentRequest, $condition, $needsMaintenance, $notes, $actor) {
            $request = EquipmentRequest::query()->lockForUpdate()->findOrFail($equipmentRequest->id);

            if ($request->status !== 'issued') {
                throw ValidationException::withMessages([
                    'status' => 'Only issued equipment can be returned.',
                ]);
            }

            $asset = EquipmentAsset::query()->lockForUpdate()->findOrFail($request->equipment_asset_id);
            $request->update([
                'status' => 'returned',
                'returned_at' => now(),
                'return_condition' => $condition,
            ]);
            $asset->update([
                'condition' => $condition,
                'availability_status' => $needsMaintenance ? 'maintenance' : 'available',
            ]);

            if ($needsMaintenance) {
                EquipmentMaintenance::create([
                    'equipment_asset_id' => $asset->id,
                    'reported_by' => $actor->id,
                    'status' => 'open',
                    'description' => $notes ?: 'Maintenance required after equipment return.',
                    'started_at' => now(),
                ]);
            }

            $this->record($asset, $request, 'returned', $actor, $condition, $notes);
            ActivityLog::log('updated', 'Equipment request '.$request->reference.' returned.', $request->reference, $actor->id, 'aeams');
            $request->farmer->user->notify(new EquipmentRequestStatusChanged($request->reference, 'returned'));

            return $request->refresh();
        });
    }

    public function startMaintenance(EquipmentAsset $asset, array $data, User $actor): EquipmentMaintenance
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $lockedAsset = EquipmentAsset::query()->lockForUpdate()->findOrFail($asset->id);

            if ($lockedAsset->availability_status === 'issued') {
                throw ValidationException::withMessages([
                    'equipment_asset_id' => 'Issued equipment must be returned before maintenance can begin.',
                ]);
            }
            if ($lockedAsset->maintenances()->whereIn('status', ['open', 'in_progress'])->exists()) {
                throw ValidationException::withMessages([
                    'equipment_asset_id' => 'This equipment already has an open maintenance record.',
                ]);
            }

            $maintenance = EquipmentMaintenance::create([
                'equipment_asset_id' => $lockedAsset->id,
                'reported_by' => $actor->id,
                'status' => 'open',
                'description' => $data['description'],
                'cost' => $data['cost'] ?? null,
                'started_at' => now(),
            ]);
            $lockedAsset->update(['availability_status' => 'maintenance']);

            $this->record($lockedAsset, null, 'maintenance_started', $actor, $lockedAsset->condition, $maintenance->description);

            return $maintenance->load('asset');
        });
    }

    public function completeMaintenance(EquipmentMaintenance $maintenance, ?string $condition, User $actor): EquipmentMaintenance
    {
        return DB::transaction(function () use ($maintenance, $condition, $actor) {
            $lockedMaintenance = EquipmentMaintenance::query()->lockForUpdate()->findOrFail($maintenance->id);

            if (in_array($lockedMaintenance->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This maintenance record is already closed.',
                ]);
            }

            $asset = EquipmentAsset::query()->lockForUpdate()->findOrFail($lockedMaintenance->equipment_asset_id);
            $lockedMaintenance->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $hasAnotherOpenRecord = EquipmentMaintenance::query()
                ->where('equipment_asset_id', $asset->id)
                ->whereIn('status', ['open', 'in_progress'])
                ->where('id', '<>', $lockedMaintenance->id)
                ->exists();
            $asset->update([
                'condition' => $condition ?: $asset->condition,
                'availability_status' => $hasAnotherOpenRecord ? 'maintenance' : 'available',
            ]);

            $this->record($asset, null, 'maintenance_completed', $actor, $asset->condition, 'Maintenance completed.');

            return $lockedMaintenance->refresh();
        });
    }

    private function ensureServiceable(EquipmentAsset $asset): void
    {
        if ($asset->availability_status !== 'available') {
            throw ValidationException::withMessages([
                'equipment_asset_id' => 'This equipment is not currently available.',
            ]);
        }

        if ($asset->maintenances()->whereIn('status', ['open', 'in_progress'])->exists()) {
            throw ValidationException::withMessages([
                'equipment_asset_id' => 'Equipment under maintenance cannot be requested or issued.',
            ]);
        }
    }

    private function ensureNoConflict(int $assetId, Carbon|string $start, Carbon|string $end, ?int $ignoreRequestId = null): void
    {
        $query = EquipmentRequest::query()
            ->where('equipment_asset_id', $assetId)
            ->whereIn('status', ['pending', 'approved', 'issued'])
            ->where('schedule_start', '<', $end)
            ->where('schedule_end', '>', $start);

        if ($ignoreRequestId) {
            $query->whereKeyNot($ignoreRequestId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'schedule_start' => 'This equipment already has an overlapping active reservation.',
            ]);
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'AEAMS-'.now()->format('Ymd').'-'.Str::upper(Str::random(7));
        } while (EquipmentRequest::where('reference', $reference)->exists());

        return $reference;
    }

    private function record(EquipmentAsset $asset, ?EquipmentRequest $request, string $action, User $actor, ?string $condition, ?string $notes): void
    {
        EquipmentTransaction::create([
            'equipment_asset_id' => $asset->id,
            'equipment_request_id' => $request?->id,
            'performed_by' => $actor->id,
            'action' => $action,
            'condition' => $condition,
            'notes' => $notes,
        ]);
    }
}
