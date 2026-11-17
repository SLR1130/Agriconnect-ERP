<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetPeriod extends Model
{
    protected $fillable = ['year', 'name', 'starts_on', 'ends_on', 'status'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }
}
