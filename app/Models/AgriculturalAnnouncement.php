<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgriculturalAnnouncement extends Model
{
    protected $fillable = ['title', 'body', 'status', 'assistance_program_id', 'published_at', 'expires_at', 'created_by', 'published_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AssistanceProgram::class, 'assistance_program_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleToFarmers($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now())
            ->where(fn ($notices) => $notices->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
