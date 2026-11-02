<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AssistanceApplicationDocumentAccessLog extends Model
{
    protected $fillable = ['document_id', 'actor_id', 'access_action', 'accessed_at'];
    protected function casts(): array { return ['accessed_at' => 'immutable_datetime']; }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Document access audit records are immutable.'));
        static::deleting(fn () => throw new LogicException('Document access audit records are retained.'));
    }
}
