<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryLocation extends Model { protected $fillable=['name','description','is_active']; protected $casts=['is_active'=>'boolean']; public function batches(){return $this->hasMany(SupplyBatch::class,'location_id');} }
