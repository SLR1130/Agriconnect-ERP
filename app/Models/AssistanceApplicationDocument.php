<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AssistanceApplicationDocument extends Model
{
    protected $fillable = [
        'application_id', 'requirement_version_id', 'requirement_id', 'version_number',
        'supersedes_document_id', 'uploaded_by', 'original_filename', 'mime_type',
        'file_size', 'sha256', 'storage_disk', 'storage_path', 'scan_status',
        'scan_engine', 'scan_completed_at', 'review_status', 'reviewed_by',
        'reviewed_at', 'farmer_correction_notes', 'internal_review_notes', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'scan_completed_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'uploaded_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            $immutable = [
                'application_id', 'requirement_version_id', 'requirement_id', 'version_number',
                'supersedes_document_id', 'uploaded_by', 'original_filename', 'mime_type',
                'file_size', 'sha256', 'storage_disk', 'storage_path', 'uploaded_at',
            ];
            if (array_intersect($immutable, array_keys($document->getDirty()))) {
                throw new LogicException('Uploaded document version metadata is immutable. Upload a replacement version instead.');
            }
        });
        static::deleting(fn () => throw new LogicException('Submitted document evidence is retained and cannot be deleted.'));
    }

    public function application(): BelongsTo { return $this->belongsTo(AssistanceApplication::class, 'application_id'); }
    public function requirementVersion(): BelongsTo { return $this->belongsTo(AssistanceProgramRequirementVersion::class, 'requirement_version_id'); }
    public function requirement(): BelongsTo { return $this->belongsTo(AssistanceProgramDocumentRequirement::class, 'requirement_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_document_id'); }
    public function events(): HasMany { return $this->hasMany(AssistanceApplicationDocumentEvent::class, 'document_id')->orderBy('created_at')->orderBy('id'); }
}
