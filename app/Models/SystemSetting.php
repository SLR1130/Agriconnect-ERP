<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'key',
        'group',
        'value',
        'is_public',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
