<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Farmer & Farm Registration Management System',
                'slug' => 'ffrms',
                'developer' => 'Lei',
                'description' => 'Farmer profiles, farm registration, land information, farmer associations, and registration records.',
                'route_prefix' => 'modules/ffrms',
                'icon' => 'users',
                'sort_order' => 1,
                'is_enabled' => true,
                'is_active' => true,
                'development_progress' => 100,
            ],
            [
                'name' => 'Agricultural Production & Monitoring System',
                'slug' => 'apms',
                'developer' => 'Roselle',
                'description' => 'Agricultural production, planting records, harvesting information, crop monitoring, livestock, fisheries, and production reports.',
                'route_prefix' => 'modules/apms',
                'icon' => 'chart-bar',
                'sort_order' => 2,
                'is_enabled' => true,
                'is_active' => true,
                'development_progress' => 100,
            ],
            [
                'name' => 'Agricultural Inventory & Supply Management System',
                'slug' => 'aisms',
                'developer' => 'Christine',
                'description' => 'Agricultural supply inventory, stock transactions, supply distribution, and inventory monitoring.',
                'route_prefix' => 'modules/aisms',
                'icon' => 'package',
                'sort_order' => 3,
                'is_enabled' => true,
                'is_active' => false,
                'development_progress' => 75,
            ],
            [
                'name' => 'Agricultural Assistance & Subsidy Management System',
                'slug' => 'aasms',
                'developer' => 'Flor',
                'description' => 'Farmer assistance applications, beneficiary verification, subsidies, and assistance distribution.',
                'route_prefix' => 'modules/aasms',
                'icon' => 'gift',
                'sort_order' => 4,
                'is_enabled' => true,
                'is_active' => false,
                'development_progress' => 60,
            ],
            [
                'name' => 'Agricultural Equipment & Asset Management System',
                'slug' => 'aeams',
                'developer' => 'Jaymark',
                'description' => 'Equipment inventory, machinery reservations, borrowing, returns, maintenance, and asset monitoring.',
                'route_prefix' => 'modules/aeams',
                'icon' => 'tool',
                'sort_order' => 5,
                'is_enabled' => true,
                'is_active' => true,
                'development_progress' => 100,
            ],
            [
                'name' => 'Agricultural Budget & Procurement Management System',
                'slug' => 'abpms',
                'developer' => 'Rainier',
                'description' => 'Budget allocations, procurement requests, purchasing activities, expenses, and financial monitoring.',
                'route_prefix' => 'modules/abpms',
                'icon' => 'credit-card',
                'sort_order' => 6,
                'is_enabled' => true,
                'is_active' => false,
                'development_progress' => 30,
            ],
        ];

        $now = now();
        $modules = array_map(fn (array $module) => $module + [
            'created_at' => $now,
            'updated_at' => $now,
        ], $modules);

        DB::table('modules')->upsert(
            $modules,
            ['slug'],
            ['name', 'developer', 'description', 'route_prefix', 'icon', 'sort_order', 'is_enabled', 'is_active', 'development_progress', 'updated_at'],
        );
    }
}
