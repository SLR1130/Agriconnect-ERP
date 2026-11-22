<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    use HasFactory;

    protected $fillable = [
        'farmer_id', 'name', 'barangay', 'location_details', 'area', 'area_unit',
        'ownership_type', 'classification', 'tenancy_details', 'soil_type', 'irrigation_type',
        'agricultural_activities', 'categories', 'status', 'latitude', 'longitude',
        'location_accuracy_meters', 'location_source', 'boundary_coordinates',
    ];

    protected function casts(): array
    {
        return [
            'area' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_accuracy_meters' => 'decimal:2',
            'boundary_coordinates' => 'array',
            'agricultural_activities' => 'array',
            'categories' => 'array',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

}
