<?php

namespace App\Console\Commands;

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\ProcurementSupplier;
use App\Models\SupplyBatch;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ImportStandaloneAisms extends Command
{
    protected $signature = 'aisms:import-standalone
        {--user= : ERP user ID recorded as the responsible person for imported opening balances}
        {--commit : Write the import; without this flag the command only validates and reports}';

    protected $description = 'Consolidate standalone AISMS catalog and current lot balances into the authoritative ERP ledger';

    public function handle(): int
    {
        $legacy = DB::connection('aisms_legacy');

        try {
            $this->assertLegacySchema($legacy);
            $this->validateLegacyData($legacy);
            $counts = [
                ['Products', $legacy->table('products')->count()],
                ['Warehouses', $legacy->table('warehouses')->count()],
                ['Suppliers', $legacy->table('suppliers')->count()],
                ['Inventory batches', $legacy->table('inventory_batches')->count()],
                ['Positive lot balances', $legacy->table('inventory_batches')->where('quantity', '>', 0)->count()],
                ['Non-positive balances ignored', $legacy->table('inventory_batches')->where('quantity', '<=', 0)->count()],
            ];
        } catch (Throwable $exception) {
            $this->error('Standalone AISMS validation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Source record', 'Count'], $counts);
        $this->info('Preflight validation passed. No legacy records were changed.');
        $this->warn('Historical standalone movement rows remain in the legacy database/export. This command imports catalog records and current lot balances so the ERP becomes the single live ledger.');

        if (! $this->option('commit')) {
            $this->info('Dry run complete. Re-run with --user=<ERP user ID> --commit after reviewing these counts.');

            return self::SUCCESS;
        }

        $user = User::query()->find($this->option('user'));
        if (! $user || ! $user->isActive()) {
            $this->error('A valid active ERP --user ID is required for the import audit trail.');

            return self::FAILURE;
        }

        $imported = ['products' => 0, 'warehouses' => 0, 'suppliers' => 0, 'batches' => 0, 'skipped_batches' => 0];

        try {
            DB::transaction(function () use ($legacy, $user, &$imported): void {
                $locations = $this->importLocations($legacy, $imported);
                $suppliers = $this->importSuppliers($legacy, $imported);
                $products = $this->importProducts($legacy, $imported);

                $legacy->table('inventory_batches')->where('quantity', '>', 0)->orderBy('id')->chunkById(250, function ($rows) use ($locations, $suppliers, $products, $user, &$imported): void {
                    foreach ($rows as $legacyBatch) {
                        $scaledQuantity = (float) $legacyBatch->quantity * 100;
                        if (abs($scaledQuantity - round($scaledQuantity)) > 0.00001) {
                            throw new \RuntimeException('Legacy batch '.$legacyBatch->uuid.' uses more than two quantity decimal places. Resolve that precision before committing the import.');
                        }
                        $existing = SupplyBatch::query()
                            ->where('supply_item_id', $products[$legacyBatch->product_id])
                            ->where('location_id', $locations[$legacyBatch->warehouse_id])
                            ->where('batch_number', $legacyBatch->lot_number)
                            ->first();
                        if ($existing) {
                            $imported['skipped_batches']++;

                            continue;
                        }

                        $quantity = number_format((float) $legacyBatch->quantity, 2, '.', '');
                        $receivedOn = $legacyBatch->received_at
                            ?: (filled($legacyBatch->created_at) ? substr((string) $legacyBatch->created_at, 0, 10) : today()->toDateString());
                        $status = match (Str::lower(trim((string) $legacyBatch->status))) {
                            'active' => 'available',
                            'quarantined' => 'quarantined',
                            'disposed' => 'disposed',
                            default => filled($legacyBatch->expires_at) ? 'available' : 'quarantined',
                        };
                        $batch = SupplyBatch::create([
                            'supply_item_id' => $products[$legacyBatch->product_id],
                            'location_id' => $locations[$legacyBatch->warehouse_id],
                            'supplier_id' => $legacyBatch->supplier_id ? ($suppliers[$legacyBatch->supplier_id] ?? null) : null,
                            'batch_number' => $legacyBatch->lot_number,
                            'received_on' => $receivedOn,
                            'manufactured_on' => $legacyBatch->manufactured_at,
                            'expires_on' => $legacyBatch->expires_at,
                            'quantity_received' => $quantity,
                            'quantity_available' => $quantity,
                            'unit_cost' => $legacyBatch->unit_cost,
                            'status' => $status,
                        ]);

                        if ((float) $quantity > 0) {
                            InventoryMovement::create([
                                'reference' => 'LEG-'.strtoupper(substr(sha1((string) $legacyBatch->uuid), 0, 16)),
                                'supply_batch_id' => $batch->id,
                                'user_id' => $user->id,
                                'movement_type' => 'receipt',
                                'quantity' => $quantity,
                                'idempotency_key' => 'legacy-aisms-'.$legacyBatch->uuid,
                                'notes' => trim('Imported from standalone AISMS. '.($legacyBatch->source_reference ? 'Source reference: '.$legacyBatch->source_reference : '')),
                            ]);
                        }
                        $imported['batches']++;
                    }
                }, 'id');
            });
        } catch (Throwable $exception) {
            $this->error('Import rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Result', 'Count'], collect($imported)->map(fn (int $value, string $key) => [Str::headline($key), $value])->values()->all());
        $this->info('Standalone AISMS current inventory is now consolidated into the ERP ledger. Keep the legacy database read-only for historical lookup.');

        return self::SUCCESS;
    }

    private function assertLegacySchema(ConnectionInterface $legacy): void
    {
        $schema = $legacy->getSchemaBuilder();
        foreach (['products', 'warehouses', 'suppliers', 'inventory_batches'] as $table) {
            if (! $schema->hasTable($table)) {
                throw new \RuntimeException("Required source table [{$table}] is missing.");
            }
        }
    }

    private function validateLegacyData(ConnectionInterface $legacy): void
    {
        $products = $legacy->table('products')->orderBy('id')->get();
        $warehouses = $legacy->table('warehouses')->orderBy('id')->get();
        $suppliers = $legacy->table('suppliers')->orderBy('id')->get();

        $this->assertUniqueTextField($products, 'sku', 'product SKU');
        $this->assertUniqueTextField($warehouses, 'name', 'warehouse name');
        $this->assertUniqueTextField($suppliers, 'name', 'supplier name');

        foreach ($products as $source) {
            foreach (['name', 'category', 'unit'] as $field) {
                if (blank($source->{$field})) {
                    throw new \RuntimeException("Legacy product {$source->sku} has a blank {$field}.");
                }
            }

            $scaledMinimum = (float) $source->reorder_level * 100;
            if (abs($scaledMinimum - round($scaledMinimum)) > 0.00001) {
                throw new \RuntimeException('Legacy product '.$source->sku.' uses more than two reorder-level decimal places. Resolve that precision before committing the import.');
            }

            $existing = SupplyItem::query()
                ->with(['category', 'unit'])
                ->where('code', $source->sku)
                ->first();
            if (! $existing) {
                continue;
            }

            [, $unitSymbol] = $this->normalizeUnit((string) $source->unit);
            if (
                Str::lower(trim($existing->name)) !== Str::lower(trim((string) $source->name))
                || Str::lower(trim((string) $existing->category?->name)) !== Str::lower(trim((string) $source->category))
                || Str::lower(trim((string) $existing->unit?->symbol)) !== Str::lower($unitSymbol)
            ) {
                throw new \RuntimeException('Legacy product code '.$source->sku.' conflicts with different ERP item details.');
            }
        }

        $productIds = array_fill_keys($products->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $warehouseIds = array_fill_keys($warehouses->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $supplierIds = array_fill_keys($suppliers->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $batchUuids = [];
        $lotKeys = [];

        foreach ($legacy->table('inventory_batches')->where('quantity', '>', 0)->orderBy('id')->get() as $batch) {
            if (blank($batch->uuid) || blank($batch->lot_number)) {
                throw new \RuntimeException('Every positive legacy batch requires a UUID and lot number.');
            }

            $uuid = Str::lower(trim((string) $batch->uuid));
            if (isset($batchUuids[$uuid])) {
                throw new \RuntimeException('Legacy batch UUID '.$batch->uuid.' is duplicated.');
            }
            $batchUuids[$uuid] = true;

            $productId = (int) $batch->product_id;
            $warehouseId = (int) $batch->warehouse_id;
            if (! isset($productIds[$productId])) {
                throw new \RuntimeException('Legacy batch '.$batch->uuid.' references missing product ID '.$productId.'.');
            }
            if (! isset($warehouseIds[$warehouseId])) {
                throw new \RuntimeException('Legacy batch '.$batch->uuid.' references missing warehouse ID '.$warehouseId.'.');
            }
            if (filled($batch->supplier_id) && ! isset($supplierIds[(int) $batch->supplier_id])) {
                throw new \RuntimeException('Legacy batch '.$batch->uuid.' references missing supplier ID '.$batch->supplier_id.'.');
            }

            $lotKey = $productId.'|'.$warehouseId.'|'.Str::lower(trim((string) $batch->lot_number));
            if (isset($lotKeys[$lotKey])) {
                throw new \RuntimeException('Legacy lot '.$batch->lot_number.' is duplicated for the same product and warehouse.');
            }
            $lotKeys[$lotKey] = true;

            $scaledQuantity = (float) $batch->quantity * 100;
            if (abs($scaledQuantity - round($scaledQuantity)) > 0.00001) {
                throw new \RuntimeException('Legacy batch '.$batch->uuid.' uses more than two quantity decimal places. Resolve that precision before committing the import.');
            }

            $status = Str::lower(trim((string) $batch->status));
            if (! in_array($status, ['active', 'quarantined', 'disposed'], true)) {
                throw new \RuntimeException('Legacy batch '.$batch->uuid.' has unsupported status ['.$batch->status.'].');
            }
        }
    }

    private function assertUniqueTextField(iterable $records, string $field, string $label): void
    {
        $seen = [];
        foreach ($records as $record) {
            $value = trim((string) $record->{$field});
            if ($value === '') {
                throw new \RuntimeException("Legacy {$label} cannot be blank.");
            }

            $key = Str::lower($value);
            if (isset($seen[$key])) {
                throw new \RuntimeException("Legacy {$label} [{$value}] is duplicated.");
            }
            $seen[$key] = true;
        }
    }

    /** @return array<int, int> */
    private function importLocations(ConnectionInterface $legacy, array &$imported): array
    {
        $map = [];
        foreach ($legacy->table('warehouses')->orderBy('id')->get() as $warehouse) {
            $location = InventoryLocation::firstOrCreate(
                ['name' => $warehouse->name],
                ['description' => $warehouse->location ?: 'Imported from standalone AISMS warehouse '.$warehouse->code, 'is_active' => (bool) $warehouse->is_active],
            );
            $map[$warehouse->id] = $location->id;
            $imported['warehouses'] += $location->wasRecentlyCreated ? 1 : 0;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function importSuppliers(ConnectionInterface $legacy, array &$imported): array
    {
        $map = [];
        foreach ($legacy->table('suppliers')->orderBy('id')->get() as $source) {
            $supplier = ProcurementSupplier::firstOrCreate(['name' => $source->name], [
                'contact_person' => $source->contact_person,
                'phone' => $source->phone,
                'email' => $source->email,
                'address' => $source->address,
                'registration_reference' => $source->code,
                'is_active' => (bool) $source->is_active,
            ]);
            $map[$source->id] = $supplier->id;
            $imported['suppliers'] += $supplier->wasRecentlyCreated ? 1 : 0;
        }

        return $map;
    }

    /** @return array<int, int> */
    private function importProducts(ConnectionInterface $legacy, array &$imported): array
    {
        $map = [];
        foreach ($legacy->table('products')->orderBy('id')->get() as $source) {
            $scaledMinimum = (float) $source->reorder_level * 100;
            if (abs($scaledMinimum - round($scaledMinimum)) > 0.00001) {
                throw new \RuntimeException('Legacy product '.$source->sku.' uses more than two reorder-level decimal places. Resolve that precision before committing the import.');
            }
            $category = SupplyCategory::firstOrCreate(['name' => $source->category], ['is_active' => true]);
            [$unitName, $unitSymbol] = $this->normalizeUnit((string) $source->unit);
            $unit = UnitOfMeasurement::firstOrCreate(['symbol' => $unitSymbol], ['name' => $unitName]);
            $item = SupplyItem::firstOrCreate(['code' => $source->sku], [
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'name' => $source->name,
                'minimum_stock' => $source->reorder_level,
                'status' => $source->is_active ? 'active' : 'inactive',
            ]);
            if (! $item->wasRecentlyCreated && (
                ! hash_equals(Str::lower(trim($item->name)), Str::lower(trim($source->name)))
                || (int) $item->category_id !== (int) $category->id
                || (int) $item->unit_id !== (int) $unit->id
            )) {
                throw new \RuntimeException('Legacy product code '.$source->sku.' conflicts with different ERP item details.');
            }
            $map[$source->id] = $item->id;
            $imported['products'] += $item->wasRecentlyCreated ? 1 : 0;
        }

        return $map;
    }

    /** @return array{string, string} */
    private function normalizeUnit(string $source): array
    {
        $key = Str::lower(trim($source));

        return match ($key) {
            'kg', 'kilogram', 'kilograms' => ['Kilogram', 'kg'],
            'l', 'liter', 'liters', 'litre', 'litres' => ['Liter', 'L'],
            'pc', 'piece', 'pieces', 'unit', 'units' => ['Piece', 'pc'],
            default => [Str::headline($key), substr(Str::slug($key, ''), 0, 20)],
        };
    }
}
