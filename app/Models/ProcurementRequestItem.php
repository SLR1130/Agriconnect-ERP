<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementRequestItem extends Model
{
    protected $fillable = ['procurement_request_id', 'supply_item_id', 'description', 'quantity', 'estimated_unit_price', 'estimated_total'];
    protected $casts = ['quantity' => 'decimal:2', 'estimated_unit_price' => 'decimal:2', 'estimated_total' => 'decimal:2'];

    public function request(): BelongsTo { return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id'); }
    public function supplyItem(): BelongsTo { return $this->belongsTo(SupplyItem::class, 'supply_item_id'); }
    public function purchaseOrderItems(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }
}
