<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplyItem extends Model { protected $fillable=['category_id','unit_id','code','name','description','minimum_stock','status']; protected $casts=['minimum_stock'=>'decimal:2']; public function category(){return $this->belongsTo(SupplyCategory::class,'category_id');} public function unit(){return $this->belongsTo(UnitOfMeasurement::class,'unit_id');} public function batches(){return $this->hasMany(SupplyBatch::class);} }
