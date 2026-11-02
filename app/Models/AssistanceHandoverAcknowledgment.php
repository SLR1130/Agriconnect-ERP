<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceHandoverAcknowledgment extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['quantity' => 'decimal:2', 'distributed_at' => 'immutable_datetime', 'acknowledged_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::creating(static function (self $acknowledgment): void {
            if (! app()->bound('aasms.handover.commit_ticket_id')
                || (int) app('aasms.handover.commit_ticket_id') !== (int) $acknowledgment->claim_ticket_id
                || blank($acknowledgment->distribution_id)) {
                throw new LogicException('Handover acknowledgments must be created by the committed physical handover transaction.');
            }
        });
        static::updating(static fn () => throw new LogicException('Committed handover acknowledgments are immutable.'));
        static::deleting(static fn () => throw new LogicException('Committed handover acknowledgments are immutable.'));
    }

    public function application(): BelongsTo { return $this->belongsTo(AssistanceApplication::class); }
    public function ticket(): BelongsTo { return $this->belongsTo(AssistanceClaimTicket::class, 'claim_ticket_id'); }
    public function distribution(): BelongsTo { return $this->belongsTo(AssistanceDistribution::class); }
    public function identityConfirmation(): BelongsTo { return $this->belongsTo(AssistanceIdentityConfirmation::class); }
    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
    public function batch(): BelongsTo { return $this->belongsTo(SupplyBatch::class, 'supply_batch_id'); }
    public function distributor(): BelongsTo { return $this->belongsTo(User::class, 'distributed_by'); }
}
