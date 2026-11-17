<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AssistanceHandoverEvent extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['safe_metadata' => 'array', 'occurred_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::creating(static function (self $event): void {
            if ($event->event_type === 'physical_handover_committed'
                && (! app()->bound('aasms.handover.commit_ticket_id')
                    || (int) app('aasms.handover.commit_ticket_id') !== (int) $event->claim_ticket_id)) {
                throw new LogicException('Committed handover events must be written by the atomic handover service.');
            }
        });
        static::updating(static fn () => throw new LogicException('Handover audit events are append-only.'));
        static::deleting(static fn () => throw new LogicException('Handover audit events are append-only.'));
    }
}
