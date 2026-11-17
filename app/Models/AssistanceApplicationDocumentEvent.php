<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceApplicationDocumentEvent extends Model
{
    protected $fillable = ['document_id', 'event_type', 'from_scan_status', 'to_scan_status', 'from_review_status', 'to_review_status', 'actor_id', 'visibility', 'note', 'internal_note', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Document audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Document audit events are retained.'));
    }
    public function document(): BelongsTo { return $this->belongsTo(AssistanceApplicationDocument::class, 'document_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
