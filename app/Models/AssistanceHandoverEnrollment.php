<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceHandoverEnrollment extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['enrolled_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException('Handover cohort enrollment is immutable.'));
        static::deleting(static fn () => throw new LogicException('Handover cohort enrollment is immutable.'));
    }

    public function application(): BelongsTo { return $this->belongsTo(AssistanceApplication::class); }
    public function firstTicket(): BelongsTo { return $this->belongsTo(AssistanceClaimTicket::class, 'first_ticket_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'enrolled_by'); }
}
