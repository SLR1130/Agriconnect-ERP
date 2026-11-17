# APMS — Agricultural Production and Monitoring perspective

APMS records agricultural production and field monitoring against the same verified FFRMS farmer and farm records. It is not a separate farmer registry.

## Scope and objectives

- maintain agricultural seasons;
- record crop production, livestock, and fisheries data;
- report pest/disease incidents;
- record field monitoring findings and recommendations;
- expose operational statistics and paginated records to authorized staff.

## Actors and permissions

| Actor | Guard | Operations |
|---|---|---|
| MAO administrator / agriculturist | `modules.apms.*` or module access | Create and review production/monitoring records |
| Module personnel | APMS module create/update access | Enter operational records |
| Auditor | APMS view/report access | Read authorized records |
| Farmer | No APMS staff route access in current implementation | Supplies the authoritative farmer/farm identity through FFRMS |

## Actual implementation

- Routes: `modules.apms.dashboard`, `modules.apms.seasons.store`, `modules.apms.crops.store`, `modules.apms.livestock.store`, `modules.apms.fisheries.store`, `modules.apms.incidents.store`, `modules.apms.monitoring.store`.
- Controller: `app/Http/Controllers/APMSController.php`.
- Entities: `agricultural_seasons`, `crop_production_records`, `livestock_records`, `fishery_records`, `pest_disease_incidents`, `farm_monitoring_records`.
- FFRMS dependency: `APMSController::validateFarmOwner` requires a farm belonging to a verified farmer.
- Evidence: the shared farmer/farm-to-crop flow in `IntegratedERPWorkflowTest`; controller validation and dashboard rendering.

See [diagrams.md](diagrams.md) for the focused process and data views.
