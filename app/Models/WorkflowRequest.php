<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowRequest extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'module_id',
        'requester_id',
        'assigned_to',
        'type',
        'title',
        'description',
        'status',
        'payload',
        'due_at',
        'submitted_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'due_at' => 'datetime',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->reference ??= 'WR-' . Str::upper((string) Str::ulid());
            $request->submitted_at ??= now();
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class);
    }
}
