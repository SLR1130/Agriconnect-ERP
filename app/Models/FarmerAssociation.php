<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FarmerAssociation extends Model
{
    protected $fillable = ['name', 'registration_reference', 'barangay', 'description', 'status', 'created_by'];
    public function farmers(): BelongsToMany { return $this->belongsToMany(Farmer::class, 'farmer_association_members', 'association_id', 'farmer_id')->withPivot(['membership_status', 'joined_at'])->withTimestamps(); }
    public function members(): BelongsToMany { return $this->farmers(); }
}
