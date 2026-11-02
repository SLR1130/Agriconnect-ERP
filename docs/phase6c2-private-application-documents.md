# Phase 6C.2 — private assistance-application documents

## Current activation state

The workflow is implemented behind `AGRICULTURAL_APPLICATION_DOCUMENTS_ENABLED`, which defaults to `false`. The local development environment inspected for this phase has no ClamAV executable. Do not enable uploads for operational users until the MAO approves its requirement checklist, retention and file-size policy, and an approved malware-scanning mechanism is installed and tested.

With uploads disabled, farmer and staff pages continue to show existing application records and versioned requirements. New uploads, document review and file access remain unavailable. If an operator explicitly enables upload intake while the scanner is unavailable, files are recorded only in private quarantine with `scan_status=unavailable`; they cannot be previewed, downloaded, reviewed, or counted as verified. An unavailable or failed scanner never yields a clean result.

## Data and access controls

- Every document row belongs to one `AssistanceApplication`, its immutable requirement version, and one requirement from that version.
- Each upload creates a new version with a random storage name, detected MIME type, measured size and SHA-256 digest. Replacements point to the prior version; prior file metadata and review events are retained.
- Files use the private `application-documents` disk under `storage/app/private/agricultural-application-documents`; application routes stream only scanner-clean files. Storage paths are never rendered or used as public URLs.
- Farmer routes enforce owner-scoped access. Staff file access requires `modules.aasms.documents_view_sensitive`; review additionally requires `modules.aasms.documents_review`. These are literal grants. Generic AASMS view access, role wildcards and Auditor access do not grant private evidence access.
- Review decisions and access events are append-only. Internal notes are stored separately from farmer-visible feedback. Application approval/resubmission is blocked while required evidence is missing or not both scan-clean and MAO-verified.
- The first-submission acknowledgment remains immutable. Each resubmission receives a separate immutable snapshot; it does not rewrite the original receipt.

## Dependencies before operational activation

1. Approve actual program checklists, accepted types, size limits, retention and correction procedures with the MAO. Current automated requirements are synthetic QA data only.
2. Install and configure a currently supported ClamAV scanner and confirm it is reachable by the PHP process. This adapter invokes a local `clamscan` executable; a remote/container-only service needs a separately reviewed adapter. Configure the official FreshClam updater and monitor successful signature updates and database freshness. A missing database, update failure, timeout, unavailable scanner, or scan error must remain non-clean. Set `AGRICULTURAL_DOCUMENT_SCANNER_BINARY` only after the executable, database path, and permissions are verified, then test clean, infected, timeout, and unavailable outcomes in a controlled QA environment.
3. Review and separately approve activation of the pending Phase 6C.1 migrations and `2026_11_02_000100_create_aasms_application_document_workflow_tables.php` on the operational database. They have not been applied by this phase.
4. Grant the two exact permissions only to authorized personnel, and verify farmer ownership, staff scope and Auditor denial before setting `AGRICULTURAL_APPLICATION_DOCUMENTS_ENABLED=true`.

The application adapter currently fails closed on scanner absence, scanner exceptions/timeouts, and non-clean results, but it does not independently enforce a maximum signature age. Operational activation therefore also requires monitored FreshClam updates and an approved health check or code change that prevents a stale signature database from being treated as a clean scan.

## Migration failure and restoration procedure

The operational database currently has the Phase 6C.1 announcement-media and requirement-version migrations, plus the Phase 6C.2 document-workflow migration, pending. A read-only `migrate --pretend` preview was run against `agriconnect_erp`; none of those migrations has been applied there.

For any later, separately approved activation:

1. Take a new private, consistent SQL backup outside the repository; record its checksum and restore it into a uniquely named disposable MySQL database.
2. Compare existing table row counts and key record fingerprints before and after applying only the approved migrations to that disposable restore. Verify expected foreign keys, indexes, and the `ON DELETE RESTRICT` history constraints.
3. Reconfirm the resolved connection name and database immediately before activation. Apply only the explicitly approved migration files, in order.
4. If a migration reports an error or only partially applies, stop. Preserve the error output and database state for diagnosis; do not retry blindly, run rollback/reset commands, or continue to the next migration.
5. Diagnose and test a correction on a fresh disposable restore. Any operational recovery or restore must follow a separately authorized DBA recovery plan; this application must not automatically restore or overwrite the live database.
6. After a successful activation, verify migration-history entries, schema objects, pre-existing row counts and record fingerprints, and take a new private post-activation backup.

Do not use `storage:link`, a public disk, or announcement-media routes for application documents. Do not manually change document scan state to `clean`.
