<?php

namespace App\Services;

use App\Models\{AssistanceApplication, AssistanceDistribution, AssistanceStatusHistory, SupplyBatch};
use App\Notifications\AssistanceStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistanceService
{
    public function status(AssistanceApplication $application, string $status, int $userId, ?string $notes = null, ?float $quantity = null, ?int $supplyItemId = null): AssistanceApplication
    {
        $allowed = ['submitted'=>['approved','rejected','returned'], 'returned'=>['approved','rejected']];
        if (! in_array($status, $allowed[$application->status] ?? [], true)) throw ValidationException::withMessages(['status'=>'Invalid application transition.']);
        if (in_array($status, ['rejected','returned'], true) && blank($notes)) throw ValidationException::withMessages(['review_notes'=>'A reason is required.']);
        if ($status === 'approved' && ($quantity === null || $quantity <= 0 || ! $supplyItemId) && $application->program->supply_item_id) throw ValidationException::withMessages(['approved_quantity'=>'Approve with a positive quantity and an approved supply item.']);
        if ($status === 'approved' && $application->program->allocation_limit !== null && $quantity > (float) $application->program->allocation_limit) throw ValidationException::withMessages(['approved_quantity'=>'Approved quantity exceeds the program beneficiary limit.']);
        return DB::transaction(function () use ($application,$status,$userId,$notes,$quantity,$supplyItemId) {
            $old = $application->status;
            $attributes = ['status'=>$status,'reviewed_by'=>$userId,'review_notes'=>$notes];
            if ($status === 'approved') $attributes += ['approved_quantity'=>$quantity,'approved_supply_item_id'=>$supplyItemId,'allocation_confirmed_at'=>now()];
            $application->update($attributes);
            AssistanceStatusHistory::create(['application_id'=>$application->id,'from_status'=>$old,'to_status'=>$status,'changed_by'=>$userId,'notes'=>$notes]);
            $application->farmer?->user?->notify(new AssistanceStatusNotification('Your assistance application is '.$status.'.'));
            return $application->fresh();
        });
    }
    public function distribute(AssistanceApplication $application, array $data, int $userId, InventoryService $inventory): AssistanceDistribution
    {
        return DB::transaction(function () use ($application,$data,$userId,$inventory) {
            $key = $data['idempotency_key'] ?? ('aasms-'.$application->id.'-'.$data['supply_batch_id']);
            if ($existing = AssistanceDistribution::where('operation_key', $key)->first()) return $existing;
            if ($application->status !== 'approved') throw ValidationException::withMessages(['application_id'=>'Only approved applications may be distributed.']);
            if ($application->distributions()->exists()) throw ValidationException::withMessages(['application_id'=>'This application already has a committed single distribution.']);
            if (($application->approved_quantity === null || ! $application->approved_supply_item_id) && $application->eligibility_passed !== null) throw ValidationException::withMessages(['application_id'=>'An authorized allocation confirmation is required before distribution.']);
            if ($application->approved_quantity !== null && (float) $data['quantity'] !== (float) $application->approved_quantity) throw ValidationException::withMessages(['quantity'=>'This workflow requires the complete approved allocation in one distribution.']);
            $batch = SupplyBatch::findOrFail($data['supply_batch_id']);
            if ($application->approved_supply_item_id && (int) $batch->supply_item_id !== (int) $application->approved_supply_item_id) throw ValidationException::withMessages(['supply_batch_id'=>'Selected batch does not match the approved supply item.']);
            $movement = $inventory->release(['supply_batch_id'=>$batch->id,'quantity'=>$data['quantity'],'recipient'=>$application->farmer_id,'purpose'=>'AASMS application '.$application->id,'idempotency_key'=>$key], $userId);
            $distribution = AssistanceDistribution::create(['application_id'=>$application->id,'supply_batch_id'=>$batch->id,'reference'=>$movement->reference,'operation_key'=>$key,'quantity'=>$data['quantity'],'status'=>'distributed','processed_by'=>$userId,'distributed_at'=>now()]);
            $application->update(['status'=>'completed']);
            $application->farmer?->user?->notify(new AssistanceStatusNotification('Your assistance distribution has been completed.'));
            return $distribution;
        });
    }
}
