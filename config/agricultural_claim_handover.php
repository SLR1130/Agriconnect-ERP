<?php

return [
    // Physical handover is explicitly disabled until its matching claim-ticket
    // mode has been enabled and the required schema is present.
    'enabled' => (bool) env('AGRICULTURAL_CLAIM_HANDOVER_ENABLED', false),
    'qa_label' => 'SYNTHETIC QA ONLY - NOT EVIDENCE OF ACTUAL FARMER RECEIPT',
    'operational_label' => env('AGRICULTURAL_CLAIM_HANDOVER_OPERATIONAL_LABEL', 'MAO-authorized physical assistance handover'),
    'qa_backup_path' => env('AGRICULTURAL_HANDOVER_QA_BACKUP_PATH'),
];
