<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LogicException;

class AssistanceDistribution extends Model
{
    protected $fillable = ['application_id', 'supply_batch_id', 'claim_ticket_id', 'reference', 'operation_key', 'quantity', 'status', 'processed_by', 'distributed_at'];
    protected $casts = ['quantity' => 'decimal:2', 'distributed_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::creating(static function (self $distribution): void {
            if ($distribution->claim_ticket_id !== null
                && (! app()->bound('aasms.handover.commit_ticket_id')
                    || (int) app('aasms.handover.commit_ticket_id') !== (int) $distribution->claim_ticket_id)) {
                throw new LogicException('Ticket-linked assistance distributions must be created by the atomic handover service.');
            }

            if (Schema::hasColumn('assistance_distributions', 'farmer_barangay_at_distribution')) {
                $distribution->farmer_barangay_at_distribution = AssistanceApplication::query()
                    ->with('farmer:id,barangay')
                    ->findOrFail($distribution->application_id)->farmer?->barangay;
            }
        });
    }

    public function application() { return $this->belongsTo(AssistanceApplication::class, 'application_id'); }
    public function batch() { return $this->belongsTo(SupplyBatch::class, 'supply_batch_id'); }
    public function processor() { return $this->belongsTo(User::class, 'processed_by'); }
    public function inventoryMovement() { return $this->belongsTo(InventoryMovement::class, 'reference', 'reference'); }
    public function ticket() { return $this->belongsTo(AssistanceClaimTicket::class, 'claim_ticket_id'); }
    public function acknowledgment() { return $this->hasOne(AssistanceHandoverAcknowledgment::class, 'distribution_id'); }
}
