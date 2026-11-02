# Phase 6E — Assistance Handover Integration Specification

Status: Phase 6E.1 is implemented and exercised only in isolated QA. The operational MySQL database remains untouched and no real municipal distribution is enabled. The migration is pending on the operational database; no production permission grants were made.

## Pre-implementation baseline

- `POST /modules/aasms/applications/{application}/distribute` is the current direct AASMS distribution action. `AASMSController::distribute` checks the AASMS `distribute` module action and calls `AssistanceService::distribute`.
- `AssistanceService::distribute` locks the application, requires an approved application, a confirmed full allocation where present, a matching batch item, and no prior distribution. It delegates the release to `InventoryService`, creates one `AssistanceDistribution`, completes the application, writes status history, and notifies the farmer.
- `InventoryService::release` locks the batch and rejects non-available batches, expired batches, and insufficient quantities. Its operation key is idempotent for an identical retry and rejects reuse for a different operation.
- At the Phase 6D baseline, the direct AASMS action did not require a claim ticket or distinct AISMS release permission. Phase 6E.1 closes that bypass for the durably enrolled ticket cohort in both the service and route workflow; applications without an enrollment retain the prior legacy path.
- Phase 6D.1 verification is read-only. It checks token/reference status, expiry, revocation, and the allocation snapshot. It does not confirm identity, release inventory, create a distribution, or complete an application.

## Phase 6E.1 implementation status

- Ticket issuance creates one immutable `assistance_handover_enrollments` row for the application. The Phase 6E QA migration enrolls existing undistributed applications that already have claim tickets. Existing distributions remain unlinked (`claim_ticket_id = NULL`) and unchanged.
- `AssistanceService::distribute()` rejects any application in that durable ticket cohort. The prior AASMS route remains available for applications without a ticket cohort, preserving the existing legacy workflow; ticketed cases cannot fall back to it even when the QA feature gate is off.
- The new handover service requires effective `modules.aasms.distribute`, `modules.aisms.release`, and exact `modules.aasms.handover_distribute`. Identity capture separately requires exact `modules.aasms.identity_confirm` and ticket-verification authority. The two new permissions are literal default-deny grants; wildcards, claim verification, application approval, and Super Admin's general module policy do not imply them. No live role was granted either permission.
- QA identity confirmation is fixed to `synthetic_qa_test_only`, stores only a match/no-match scenario marker, requires a successful token/QR event for the same ticket, actor, allocation snapshot, and a recent confirmation window. It does not collect personal ID data, signatures, consent, or biometric evidence.
- Distribution lock order is application → ticket → batch. The existing `InventoryService::release()` performs the locked stock, expiration, availability, and idempotency checks on the same default Laravel DB connection and within the outer transaction. Ticket, inventory movement, AASMS distribution, application completion/history, ticket event, immutable acknowledgment, audit event, and database notification commit or roll back together.
- The server generates the deterministic `aasms-claim-ticket:{ticket_id}` operation key. Unique ticket/distribution links and immutable records prevent duplicate operations. An exact retry returns the committed distribution; a changed batch, quantity, or confirmation is rejected.
- MySQL activation of the Phase 6E migration is allowed only in local QA with a matching private-backup checksum and exact database name, server hostname, port, and MySQL server UUID. The migration writes a database attestation after the target checks pass. The runtime feature gate also checks that attestation; a writable flag or schema-name prefix alone is insufficient. PHPUnit's in-memory SQLite tests remain isolated.
- The farmer gets the redeemed ticket state, distribution details, and a private owner-scoped print view. The acknowledgment is expressly labeled synthetic QA and is not a signature or proof of actual receipt.

## Operational policy remains unapproved

This code is not operationally enabled. Before real municipal use, the MAO must define and approve the in-person identity method, minimum evidence, verifier/distributor responsibility, access/retention rules, notification wording, ticket validity and pickup rules, and any acceptable beneficiary acknowledgment. Synthetic QA confirmation cannot be represented as those procedures. The current service supports one full allocation from one batch only. Inventory expiry continues to use the existing `InventoryService` date behavior. No live schema migration, permission grant, or handover occurred in this implementation.

## Implemented QA handover sequence

