<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $fillable = [
        'workflow_request_id',
        'from_status',
        'to_status',
        'transitioned_by',
        'comments',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function workflowRequest(): BelongsTo
    {
        return $this->belongsTo(WorkflowRequest::class);
    }

    public function transitionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }
}
