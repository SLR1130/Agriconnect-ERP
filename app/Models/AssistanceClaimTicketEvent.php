<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceClaimTicketEvent extends Model
{
    protected $fillable = [
        'ticket_id', 'event_type', 'from_status', 'to_status', 'actor_id',
        'verification_method', 'outcome', 'reason_code', 'occurred_at', 'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Claim-ticket audit events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Claim-ticket audit events are append-only.');
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AssistanceClaimTicket::class, 'ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
