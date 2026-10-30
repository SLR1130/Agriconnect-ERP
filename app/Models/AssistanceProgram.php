<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceProgram extends Model
{
    protected $fillable = ['name','category','description','status','opens_on','closes_on','eligibility_rules','supply_item_id','allocation_limit'];
    protected $casts = ['opens_on'=>'date','closes_on'=>'date','eligibility_rules'=>'array','allocation_limit'=>'decimal:2'];
    public function applications(){ return $this->hasMany(AssistanceApplication::class, 'program_id'); }
    public function supplyItem(){ return $this->belongsTo(SupplyItem::class, 'supply_item_id'); }
    public function isAvailable(): bool
    {
        $today = today();

        return $this->status === 'open'
            && (! $this->opens_on || $this->opens_on->lte($today))
            && (! $this->closes_on || $this->closes_on->gte($today));
    }
}
