<?php

return [
    // Physical handover is explicitly disabled unless enabled for a verified QA database.
    'enabled' => (bool) env('AGRICULTURAL_CLAIM_HANDOVER_ENABLED', false),
    'qa_label' => 'SYNTHETIC QA ONLY - NOT EVIDENCE OF ACTUAL FARMER RECEIPT',
    'qa_backup_path' => env('AGRICULTURAL_HANDOVER_QA_BACKUP_PATH'),
];
