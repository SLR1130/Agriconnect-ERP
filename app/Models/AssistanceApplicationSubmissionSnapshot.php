<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceApplicationSubmissionSnapshot extends Model
{
    protected $fillable = ['application_id', 'attempt_number', 'submission_type', 'snapshot', 'snapshot_sha256', 'created_by', 'submitted_at'];
    protected function casts(): array { return ['snapshot' => 'array', 'submitted_at' => 'immutable_datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Submission snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Submission snapshots are retained.'));
    }
    public function application(): BelongsTo { return $this->belongsTo(AssistanceApplication::class, 'application_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
