<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceIdentityConfirmation extends Model
{
    public const QA_METHOD = 'synthetic_qa_test_only';
    public const IN_PERSON_METHOD = 'in_person_ticket_identity_check';

    protected $guarded = ['id'];
    protected $casts = ['confirmed_at' => 'immutable_datetime', 'evidence_metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(static function (self $confirmation): void {
            $allowedScenario = match ($confirmation->method) {
                self::QA_METHOD => $confirmation->outcome === 'confirmed' ? 'synthetic_qa_match' : 'synthetic_qa_no_match',
                self::IN_PERSON_METHOD => $confirmation->outcome === 'confirmed' ? 'in_person_match' : 'in_person_not_confirmed',
                default => null,
            };
            if ($allowedScenario === null
                || ! in_array($confirmation->outcome, ['confirmed', 'not_confirmed'], true)
                || data_get($confirmation->evidence_metadata, 'scenario') !== $allowedScenario) {
                throw new LogicException('Identity-confirmation evidence must use a supported method and immutable outcome.');
            }
        });
        static::updating(static fn () => throw new LogicException('Identity-confirmation evidence is append-only.'));
        static::deleting(static fn () => throw new LogicException('Identity-confirmation evidence is append-only.'));
    }

    public function application(): BelongsTo { return $this->belongsTo(AssistanceApplication::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(AssistanceClaimTicket::class, 'claim_ticket_id'); }
    public function verificationEvent(): BelongsTo { return $this->belongsTo(AssistanceClaimTicketEvent::class, 'verification_event_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'staff_actor_id'); }
}
