# FFRMS — Farmer and Farm Registration perspective

FFRMS is the registration and identity foundation for the ERP. It does not own a separate user directory: it extends the shared `users` record with one authoritative `farmers` record and farmer-owned `farms`.

## Scope and objectives

- accept public farmer registration;
- maintain farmer contact, barangay, registration status, and review notes;
- allow a farmer to maintain only the farmer's own farm records;
- let authorized MAO staff verify, return, or reject a registration;
- preserve registration status history and notify the farmer;
- provide verified farmer/farm records to APMS, AASMS, and AEAMS.

## Actors and permissions

| Actor | Permission / guard | Operations |
|---|---|---|
| Farmer | `farmer` role; `farmer-portal` gate | Register, update own profile, create/update own farms |
| MAO admin / agriculturist | `farmer.verify`, FFRMS update access | Review registrations, manage associations |
| Module personnel | FFRMS module update access | FFRMS operational work according to assigned module access |
| Auditor | Read-only module/report permissions | Read registration data where authorized |

## Actual implementation

- Routes: `farmer.register`, `farmer.register.store`, `farmer.portal`, `farmer.profile.update`, `farmer.farms.store`, `farmer.farms.update`, `modules.ffrms.dashboard`, `modules.ffrms.review`, `modules.ffrms.associations.store`, `modules.ffrms.associations.members.store`.
- Controllers: `FarmerPortalController`, `FFRMSController`.
- Service: `FarmerRegistrationService`.
- Entities: `users`, `roles`, `role_user`, `farmers`, `farmer_registration_histories`, `farms`, `farmer_associations`, `farmer_association_members`.
- Relevant tests: `tests/Feature/FarmerPortalTest.php` and the FFRMS/APMS portion of `tests/Feature/IntegratedERPWorkflowTest.php`.

## Main process

Registration creates an active login account with the Farmer role, an FFRMS record in `pending` status, an initial history row, and an activity log. Staff transitions the farmer to `verified`, `returned`, or `rejected`; the transition records the processor, reason, timestamp, and notification. APMS and other domains consume the same farmer ID rather than creating a duplicate profile.

See [diagrams.md](diagrams.md) for the focused use case, DFD, ERD, class, activity, and sequence diagrams.
