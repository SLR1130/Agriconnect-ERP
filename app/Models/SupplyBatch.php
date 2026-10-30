<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplyBatch extends Model { protected $fillable=['supply_item_id','location_id','batch_number','received_on','expires_on','quantity_received','quantity_available','status']; protected $casts=['received_on'=>'date','expires_on'=>'date','quantity_received'=>'decimal:2','quantity_available'=>'decimal:2']; public function item(){return $this->belongsTo(SupplyItem::class,'supply_item_id');} public function location(){return $this->belongsTo(InventoryLocation::class,'location_id');} public function movements(){return $this->hasMany(InventoryMovement::class);} }
