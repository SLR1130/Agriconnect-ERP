# Phase 6C.3: Staff-assisted AASMS intake

## Shared application flow

Staff-assisted intake creates the same `AssistanceApplication` record as the farmer-portal route by calling `AssistanceApplicationSubmissionService`. The service rechecks FFRMS verification, current program dates, program eligibility, the farmer/program uniqueness rule, submission-key idempotency, the active requirement version, and acknowledgment creation in its existing transaction.

Intake provenance is recorded in the immutable acknowledgment snapshot and the existing application submission/status history: channel, actual staff user ID and name, identity-method label, timestamp, and an explicit synthetic-QA-only attestation statement. The initial status-history actor and document-submission snapshot actor identify the staff member. Later MAO review and private-document review continue to use their existing separate actors and authorization checks.

No second application table, database migration, role grant, farmer account, impersonation, or signature was added. The normal farmer application and resubmission behavior remains on the same service and reference.

## Authorization

The effective permission is `modules.aasms.intake_assist`. It is an exact permission only. A generic AASMS `view`, `create`, wildcard, approval, or distribution grant does not imply it. Module Personnel may receive it through an explicit individual AASMS responsibility assignment. The capability grants neither private-document viewing/review nor application review, approval, rejection, or distribution.

The account permission inspector labels this action as “Assist farmer application intake (isolated QA only).” No live role or user permission was changed.

## Activation boundary

The feature configuration defaults to disabled. It executes only when all QA flags are enabled, the application environment is `local` or `testing`, the active connection is SQLite, and the target is either PHPUnit `:memory:` or the specifically named `storage/app/qa/agriconnect-phase6c3-qa.sqlite` file configured as the QA path. MySQL is rejected regardless of flags. The only accepted identity method is `synthetic_qa`, and the acknowledgment says that it is not identity proof, legal consent, or a signature.

The `AGRICULTURAL_APPLICATION_DOCUMENTS_ENABLED` flag remains independent and unchanged. Intake uploads call the existing private-document service; scanner-unavailable, failed, and infected files remain quarantined. The normal AASMS document-view/review permissions remain mandatory.

This cannot be activated operationally until the MAO approves identity-confirmation and consent procedures, defines who may record each procedure, approves the public acknowledgment language, and separately reviews the required database activation. The operational MySQL database still has the three Phase 6C.2 migrations pending; no migration was run for this work.
