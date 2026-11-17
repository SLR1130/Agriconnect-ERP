<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CropProductionRecord;
use App\Models\Farmer;
use App\Models\FisheryRecord;
use App\Models\LivestockRecord;
use App\Models\ProductionSubmission;
use App\Models\User;
use App\Notifications\ProductionSubmissionReviewed;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionSubmissionService
{
    public function submit(Farmer $farmer, User $actor, string $category, int $farmId, array $payload, string $key): ProductionSubmission
    {
        try {
            return DB::transaction(function () use ($farmer, $actor, $category, $farmId, $payload, $key): ProductionSubmission {
                $existing = ProductionSubmission::query()->where('farmer_id', $farmer->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    if ($existing->category !== $category || (int) $existing->farm_id !== $farmId || $existing->payload !== $payload) {
                        throw ValidationException::withMessages(['idempotency_key' => 'This submission reference was already used for different information.']);
                    }

                    return $existing;
                }

                $submission = ProductionSubmission::create([
                    'farmer_id' => $farmer->id, 'farm_id' => $farmId, 'category' => $category,
                    'payload' => $payload, 'status' => 'pending', 'idempotency_key' => $key,
                ]);
                $submission->history()->create(['from_status' => null, 'to_status' => 'pending', 'changed_by' => $actor->id, 'notes' => 'Farmer submitted information for MAO review.']);
                ActivityLog::log('created', 'Farmer production submission received.', 'production-submission:'.$submission->id, $actor->id, 'apms');

                return $submission;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ProductionSubmission::query()->where('farmer_id', $farmer->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->category !== $category || (int) $existing->farm_id !== $farmId || $existing->payload !== $payload) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This submission reference was already used for different information.']);
                }

                return $existing;
            }

            throw $exception;
        }
    }

    public function resubmit(ProductionSubmission $submission, User $actor, int $farmId, array $payload): ProductionSubmission
    {
        return DB::transaction(function () use ($submission, $actor, $farmId, $payload): ProductionSubmission {
            $locked = ProductionSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($actor->hasRole('farmer') && (int) $actor->farmer?->id === (int) $locked->farmer_id, 404);
            if ($locked->status !== 'returned') {
                throw ValidationException::withMessages(['submission' => 'Only returned information can be corrected and resubmitted.']);
            }
            $locked->update(['farm_id' => $farmId, 'payload' => $payload, 'status' => 'pending', 'review_notes' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
            $locked->history()->create(['from_status' => 'returned', 'to_status' => 'pending', 'changed_by' => $actor->id, 'notes' => 'Farmer corrected and resubmitted information.']);
            ActivityLog::log('updated', 'Farmer production submission resubmitted.', 'production-submission:'.$locked->id, $actor->id, 'apms');

            return $locked->refresh();
        });
    }

    public function review(ProductionSubmission $submission, User $reviewer, string $decision, ?string $notes): ProductionSubmission
    {
        if (! in_array($decision, ['approved', 'returned', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Unsupported review decision.']);
        }
        if ($decision !== 'approved' && blank($notes)) {
            throw ValidationException::withMessages(['review_notes' => 'A reason is required when returning or rejecting information.']);
        }

        return DB::transaction(function () use ($submission, $reviewer, $decision, $notes): ProductionSubmission {
            $locked = ProductionSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Only pending submissions can be reviewed.']);
            }
            if ((int) $locked->farm->farmer_id !== (int) $locked->farmer_id) {
                throw ValidationException::withMessages(['farm_id' => 'The farm no longer belongs to this farmer.']);
            }

            $officialId = null;
            if ($decision === 'approved') {
                $attributes = $locked->payload + ['farmer_id' => $locked->farmer_id, 'farm_id' => $locked->farm_id, 'recorded_by' => $reviewer->id];
                $record = match ($locked->category) {
                    'crop' => CropProductionRecord::create($attributes),
                    'livestock' => LivestockRecord::create($attributes),
                    'fishery' => FisheryRecord::create($attributes),
                    default => throw ValidationException::withMessages(['category' => 'Unsupported production category.']),
                };
                $officialId = $record->id;
            }
            $locked->update(['status' => $decision, 'review_notes' => $notes, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'official_record_id' => $officialId]);
            $locked->history()->create(['from_status' => 'pending', 'to_status' => $decision, 'changed_by' => $reviewer->id, 'notes' => $notes]);
            ActivityLog::log($decision, 'Farmer production submission reviewed.', 'production-submission:'.$locked->id, $reviewer->id, 'apms');
            $locked->farmer->user->notify(new ProductionSubmissionReviewed($locked->id, $decision, $notes));

            return $locked->refresh();
        });
    }
}
