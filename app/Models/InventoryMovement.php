<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryMovement extends Model { protected $fillable=['reference','supply_batch_id','user_id','movement_type','quantity','recipient','purpose','notes','status','idempotency_key','adjustment_reason']; protected $casts=['quantity'=>'decimal:2']; public function batch(){return $this->belongsTo(SupplyBatch::class,'supply_batch_id');} public function user(){return $this->belongsTo(User::class);} }
