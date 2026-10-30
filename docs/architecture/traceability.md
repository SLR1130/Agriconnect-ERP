# Implementation traceability matrix

The matrix links academic requirements to the actual unified application. A route listed here is a Laravel route name from `routes/web.php`; entities are the authoritative tables/models used by the implementation.

| Requirement / capability | Primary actor | Route(s) | Entities | Service / controller | Evidence |
|---|---|---|---|---|---|
| Register a farmer account | Farmer | `farmer.register`, `farmer.register.store` | `users`, `roles`, `farmers`, `farmer_registration_histories` | `FarmerPortalController`, `FarmerRegistrationService` | `FarmerPortalTest`, `IntegratedERPWorkflowTest` |
| Verify / return / reject a farmer | MAO staff | `modules.ffrms.dashboard`, `modules.ffrms.review` | `farmers`, `farmer_registration_histories`, `notifications` | `FFRMSController`, `FarmerRegistrationService` | `FarmerPortalTest` |
| Maintain farms | Verified farmer / staff portal | `farmer.portal`, `farmer.farms.store`, `farmer.farms.update` | `farms`, `farmers` | `FarmerPortalController` | `FarmerPortalTest`, `IntegratedERPWorkflowTest` |
| Record crop, livestock, fishery and monitoring data | APMS staff | `modules.apms.*` | `agricultural_seasons`, production tables, incidents, monitoring | `APMSController` | `IntegratedERPWorkflowTest` and route/controller validation |
| Register supply catalog items | Inventory personnel | `modules.aisms.items.store` | `supply_categories`, `units_of_measurement`, `supply_items` | `AISMSController` | `AISMSInventoryTest` |
| Receive stock | Inventory / procurement personnel | `modules.aisms.receipts.store`, `modules.abpms.receipts.store` | `supply_batches`, `inventory_movements`, procurement receipts | `InventoryService`, `ProcurementService` | `AISMSInventoryTest`, `ABPMSProcurementTest`, browser evidence |
| Release / adjust stock | Authorized inventory personnel | `modules.aisms.releases.store`, `modules.aisms.adjustments.store` | `supply_batches`, `inventory_movements` | `InventoryService` | `AISMSInventoryTest` |
| Apply for assistance | Verified farmer | `farmer.assistance`, `farmer.assistance.apply`, `farmer.assistance.resubmit` | `assistance_programs`, `assistance_applications`, histories | `AASMSController`, `ProgramEligibilityService` | `AASMSAssistanceTest`, `IntegratedERPWorkflowTest` |
| Review assistance | MAO staff | `modules.aasms.review` | application, history, notifications | `AASMSController`, `AssistanceService` | `AASMSAssistanceTest`, browser evidence |
| Distribute assistance | Authorized MAO staff | `modules.aasms.distribute` | distributions, batches, movements, application | `AssistanceService`, `InventoryService` | `AASMSAssistanceTest`, `IntegratedERPWorkflowTest`, browser evidence |
| Request equipment | Verified farmer | `modules.aeams.requests.store` | `equipment_requests`, `equipment_assets` | `AEAMSController`, `EquipmentService` | `AEAMSEquipmentTest`, browser evidence |
| Approve, issue and return equipment | MAO / equipment personnel | `modules.aeams.requests.review`, `.issue`, `.return` | requests, assets, transactions | `EquipmentService` | `AEAMSEquipmentTest`, browser evidence |
| Manage maintenance | Equipment personnel | `modules.aeams.maintenance.store`, `.complete` | `equipment_maintenances`, `equipment_assets`, transactions | `EquipmentService` | `AEAMSEquipmentTest` |
| Create budget and procurement request | Authorized procurement staff | `modules.abpms.periods.store`, `modules.abpms.allocations.store`, `modules.abpms.requests.store` | budget periods, allocations, ledger, requests/items | `BudgetService`, `ProcurementService` | `ABPMSProcurementTest`, browser evidence |
| Review and order procurement | Approver | `modules.abpms.requests.review`, `modules.abpms.purchase-orders.approve`, `modules.abpms.purchase-orders.order` | requests, POs, histories, budget transactions | `ProcurementService` | `ABPMSProcurementTest`, browser evidence |
| Receive physical supplies | Procurement / inventory staff | `modules.abpms.receipts.store` | receipts, receipt items, AISMS batches/movements | `ProcurementService`, `InventoryService` | `ABPMSProcurementTest`, browser evidence |
| Link received equipment to AEAMS | Procurement / asset staff | `modules.abpms.purchase-order-items.link-equipment` | receipt items, PO item, `equipment_assets` | `ProcurementService` | `ABPMSProcurementTest`, browser evidence |
| Dashboard and health status | Authenticated staff | `dashboard`, `admin.dashboard`, `mao.dashboard` | users, roles, modules, activity logs, workflow requests | `DashboardController`, `DashboardService` | `ErpCoreTest`, `RoleDashboardTest` |
| Diagnostics protection | Authorized administrator | `setup-test` | runtime/configuration only | `SetupStatusController`, `active` middleware | `ErpCoreTest` |

## Acceptance status

The latest verified milestone recorded 56 passing tests and 266 assertions, plus browser acceptance against an isolated SQLite demonstration database. The browser evidence is stored under `storage/app/qa-screenshots/`; it is not part of the live operational data model.
