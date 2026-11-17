# AISMS — Agricultural Inventory and Supply perspective

AISMS is the authoritative stock ledger for agricultural supplies. A stock balance is held on `supply_batches.quantity_available` and every receipt, release, or adjustment is recorded in `inventory_movements`.

## Scope and objectives

- maintain supply categories, units, items, shared procurement suppliers, and warehouse locations;
- receive physical stock into a batch;
- release available, unexpired stock;
- transfer a traceable lot between active warehouse locations without changing total ERP stock;
- adjust balances only with a reason;
- quarantine or dispose a batch while preserving the audit record;
- expose low-stock, expiration, batch, movement, and CSV views;
- provide the shared inventory transaction boundary used by AASMS and ABPMS.

## Actors and permissions

| Actor | Relevant authorization | Operations |
|---|---|---|
| Module personnel | `modules.aisms.receive`, `.release`, `.transfer`, `.adjust`, or matching module access | Receive, release, transfer, adjust |
| MAO admin / super admin | Wildcard or AISMS module access | Full inventory management |
| Agriculturist / auditor | View/report access | Read inventory and export where granted |
| AASMS service | Internal service call | Release approved assistance allocation |
| ABPMS service | Internal service call | Add supply stock when physical goods are received |

## Actual implementation

- Routes: `modules.aisms.dashboard`, catalog/location/supplier setup, receipt/release/transfer/adjustment posting, batch metadata/status/disposal controls, and `modules.aisms.export`.
- Controller: `AISMSController`.
- Service: `InventoryService`.
- Entities: `supply_categories`, `units_of_measurement`, `inventory_locations`, `supply_items`, `supply_batches`, `inventory_movements`, `inventory_alert_configurations`, and the shared `procurement_suppliers` catalog. Batch traceability includes supplier, manufacturing date, expiration date, and unit cost. The live dashboard calculates low-stock items from usable balances.
- Invariants: `InventoryService` uses database transactions and row locks; receipt, transfer, adjustment, and disposal retries are keyed by `inventory_movements.idempotency_key`; releases and transfers reject expired, quarantined, and insufficient batches; transfers debit and credit the same lot atomically; disposal writes the offsetting adjustment before marking the lot disposed.
- Evidence: `AISMSInventoryTest`, `ABPMSProcurementTest`, `AASMSAssistanceTest`, `IntegratedERPWorkflowTest`, and browser acceptance screenshots.

See [diagrams.md](diagrams.md) for the focused inventory model and cross-feature paths.
