<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionSubmission extends Model
{
    protected $fillable = ['farmer_id', 'farm_id', 'category', 'payload', 'status', 'idempotency_key', 'review_notes', 'reviewed_by', 'reviewed_at', 'official_record_id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProductionSubmissionHistory::class);
    }
}
