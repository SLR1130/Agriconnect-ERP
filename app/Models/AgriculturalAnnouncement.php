<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgriculturalAnnouncement extends Model
{
    protected $fillable = [
        'title', 'body', 'status', 'assistance_program_id', 'published_at', 'expires_at',
        'created_by', 'published_by', 'office', 'category', 'service_area',
        'requirements_summary', 'contact_details', 'instructions', 'current_revision_id', 'published_revision_id',
    ];

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

    public function revisions(): HasMany
    {
        return $this->hasMany(AgriculturalAnnouncementRevision::class, 'announcement_id')->orderByDesc('revision_number');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(AgriculturalAnnouncementRevision::class, 'current_revision_id');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(AgriculturalAnnouncementRevision::class, 'published_revision_id');
    }

    public function scopeVisibleToFarmers($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now())
            ->where(fn ($notices) => $notices->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($notices) => $notices->whereNull('assistance_program_id')
                ->orWhereHas('program', fn ($program) => $program->where('status', 'open')));
    }

    public function editorContent(): array
    {
        return $this->currentRevision?->content ?? $this->legacyContent();
    }

    public function publicContent(): array
    {
        return $this->publishedRevision?->content ?? $this->legacyContent();
    }

    private function legacyContent(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'assistance_program_id' => $this->assistance_program_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'office' => $this->office,
            'category' => $this->category,
            'service_area' => $this->service_area,
            'requirements_summary' => $this->requirements_summary,
            'contact_details' => $this->contact_details,
            'instructions' => $this->instructions,
        ];
    }
}
