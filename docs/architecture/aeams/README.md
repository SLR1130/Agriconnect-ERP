# AEAMS — Agricultural Equipment and Asset perspective

AEAMS owns the equipment asset registry and the lifecycle of farmer equipment requests. Procurement may link a physically received purchase-order item to an existing AEAMS asset; procurement does not create a competing asset registry.

## Scope and objectives

- register equipment categories and assets;
- allow verified farmers to request available equipment for a schedule;
- prevent overlapping reservations and use of non-serviceable assets;
- let authorized personnel approve/reject, issue, and return equipment;
- manage maintenance state and maintenance completion;
- record an auditable equipment transaction for lifecycle actions.

## Actors and permissions

| Actor | Authorization | Operations |
|---|---|---|
| Verified farmer | Farmer role, `farmer.equipment.request`, verified FFRMS record | View available assets, submit/cancel own requests |
| MAO / equipment staff | AEAMS module view/create/update access | Register assets, review, issue, return, maintain assets |
| Procurement staff | ABPMS update access | Link received equipment to an AEAMS asset |
| Auditor | Module view access | Inspect assets and transaction history |

## Actual implementation

- Routes: `modules.aeams.dashboard`, `modules.aeams.equipment.store`, `modules.aeams.equipment.show`, `modules.aeams.requests.store`, `modules.aeams.requests.review`, `modules.aeams.requests.cancel`, `modules.aeams.requests.issue`, `modules.aeams.requests.return`, `modules.aeams.maintenance.store`, `modules.aeams.maintenance.complete`.
- Controller: `AEAMSController`.
- Service: `EquipmentService`.
- Entities: `equipment_categories`, `equipment_assets`, `equipment_requests`, `equipment_maintenances`, `equipment_transactions`.
- FFRMS dependency: request creation requires `Farmer::isVerified()`.
- ABPMS dependency: `ProcurementService::linkEquipment` requires physical receipt before setting `purchase_order_items.equipment_asset_id` and acquisition values.
- Evidence: `AEAMSEquipmentTest`, `ABPMSProcurementTest`, integrated browser acceptance.

See [diagrams.md](diagrams.md) for the focused equipment lifecycle views.
