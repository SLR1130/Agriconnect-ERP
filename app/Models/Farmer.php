<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farmer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'farmer_code', 'phone', 'address', 'barangay', 'registration_status',
        'review_notes', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    public function registrationHistory(): HasMany
    {
        return $this->hasMany(FarmerRegistrationHistory::class);
    }

    public function associations(): BelongsToMany
    {
        return $this->belongsToMany(FarmerAssociation::class, 'farmer_association_members', 'farmer_id', 'association_id')->withPivot(['membership_status', 'joined_at'])->withTimestamps();
    }

    public function isVerified(): bool
    {
        return $this->registration_status === 'verified';
    }
}
