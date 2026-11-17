<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PestDiseaseIncident extends Model
{
    protected $fillable = ['farmer_id','farm_id','incident_type','affected_crop_or_animal','reported_on','severity','status','description','action_taken','reported_by'];
    protected function casts(): array { return ['reported_on' => 'date']; }
    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
    public function farm(): BelongsTo { return $this->belongsTo(Farm::class); }
}
