<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceApplicationAcknowledgment extends Model
{
    protected $fillable = ['application_id', 'application_reference', 'snapshot', 'snapshot_sha256', 'generated_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'generated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Application acknowledgment snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Application acknowledgment snapshots are retained.'));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AssistanceApplication::class, 'application_id');
    }
}
