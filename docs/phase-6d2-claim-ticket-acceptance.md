# Phase 6D.2 — Claim Ticket Validation Acceptance

Status: isolated QA implementation complete. Visual acceptance is pending normal-browser review. Phase 6E remains planning only.

## Scope and safety

- This phase adds a dedicated MAO credential-validation page and improves farmer ticket/reissue and print guidance.
- Ticket validation is not identity confirmation or a physical handover. No redemption state, distribution, inventory release, stock reservation, or application completion was added.
- The project `.env` was read-only checked: `APP_ENV=local`, connection `mysql`, database `agriconnect_erp`, no configured DB URL, claim-ticket feature flag `false`, feature gate `false`.
- `php artisan migrate:status` was read-only. These four operational migrations remain pending; none was applied:
  - `2026_11_01_070210_add_media_and_revision_support_to_agricultural_announcements`
  - `2026_11_01_120000_add_aasms_requirement_versions_and_acknowledgments`
  - `2026_11_02_000100_create_aasms_application_document_workflow_tables`
  - `2026_11_02_110000_create_aasms_claim_ticket_tables`
- MySQL transactional checks used only the disposable `agriconnect_erp_claimtickets_qa_20261102_1c12e8d8` clone. Two simultaneous independent verifiers both returned `valid`; each produced one safe audit event. Ticket/application states remained issued/approved, inventory movement and distribution counts did not change, and the supply-batch fingerprint was unchanged.

## Validation behavior

- Dedicated authenticated page: `GET /modules/aasms/claim-tickets/verify`; authenticated, throttled POST remains `POST /modules/aasms/claim-tickets/verify`.
- Token or QR payload validation checks stored hash, effective status, expiry, current farmer/program/item eligibility, and allocation snapshot. It displays the linked farmer/application, approved program, item, quantity, status, and expiry.
- A public reference is a lookup only. Its result is not styled or described as a valid token and is explicitly not proof of possession or identity.
- Invalid, not-found, expired, revoked/superseded, and changed-allocation results have distinct guidance. Verification events contain safe method/outcome metadata only. Raw secrets are accepted only from the POST body, are removed before redirect, and are not placed in a URL, session old input, or audit metadata.
- GET of the validation route only renders the form. No redemption route exists; a GET attempt at the proposed redemption path returns 404. Validation does not change the ticket to a used state.
- There is no `redeemed` or “previously used” state in this phase. Superseded/revoked token retry is tested and denied. Testing a redeemed credential belongs to Phase 6E after a state transition is approved and implemented.

## Camera and QR dependency

- No browser camera scanner was added. The screen does not request camera permission and works with manual token/payload/reference entry. A staff-approved local reader may provide the text to paste; the app does not call a remote QR API or store camera images.
- The locked renderer is `bacon/bacon-qr-code` **3.1.1**, BSD-2-Clause; its package metadata requires PHP `^8.1`, `ext-iconv`, and `dasprid/enum ^1.0.3`. The project runs PHP 8.3. QR SVG generation is local through `SvgImageBackEnd`; no network service is involved. [Locked package metadata](https://github.com/Bacon/BaconQrCode/blob/v3.1.1/composer.json), [v3.1.1 release](https://github.com/Bacon/BaconQrCode/releases/tag/v3.1.1).
- Security controls include 256-bit random tokens, a domain-separated one-way verification hash, encrypted-at-rest token ciphertext, local SVG generation, owner-only private no-store QR delivery, `nosniff`, restrictive CSP, no PII in the QR payload, no token URL, and no ticket event containing the secret.
- GitHub Advisory Database search returned no matching BaconQrCode advisories during this review. `composer audit --locked` could not reach Packagist from this environment, so that command did not provide an independent complete audit result. A matching-advisory query is not a guarantee that a dependency is vulnerability-free.

## Farmer ticket display

- Farmer detail remains owner-scoped and shows program, approved item/quantity, issue/expiry time, private QR, configured pickup guidance, separate ticket history, and a warning that the ticket neither reserves stock nor proves completed distribution.
- Detail and print views now explain how to contact the MAO for a lost/damaged ticket. Reissue/supersession links and old-ticket invalidation are visible. The printable page includes expiry (or “No expiry configured”), status, local Philippine-time formatting, pickup information when configured, and the non-redeemable warning.

## Automated acceptance

Latest focused claim-ticket feature suite: 17 passed, 194 assertions. It covers direct authenticated HTTP forms, exact permission checks, reference-only presentation, no GET redemption, throttling, invalid/expired/revoked/reissued credentials, owner scoping, print content, and zero inventory/distribution changes.

## Manual responsive review checklist

No screenshots were captured and a normal browser visual review was unavailable in this execution. Inspect the following authenticated pages at 390px, 768px, and 1440px; do not treat HTTP feature tests as screenshot acceptance:

1. As authorized MAO ticket staff, open `/modules/aasms/claim-tickets`, then follow **Validate a ticket** to `/modules/aasms/claim-tickets/verify`.
2. Enter a current QA QR payload/token. Confirm the result says the token is valid but identity is unconfirmed and shows its approved application, program, item, quantity, status, and expiry.
3. Enter a ticket reference, invalid token, unknown reference, expired token, and revoked/superseded token. Confirm each result remains distinct and reference lookup is not presented as token validity.
4. As the owning QA farmer, open the ticket detail and print page. Check QR clarity, expiration, replacement history, lost-ticket instructions, and no clipped content. Repeat as a different farmer and confirm access is denied.
5. Confirm no camera permission prompt appears, no action implies stock release, and neither page has document-level horizontal overflow.

## Phase 6E planning

See [Phase 6E Assistance Handover Integration Specification](phase-6e-assistance-handover-spec.md). The legacy direct AASMS distribution route is still active and currently checks AASMS distribute permission while delegating to `InventoryService`; it does not require a ticket or distinct AISMS release authority. Phase 6E must close this bypass at the service boundary before ticket redemption is enabled. MAO policy decisions and transactional requirements are listed in the specification.
