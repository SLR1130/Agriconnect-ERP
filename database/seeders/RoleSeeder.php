<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super_admin',
                'description' => 'Full system access with all permissions',
                'permissions' => json_encode(['*']),
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'MAO Administrator',
                'slug' => 'mao_admin',
                'description' => 'Municipal Agriculture Office administrative access',
                'permissions' => json_encode([
                    'dashboard.view',
                    'modules.view',
                    'modules.ffrms.view',
                    'modules.ffrms.create',
                    'modules.ffrms.update',
                    'modules.ffrms.review',
                    'modules.ffrms.approve',
                    'modules.ffrms.reject',
                    'modules.apms.view',
                    'modules.apms.create',
                    'modules.apms.update',
                    'modules.aisms.view',
                    'modules.aisms.create',
                    'modules.aisms.receive',
                    'modules.aisms.release',
                    'modules.aisms.adjust',
                    'modules.aasms.view',
                    'modules.aasms.create',
                    'modules.aasms.update',
                    'modules.aasms.review',
                    'modules.aasms.approve',
                    'modules.aasms.reject',
                    'modules.aasms.distribute',
                    'modules.aeams.view',
                    'modules.aeams.create',
                    'modules.aeams.update',
                    'modules.aeams.review',
                    'modules.aeams.issue',
                    'modules.aeams.return',
                    'modules.aeams.maintenance',
                    'modules.abpms.view',
                    'modules.abpms.create',
                    'modules.abpms.update',
                    'modules.abpms.review',
                    'modules.abpms.approve',
                    'modules.abpms.reject',
                    'farmer.verify',
                    'reports.view',
                    'reports.generate',
                ]),
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Municipal Agriculturist',
                'slug' => 'municipal_agriculturist',
                'description' => 'Technical agricultural functions and monitoring',
                'permissions' => json_encode([
                    'dashboard.view',
                    'modules.view',
                    'modules.ffrms.view',
                    'modules.ffrms.update',
                    'modules.ffrms.review',
                    'modules.apms.view',
                    'modules.apms.create',
                    'modules.apms.update',
                    'modules.aasms.view',
                    'modules.aasms.review',
                    'farmer.verify',
                    'reports.view',
                ]),
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Module Personnel',
                'slug' => 'module_personnel',
                'description' => 'Module-specific access for assigned functions',
                'permissions' => json_encode([
                    'dashboard.view',
                    'modules.aisms.view',
                    'modules.aisms.create',
                    'modules.aisms.update',
                    'modules.aisms.receive',
                    'modules.aisms.release',
                    'modules.aisms.adjust',
                ]),
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Authorized Viewer/Auditor',
                'slug' => 'authorized_viewer',
                'description' => 'Read-only access for auditing and monitoring',
                'permissions' => json_encode([
                    'dashboard.view',
                    'modules.*.view',
                    'reports.view',
                    'activity_logs.view',
                ]),
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Farmer',
                'slug' => 'farmer',
                'description' => 'Farmer portal account with access only to the account owner\'s own records and requests',
                'permissions' => json_encode([
                    'farmer.profile.manage',
                    'farmer.farms.manage',
                    'farmer.assistance.apply',
                    'farmer.equipment.request',
                ]),
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        $now = now();
        $roles = array_map(fn (array $role) => $role + [
            'created_at' => $now,
            'updated_at' => $now,
        ], $roles);

        DB::table('roles')->upsert(
            $roles,
            ['slug'],
            ['name', 'description', 'permissions', 'is_default', 'is_active', 'updated_at'],
        );
    }
}
