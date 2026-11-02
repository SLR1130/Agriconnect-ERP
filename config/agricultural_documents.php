<?php

return [
    // Keep disabled until the MAO approves its checklist and malware scanner.
    'enabled' => (bool) env('AGRICULTURAL_APPLICATION_DOCUMENTS_ENABLED', false),
    'scanner_binary' => env('AGRICULTURAL_DOCUMENT_SCANNER_BINARY'),
    'scan_timeout_seconds' => (int) env('AGRICULTURAL_DOCUMENT_SCAN_TIMEOUT', 30),
    'maximum_size_bytes' => 64 * 1024 * 1024,
];
