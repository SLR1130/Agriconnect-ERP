<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

/**
 * Effective ERP authorization policy.
 *
 * Stored role permissions remain the source of grants, but built-in roles have
 * an explicit upper bound so legacy broad permissions cannot accidentally turn
 * an operational role into a system administrator. Custom roles continue to
 * use the existing permission behaviour for backwards compatibility.
 */
class ErpAuthorizationService
{
    private const BUILT_IN_ROLES = [
        'super_admin',
        'mao_admin',
        'municipal_agriculturist',
        'module_personnel',
        'authorized_viewer',
        'farmer',
    ];

    private const ROLE_POLICY = [
        'mao_admin' => [
            'global' => ['dashboard.view', 'modules.view', 'reports.view', 'reports.generate', 'farmer.verify'],
            'modules' => [
                'ffrms' => ['view', 'create', 'update', 'review', 'approve', 'reject', 'export'],
                'apms' => ['view', 'create', 'update', 'export'],
                'aisms' => ['view', 'create', 'receive', 'release', 'adjust', 'export'],
                'aasms' => ['view', 'create', 'update', 'review', 'approve', 'reject', 'distribute', 'export'],
                'aeams' => ['view', 'create', 'update', 'review', 'issue', 'return', 'maintenance', 'export'],
                'abpms' => ['view', 'create', 'update', 'review', 'approve', 'reject', 'export'],
            ],
        ],
        'municipal_agriculturist' => [
            'global' => ['dashboard.view', 'reports.view', 'farmer.verify'],
            'modules' => [
                'ffrms' => ['view', 'update', 'review', 'export'],
                'apms' => ['view', 'create', 'update', 'export'],
                'aasms' => ['view', 'review', 'export'],
            ],
        ],
        'module_personnel' => [
            'global' => ['dashboard.view'],
            'modules' => [
                'ffrms' => ['view', 'create', 'update', 'review', 'export'],
                'apms' => ['view', 'create', 'update', 'export'],
                'aisms' => ['view', 'create', 'update', 'receive', 'release', 'adjust', 'export'],
                'aasms' => ['view', 'create', 'update', 'review', 'export'],
                'aeams' => ['view', 'create', 'update', 'review', 'issue', 'return', 'maintenance', 'export'],
                'abpms' => ['view', 'create', 'update', 'review', 'export'],
            ],
        ],
        'authorized_viewer' => [
            'global' => ['dashboard.view', 'reports.view', 'activity_logs.view'],
            'modules' => [
                'ffrms' => ['view', 'export'],
                'apms' => ['view', 'export'],
                'aisms' => ['view', 'export'],
                'aasms' => ['view', 'export'],
                'aeams' => ['view', 'export'],
                'abpms' => ['view', 'export'],
            ],
        ],
        'farmer' => [
            'global' => ['farmer.profile.manage', 'farmer.farms.manage', 'farmer.assistance.apply', 'farmer.equipment.request'],
            'modules' => [],
        ],
    ];

    public function allows(?User $user, string $permission): bool
    {
        if (! $user || ! $user->isActive()) {
            return false;
        }

        $roles = $user->roles()->with(['moduleAccess.module'])->get();
        $userAssignments = $user->moduleAssignments()->with('module')->get();
        $hasExplicitModulePersonnelAssignments = $userAssignments->isNotEmpty();

        foreach ($roles as $role) {
            if ($role->slug === 'module_personnel'
                && $hasExplicitModulePersonnelAssignments
                && str_starts_with($permission, 'modules.')) {
                // Once an account has an explicit assignment record, that
                // record is authoritative for Module Personnel module access.
                // This lets an administrator revoke legacy AISMS defaults
                // without changing untouched legacy accounts.
                if ($this->userModuleAllows($role, $permission, $userAssignments)) {
                    return true;
                }

                continue;
            }

            if ($this->roleAllows($role, $permission)) {
                return true;
            }

            if ($this->userModuleAllows($role, $permission, $userAssignments)) {
                return true;
            }
        }

        return false;
    }

    public function canModule(?User $user, string $module, string $action = 'view'): bool
    {
        return $this->allows($user, 'modules.'.$module.'.'.$action);
    }

    public function isSuperAdmin(?User $user): bool
    {
        return (bool) $user?->isActive()
            && $user->roles()->where('slug', 'super_admin')->where('is_active', true)->exists();
    }

    /**
     * Evaluate one role without bypassing the effective policy boundary.
     *
     * This is also used by the administrator read-only permission inspector so
     * the UI and request authorization share the same decision point.
     */
    public function roleAllows(Role $role, string $permission): bool
    {
        if (! $role->is_active) {
            return false;
        }

        if ($role->slug === 'super_admin') {
            return true;
        }

        if (! in_array($role->slug, self::BUILT_IN_ROLES, true)) {
            return $this->rawRoleGrant($role, $permission);
        }

        if (! $this->withinRolePolicy($role->slug, $permission)) {
            return false;
        }

        return $this->roleGrant($role, $permission);
    }

