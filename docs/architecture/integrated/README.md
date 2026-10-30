# Integrated AgriConnect ERP Architecture

This folder documents the complete system boundary and the interactions among the six agricultural domains. The diagrams are implementation-based views of the routes, controllers, services, models, and migrations already in the application.

## Application boundary

The browser reaches one Laravel web application through `routes/web.php`. Authentication is handled once by `AuthenticatedSessionController`; active-account enforcement is shared; role and module permissions are evaluated by the same `User` model. The six domains share the same MySQL schema and the same activity, notification, workflow, and user records.

## Overall diagrams

See [diagrams.md](diagrams.md) for:

1. overall use cases;
2. system context;
3. Level 1 data flow;
4. consolidated ERD;
5. UML component view;
6. core UML class view;
7. deployment view;
8. integrated activity flow;
9. integrated sequence flow.

## Transaction boundaries

The service layer is the business transaction boundary:

- `FarmerRegistrationService` wraps registration and verification history/notification writes.
- `InventoryService` locks a batch, changes its balance, and creates a movement in one database transaction.
- `AssistanceService` wraps status history, notifications, inventory release, distribution creation, and completion.
- `EquipmentService` locks assets/requests and writes request state plus equipment transactions.
- `BudgetService` maintains budget ledger invariants and available-fund checks.
- `ProcurementService` coordinates procurement state, budget commitments, physical receipts, AISMS receipt calls, and AEAMS asset links.

The cross-feature diagrams intentionally show these service calls instead of inventing message queues or duplicate repositories that do not exist in the source.

## Shared authoritative records

The same `farmers` row is used by APMS, AASMS, and AEAMS. The same `supply_batches` and `inventory_movements` rows are used by AISMS, AASMS, and ABPMS. The same `equipment_assets` row is used by AEAMS and ABPMS. Budget commitments are recorded in `budget_transactions`, not copied into procurement-only totals.

## Source anchors

- Routes: `routes/web.php`
- Authentication: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Roles: `database/seeders/RoleSeeder.php`, `app/Models/User.php`, `app/Models/Role.php`
- Core services: `app/Services/`
- Schema: `database/migrations/2026_10_28_*.php`, `database/migrations/2026_10_29_*.php`
- Integration tests: `tests/Feature/IntegratedERPWorkflowTest.php` and module-specific feature tests.
