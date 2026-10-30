<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleAccessSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'super_admin' => ['*'],
            'mao_admin' => ['view', 'create', 'update', 'delete', 'manage'],
            'municipal_agriculturist' => ['view', 'create', 'update'],
            // The default local assignment is AISMS only. Additional module
            // assignments must be explicitly reviewed and added by an admin.
            'module_personnel' => ['view', 'create', 'update', 'receive', 'release', 'adjust'],
            'authorized_viewer' => ['view'],
        ];

        $now = now();

        foreach ($rolePermissions as $roleSlug => $permissions) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

            if (! $roleId) {
                continue;
            }

            $moduleIds = DB::table('modules')->pluck('id', 'slug');

            foreach ($moduleIds as $moduleSlug => $moduleId) {
                $allowed = match ($roleSlug) {
                    'super_admin', 'mao_admin', 'authorized_viewer' => true,
                    'module_personnel' => $moduleSlug === 'aisms',
                    'municipal_agriculturist' => in_array($moduleSlug, ['ffrms', 'apms', 'aasms'], true),
                    default => false,
                };

                if (! $allowed) {
                    continue;
                }

                DB::table('module_access')->updateOrInsert(
                    ['module_id' => $moduleId, 'role_id' => $roleId],
                    [
                        'permissions' => json_encode($permissions),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        }
    }
}
