<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EquipmentAsset extends Model
{
    protected $fillable = [
        'asset_code',
        'name',
        'category_id',
        'description',
        'acquired_on',
        'acquisition_cost',
        'condition',
        'availability_status',
        'assigned_location',
        'responsible_user_id',
    ];

    protected $casts = [
        'acquired_on' => 'date',
        'acquisition_cost' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(EquipmentRequest::class, 'equipment_asset_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class, 'equipment_asset_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EquipmentTransaction::class, 'equipment_asset_id');
    }

    public function procurementAcquisition(): HasOne
    {
        return $this->hasOne(EquipmentAcquisitionAsset::class, 'equipment_asset_id');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'equipment_asset_id');
    }
}
