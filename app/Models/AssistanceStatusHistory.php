<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; class AssistanceStatusHistory extends Model {protected $fillable=['application_id','from_status','to_status','changed_by','notes'];}
