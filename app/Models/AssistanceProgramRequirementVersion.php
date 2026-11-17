<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AssistanceProgramRequirementVersion extends Model
{
    protected $fillable = [
        'program_id', 'version_number', 'status', 'source', 'eligibility_rules',
        'opens_on', 'closes_on', 'created_by', 'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'eligibility_rules' => 'array',
            'opens_on' => 'date',
            'closes_on' => 'date',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('status') !== 'draft') {
                throw new LogicException('Published and legacy requirement versions are immutable.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->status !== 'draft') {
                throw new LogicException('Published and legacy requirement versions cannot be deleted.');
            }
        });
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AssistanceProgram::class, 'program_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function documentRequirements(): HasMany
    {
        return $this->hasMany(AssistanceProgramDocumentRequirement::class, 'requirement_version_id')
            ->orderBy('display_order')->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AssistanceApplication::class, 'requirement_version_id');
    }
}
