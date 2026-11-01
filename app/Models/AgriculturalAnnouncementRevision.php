<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriculturalAnnouncementRevision extends Model
{
    protected $fillable = [
        'announcement_id', 'revision_number', 'state', 'content', 'created_by', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return ['content' => 'array', 'published_at' => 'datetime'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(AgriculturalAnnouncement::class, 'announcement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(AgriculturalAnnouncementMedia::class, 'revision_id')->orderBy('sort_order')->orderBy('id');
    }
}
