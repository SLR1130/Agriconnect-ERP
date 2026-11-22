<?php

namespace App\Services;

use App\Models\AssistanceApplication;
use App\Models\AssistanceClaimTicket;
use App\Models\AssistanceClaimTicketEvent;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceHandoverAcknowledgment;
use App\Models\AssistanceHandoverEvent;
use App\Models\AssistanceHandoverEnrollment;
use App\Models\AssistanceIdentityConfirmation;
use App\Models\AssistanceStatusHistory;
use App\Models\SupplyBatch;
use App\Models\User;
use App\Notifications\AssistanceStatusNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistanceHandoverService
{
    public function __construct(
        private readonly AssistanceHandoverFeatureGate $feature,
        private readonly AssistanceClaimTicketService $tickets,
    ) {}

    public function confirmIdentity(
        AssistanceClaimTicket $ticket,
        int $verificationEventId,
        string $outcome,
        User $actor,
        bool $staffAttestation = false,
    ): AssistanceIdentityConfirmation {
        $this->feature->requireEnabled();
        $this->authorizeIdentityConfirmation($actor);

        if (! in_array($outcome, ['confirmed', 'not_confirmed'], true)) {
            throw ValidationException::withMessages(['outcome' => 'Choose whether the in-person identity check was confirmed.']);
        }
        if ($this->feature->operational() && ! $staffAttestation) {
            throw ValidationException::withMessages(['identity_attestation' => 'The staff member must attest that the farmer and ticket were checked in person.']);
        }

        return DB::transaction(function () use ($ticket, $verificationEventId, $outcome, $actor): AssistanceIdentityConfirmation {
            $this->feature->requireEnabled();
            $this->authorizeIdentityConfirmation($actor);

            $method = $this->identityMethod();
            $scenario = $this->identityScenario($outcome);

            $application = AssistanceApplication::query()->lockForUpdate()->findOrFail($ticket->application_id);
            $lockedTicket = AssistanceClaimTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertCurrentApprovedClaim($application, $lockedTicket);

            $verification = AssistanceClaimTicketEvent::query()
                ->whereKey($verificationEventId)
                ->where('ticket_id', $lockedTicket->id)
                ->where('event_type', 'verification_attempt')
                ->where('outcome', 'valid')
                ->whereIn('verification_method', ['token', 'qr'])
                ->where('actor_id', $actor->id)
                ->where('occurred_at', '>=', now('UTC')->subMinutes(10))
                ->first();

            if (! $verification) {
                throw ValidationException::withMessages(['verification' => 'A successful token or QR validation by this staff member is required. Reference lookup is not sufficient.']);
            }

            $confirmation = AssistanceIdentityConfirmation::query()->firstOrCreate(
                ['verification_event_id' => $verification->id],
                [
                    'application_id' => $application->id,
                    'claim_ticket_id' => $lockedTicket->id,
                    'staff_actor_id' => $actor->id,
                    'method' => $method,
                    'outcome' => $outcome,
                    'confirmed_at' => $outcome === 'confirmed' ? now('UTC') : null,
                    'allocation_snapshot_sha256' => $lockedTicket->allocation_snapshot_sha256,
                    'evidence_metadata' => ['scenario' => $scenario],
                ],
            );

            if ((int) $confirmation->application_id !== (int) $application->id
                || (int) $confirmation->claim_ticket_id !== (int) $lockedTicket->id
                || $confirmation->outcome !== $outcome
                || $confirmation->method !== $method) {
                throw ValidationException::withMessages(['verification' => 'This verification event already has a conflicting immutable identity-confirmation record.']);
            }

            if ($confirmation->wasRecentlyCreated) {
                AssistanceHandoverEvent::query()->create([
                    'application_id' => $application->id,
                    'claim_ticket_id' => $lockedTicket->id,
                    'identity_confirmation_id' => $confirmation->id,
                    'actor_id' => $actor->id,
                    'event_type' => $this->feature->operational() ? 'in_person_identity_confirmation' : 'synthetic_identity_confirmation',
                    'outcome' => $outcome,
                    'reason_code' => $this->feature->operational() ? 'staff_attested_ticket_and_identity_check' : 'qa_only_no_real_id_or_signature',
                    'safe_metadata' => ['method' => $method],
                    'occurred_at' => now('UTC'),
                ]);
            }

            return $confirmation;
        }, 3);
    }

    /** Backward-compatible QA test entry point. */
    public function confirmSyntheticIdentity(
        AssistanceClaimTicket $ticket,
        int $verificationEventId,
        string $outcome,
        User $actor,
    ): AssistanceIdentityConfirmation {
        return $this->confirmIdentity($ticket, $verificationEventId, $outcome, $actor);
    }

    /**
     * Lock order: application, active claim ticket, then the batch lock in the
     * existing InventoryService::release call. All writes share the default
     * Laravel DB connection and one outer transaction.
     */
    public function handover(
        AssistanceApplication $application,
        AssistanceClaimTicket $ticket,
        AssistanceIdentityConfirmation $confirmation,
        array $data,
        User $actor,
        InventoryService $inventory,
    ): AssistanceDistribution {
        $this->feature->requireEnabled();
        $this->authorizeHandover($actor);
        if (! in_array($data['confirm_physical_handover'] ?? null, [true, 1, '1', 'yes', 'on'], true)) {
            throw ValidationException::withMessages(['confirm_physical_handover' => 'Explicit physical handover confirmation is required.']);
        }

        try {
            return DB::transaction(function () use ($application, $ticket, $confirmation, $data, $actor, $inventory): AssistanceDistribution {
                $this->feature->requireEnabled();
                $this->authorizeHandover($actor);

                $lockedApplication = AssistanceApplication::query()->lockForUpdate()->findOrFail($application->id);
                $lockedTicket = AssistanceClaimTicket::query()->lockForUpdate()->findOrFail($ticket->id);

                if ((int) $lockedTicket->application_id !== (int) $lockedApplication->id) {
                    throw ValidationException::withMessages(['ticket' => 'The claim ticket does not belong to this assistance application.']);
                }

                // Use a locking/current read here. Under MySQL REPEATABLE READ,
                // an earlier permission or gate SELECT may establish a snapshot
                // before this request waits on the application lock. A plain
                // SELECT could then miss the distribution committed by the
                // competing counter and incorrectly report "already completed".
                $existing = AssistanceDistribution::query()
                    ->where('claim_ticket_id', $lockedTicket->id)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $this->returnIdenticalRetry($existing, $lockedApplication, $lockedTicket, $confirmation, $data);
                }

                if (! AssistanceHandoverEnrollment::query()->where('application_id', $lockedApplication->id)->exists()) {
                    throw ValidationException::withMessages(['application' => 'This application is not enrolled in ticket-governed physical handover.']);
                }
                $this->assertCurrentApprovedClaim($lockedApplication, $lockedTicket);

                $approvedQuantity = (string) $lockedApplication->approved_quantity;
                if (! $this->sameQuantity($data['quantity'] ?? null, $approvedQuantity)) {
                    throw ValidationException::withMessages(['quantity' => 'The handover quantity must match the complete approved allocation.']);
                }

                $identity = AssistanceIdentityConfirmation::query()
                    ->whereKey($confirmation->id)
                    ->where('application_id', $lockedApplication->id)
                    ->where('claim_ticket_id', $lockedTicket->id)
                    ->where('outcome', 'confirmed')
                    ->where('method', $this->identityMethod())
                    ->first();
                if (! $identity || ! $identity->confirmed_at
                    || ! hash_equals($lockedTicket->allocation_snapshot_sha256, (string) $identity->allocation_snapshot_sha256)) {
                    throw ValidationException::withMessages(['identity_confirmation' => 'A successful identity confirmation for this exact ticket and allocation is required.']);
                }
                if ($identity->confirmed_at->lt(now('UTC')->subMinutes(10))) {
                    throw ValidationException::withMessages(['identity_confirmation' => 'The identity confirmation has expired. Revalidate the current ticket and record a fresh confirmation.']);
                }

                $verification = AssistanceClaimTicketEvent::query()
                    ->whereKey($identity->verification_event_id)
                    ->where('ticket_id', $lockedTicket->id)
                    ->where('event_type', 'verification_attempt')
                    ->where('outcome', 'valid')
                    ->whereIn('verification_method', ['token', 'qr'])
                    ->where('occurred_at', '>=', $identity->confirmed_at->subMinutes(10))
                    ->first();
                if (! $verification) {
                    throw ValidationException::withMessages(['identity_confirmation' => 'The linked ticket credential check is no longer valid.']);
                }

                if (AssistanceDistribution::query()->where('application_id', $lockedApplication->id)->exists()) {
                    throw ValidationException::withMessages(['application' => 'This application already has a committed distribution.']);
                }

                $batchId = (int) ($data['supply_batch_id'] ?? 0);
                if ($batchId < 1) {
                    throw ValidationException::withMessages(['supply_batch_id' => 'Select an available inventory batch.']);
                }
                $batch = SupplyBatch::query()->with(['item', 'location'])->findOrFail($batchId);
                if ((int) $batch->supply_item_id !== (int) $lockedApplication->approved_supply_item_id
                    || $batch->item?->status !== 'active') {
                    throw ValidationException::withMessages(['supply_batch_id' => 'The selected batch does not match the approved active supply item.']);
                }

                $operationKey = 'aasms-claim-ticket:'.$lockedTicket->id;
                $movement = $inventory->release([
                    'supply_batch_id' => $batch->id,
                    'expected_supply_item_id' => $lockedApplication->approved_supply_item_id,
                    'quantity' => $approvedQuantity,
                    'recipient' => (string) $lockedApplication->farmer_id,
                    'purpose' => 'AASMS physical handover for application '.$lockedApplication->id,
                    'idempotency_key' => $operationKey,
                ], (int) $actor->id);

                $distributedAt = now('UTC');
                app()->instance('aasms.handover.commit_ticket_id', (int) $lockedTicket->id);
                try {
                $distribution = AssistanceDistribution::query()->create([
                    'application_id' => $lockedApplication->id,
                    'supply_batch_id' => $batch->id,
                    'claim_ticket_id' => $lockedTicket->id,
                    'reference' => $movement->reference,
                    'operation_key' => $operationKey,
                    'quantity' => $approvedQuantity,
                    'status' => 'distributed',
                    'processed_by' => $actor->id,
                    'distributed_at' => $distributedAt,
                ]);

                $lockedTicket->forceFill([
                    'status' => AssistanceClaimTicket::STATUS_REDEEMED,
                    'active_slot' => null,
                    'token_ciphertext' => null,
                    'redeemed_at' => $distributedAt,
                    'redeemed_by' => $actor->id,
                    'redeemed_distribution_id' => $distribution->id,
                ])->save();

                $lockedApplication->update(['status' => 'completed']);
                AssistanceStatusHistory::query()->create([
                    'application_id' => $lockedApplication->id,
                    'from_status' => 'approved',
                    'to_status' => 'completed',
                    'changed_by' => $actor->id,
                    'notes' => 'Authorized physical handover committed: '.$distribution->reference,
                ]);
                AssistanceClaimTicketEvent::query()->create([
                    'ticket_id' => $lockedTicket->id,
                    'event_type' => 'redeemed',
                    'from_status' => 'issued',
                    'to_status' => AssistanceClaimTicket::STATUS_REDEEMED,
                    'actor_id' => $actor->id,
                    'verification_method' => 'physical_handover',
                    'outcome' => 'success',
                    'reason_code' => 'authorized_inventory_distribution',
                    'occurred_at' => $distributedAt,
                    'metadata' => ['distribution_reference' => $distribution->reference],
                ]);

                $acknowledgment = AssistanceHandoverAcknowledgment::query()->create([
                    'reference' => $this->newAcknowledgmentReference(),
                    'application_id' => $lockedApplication->id,
                    'claim_ticket_id' => $lockedTicket->id,
                    'distribution_id' => $distribution->id,
                    'identity_confirmation_id' => $identity->id,
                    'farmer_id' => $lockedApplication->farmer_id,
                    'supply_batch_id' => $batch->id,
                    'distributed_by' => $actor->id,
                    'application_reference_snapshot' => $lockedApplication->application_reference,
                    'ticket_reference_snapshot' => $lockedTicket->ticket_reference,
                    'farmer_name_snapshot' => (string) $lockedApplication->farmer->user->name,
                    'program_name_snapshot' => (string) $lockedApplication->program->name,
                    'item_name_snapshot' => (string) $batch->item->name,
                    'batch_reference_snapshot' => (string) $batch->batch_number,
                    'quantity' => $approvedQuantity,
                    'confirmation_method' => $this->identityMethod(),
                    'environment_label' => $this->handoverLabel(),
                    'distributed_at' => $distributedAt,
                    'acknowledged_at' => $distributedAt,
                ]);

                AssistanceHandoverEvent::query()->create([
                    'application_id' => $lockedApplication->id,
                    'claim_ticket_id' => $lockedTicket->id,
                    'identity_confirmation_id' => $identity->id,
                    'distribution_id' => $distribution->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'physical_handover_committed',
                    'outcome' => 'success',
                    'reason_code' => $this->feature->operational() ? 'authorized_physical_assistance_handover' : 'synthetic_qa_only_no_actual_receipt_claim',
                    'safe_metadata' => ['acknowledgment_reference' => $acknowledgment->reference, 'distribution_reference' => $distribution->reference],
                    'occurred_at' => $distributedAt,
                ]);

                // This notification uses Laravel's synchronous database channel on
                // this same connection. It commits or rolls back with the handover.
                $lockedApplication->farmer?->user?->notify(
                    new AssistanceStatusNotification($lockedApplication->id, 'completed', null, $distribution->reference)
                );

                return $distribution->load(['application', 'batch.item', 'processor', 'ticket.handoverAcknowledgment', 'acknowledgment']);
                } finally {
                    app()->forgetInstance('aasms.handover.commit_ticket_id');
                }
            }, 3);
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['handover'])
                || isset($exception->errors()['ticket'])
                || isset($exception->errors()['application'])) {
                $this->auditCommittedRetryConflict($application, $ticket, $confirmation, $data, $actor);
            }

            throw $exception;
        } catch (QueryException $exception) {
            $committed = AssistanceDistribution::query()->where('claim_ticket_id', $ticket->id)->first();
            if ($committed) {
                return $this->returnIdenticalRetry($committed, $application, $ticket, $confirmation, $data);
            }
            throw $exception;
        }
    }

    public function acknowledgmentForFarmer(AssistanceHandoverAcknowledgment $acknowledgment, User $farmerUser): AssistanceHandoverAcknowledgment
    {
        abort_unless($farmerUser->hasRole('farmer'), 404);
        $farmerId = $farmerUser->farmer()->value('id');
        abort_unless($farmerId && (int) $acknowledgment->farmer_id === (int) $farmerId, 404);

        return $acknowledgment->load(['application', 'ticket', 'distribution.batch', 'distributor']);
    }

    private function returnIdenticalRetry(
        AssistanceDistribution $existing,
        AssistanceApplication $application,
        AssistanceClaimTicket $ticket,
        AssistanceIdentityConfirmation $confirmation,
        array $data,
    ): AssistanceDistribution {
        $ack = AssistanceHandoverAcknowledgment::query()
            ->where('distribution_id', $existing->id)
            ->lockForUpdate()
            ->first();
        $same = (int) $existing->application_id === (int) $application->id
            && (int) $existing->claim_ticket_id === (int) $ticket->id
            && (int) $existing->supply_batch_id === (int) ($data['supply_batch_id'] ?? 0)
            && $this->sameQuantity($data['quantity'] ?? null, (string) $existing->quantity)
            && $ack !== null
            && (int) $ack->identity_confirmation_id === (int) $confirmation->id;

        if (! $same) {
            throw ValidationException::withMessages(['handover' => 'This ticket already has a committed handover with a different request. No additional inventory was released.']);
        }

        return $existing->load(['application', 'batch.item', 'processor', 'ticket.handoverAcknowledgment', 'acknowledgment']);
    }

    private function auditCommittedRetryConflict(
        AssistanceApplication $application,
        AssistanceClaimTicket $ticket,
        AssistanceIdentityConfirmation $confirmation,
        array $data,
        User $actor,
    ): void {
        $this->feature->requireEnabled();

        DB::transaction(function () use ($application, $ticket, $confirmation, $data, $actor): void {
            $this->feature->requireEnabled();
            $this->authorizeHandover($actor);

            $lockedApplication = AssistanceApplication::query()->lockForUpdate()->find($application->id);
            $lockedTicket = AssistanceClaimTicket::query()->lockForUpdate()->find($ticket->id);
            if (! $lockedApplication || ! $lockedTicket) {
                return;
            }

            $committed = AssistanceDistribution::query()
                ->where('claim_ticket_id', $lockedTicket->id)
                ->orWhere('application_id', $lockedApplication->id)
                ->lockForUpdate()
                ->first();
            if (! $committed) {
                return;
            }

            $acknowledgment = AssistanceHandoverAcknowledgment::query()
                ->where('distribution_id', $committed->id)
                ->lockForUpdate()
                ->first();
            $committedItemId = SupplyBatch::query()->whereKey($committed->supply_batch_id)->value('supply_item_id');
            $mismatches = [
                'application_mismatch' => (int) $committed->application_id !== (int) $lockedApplication->id
                    || (int) $lockedTicket->application_id !== (int) $lockedApplication->id,
                'ticket_mismatch' => (int) $committed->claim_ticket_id !== (int) $lockedTicket->id,
                'batch_mismatch' => (int) $committed->supply_batch_id !== (int) ($data['supply_batch_id'] ?? 0),
                'supply_item_mismatch' => (int) $committedItemId !== (int) $lockedApplication->approved_supply_item_id,
                'quantity_mismatch' => ! $this->sameQuantity($data['quantity'] ?? null, (string) $committed->quantity),
                'identity_confirmation_mismatch' => ! $acknowledgment
                    || (int) $acknowledgment->identity_confirmation_id !== (int) $confirmation->id,
                'farmer_mismatch' => $acknowledgment
                    && (int) $acknowledgment->farmer_id !== (int) $lockedApplication->farmer_id,
            ];

            if (! in_array(true, $mismatches, true)) {
                return;
            }

            AssistanceHandoverEvent::query()->create([
                'application_id' => $committed->application_id,
                'claim_ticket_id' => $committed->claim_ticket_id,
                'identity_confirmation_id' => $acknowledgment?->identity_confirmation_id,
                'distribution_id' => $committed->id,
                'actor_id' => $actor->id,
                'event_type' => 'handover_retry_conflict',
                'outcome' => 'rejected',
                'reason_code' => 'idempotency_payload_conflict',
                'safe_metadata' => $mismatches,
                'occurred_at' => now('UTC'),
            ]);
        }, 3);
    }

    private function assertCurrentApprovedClaim(AssistanceApplication $application, AssistanceClaimTicket $ticket): void
    {
        if ($application->status !== 'approved') {
            throw ValidationException::withMessages(['application' => 'Only an approved, incomplete assistance application may be handed over.']);
        }
        if ($ticket->status !== AssistanceClaimTicket::STATUS_ISSUED || ! $ticket->isEffectivelyIssued() || (int) $ticket->active_slot !== 1) {
            throw ValidationException::withMessages(['ticket' => 'The claim ticket is expired, revoked, superseded, redeemed, or otherwise inactive.']);
        }
        if (! $this->tickets->matchesCurrentApproval($ticket)) {
            throw ValidationException::withMessages(['ticket' => 'The ticket approval snapshot no longer matches the current verified farmer and approved allocation.']);
        }
    }

    private function authorizeIdentityConfirmation(User $actor): void
    {
        abort_unless($actor->canModuleAction('aasms', 'view'), 403);
        abort_unless($actor->canErp('modules.aasms.claim_ticket_verify'), 403);
        abort_unless($actor->canErp('modules.aasms.identity_confirm'), 403);
    }

    private function authorizeHandover(User $actor): void
    {
        abort_unless($actor->canModuleAction('aasms', 'distribute'), 403);
        abort_unless($actor->canModuleAction('aisms', 'release'), 403);
        abort_unless($actor->canErp('modules.aasms.handover_distribute'), 403);
    }

    private function sameQuantity(mixed $requested, mixed $approved): bool
    {
        if (! is_numeric($requested) || ! is_numeric($approved)) {
            return false;
        }
        return number_format((float) $requested, 2, '.', '') === number_format((float) $approved, 2, '.', '');
    }

    private function identityMethod(): string
    {
        return $this->feature->operational()
            ? AssistanceIdentityConfirmation::IN_PERSON_METHOD
            : AssistanceIdentityConfirmation::QA_METHOD;
    }

    private function identityScenario(string $outcome): string
    {
        if ($this->feature->operational()) {
            return $outcome === 'confirmed' ? 'in_person_match' : 'in_person_not_confirmed';
        }

        return $outcome === 'confirmed' ? 'synthetic_qa_match' : 'synthetic_qa_no_match';
    }

    private function handoverLabel(): string
    {
        return $this->feature->operational()
            ? (string) config('agricultural_claim_handover.operational_label')
            : (string) config('agricultural_claim_handover.qa_label');
    }

    private function newAcknowledgmentReference(): string
    {
        do {
            $reference = 'AHA-'.strtoupper(bin2hex(random_bytes(8)));
        } while (AssistanceHandoverAcknowledgment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
