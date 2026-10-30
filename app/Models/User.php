<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'is_active', 'suspended_at', 'activated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'suspended_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * Get the roles associated with the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function moduleAssignments()
    {
        return $this->hasMany(UserModuleAccess::class);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole($roleSlug): bool
    {
        return $this->roles()
            ->where('roles.slug', $roleSlug)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission($permission): bool
    {
        return $this->roles()
            ->get()
            ->some(function ($role) use ($permission) {
                return $role->hasPermission($permission);
            });
    }

    /**
     * Check the effective ERP policy rather than only the stored role wildcard.
     */
    public function canErp(string $permission): bool
    {
        return app(\App\Services\ErpAuthorizationService::class)->allows($this, $permission);
    }

    public function canModuleAction(string $moduleSlug, string $action = 'view'): bool
    {
        return app(\App\Services\ErpAuthorizationService::class)->canModule($this, $moduleSlug, $action);
    }

    public function isSuperAdministrator(): bool
    {
        return app(\App\Services\ErpAuthorizationService::class)->isSuperAdmin($this);
    }

    public function farmer(): HasOne
    {
        return $this->hasOne(Farmer::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->suspended_at === null;
    }

    /**
     * Get all permissions for the user across all roles.
     */
    public function getPermissions()
    {
        $permissions = [];
        foreach ($this->roles as $role) {
            if ($role->permissions) {
                $permissions = array_merge($permissions, $role->permissions);
            }
        }
        return array_unique($permissions);
    }

    /**
     * Get workflow requests submitted by the user.
     */
    public function workflowRequests(): HasMany
    {
        return $this->hasMany(WorkflowRequest::class, 'requester_id');
    }

    /**
     * Get workflow requests assigned to the user.
     */
    public function assignedWorkflowRequests(): HasMany
    {
        return $this->hasMany(WorkflowRequest::class, 'assigned_to');
    }

    /**
     * Get workflow transitions performed by the user.
     */
    public function workflowTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'transitioned_by');
    }

    /**
     * Check whether the user has access to an enabled module.
     */
    public function hasModuleAccess(string $moduleSlug, ?string $permission = null): bool
    {
        $access = $this->roles()
            ->with(['moduleAccess.module'])
            ->get()
            ->flatMap(fn (Role $role) => $role->moduleAccess)
            ->first(fn (ModuleAccess $moduleAccess) =>
                $moduleAccess->module?->slug === $moduleSlug
                && $moduleAccess->module->is_enabled
            );

        return $access !== null && ($permission === null || $access->hasPermission($permission));
    }
}
