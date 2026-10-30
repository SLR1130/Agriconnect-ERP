<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmMonitoringRecord extends Model
{
    protected $fillable = ['farmer_id','farm_id','visited_on','monitoring_type','findings','recommendations','status','recorded_by'];
    protected function casts(): array { return ['visited_on' => 'date']; }
    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
    public function farm(): BelongsTo { return $this->belongsTo(Farm::class); }
}
