<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceApplication extends Model
{
    protected $fillable = ['program_id','farmer_id','purpose','eligibility_passed','eligibility_notes','status','reviewed_by','review_notes','approved_quantity','approved_supply_item_id','allocation_confirmed_at'];
    protected $casts = ['approved_quantity'=>'decimal:2','eligibility_passed'=>'boolean','allocation_confirmed_at'=>'datetime'];
    public function program(){ return $this->belongsTo(AssistanceProgram::class, 'program_id'); }
    public function farmer(){ return $this->belongsTo(Farmer::class); }
    public function distributions(){ return $this->hasMany(AssistanceDistribution::class, 'application_id'); }
    public function history(){ return $this->hasMany(AssistanceStatusHistory::class, 'application_id'); }
    public function approvedSupplyItem(){ return $this->belongsTo(SupplyItem::class, 'approved_supply_item_id'); }
}
