<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentAcquisitionAsset extends Model
{
    protected $table = 'procurement_equipment_acquisition_assets';

    protected $fillable = ['procurement_equipment_acquisition_id', 'equipment_asset_id', 'unit_number'];

    protected $casts = ['unit_number' => 'integer'];

    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(EquipmentAcquisition::class, 'procurement_equipment_acquisition_id');
    }

    public function equipmentAsset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class);
    }
}
