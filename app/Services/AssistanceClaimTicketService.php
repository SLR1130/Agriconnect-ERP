<?php

namespace App\Services;

use App\Models\AssistanceApplication;
use App\Models\AssistanceClaimTicket;
use App\Models\AssistanceClaimTicketEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * QA-only assistance claim-ticket lifecycle. This service deliberately never
 * calls AISMS/InventoryService and never reserves or changes stock.
 */
class AssistanceClaimTicketService
{
    private const PERMISSIONS = [
        'view' => 'modules.aasms.claim_ticket_view',
        'issue' => 'modules.aasms.claim_ticket_issue',
        'verify' => 'modules.aasms.claim_ticket_verify',
        'revoke' => 'modules.aasms.claim_ticket_revoke',
        'reissue' => 'modules.aasms.claim_ticket_reissue',
        'history' => 'modules.aasms.claim_ticket_history',
    ];

    public function issue(AssistanceApplication $application, User $actor): AssistanceClaimTicket
    {
        $this->authorize($actor, 'issue');

        try {
            return DB::transaction(function () use ($application, $actor): AssistanceClaimTicket {
                $this->authorize($actor, 'issue');
                $locked = AssistanceApplication::query()->lockForUpdate()->findOrFail($application->id);
                $this->assertEligible($locked);

                $previous = AssistanceClaimTicket::query()
                    ->where('application_id', $locked->id)
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();

                if ($previous) {
                    throw ValidationException::withMessages([
                        'application' => 'A claim ticket already exists for this application. Use the authorized reissue workflow for a replacement.',
                    ]);
                }

                $ticket = $this->createTicket($locked, $actor, null);
                $this->event($ticket, 'issued', null, AssistanceClaimTicket::STATUS_ISSUED, $actor, 'staff', 'success');

                return $ticket->load(['application.program', 'approvedSupplyItem']);
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                throw ValidationException::withMessages([
                    'application' => 'A claim ticket was issued concurrently. Refresh the application before continuing.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Validate a QR payload, raw 256-bit token, or manual public reference.
     * A manual reference is lookup only and is never represented as proof that
     * its presenter is the beneficiary.
     *
     * @return array{outcome:string, method:string, ticket:?AssistanceClaimTicket, message:string}
     */
    public function verify(string $input, User $actor): array
    {
        $this->authorize($actor, 'verify');
        $value = trim($input);
        if ($value === '') {
            throw ValidationException::withMessages(['ticket_input' => 'Enter a QR payload, verification token, or ticket reference.']);
        }

        $isQrPayload = str_starts_with($value, 'AGC1:');
        $token = $isQrPayload ? substr($value, 5) : $value;
        $isToken = preg_match('/\A[a-f0-9]{64}\z/i', $token) === 1;

        return DB::transaction(function () use ($actor, $value, $token, $isQrPayload, $isToken): array {
            $this->authorize($actor, 'verify');

            if ($isToken) {
                $ticket = AssistanceClaimTicket::query()
                    ->where('token_hash', $this->tokenHash(strtolower($token)))
                    ->lockForUpdate()
                    ->first();

                if (! $ticket) {
                    $this->event(null, 'verification_attempt', null, null, $actor, $isQrPayload ? 'qr' : 'token', 'invalid', 'invalid_token');

                    return $this->result('invalid', $isQrPayload ? 'qr' : 'token', null, 'No active ticket matches this verification code.');
                }

                if ($ticket->status === AssistanceClaimTicket::STATUS_ISSUED
                    && $ticket->expires_at
                    && $ticket->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                    $this->expire($ticket, $actor, 'expired_during_verification');

                    return $this->result('expired', $isQrPayload ? 'qr' : 'token', $ticket->fresh(), 'This ticket has expired. Do not release assistance based on it.');
                }

                if (! $ticket->isEffectivelyIssued()) {
                    $this->event($ticket, 'verification_attempt', $ticket->status, $ticket->status, $actor, $isQrPayload ? 'qr' : 'token', 'denied', $ticket->status);

                    return $this->result($ticket->status, $isQrPayload ? 'qr' : 'token', $ticket, 'This ticket is not currently valid.');
                }

                if (! $this->ticketMatchesCurrentApproval($ticket)) {
                    $this->event($ticket, 'verification_attempt', $ticket->status, $ticket->status, $actor, $isQrPayload ? 'qr' : 'token', 'denied', 'approval_or_allocation_changed');

                    return $this->result('approval_changed', $isQrPayload ? 'qr' : 'token', $ticket, 'The approved application or allocation has changed. Refer this case to the MAO.');
                }

                $this->event($ticket, 'verification_attempt', $ticket->status, $ticket->status, $actor, $isQrPayload ? 'qr' : 'token', 'valid');

                return $this->result('valid', $isQrPayload ? 'qr' : 'token', $ticket->load(['application.farmer.user', 'application.program', 'approvedSupplyItem']), 'Ticket token is valid. Verify the beneficiary in person separately. No inventory has been released.');
            }

            $reference = strtoupper($value);
            $ticket = AssistanceClaimTicket::query()
                ->whereRaw('UPPER(ticket_reference) = ?', [$reference])
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                $this->event(null, 'verification_attempt', null, null, $actor, 'reference', 'invalid', 'reference_not_found');

                return $this->result('not_found', 'reference', null, 'No ticket was found for that reference. A reference alone does not prove beneficiary identity.');
            }

            if ($ticket->status === AssistanceClaimTicket::STATUS_ISSUED
                && $ticket->expires_at
                && $ticket->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                $this->expire($ticket, $actor, 'expired_during_reference_lookup');

                return $this->result('expired', 'reference', $ticket->fresh(), 'This ticket has expired. A reference alone does not prove beneficiary identity.');
            }

            if (! $ticket->isEffectivelyIssued() || ! $this->ticketMatchesCurrentApproval($ticket)) {
                $outcome = $ticket->isEffectivelyIssued() ? 'approval_changed' : $ticket->status;
                $this->event($ticket, 'verification_attempt', $ticket->status, $ticket->status, $actor, 'reference', 'denied', $outcome);

                return $this->result($outcome, 'reference', $ticket, 'The ticket is not currently valid. A reference alone does not prove beneficiary identity.');
            }

            $this->event($ticket, 'verification_attempt', $ticket->status, $ticket->status, $actor, 'reference', 'reference_only');

            return $this->result('reference_only', 'reference', $ticket->load(['application.farmer.user', 'application.program', 'approvedSupplyItem']), 'Reference located. This is not proof of possession or beneficiary identity. Confirm identity in person under the future handover policy. No inventory has been released.');
        });
    }

    public function revoke(AssistanceClaimTicket $ticket, User $actor, string $reason): AssistanceClaimTicket
    {
        $this->authorize($actor, 'revoke');
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to revoke a claim ticket.']);
        }

        return DB::transaction(function () use ($ticket, $actor, $reason): AssistanceClaimTicket {
            $this->authorize($actor, 'revoke');
            AssistanceApplication::query()->lockForUpdate()->findOrFail($ticket->application_id);
            $locked = AssistanceClaimTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status === AssistanceClaimTicket::STATUS_ISSUED
                && $locked->expires_at
                && $locked->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                $this->expire($locked, $actor, 'expired_during_revoke');
                return $locked->fresh();
            }

            if (! $locked->isEffectivelyIssued()) {
                throw ValidationException::withMessages(['ticket' => 'Only an active issued ticket can be revoked.']);
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => AssistanceClaimTicket::STATUS_REVOKED,
                'active_slot' => null,
                'token_ciphertext' => null,
                'revoked_by' => $actor->id,
                'revoked_at' => CarbonImmutable::now('UTC'),
                'revocation_reason' => $reason,
            ])->save();
            $this->event($locked, 'revoked', $from, AssistanceClaimTicket::STATUS_REVOKED, $actor, 'staff', 'success', 'staff_revocation', ['reason' => $reason]);

            return $locked->fresh();
        }, 3);
    }

    public function reissue(AssistanceApplication $application, AssistanceClaimTicket $previous, User $actor, string $reason): AssistanceClaimTicket
    {
        $this->authorize($actor, 'reissue');
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to reissue a claim ticket.']);
        }

        try {
            return DB::transaction(function () use ($application, $previous, $actor, $reason): AssistanceClaimTicket {
                $this->authorize($actor, 'reissue');
                $lockedApplication = AssistanceApplication::query()->lockForUpdate()->findOrFail($application->id);
                $this->assertEligible($lockedApplication);
                $lockedPrevious = AssistanceClaimTicket::query()->lockForUpdate()->findOrFail($previous->id);

                if ((int) $lockedPrevious->application_id !== (int) $lockedApplication->id) {
                    throw ValidationException::withMessages(['ticket' => 'The selected ticket does not belong to this application.']);
                }
                if ($lockedPrevious->successor()->exists()) {
                    throw ValidationException::withMessages(['ticket' => 'This ticket already has a replacement. Reissue only from the current ticket.']);
                }
                if (! in_array($lockedPrevious->status, [AssistanceClaimTicket::STATUS_ISSUED, AssistanceClaimTicket::STATUS_EXPIRED, AssistanceClaimTicket::STATUS_REVOKED], true)) {
                    throw ValidationException::withMessages(['ticket' => 'This ticket cannot be reissued from its current status.']);
                }
                if (! hash_equals($lockedPrevious->allocation_snapshot_sha256, $this->allocationSnapshot($lockedApplication))) {
                    throw ValidationException::withMessages(['ticket' => 'The allocation changed after this ticket was issued. Review the application and issue a new ticket through the approved workflow.']);
                }
                if ($lockedPrevious->status === AssistanceClaimTicket::STATUS_ISSUED
                    && $lockedPrevious->expires_at
                    && $lockedPrevious->expires_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                    $this->expire($lockedPrevious, $actor, 'expired_during_reissue');
                } elseif ($lockedPrevious->status === AssistanceClaimTicket::STATUS_ISSUED) {
                    $from = $lockedPrevious->status;
                    $lockedPrevious->forceFill([
                        'status' => AssistanceClaimTicket::STATUS_REVOKED,
                        'active_slot' => null,
                        'token_ciphertext' => null,
                        'revoked_by' => $actor->id,
                        'revoked_at' => CarbonImmutable::now('UTC'),
                        'revocation_reason' => 'Reissued: '.$reason,
                    ])->save();
                    $this->event($lockedPrevious, 'revoked_for_reissue', $from, AssistanceClaimTicket::STATUS_REVOKED, $actor, 'staff', 'success', 'reissued', ['reason' => $reason]);
                }

                $replacement = $this->createTicket($lockedApplication, $actor, $lockedPrevious->id);
                $this->event($replacement, 'reissued', null, AssistanceClaimTicket::STATUS_ISSUED, $actor, 'staff', 'success', 'reissued', ['supersedes_ticket_id' => $lockedPrevious->id]);
                $this->event($lockedPrevious, 'superseded', $lockedPrevious->status, $lockedPrevious->status, $actor, 'staff', 'success', 'superseded', ['successor_ticket_id' => $replacement->id]);

                return $replacement->load(['application.program', 'approvedSupplyItem']);
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                throw ValidationException::withMessages([
                    'ticket' => 'A replacement ticket was issued concurrently. Refresh the ticket history before continuing.',
                ]);
            }

            throw $exception;
        }
    }

    /** Return an owner-only, short-lived-in-memory QR payload; never put it in a URL. */
    public function qrPayload(AssistanceClaimTicket $ticket): ?string
    {
        if (! $ticket->isEffectivelyIssued() || ! $this->ticketMatchesCurrentApproval($ticket) || blank($ticket->token_ciphertext)) {
            return null;
        }

        try {
            return 'AGC1:'.Crypt::decryptString($ticket->token_ciphertext);
        } catch (Throwable) {
            return null;
        }
    }

    private function createTicket(AssistanceApplication $application, User $actor, ?int $supersedesId): AssistanceClaimTicket
    {
        $token = bin2hex(random_bytes(32));
        $validityDays = config('agricultural_claim_tickets.validity_days');
        if ($validityDays !== null && (int) $validityDays < 1) {
            throw ValidationException::withMessages(['expires_at' => 'Ticket validity must be a positive whole number of days.']);
        }
        $issuedAt = CarbonImmutable::now('UTC');
        $expiresAt = $validityDays ? $issuedAt->addDays((int) $validityDays) : null;

        return AssistanceClaimTicket::query()->create([
            'application_id' => $application->id,
            'ticket_reference' => $this->newReference(),
            'token_hash' => $this->tokenHash($token),
            'token_ciphertext' => Crypt::encryptString($token),
            'status' => AssistanceClaimTicket::STATUS_ISSUED,
            'active_slot' => 1,
            'approved_supply_item_id' => $application->approved_supply_item_id,
            'approved_quantity' => $application->approved_quantity,
            'allocation_confirmed_at' => $application->allocation_confirmed_at,
            'allocation_snapshot_sha256' => $this->allocationSnapshot($application),
            'issued_by' => $actor->id,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'supersedes_ticket_id' => $supersedesId,
        ]);
    }

    private function assertEligible(AssistanceApplication $application): void
    {
        $application->loadMissing(['farmer.user', 'program', 'approvedSupplyItem']);

        if ($application->status !== 'approved') {
            throw ValidationException::withMessages(['application' => 'Only an approved, not-yet-completed application can receive a claim ticket.']);
        }
        if (! $application->farmer || ! $application->farmer->user || (int) $application->farmer->user_id !== (int) $application->farmer->user->id) {
            throw ValidationException::withMessages(['application' => 'The application does not have a valid farmer account association.']);
        }
        if (! $application->farmer->isVerified()) {
            throw ValidationException::withMessages(['application' => 'The farmer must have a verified FFRMS registration before a claim ticket can be issued.']);
        }
        if (! $application->program) {
            throw ValidationException::withMessages(['application' => 'The assistance program linked to this application is unavailable.']);
        }
        if (! $application->approved_supply_item_id || ! $application->approvedSupplyItem) {
            throw ValidationException::withMessages(['application' => 'An approved assistance item must be recorded before ticket issuance.']);
        }
        if ($application->approvedSupplyItem->status !== 'active') {
            throw ValidationException::withMessages(['application' => 'The approved assistance item is not active.']);
        }
        if ((float) $application->approved_quantity <= 0) {
            throw ValidationException::withMessages(['application' => 'A positive approved quantity is required before ticket issuance.']);
        }
        if (! $application->allocation_confirmed_at) {
            throw ValidationException::withMessages(['application' => 'The approved allocation has not been confirmed.']);
        }
        if ($application->distributions()->exists()) {
            throw ValidationException::withMessages(['application' => 'Assistance has already been distributed for this application.']);
        }
    }

    private function ticketMatchesCurrentApproval(AssistanceClaimTicket $ticket): bool
    {
        $application = $ticket->application()->with(['farmer.user', 'program', 'approvedSupplyItem'])->first();

        return $application !== null
            && $application->status === 'approved'
            && $application->farmer?->isVerified()
            && $application->farmer?->user !== null
            && $application->program !== null
            && $application->approvedSupplyItem !== null
            && $application->approvedSupplyItem->status === 'active'
            && (float) $application->approved_quantity > 0
            && $application->allocation_confirmed_at !== null
            && ! $application->distributions()->exists()
            && hash_equals($ticket->allocation_snapshot_sha256, $this->allocationSnapshot($application));
    }

    private function allocationSnapshot(AssistanceApplication $application): string
    {
        $confirmedAt = $application->allocation_confirmed_at
            ? CarbonImmutable::parse($application->allocation_confirmed_at)->utc()->format('Y-m-d H:i:s')
            : null;
        $payload = json_encode([
            'application_id' => (int) $application->id,
            'supply_item_id' => (int) $application->approved_supply_item_id,
            'quantity' => number_format((float) $application->approved_quantity, 2, '.', ''),
            'allocation_confirmed_at' => $confirmedAt,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }

    private function expire(AssistanceClaimTicket $ticket, ?User $actor, string $reasonCode): void
    {
        if ($ticket->status !== AssistanceClaimTicket::STATUS_ISSUED) {
            return;
        }

        $from = $ticket->status;
        $ticket->forceFill([
            'status' => AssistanceClaimTicket::STATUS_EXPIRED,
            'active_slot' => null,
            'token_ciphertext' => null,
        ])->save();
        $this->event($ticket, 'expired', $from, AssistanceClaimTicket::STATUS_EXPIRED, $actor, 'system', 'expired', $reasonCode);
    }

    private function event(
        ?AssistanceClaimTicket $ticket,
        string $type,
        ?string $from,
        ?string $to,
        ?User $actor,
        ?string $method,
        ?string $outcome,
        ?string $reasonCode = null,
        ?array $metadata = null,
    ): void {
        AssistanceClaimTicketEvent::query()->create([
            'ticket_id' => $ticket?->id,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'verification_method' => $method,
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'occurred_at' => CarbonImmutable::now('UTC'),
            'metadata' => $metadata,
        ]);
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', 'agriconnect-claim-ticket-v1:'.$token);
    }

    private function newReference(): string
    {
        do {
            $reference = 'AGC-'.strtoupper(bin2hex(random_bytes(6)));
        } while (AssistanceClaimTicket::query()->where('ticket_reference', $reference)->exists());

        return $reference;
    }

    private function authorize(User $actor, string $action): void
    {
        $permission = self::PERMISSIONS[$action] ?? null;
        abort_unless($permission && $actor->canErp($permission), 403);
    }

    private function result(string $outcome, string $method, ?AssistanceClaimTicket $ticket, string $message): array
    {
        return ['outcome' => $outcome, 'method' => $method, 'ticket' => $ticket, 'message' => $message];
    }

    private function isUniqueConflict(QueryException $exception): bool
    {
        $code = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return $code === '1062'
            || (in_array($code, ['19', '23000'], true) && str_contains($message, 'unique constraint failed'))
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'duplicate key value');
    }
}
