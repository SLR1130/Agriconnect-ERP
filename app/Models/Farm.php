<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Farm extends Model
{
    protected $fillable = [
        'farmer_id', 'name', 'barangay', 'location_details', 'area', 'area_unit',
        'ownership_type', 'classification', 'tenancy_details', 'soil_type', 'irrigation_type',
        'agricultural_activities', 'categories', 'status',
    ];

    protected function casts(): array
    {
        return ['area' => 'decimal:2', 'agricultural_activities' => 'array', 'categories' => 'array'];
    }

    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
}
