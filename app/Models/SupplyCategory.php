<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplyCategory extends Model { protected $fillable=['name','description','is_active']; protected $casts=['is_active'=>'boolean']; public function items(){return $this->hasMany(SupplyItem::class,'category_id');} }
