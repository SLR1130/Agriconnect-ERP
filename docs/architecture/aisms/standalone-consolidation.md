# Standalone AISMS consolidation

The standalone AISMS is a migration source, not a second production ledger. The ERP database becomes the only writable inventory ledger after cutover. Keep the legacy database available with read-only credentials for historical lookup and audit evidence.

## Before the maintenance window

1. Confirm the ERP deployment commit and application configuration.
2. Take timestamped, checksum-verified backups of both databases.
3. Give `AISMS_LEGACY_DB_*` a database account with `SELECT` permission only.
4. Identify the active ERP user whose ID will own the imported opening-balance movements.
5. Record source catalog and positive-balance counts for reconciliation.

```sql
SELECT COUNT(*) AS products FROM products;
SELECT COUNT(*) AS warehouses FROM warehouses;
SELECT COUNT(*) AS suppliers FROM suppliers;
SELECT COUNT(*) AS positive_batches,
       COALESCE(SUM(quantity), 0) AS positive_quantity
FROM inventory_batches
WHERE quantity > 0;
```

The alignment migration is independent of the repository's intentionally QA-only claim-ticket and handover migrations. If those migrations remain pending, apply the reviewed AISMS migration directly instead of bypassing their safety guards:

```bash
php artisan migrate --path=database/migrations/2026_11_09_000000_align_aisms_inventory_workflows.php --force
```

## Validate before writing

Configure the `AISMS_LEGACY_DB_*` connection with a database account that has read-only access, then run:

```bash
php artisan aisms:import-standalone
```

The dry run performs no writes. It rejects:

- missing source tables and orphan product, warehouse, or supplier references;
- blank or duplicate catalog keys, batch UUIDs, and product/warehouse/lot combinations;
- quantities or reorder levels that exceed the ERP's two-decimal precision;
- unsupported source statuses; and
- source SKUs that conflict with existing ERP item details.

Non-positive batch balances are reported and intentionally ignored because the import establishes current on-hand stock, not historical movements.

## Commit the opening balances

After the dry-run counts match the backup evidence:

```bash
php artisan aisms:import-standalone --user=<active-erp-user-id> --commit
```

The command imports catalog records and positive lot balances in one ERP transaction. Each imported balance receives a deterministic `legacy-aisms-<uuid>` idempotency key, so an exact retry cannot create a second opening receipt. The importer never writes to the legacy connection, does not import a duplicate user/role model, and does not recreate generic request or integration-log tables that the integrated ERP no longer needs.

## Reconcile immediately

Compare the source query above with the ERP opening movements:

```sql
SELECT COUNT(*) AS imported_opening_receipts,
       COALESCE(SUM(quantity), 0) AS imported_quantity
FROM inventory_movements
WHERE movement_type = 'receipt'
  AND idempotency_key LIKE 'legacy-aisms-%';
```

Then run the commit command one more time. It must report the existing batches as skipped and must not increase the opening-receipt count or quantity. Also verify the imported items, suppliers, locations, lot dates, unit costs, quarantine states, and expiry dates through the AISMS dashboard.

## Complete the cutover

1. Preserve the backup paths, SHA-256 checksums, command output, reconciliation queries, ERP user ID, deployment commit, and reviewer approval in the cutover record.
2. Remove application write access to the standalone AISMS and redirect staff to the ERP AISMS workspace.
3. Monitor inventory movements and failed jobs during the first operating day.
4. Do not delete the legacy database. Retain it read-only according to the municipality's records policy.

If reconciliation fails before live ERP activity begins, stop the cutover and restore the pre-import ERP backup. Once new ERP inventory movements exist, do not perform an unreviewed rollback; use an approved corrective migration or adjustment with a complete audit reason.
