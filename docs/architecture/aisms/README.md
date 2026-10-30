# AISMS — Agricultural Inventory and Supply perspective

AISMS is the authoritative stock ledger for agricultural supplies. A stock balance is held on `supply_batches.quantity_available` and every receipt, release, or adjustment is recorded in `inventory_movements`.

## Scope and objectives

- maintain supply categories, units, items, and warehouse locations;
- receive physical stock into a batch;
- release available, unexpired stock;
- adjust balances only with a reason;
- expose low-stock, expiration, batch, movement, and CSV views;
- provide the shared inventory transaction boundary used by AASMS and ABPMS.

## Actors and permissions

| Actor | Relevant authorization | Operations |
|---|---|---|
| Module personnel | `modules.aisms.receive`, `.release`, `.adjust`, or matching module access | Receive, release, adjust |
| MAO admin / super admin | Wildcard or AISMS module access | Full inventory management |
| Agriculturist / auditor | View/report access | Read inventory and export where granted |
| AASMS service | Internal service call | Release approved assistance allocation |
| ABPMS service | Internal service call | Add supply stock when physical goods are received |

## Actual implementation

- Routes: `modules.aisms.dashboard`, `modules.aisms.items.store`, `modules.aisms.receipts.store`, `modules.aisms.releases.store`, `modules.aisms.adjustments.store`, `modules.aisms.export`.
- Controller: `AISMSController`.
- Service: `InventoryService`.
- Entities: `supply_categories`, `units_of_measurement`, `inventory_locations`, `supply_items`, `supply_batches`, `inventory_movements`, `inventory_alert_configurations`. The schema supports alert configuration, but the current dashboard controller still returns `$lowStock = 0` rather than calculating item-level alerts.
- Invariants: `InventoryService` uses database transactions and row locks; receipt idempotency is keyed by `inventory_movements.idempotency_key`; releases reject expired and insufficient batches.
- Evidence: `AISMSInventoryTest`, `ABPMSProcurementTest`, `AASMSAssistanceTest`, `IntegratedERPWorkflowTest`, and browser acceptance screenshots.

See [diagrams.md](diagrams.md) for the focused inventory model and cross-feature paths.
