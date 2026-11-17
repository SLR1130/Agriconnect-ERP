# ABPMS — Agricultural Budget and Procurement perspective

ABPMS manages the budget ledger and procurement lifecycle. It is the acquisition boundary for supplies and equipment, while AISMS and AEAMS remain the authoritative operational registries after receipt.

## Scope and objectives

- create fiscal budget periods and allocations;
- post budget adjustments, commitments, expenditure, and commitment releases;
- submit and review procurement requests;
- create, approve, order, cancel, and receive purchase orders;
- update AISMS only when a physical supply receipt is recorded;
- link physically received equipment to an existing AEAMS asset;
- preserve procurement request/order status histories and idempotency.

## Actors and permissions

| Actor | Authorization | Operations |
|---|---|---|
| Module personnel / procurement creator | ABPMS create/update access | Create suppliers, requests, and draft POs; receive goods where granted |
| MAO admin / approver | ABPMS approve/manage access | Review requests, approve/order/cancel POs, manage budgets |
| Auditor | ABPMS view/report access | Read budget/procurement records and export reports |
| Supplier | External delivery party | Supplies goods; current system records supplier, not supplier login |
| AISMS / AEAMS | Internal shared services/entities | Receive supply stock or link equipment after physical receipt |

## Actual implementation

- Routes: `modules.abpms.dashboard`, `modules.abpms.periods.store`, `modules.abpms.allocations.store`, `modules.abpms.allocations.adjust`, `modules.abpms.allocations.expenditure`, `modules.abpms.suppliers.store`, `modules.abpms.requests.store`, `modules.abpms.requests.review`, `modules.abpms.purchase-orders.store`, `modules.abpms.purchase-orders.approve`, `modules.abpms.purchase-orders.order`, `modules.abpms.purchase-orders.cancel`, `modules.abpms.receipts.store`, `modules.abpms.purchase-order-items.link-equipment`, `modules.abpms.export`.
- Controller: `ABPMSController`.
- Services: `ProcurementService`, `BudgetService`; `ProcurementService` composes `InventoryService` and `BudgetService`.
- Entities: budget periods, allocations, transactions; suppliers, requests/items, purchase orders/items, receipts/items, procurement histories.
- Evidence: `ABPMSProcurementTest`, `IntegratedERPWorkflowTest`, browser acceptance screenshots.

See [diagrams.md](diagrams.md) for budget, procurement, inventory, and asset integration views.
