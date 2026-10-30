<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UnitOfMeasurement extends Model { protected $table='units_of_measurement'; protected $fillable=['name','symbol']; public function items(){return $this->hasMany(SupplyItem::class,'unit_id');} }
