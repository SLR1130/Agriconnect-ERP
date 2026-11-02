<?php

return [
    // This path is deliberately limited to an explicitly designated disposable
    // SQLite QA database. Real MAO consent and identity procedures are pending.
    'enabled' => (bool) env('AGRICULTURAL_STAFF_ASSISTED_INTAKE_ENABLED', false),
    'qa_isolated_database' => (bool) env('AGRICULTURAL_STAFF_ASSISTED_QA_ISOLATED_DATABASE', false),
    'qa_database_path' => env('AGRICULTURAL_STAFF_ASSISTED_QA_DATABASE_PATH'),
    'synthetic_qa_attestations' => (bool) env('AGRICULTURAL_STAFF_ASSISTED_SYNTHETIC_QA_ATTESTATIONS', false),
    'identity_confirmation_methods' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('AGRICULTURAL_STAFF_ASSISTED_IDENTITY_CONFIRMATION_METHODS', '')),
    ))),
    'consent_policy_version' => env('AGRICULTURAL_STAFF_ASSISTED_CONSENT_POLICY_VERSION'),
];
