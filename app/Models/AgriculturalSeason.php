<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriculturalSeason extends Model
{
    protected $fillable = ['name', 'starts_on', 'ends_on', 'status'];
    protected function casts(): array { return ['starts_on' => 'date', 'ends_on' => 'date']; }
    public function crops(): HasMany { return $this->hasMany(CropProductionRecord::class, 'season_id'); }
}
