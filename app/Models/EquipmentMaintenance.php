<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMaintenance extends Model
{
    protected $fillable = [
        'equipment_asset_id',
        'reported_by',
        'status',
        'description',
        'cost',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
