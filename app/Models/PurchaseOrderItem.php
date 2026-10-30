<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'procurement_request_item_id', 'supply_item_id', 'equipment_asset_id', 'description', 'ordered_quantity', 'unit_price', 'line_total', 'received_quantity'];
    protected $casts = ['ordered_quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'received_quantity' => 'decimal:2'];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function requestItem(): BelongsTo { return $this->belongsTo(ProcurementRequestItem::class, 'procurement_request_item_id'); }
    public function supplyItem(): BelongsTo { return $this->belongsTo(SupplyItem::class, 'supply_item_id'); }
    public function equipmentAsset(): BelongsTo { return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id'); }
    public function receiptItems(): HasMany { return $this->hasMany(ProcurementReceiptItem::class); }
}
