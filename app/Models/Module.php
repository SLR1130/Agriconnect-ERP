<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'developer',
        'description',
        'route_prefix',
        'icon',
        'sort_order',
        'is_enabled',
        'is_active',
        'development_progress',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'is_enabled' => 'boolean',
        'is_active' => 'boolean',
        'development_progress' => 'integer',
    ];

    /**
     * Get the access control records for this module.
     */
    public function moduleAccess(): HasMany
    {
        return $this->hasMany(ModuleAccess::class);
    }

    /**
     * Get the roles that can access this module.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'module_access')
            ->withPivot('permissions')
            ->withTimestamps();
    }

    /**
     * Check if a role has access to this module.
     */
    public function hasRoleAccess($roleId): bool
    {
        return $this->moduleAccess()
            ->where('role_id', $roleId)
            ->exists();
    }

    /**
     * Get permissions for a specific role.
     */
    public function getPermissionsForRole($roleId)
    {
        $access = $this->moduleAccess()
            ->where('role_id', $roleId)
            ->first();

        return $access ? $access->permissions : [];
    }
}
