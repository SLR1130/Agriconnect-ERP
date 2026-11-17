<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ModuleAccess extends Model
{
    protected $table = 'module_access';

    protected $fillable = [
        'module_id',
        'role_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the module associated with this access record.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the role associated with this access record.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if this access has a specific permission.
     */
    public function hasPermission($permission): bool
    {
        if (!$this->permissions) {
            return false;
        }

        if (in_array('*', $this->permissions)) {
            return true;
        }

        return collect($this->permissions)->contains(
            fn (string $granted) => Str::is($granted, $permission),
        );
    }
}
