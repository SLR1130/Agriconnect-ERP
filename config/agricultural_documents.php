<?php

return [
    // Keep disabled until the MAO approves its checklist and malware scanner.
    'enabled' => (bool) env('AGRICULTURAL_APPLICATION_DOCUMENTS_ENABLED', false),
    'scanner_binary' => env('AGRICULTURAL_DOCUMENT_SCANNER_BINARY'),
    'scanner_database_directory' => env('AGRICULTURAL_DOCUMENT_SCANNER_DATABASE_DIRECTORY'),
    'signature_max_age_hours' => (int) env('AGRICULTURAL_DOCUMENT_SIGNATURE_MAX_AGE_HOURS', 36),
    'required_signature_databases' => ['main', 'daily', 'bytecode'],
    'scan_timeout_seconds' => (int) env('AGRICULTURAL_DOCUMENT_SCAN_TIMEOUT', 60),
    // Conservative QA default. This is a technical ceiling, not an MAO policy.
    'maximum_size_bytes' => (int) env('AGRICULTURAL_APPLICATION_DOCUMENT_MAXIMUM_SIZE_BYTES', 10 * 1024 * 1024),
];
