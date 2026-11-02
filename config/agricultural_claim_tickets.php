<?php

return [
    'enabled' => (bool) env('AGRICULTURAL_CLAIM_TICKETS_ENABLED', false),

    // Local SQLite preview is restricted to this file. PHPUnit uses :memory:.
    'qa_sqlite_path' => env(
        'AGRICULTURAL_CLAIM_TICKETS_QA_SQLITE_PATH',
        storage_path('app/qa/agriconnect-phase6d1-qa.sqlite'),
    ),

    // A MySQL preview must set the exact disposable schema name as well as use
    // the required prefix. The live agric onnect_erp schema is never accepted.
    'qa_mysql_database' => env('AGRICULTURAL_CLAIM_TICKETS_QA_MYSQL_DATABASE'),

    // No validity period is assumed until MAO adopts one.
    'validity_days' => env('AGRICULTURAL_CLAIM_TICKETS_VALIDITY_DAYS') !== null
        ? (int) env('AGRICULTURAL_CLAIM_TICKETS_VALIDITY_DAYS')
        : null,

    // Optional locally configured pickup guidance; leave empty until MAO
    // supplies approved wording and office details.
    'pickup_instructions' => env('AGRICULTURAL_CLAIM_TICKETS_PICKUP_INSTRUCTIONS'),
    'office_name' => env('AGRICULTURAL_CLAIM_TICKETS_OFFICE_NAME'),
    'office_address' => env('AGRICULTURAL_CLAIM_TICKETS_OFFICE_ADDRESS'),
];
