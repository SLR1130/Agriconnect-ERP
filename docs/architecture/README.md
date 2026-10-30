# AgriConnect ERP — Implementation-Based Architecture

This documentation describes the running AgriConnect ERP as **one Laravel application**. FFRMS, APMS, AISMS, AASMS, AEAMS, and ABPMS are functional perspectives of the same authenticated system, not separately deployed applications.

## Authoritative implementation baseline

The diagrams and descriptions are derived from the source tree, migrations, services, controllers, routes, tests, and isolated demonstration workflow in this repository.

| Item | Implemented evidence |
|---|---|
| Framework | Laravel Framework 13.39.0 (`php artisan --version`) |
| Application boundary | `routes/web.php`, shared `layouts/app.blade.php`, shared authentication, shared database |
| Authentication | `AuthenticatedSessionController`, `auth` and `active` middleware |
| Authorization | `User::hasRole`, `User::hasPermission`, `User::hasModuleAccess`, controller guards, farmer ownership checks |
| Persistence | MySQL operational database; isolated SQLite demo database through `DemoDatabaseSeeder` |
| Latest verified regression baseline | 83 passing tests and 437 assertions |
| Latest verified route baseline | 90 registered routes |
| Frontend/build baseline | Blade cache and Vite production build passed in the latest milestone |
| Latest migrations | Through `2026_10_30_000000_create_user_module_access_table` |

The current operational database is not changed by this documentation task. No credentials or live records are reproduced here.

## Shared actors and authorization

| Actor / role | Source role slug | Main capabilities |
|---|---|---|
| Super Administrator | `super_admin` | Full system access; administrative dashboard and settings |
| MAO Administrator | `mao_admin` | MAO administration, module management, verification, reports, settings |
| Municipal Agriculturist | `municipal_agriculturist` | FFRMS/APMS work, farmer verification, AASMS operations, reports |
| Module Personnel | `module_personnel` | Shared module view/create/update access plus AISMS receiving, release, and adjustment permissions |
| Authorized Viewer / Auditor | `authorized_viewer` | Read-only dashboard, module, report, and activity-log access |
| Farmer | `farmer` | Own profile/farm records, assistance applications, and verified equipment requests |
| Supplier / external party | External business actor | Supplies goods against an approved purchase order; no separate supplier login is implemented |

The role definitions are seeded by `database/seeders/RoleSeeder.php`; module access is represented by `modules` and `module_access`. The application uses one `users` table and one login flow. A farmer is linked one-to-one to the owning user through `farmers.user_id`.

## Six integrated functional perspectives

| Perspective | Main routes | Authoritative entities | Shared dependencies |
|---|---|---|---|
| FFRMS | `modules.ffrms.dashboard`, `farmer.*` | `farmers`, `farms`, `farmer_registration_histories`, associations | `users`, roles, APMS, AASMS, AEAMS |
| APMS | `modules.apms.dashboard`, `modules.apms.*` | seasons, crop/livestock/fishery records, incidents, monitoring | verified `farmers` and `farms` |
| AISMS | `modules.aisms.dashboard`, `modules.aisms.*` | supply catalog, locations, batches, movements, alerts | AASMS distributions, ABPMS receipts |
| AASMS | `modules.aasms.*`, `farmer.assistance.*` | programs, applications, status history, distributions | verified FFRMS farmer, AISMS batches |
| AEAMS | `modules.aeams.*` | equipment assets, requests, maintenance, transactions | verified FFRMS farmer, ABPMS equipment receipts |
| ABPMS | `modules.abpms.*` | budget periods/allocations/ledger, procurement requests, POs, receipts, histories | AISMS inventory and AEAMS asset registry |

## Documentation map

- [Integrated architecture](integrated/README.md) — overall diagrams and cross-feature transaction boundaries.
- [FFRMS perspective](ffrms/README.md)
- [APMS perspective](apms/README.md)
- [AISMS perspective](aisms/README.md)
- [AASMS perspective](aasms/README.md)
- [AEAMS perspective](aeams/README.md)
- [ABPMS perspective](abpms/README.md)
- [Traceability matrix](traceability.md) — requirement → route → entity → service → test mapping.

Each perspective contains Mermaid source in `diagrams.md`. The focused ERDs intentionally reuse the same table names and foreign keys shown by the consolidated model; they are views, not separate schemas.

The repository does not currently include `mmdc` or PlantUML rendering binaries, so this delivery keeps the diagrams as reviewable Mermaid source rather than committing unverified PNG/SVG exports.

## Verified cross-feature boundaries

1. **FFRMS → APMS:** `APMSController` accepts a farmer and farm only when the farm belongs to that farmer and the farmer is verified.
2. **FFRMS → AASMS → AISMS:** `AASMSController@apply` requires a verified farmer; `AssistanceService@status` records the allocation; `AssistanceService@distribute` calls `InventoryService@release`, records `assistance_distributions`, and completes the application.
3. **FFRMS → AEAMS:** `EquipmentService@requestForUser` requires a verified farmer and prevents overlapping asset reservations. Issue and return update the same `equipment_assets` row and append `equipment_transactions`.
4. **ABPMS → AISMS:** `ProcurementService@receive` calls `InventoryService@receive` only for a purchase-order item with `supply_item_id`.
5. **ABPMS → AEAMS:** physical equipment receipt creates a procurement receipt without an inventory movement; `linkEquipment` links the received purchase-order item to an existing `equipment_assets` row.
6. **All domains → dashboards and reports:** `DashboardService` reads shared users, roles, modules, workflow requests, activity logs, and database health; module controllers provide their own filtered views and CSV exports.

## Implemented, tested, and planned

### Implemented and tested

- Shared authentication and role-specific redirects.
- Farmer registration, MAO verification, farmer-owned farms, and registration history.
- Crop, livestock, fisheries, incident, and farm-monitoring records for verified farms.
- Inventory receipt, release, adjustment, expiration protection, stock balance changes, and idempotency.
- Assistance eligibility, review transitions, notifications, allocation, distribution, and exact-once inventory release.
- Equipment request approval, conflict checks, issue, return, maintenance, and transaction history.
- Budget ledger, procurement review, purchase-order approval/order, physical receipt, idempotent receipt, and AEAMS linking.
- Isolated demo seeding and browser acceptance workflows.

### Current limitations to disclose

- `ModuleSeeder` retains progress metadata for academic rollout; AISMS, AASMS, and ABPMS have lower `development_progress`/`is_active` seed values even though their current routes, services, views, and tests are implemented. The controller authorization path uses permissions and the module's `is_enabled` flag.
- `AISMSController` currently initializes the dashboard `$lowStock` value to `0`; the `inventory_alert_configurations` table exists, but a computed per-item low-stock alert query is not evidenced in the current controller.
- The source provides module CSV exports and operational tables, not a separate enterprise BI/reporting product.
- The automated suite covers unauthorized and idempotency cases. Browser screenshots document the completed transaction paths; every retry permutation is not manually replayed in the browser.
- Supplier is represented as procurement data and does not have a separate external portal in the current implementation.

## Reproducibility commands

These commands inspect the implementation without changing live data:

```powershell
php artisan --version
php artisan route:list
php artisan migrate:status
php artisan test
php artisan view:cache
npm run build
```

The isolated demonstration flow is documented in `database/seeders/DemoDatabaseSeeder.php`. Local browser harnesses and screenshots are intentionally excluded from the public repository because they use machine-local browser profiles and acceptance artifacts. The demo seeder is deliberately not called by `DatabaseSeeder` and must target only the isolated SQLite database.
