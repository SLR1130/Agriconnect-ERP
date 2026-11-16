# Standalone AISMS consolidation

The standalone AISMS is a migration source, not a second production ledger. See the workspace-level `INTEGRATION_ANALYSIS.md` for the capability map and cutover checklist.

Configure the `AISMS_LEGACY_DB_*` connection with a database account that has read-only access, then run:

```bash
php artisan aisms:import-standalone
php artisan aisms:import-standalone --user=<active-erp-user-id> --commit
```

The first command performs schema validation and reports source counts. The second imports current catalog and lot balances in one ERP transaction. It does not mutate the legacy database, does not import a duplicate user/role model, and does not recreate generic request or integration-log tables that the integrated ERP no longer needs.
