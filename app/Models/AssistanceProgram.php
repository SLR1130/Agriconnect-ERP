<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class AssistanceProgram extends Model
{
    protected $fillable = ['name', 'category', 'description', 'status', 'opens_on', 'closes_on', 'eligibility_rules', 'supply_item_id', 'allocation_limit'];

    protected $casts = ['opens_on' => 'date', 'closes_on' => 'date', 'eligibility_rules' => 'array', 'allocation_limit' => 'decimal:2'];

    public function applications()
    {
        return $this->hasMany(AssistanceApplication::class, 'program_id');
    }

    public function supplyItem()
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }

    public function availabilityStateOn(CarbonImmutable $businessDate): string
    {
        if ($this->status !== 'open') {
            return 'unpublished';
        }

        if ($this->isAvailableOn($businessDate)) {
            return 'available';
        }

        $opensOn = $this->opens_on?->toDateString();

        return $opensOn && $opensOn > $businessDate->toDateString() ? 'upcoming' : 'ended';
    }

    public function isAvailableOn(CarbonImmutable $businessDate): bool
    {
        $date = $businessDate->toDateString();
        $opensOn = $this->opens_on?->toDateString();
        $closesOn = $this->closes_on?->toDateString();

        return $this->status === 'open'
            && (! $opensOn || $opensOn <= $date)
            && (! $closesOn || $closesOn >= $date);
    }
}