    public function visibleModules(?User $user): array
    {
        $modules = ['ffrms', 'apms', 'aisms', 'aasms', 'aeams', 'abpms'];

        return array_values(array_filter($modules, fn (string $module) => $this->canModule($user, $module)));
    }

    public function policyMatrix(): array
    {
        return self::ROLE_POLICY;
    }

    /**
     * Actions that may be delegated to an individual Module Personnel account.
     * Workflow approval and system administration remain outside this boundary.
     */
    public function delegatedModuleActions(): array
    {
        return [
            'ffrms' => ['view', 'create', 'update', 'review', 'export'],
            'apms' => ['view', 'create', 'update', 'export'],
            'aisms' => ['view', 'create', 'update', 'receive', 'release', 'adjust', 'export'],
            'aasms' => ['view', 'create', 'update', 'review', 'export'],
            'aeams' => ['view', 'create', 'update', 'review', 'issue', 'return', 'maintenance', 'export'],
            'abpms' => ['view', 'create', 'update', 'review', 'export'],
        ];
    }

    private function withinRolePolicy(string $roleSlug, string $permission): bool
    {
        $policy = self::ROLE_POLICY[$roleSlug] ?? null;

        if (! $policy) {
            return false;
        }

        if (isset($policy['global'][$permission])) {
            return true;
        }

        if (in_array($permission, $policy['global'], true)) {
            return true;
        }

        if (preg_match('/^modules\.([a-z0-9_]+)\.([a-z0-9_]+)$/', $permission, $matches)) {
            return in_array($matches[2], $policy['modules'][$matches[1]] ?? [], true);
        }

        return false;
    }

    private function roleGrant(Role $role, string $permission): bool
    {
        if ($permission === 'dashboard.view' && in_array($role->slug, ['mao_admin', 'municipal_agriculturist', 'module_personnel'], true)) {
            // Legacy production role rows predate the explicit dashboard grant.
            // Their existing module assignment is sufficient for the shared
            // staff shell, but never broadens module or admin capabilities.
            return $role->hasPermission($permission)
                || $role->moduleAccess->isNotEmpty()
                || collect($role->permissions ?? [])->contains(fn (string $grant) => str_starts_with($grant, 'modules.'));
        }

        if ($role->slug === 'module_personnel' && str_starts_with($permission, 'modules.')) {
            return $this->modulePersonnelGrant($role, $permission);
        }

        if (str_ends_with($permission, '.export') && ($role->hasPermission('reports.view') || $role->hasPermission('reports.generate'))) {
            return true;
        }

        if ($role->hasPermission($permission)) {
            return true;
        }

        if (preg_match('/^modules\.([a-z0-9_]+)\.([a-z0-9_]+)$/', $permission, $matches)) {
            $module = $matches[1];
            $action = $matches[2];

            return $role->moduleAccess
                ->first(fn ($access) => $access->module?->slug === $module && $access->hasPermission($action)) !== null;
        }

        return false;
    }

    private function userModuleAllows(Role $role, string $permission, $assignments): bool
    {
        if ($role->slug !== 'module_personnel' || ! $role->is_active) {
            return false;
        }

        if (! preg_match('/^modules\.([a-z0-9_]+)\.([a-z0-9_]+)$/', $permission, $matches)) {
            return false;
        }

        [$module, $action] = [$matches[1], $matches[2]];
        if (! in_array($action, $this->delegatedModuleActions()[$module] ?? [], true)) {
            return false;
        }

        return $assignments->first(fn ($assignment) =>
            $assignment->module?->slug === $module && $assignment->hasPermission($action)
        ) !== null;
    }

    private function modulePersonnelGrant(Role $role, string $permission): bool
    {
        if (! preg_match('/^modules\.([a-z0-9_]+)\.([a-z0-9_]+)$/', $permission, $matches)) {
            return false;
        }

        [$module, $action] = [$matches[1], $matches[2]];
        $explicitModules = collect($role->permissions ?? [])
            ->map(function (string $granted) {
                return preg_match('/^modules\.([a-z0-9_]+)\./', $granted, $matches) ? $matches[1] : null;
            })
            ->filter()
            ->unique()
            ->values();

        if ($explicitModules->isNotEmpty() && ! $explicitModules->contains($module)) {
            return false;
        }

        if ($role->hasPermission('modules.'.$module.'.*') || $role->hasPermission($permission)) {
            return true;
        }

        return $role->moduleAccess
            ->first(fn ($access) => $access->module?->slug === $module && $access->hasPermission($action)) !== null;
    }

    private function rawRoleGrant(Role $role, string $permission): bool
    {
        if ($role->hasPermission($permission)) {
            return true;
        }

        if (preg_match('/^modules\.([a-z0-9_]+)\.([a-z0-9_]+)$/', $permission, $matches)) {
            return $role->moduleAccess
                ->first(fn ($access) => $access->module?->slug === $matches[1] && $access->hasPermission($matches[2])) !== null;
        }

        return false;
    }
}
