<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmerRegistrationHistory extends Model
{
    protected $fillable = ['farmer_id', 'from_status', 'to_status', 'reason', 'processed_by'];

    public function farmer(): BelongsTo { return $this->belongsTo(Farmer::class); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
