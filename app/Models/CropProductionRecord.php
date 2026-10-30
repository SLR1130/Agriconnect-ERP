<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CropProductionRecord extends Model
{
    protected $fillable = ['farmer_id','farm_id','season_id','crop_type','variety','planted_on','harvested_on','area_planted','area_unit','yield_quantity','yield_unit','status','notes','recorded_by'];
    protected function casts(): array { return ['planted_on' => 'date', 'harvested_on' => 'date', 'area_planted' => 'decimal:2', 'yield_quantity' => 'decimal:2']; }
    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
    public function farm(): BelongsTo { return $this->belongsTo(Farm::class); }
    public function season(): BelongsTo { return $this->belongsTo(AgriculturalSeason::class, 'season_id'); }
}
