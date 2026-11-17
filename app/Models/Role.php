<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the users associated with this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * Get the module access permissions for this role.
     */
    public function moduleAccess()
    {
        return $this->hasMany(ModuleAccess::class);
    }

    /**
     * Get the agricultural modules available to this role.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_access')
            ->withPivot('permissions')
            ->withTimestamps();
    }

    /**
     * Check if role has permission.
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
