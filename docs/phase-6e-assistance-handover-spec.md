# Phase 6E — Assistance Handover Integration Specification

Status: planning only. No ticket redemption, inventory release, operational activation, or schema change is implemented by this document.

## Existing application behavior

- `POST /modules/aasms/applications/{application}/distribute` is the current direct AASMS distribution action. `AASMSController::distribute` checks the AASMS `distribute` module action and calls `AssistanceService::distribute`.
- `AssistanceService::distribute` locks the application, requires an approved application, a confirmed full allocation where present, a matching batch item, and no prior distribution. It delegates the release to `InventoryService`, creates one `AssistanceDistribution`, completes the application, writes status history, and notifies the farmer.
- `InventoryService::release` locks the batch and rejects non-available batches, expired batches, and insufficient quantities. Its operation key is idempotent for an identical retry and rejects reuse for a different operation.
- The direct AASMS action currently does not require a claim ticket or a distinct AISMS release permission. It therefore remains a ticket-bypass path until Phase 6E changes and tests both the controller and service boundary. No route/UI-only hiding is sufficient.
- Phase 6D.1 verification is read-only. It checks token/reference status, expiry, revocation, and the allocation snapshot. It does not confirm identity, release inventory, create a distribution, or complete an application.

## Recommended handover sequence

1. Require an active issued ticket linked to the exact approved application. Lock and recheck the application and ticket; reject expired, revoked, superseded, allocation-changed, unverified-farmer, already-distributed, or already-redeemed cases.
2. Record a separate, policy-approved in-person identity check. The record identifies the authenticated staff actor, farmer record, method/policy version, timestamp, and outcome. A token/reference is not identity evidence. Do not collect government ID numbers, images, signatures, witnesses, or consent artifacts unless the MAO approves a lawful procedure, minimum data set, access policy, and retention schedule. Never fabricate them.
3. Require explicit effective authorization for AASMS distribution and AISMS stock release, plus a separately reviewed claim-ticket redemption/handover permission. A `claim_ticket_verify` grant alone must never authorize handover. If the current role model cannot safely express both authorities, add one explicit handover permission with documented role assignments; do not infer it from broad module access.
4. Select exactly one active, non-expired AISMS batch for the ticket's approved supply item. Display location and current available quantity before submission. Do not reserve inventory when issuing a ticket. If no single eligible batch can satisfy the full confirmed allocation, reject the handover and return the case to MAO; do not partially distribute or silently split across batches under the current policy.
5. In one database transaction, lock rows in a consistent order: application, ticket, then the selected batch. Recheck all state after locks. Call the existing `InventoryService::release` so its batch lock, expiry, available quantity, movement, and idempotency rules remain authoritative. Create exactly one distribution, link it to the ticket, write the immutable handover acknowledgment and both histories, set the ticket terminally redeemed, and complete the application only after the release succeeds. Any failure must roll back the stock movement, batch quantity, distribution, ticket state, acknowledgment, histories, and notification intent together.
6. Use one deterministic operation key derived from the ticket (for example `aasms-claim-ticket:{ticket_id}`) and a unique database constraint. An identical retry returns the committed result without a second movement. A retry with a different batch, quantity, farmer, ticket, or application is rejected and audited without revealing the token.
7. Send the existing farmer completion notification only after commit (or through the framework's after-commit notification behavior). Include the distribution reference and approved item/quantity, not the bearer token.

## Data and audit changes to consider in Phase 6E

- Add a unique nullable ticket reference on `assistance_distributions` or an equivalent dedicated one-to-one link, preventing multiple distributions for one ticket.
- Add a `redeemed` terminal ticket status, `redeemed_at`, and a restrictive FK to the committed distribution. The transition is only `issued -> redeemed`; expiry/revocation remain terminal and cannot be revived.
- Add an append-only handover acknowledgment record linked one-to-one to ticket and distribution. Store the minimum policy-approved identity-check metadata and the actual actor/time. Do not represent a typed name as a signature or an unperformed check as completed.
- Preserve current claim-ticket events, application status history, inventory movements, distribution reference, and farmer notification. Never update or delete earlier evidence to rewrite a failed or superseded attempt.
- Add unique operation-key and one-ticket/one-distribution constraints, restrictive foreign keys, and indexes for staff history queries. Review the migration against a restored QA clone before any separate live activation approval.

## Closing the direct-route bypass

Before operational enablement, enforce the ticket requirement inside `AssistanceService` or a single new handover service called by every distribution entry point. The existing direct route must either be converted to the ticket handover form or reject ticket-required applications. A second unguarded controller/service entry point would bypass the policy.

Recommended default: once the MAO adopts the policy, require ticket handover for all undistributed allocations in the covered cohort and disable the direct route for that cohort. If already-approved applications are to be grandfathered, define the cutoff and exception authority explicitly; exception use must be separately permissioned, reasoned, and audited. No grandfathering behavior is assumed by this plan.

## Policy decisions required before implementation

1. Which applications/programs are covered, including allocations approved before activation; the cutoff and any exception authority.
2. Accepted in-person identity-check method, required fields, who may attest, data minimization, retention, and access controls.
3. Whether both AASMS distribute and AISMS release authorities are required, or whether a new dual-purpose handover permission is preferred; the exact effective-role grants.
4. Whether a ticket validity period and pickup location/instructions should be configured, and how expiry is communicated.
5. Confirmation that one exact batch must contain the entire allocation. Partial/multi-batch/multi-item claims remain unsupported unless the MAO approves a different allocation model.
6. Whether the farmer must provide a physical acknowledgment and, if so, an approved method that does not imply a fabricated digital signature.
7. Confirmation of the business-date basis for inventory batch expiry. Existing `InventoryService` uses the application's current `today()` behavior; Phase 6E must test the agreed boundary without silently changing unrelated inventory expiry policy.

## Acceptance tests for Phase 6E

- Valid, expired, revoked, superseded, allocation-changed, and already-redeemed tickets.
- Identity confirmation absent, failed, and policy-compliant; no QR-only handover.
- Each required permission independently missing, revoked during a session, or granted; direct route and service invocation both denied without authorization.
- Wrong application/farmer/item, inactive batch, expired batch, insufficient stock, and full-allocation mismatch.
- Two concurrent handover requests; exactly one AISMS movement, distribution, acknowledgment, ticket redemption, completed application, and farmer notification.
- Exact retry returns the same committed result; changed retry key payload is rejected.
- Forced failure at each write boundary rolls back all domain rows and stock changes.
- Ticket issue/validation/revocation remain stock-neutral. No operational MySQL test data is inserted.
- Farmer/staff authorization, immutable history, notification privacy, audit redaction, responsive UI, and no token in URL/session/logs.
