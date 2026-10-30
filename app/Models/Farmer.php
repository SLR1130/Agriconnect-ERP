<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farmer extends Model
{
    protected $fillable = [
        'user_id', 'farmer_code', 'phone', 'address', 'barangay', 'registration_status',
        'review_notes', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function farms(): HasMany { return $this->hasMany(Farm::class); }
    public function registrationHistory(): HasMany { return $this->hasMany(FarmerRegistrationHistory::class); }
    public function isVerified(): bool { return $this->registration_status === 'verified'; }
}
