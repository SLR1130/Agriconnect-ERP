<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceIdentityConfirmation extends Model
{
    public const QA_METHOD = 'synthetic_qa_test_only';

    protected $guarded = ['id'];
    protected $casts = ['confirmed_at' => 'immutable_datetime', 'evidence_metadata' => 'array'];

    protected static function booted(): void
    {
        static::creating(static function (self $confirmation): void {
            $allowedScenario = $confirmation->outcome === 'confirmed' ? 'synthetic_qa_match' : 'synthetic_qa_no_match';
            if ($confirmation->method !== self::QA_METHOD
                || ! in_array($confirmation->outcome, ['confirmed', 'not_confirmed'], true)
                || data_get($confirmation->evidence_metadata, 'scenario') !== $allowedScenario) {
                throw new LogicException('Only a labeled synthetic QA identity-confirmation event may be stored in this foundation.');
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