1. Require an active issued ticket linked to the exact approved application. Lock and recheck the application and ticket; reject expired, revoked, superseded, allocation-changed, unverified-farmer, already-distributed, or already-redeemed cases.
2. Record a separate identity-confirmation event after successful token/QR validation. The current implementation accepts only the labeled synthetic QA match/no-match scenario and records the authenticated actor, ticket/application, event, timestamp, and allocation snapshot. A token/reference is not identity evidence. It does not collect government ID numbers, images, signatures, witnesses, or consent artifacts.
3. Require effective AASMS distribution and AISMS release permissions plus the literal `modules.aasms.handover_distribute` permission. Identity confirmation separately requires `modules.aasms.identity_confirm` and ticket verification. These grants are default-deny; no live role assignment was added.
4. Select exactly one active, non-expired AISMS batch for the ticket's approved supply item. Display location and current available quantity before submission. Do not reserve inventory when issuing a ticket. If no single eligible batch can satisfy the full confirmed allocation, reject the handover and return the case to MAO; do not partially distribute or silently split across batches under the current policy.
5. In one database transaction, lock rows in a consistent order: application, ticket, then the selected batch. Recheck all state after locks. Call the existing `InventoryService::release` so its batch lock, expiry, available quantity, movement, and idempotency rules remain authoritative. Create exactly one distribution, link it to the ticket, write the immutable handover acknowledgment and both histories, set the ticket terminally redeemed, and complete the application only after the release succeeds. Any failure must roll back the stock movement, batch quantity, distribution, ticket state, acknowledgment, histories, and notification intent together.
6. Use one deterministic operation key derived from the ticket (for example `aasms-claim-ticket:{ticket_id}`) and a unique database constraint. An identical retry returns the committed result without a second movement. A retry with a different batch, quantity, farmer, ticket, or application is rejected and audited without revealing the token.
7. Persist the existing database notification on the same connection and transaction as the handover. It commits only with the distribution and contains no bearer token.

## Phase 6E.1 data and audit changes

- Adds a nullable unique `claim_ticket_id` to distributions with a restrictive FK, preserving legacy rows as NULL.
- Adds terminal ticket redemption fields and restrictive references to the committed distribution and authenticated staff actor.
- Adds durable ticket-cohort enrollment, append-only synthetic identity confirmations, handover events, and immutable acknowledgment snapshots with restrictive FKs and query indexes.
- Preserves existing ticket events, application status history, movements, distribution references, and notifications. It never fabricates ticket links for existing distributions.
- The migration was applied only to a fresh restored QA MySQL clone after a migration dry run. It backfilled two undistributed pre-existing ticket applications; all pre-migration table data fingerprints remained unchanged. No operational migration was run.

## Direct-route bypass policy implemented

`AssistanceService::distribute()` checks the durable `assistance_handover_enrollments` cohort while holding the application lock and rejects enrolled applications. The ticket-specific controller also calls the authoritative handover service, which independently checks enrollment, permissions, current ticket state, and identity evidence. Hiding the old button is not part of the security boundary.

Existing undistributed ticketed applications are enrolled by the QA migration; applications without a ticket remain on the legacy path. Any operational cohort cutoff, grandfathering, or exception authority remains a separate MAO policy decision.

## Operational policy decisions required before activation

1. Which applications/programs are covered, including allocations approved before activation; the cutoff and any exception authority.
2. Accepted in-person identity-check method, required fields, who may attest, data minimization, retention, and access controls.
3. Whether both AASMS distribute and AISMS release authorities are required, or whether a new dual-purpose handover permission is preferred; the exact effective-role grants.
4. Whether a ticket validity period and pickup location/instructions should be configured, and how expiry is communicated.
5. Confirmation that one exact batch must contain the entire allocation. Partial/multi-batch/multi-item claims remain unsupported unless the MAO approves a different allocation model.
6. Whether the farmer must provide a physical acknowledgment and, if so, an approved method that does not imply a fabricated digital signature.
7. Confirmation of the business-date basis for inventory batch expiry. Existing `InventoryService` uses the application's current `today()` behavior; Phase 6E must test the agreed boundary without silently changing unrelated inventory expiry policy.

## Acceptance coverage and remaining visual review

- Valid, expired, revoked, superseded, allocation-changed, and already-redeemed tickets.
- Identity confirmation absent, failed, and policy-compliant; no QR-only handover.
- Each required permission independently missing, revoked during a session, or granted; direct route and service invocation both denied without authorization.
- Wrong application/farmer/item, inactive batch, expired batch, insufficient stock, and full-allocation mismatch.
- Two concurrent handover requests; exactly one AISMS movement, distribution, acknowledgment, ticket redemption, completed application, and farmer notification.
- Exact retry returns the same committed result; changed retry key payload is rejected.
- A forced database write failure after inventory release rolls back the batch quantity, movement, distribution, ticket/application states, histories, acknowledgment, and notification.
- Ticket issue/validation/revocation remain stock-neutral. No operational MySQL test data is inserted.
- Farmer ownership, staff authorization, immutable evidence models, and notification persistence are covered by HTTP feature tests. QA conflict audit metadata records only boolean mismatch flags; it omits submitted values and bearer credentials.
- A disposable restored MySQL clone verified a committed handover and a two-process concurrent retry: both counters returned the same distribution, with one stock movement and one distribution. A conflicting retry was rejected and appended a safe audit event without changing the balance.
- No normal-browser screenshots were captured; visual acceptance remains a manual QA step. PHPUnit uses isolated SQLite in-memory storage, while synthetic MySQL scenarios remain inside the restored QA clone.
