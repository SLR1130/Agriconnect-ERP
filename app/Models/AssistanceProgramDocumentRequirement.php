<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AssistanceProgramDocumentRequirement extends Model
{
    protected $fillable = [
        'requirement_version_id', 'requirement_key', 'display_name', 'description',
        'instructions', 'is_required', 'accepted_mime_types', 'max_size_bytes', 'display_order',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'accepted_mime_types' => 'array'];
    }

    protected static function booted(): void
    {
        $assertDraft = static function (self $requirement): void {
            if ($requirement->version?->status !== 'draft') {
                throw new LogicException('Requirements attached to a published version are immutable.');
            }
        };
        static::updating($assertDraft);
        static::deleting($assertDraft);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AssistanceProgramRequirementVersion::class, 'requirement_version_id');
    }

    public function applicationDocuments(): HasMany
    {
        return $this->hasMany(AssistanceApplicationDocument::class, 'requirement_id')->orderByDesc('version_number');
    }
}
