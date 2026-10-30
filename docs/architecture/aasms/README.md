# AASMS — Agricultural Assistance and Subsidy perspective

AASMS manages assistance programs, eligibility, farmer applications, MAO review, allocation, distribution, and status notifications. Its physical supply deduction is delegated to AISMS; it does not maintain a competing stock balance.

## Scope and objectives

- create programs and encode eligibility rules;
- expose open programs to verified farmers;
- prevent duplicate applications;
- record eligibility results and application status history;
- support MAO approval, rejection, and return transitions;
- allocate a supply item and approved quantity;
- distribute the complete approved allocation once through AISMS;
- notify the farmer and expose distribution history.

## Actors and permissions

| Actor | Authorization | Operations |
|---|---|---|
| Verified farmer | Farmer role, `farmer-portal` gate, `registration_status=verified` | View programs, apply, resubmit returned applications, view own history |
| MAO administrator / agriculturist | AASMS update/module access | Manage programs, review and distribute assistance |
| Module personnel | AASMS create/update access where granted | Operate assigned AASMS functions |
| AISMS service | Internal call | Release the selected matching batch |

## Actual implementation

- Routes: `modules.aasms.dashboard`, `modules.aasms.programs.store`, `modules.aasms.programs.update`, `modules.aasms.programs.toggle`, `modules.aasms.review`, `modules.aasms.distribute`, `modules.aasms.export`, `farmer.assistance`, `farmer.assistance.apply`, `farmer.assistance.resubmit`.
- Controllers: `AASMSController`, `FarmerPortalController` integration through the farmer portal.
- Services: `AssistanceService`, `ProgramEligibilityService`, `InventoryService`.
- Entities: `assistance_programs`, `assistance_applications`, `assistance_status_histories`, `assistance_distributions`, plus FFRMS and AISMS entities.
- Evidence: `AASMSAssistanceTest`, `IntegratedERPWorkflowTest`, browser acceptance evidence.

See [diagrams.md](diagrams.md) for the focused assistance model and verified-farmer workflow.
