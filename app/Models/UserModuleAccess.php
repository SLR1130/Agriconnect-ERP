<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleAccess extends Model
{
    protected $table = 'user_module_access';

    protected $fillable = [
        'user_id',
        'module_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array('*', $this->permissions ?? [], true)
            || collect($this->permissions ?? [])->contains(fn (string $granted) => \Illuminate\Support\Str::is($granted, $permission));
    }
}
