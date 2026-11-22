<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryMovement extends Model
{
    protected $fillable = ['reference', 'supply_batch_id', 'user_id', 'movement_type', 'quantity', 'recipient', 'purpose', 'notes', 'status', 'idempotency_key', 'adjustment_reason', 'received_on'];

    protected $casts = ['quantity' => 'decimal:2', 'received_on' => 'date'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SupplyBatch::class, 'supply_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function procurementReceiptItem(): HasOne
    {
        return $this->hasOne(ProcurementReceiptItem::class, 'inventory_movement_id');
    }

    public function assistanceDistribution(): HasOne
    {
        return $this->hasOne(AssistanceDistribution::class, 'reference', 'reference');
    }
}
