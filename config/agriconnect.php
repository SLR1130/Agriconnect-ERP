<?php

return [
    'name' => 'AgriConnect ERP',
    'version' => '1.0.0',
    'modules' => [
        'ffrms' => [
            'name' => 'Farmer & Farm Registration Management System',
            'developer' => 'Lei',
            'description' => 'Farmer profiles, farm registration, land information, farmer associations, and registration records.',
            'route_prefix' => 'modules/ffrms',
            'enabled' => true,
        ],
        'apms' => [
            'name' => 'Agricultural Production & Monitoring System',
            'developer' => 'Roselle',
            'description' => 'Agricultural production, planting records, harvesting information, crop monitoring, livestock, fisheries, and production reports.',
            'route_prefix' => 'modules/apms',
            'enabled' => true,
        ],
        'aisms' => [
            'name' => 'Agricultural Inventory & Supply Management System',
            'developer' => 'Christine',
            'description' => 'Agricultural supply inventory, stock transactions, supply distribution, and inventory monitoring.',
            'route_prefix' => 'modules/aisms',
            'enabled' => true,
        ],
        'aasms' => [
            'name' => 'Agricultural Assistance & Subsidy Management System',
            'developer' => 'Flor',
            'description' => 'Farmer assistance applications, beneficiary verification, subsidies, and assistance distribution.',
            'route_prefix' => 'modules/aasms',
            'enabled' => true,
        ],
        'aeams' => [
            'name' => 'Agricultural Equipment & Asset Management System',
            'developer' => 'Jaymark',
            'description' => 'Equipment inventory, machinery reservations, borrowing, returns, maintenance, and asset monitoring.',
            'route_prefix' => 'modules/aeams',
            'enabled' => true,
        ],
        'abpms' => [
            'name' => 'Agricultural Budget & Procurement Management System',
            'developer' => 'Rainier',
            'description' => 'Budget allocations, procurement requests, purchasing activities, expenses, and financial monitoring.',
            'route_prefix' => 'modules/abpms',
            'enabled' => true,
        ],
    ],
    
    'colors' => [
        'primary' => [
            'forest_green' => '#163D2A',
            'emerald_green' => '#237A52',
            'agricultural_gold' => '#D5AB50',
            'light_sage' => '#EAF2E8',
            'off_white' => '#F8FAF7',
            'dark_slate' => '#26352D',
        ],
    ],
    
    'roles' => [
        'super_administrator' => 'Super Administrator',
        'mao_administrator' => 'MAO Administrator',
        'municipal_agriculturist' => 'Municipal Agriculturist',
        'module_personnel' => 'Module Personnel',
        'authorized_viewer' => 'Authorized Viewer/Auditor',
    ],
];