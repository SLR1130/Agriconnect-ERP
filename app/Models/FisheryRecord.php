<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FisheryRecord extends Model
{
    protected $fillable = ['farmer_id','farm_id','fishery_type','species','production_quantity','production_unit','recorded_on','notes','recorded_by'];
    protected function casts(): array { return ['recorded_on' => 'date', 'production_quantity' => 'decimal:2']; }
    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
    public function farm(): BelongsTo { return $this->belongsTo(Farm::class); }
}
