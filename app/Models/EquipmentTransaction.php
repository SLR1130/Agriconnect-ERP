<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentTransaction extends Model
{
    protected $fillable = [
        'equipment_asset_id',
        'equipment_request_id',
        'performed_by',
        'action',
        'condition',
        'notes',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(EquipmentRequest::class, 'equipment_request_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
