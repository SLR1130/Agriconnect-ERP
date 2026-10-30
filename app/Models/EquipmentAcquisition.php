<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentAcquisition extends Model
{
    protected $table = 'procurement_equipment_acquisitions';

    protected $fillable = [
        'purchase_order_item_id', 'procurement_receipt_item_id', 'category_id', 'created_by',
        'idempotency_key', 'quantity', 'asset_name', 'condition', 'assigned_location',
        'acquired_on', 'unit_acquisition_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'acquired_on' => 'date',
        'unit_acquisition_cost' => 'decimal:2',
    ];

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementReceiptItem::class, 'procurement_receipt_item_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(EquipmentAcquisitionAsset::class, 'procurement_equipment_acquisition_id');
    }
}
