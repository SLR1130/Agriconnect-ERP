<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentRequest extends Model
{
    protected $fillable = [
        'reference',
        'farmer_id',
        'requested_by',
        'equipment_asset_id',
        'intended_use',
        'schedule_start',
        'schedule_end',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'issued_at',
        'returned_at',
        'checkout_condition',
        'return_condition',
        'idempotency_key',
    ];

    protected $casts = [
        'schedule_start' => 'datetime',
        'schedule_end' => 'datetime',
        'reviewed_at' => 'datetime',
        'issued_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(EquipmentAsset::class, 'equipment_asset_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EquipmentTransaction::class, 'equipment_request_id');
    }
}
