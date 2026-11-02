# Phase 6D.1 — AASMS Claim Ticket QA Foundation

This phase adds issue, owner display/print, authenticated verification, revocation, expiry, reissue, and ticket audit history for already approved AASMS allocations. It is an isolated QA feature and remains disabled by default.

## Scope boundary

- A ticket records an approved application, supply item, quantity, and allocation-confirmation snapshot.
- Issue, QR display, verification, revocation, and reissue do not call `InventoryService`, reserve stock, create an AISMS movement/distribution, or complete an application.
- The ticket is explicitly marked as not redeemable. Inventory must be checked again during a separately authorized physical handover in Phase 6E.
- `redeemed` is not an implemented ticket state. No ticket operation confirms beneficiary identity; staff must perform in-person identity confirmation under a future approved policy.
- Expiry days, pickup wording, office name, and office address are unset unless the local QA environment explicitly configures them. No validity or pickup policy is assumed.

## QA-only activation

`AGRICULTURAL_CLAIM_TICKETS_ENABLED` defaults to `false`. The feature gate also requires `APP_ENV=local` or `testing`, no `DB_URL`, the ticket tables, and either PHPUnit's in-memory SQLite database, the exact SQLite file under `storage/app/qa/`, or the explicitly configured MySQL clone whose name starts with `agriconnect_erp_claimtickets_qa_`. The operational `agriconnect_erp` schema is rejected. The migration has an independent target guard and checks the Phase 6C.2 migration history before real execution.

For a disposable MySQL clone, set `AGRICULTURAL_CLAIM_TICKETS_QA_MYSQL_DATABASE` to that exact cloned schema. Never set the claim-ticket feature flag for the operational database.

## Authorization

Ticket actions use literal, default-deny permissions and do not inherit from `modules.aasms.*`, AASMS review, approval, document review, or assisted intake:

- `modules.aasms.claim_ticket_view`
- `modules.aasms.claim_ticket_issue`
- `modules.aasms.claim_ticket_verify`
- `modules.aasms.claim_ticket_revoke`
- `modules.aasms.claim_ticket_reissue`
- `modules.aasms.claim_ticket_history`

An auditor may receive only explicit view/history grants. Farmers can access ticket routes only when the linked application's farmer record belongs to their authenticated account. Staff pages never render the token hash or encrypted token.

## Token and key handling

Each ticket has a random human-readable reference and a separate 256-bit random token. The database stores a domain-separated SHA-256 hash for verification and Laravel-encrypted token ciphertext only so the authenticated owner's QR can be regenerated. QR rendering is local; the payload is a version prefix plus the random token, with no personal or application data. It is not included in a URL, event, or application log. QR responses and ticket print pages are marked private and non-cacheable.

Laravel encryption uses `APP_KEY`. Rotating that key makes existing encrypted ciphertext unreadable unless the former key is securely retained for a controlled re-encryption. The one-way hashes remain usable for already printed QR payloads, but the application cannot regenerate an unreadable QR. If secure re-encryption with the old key is unavailable, revoke and reissue the affected tickets. Never place old/new keys or decrypted tokens in source control, logs, reports, or screenshots.

## Phase 6E prerequisites

Before any physical handover or redemption is implemented, obtain MAO approval for ticket validity, pickup instructions, office details, identity-check records, and the redemption authority. Then design one transactional workflow that locks the application/ticket and AISMS batch, rechecks stock and expiry, records beneficiary identity confirmation separately, creates exactly one existing AISMS release/distribution with an idempotency key, links that distribution to the ticket, and marks the ticket redeemed only after the stock transaction commits. Retry, concurrent handover, rollback, revocation, expiry, and allocation-change behavior must be covered before enabling it. Do not add a competing inventory ledger or automatic reservation.
