<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LivestockRecord extends Model
{
    protected $fillable = ['farmer_id','farm_id','animal_type','breed','quantity','recorded_on','status','notes','recorded_by'];
    protected function casts(): array { return ['recorded_on' => 'date']; }
    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
    public function farm(): BelongsTo { return $this->belongsTo(Farm::class); }
}
