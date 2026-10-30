<?php

namespace Tests\Feature;

use App\Models\InventoryLocation;
use App\Models\Role;
use App\Models\SupplyBatch;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AISMSInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function setupInventory(array $permissions = ['modules.aisms.*']): array
    {
        $role = Role::create([
            'name' => 'Inventory',
            'slug' => 'inventory',
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $category = SupplyCategory::firstOrCreate(['name' => 'Seeds'], ['is_active' => true]);
        $unit = UnitOfMeasurement::where('symbol', 'kg')->firstOrFail();
        $location = InventoryLocation::create(['name' => 'Warehouse', 'is_active' => true]);
        $item = SupplyItem::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'SEED-1',
            'name' => 'Rice seed',
            'minimum_stock' => 5,
            'status' => 'active',
        ]);

        return compact('user', 'location', 'item');
    }

    private function receiveBatch(array $data, string $number = 'B1', float $quantity = 10): SupplyBatch
    {
        app(InventoryService::class)->receive([
            'supply_item_id' => $data['item']->id,
            'location_id' => $data['location']->id,
            'batch_number' => $number,
            'received_on' => today()->toDateString(),
            'quantity' => $quantity,
            'idempotency_key' => 'receipt-'.$number,
        ], $data['user']->id);

        return SupplyBatch::where('batch_number', $number)->firstOrFail();
    }

    public function test_receipt_release_and_adjustment_persist_and_balance(): void
    {
        $data = $this->setupInventory();
        $service = app(InventoryService::class);
        $receipt = $service->receive([
            'supply_item_id' => $data['item']->id,
            'location_id' => $data['location']->id,
            'batch_number' => 'B1',
            'received_on' => today()->toDateString(),
            'quantity' => 10,
        ], $data['user']->id);
        $this->assertDatabaseHas('inventory_movements', ['id' => $receipt->id, 'movement_type' => 'receipt']);
        $batch = SupplyBatch::firstOrFail();
        $this->assertSame('10.00', (string) $batch->quantity_available);

        $service->release([
            'supply_batch_id' => $batch->id,
            'quantity' => 3,
            'recipient' => 'Farmer',
            'purpose' => 'Demo',
        ], $data['user']->id);
        $this->assertSame('7.00', (string) $batch->fresh()->quantity_available);

        $service->adjust([
            'supply_batch_id' => $batch->id,
            'quantity' => 2,
            'adjustment_reason' => 'Count correction',
            'idempotency_key' => 'adjust-basic',
        ], $data['user']->id);
        $this->assertSame('9.00', (string) $batch->fresh()->quantity_available);
    }

    public function test_insufficient_and_expired_releases_are_rejected(): void
    {
        $data = $this->setupInventory();
        $service = app(InventoryService::class);
        $service->receive([
            'supply_item_id' => $data['item']->id,
            'location_id' => $data['location']->id,
            'batch_number' => 'OLD',
            'received_on' => today()->subDays(10)->toDateString(),
            'expires_on' => today()->subDay()->toDateString(),
            'quantity' => 2,
        ], $data['user']->id);

        $this->expectException(ValidationException::class);
        $service->release([
            'supply_batch_id' => SupplyBatch::firstOrFail()->id,
            'quantity' => 1,
            'recipient' => 'x',
            'purpose' => 'x',
        ], $data['user']->id);
    }

    public function test_duplicate_receipt_is_idempotent(): void
    {
        $data = $this->setupInventory();
        $service = app(InventoryService::class);
        $payload = [
            'supply_item_id' => $data['item']->id,
            'location_id' => $data['location']->id,
            'batch_number' => 'B2',
            'received_on' => today()->toDateString(),
            'quantity' => 4,
            'idempotency_key' => 'receipt-2',
        ];
        $service->receive($payload, $data['user']->id);
        $service->receive($payload, $data['user']->id);

        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame('4.00', (string) SupplyBatch::firstOrFail()->quantity_available);
    }

    public function test_adjustment_retry_does_not_apply_stock_twice(): void
    {
        $data = $this->setupInventory();
        $batch = $this->receiveBatch($data);
        $service = app(InventoryService::class);
        $payload = [
            'supply_batch_id' => $batch->id,
            'quantity' => '-2.25',
            'adjustment_reason' => 'Physical recount',
            'idempotency_key' => 'adjust-retry-1',
        ];

        $first = $service->adjust($payload, $data['user']->id);
        $retry = $service->adjust($payload, $data['user']->id);

        $this->assertSame($first->id, $retry->id);
        $this->assertSame('7.75', (string) $batch->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('inventory_movements', [
            'id' => $first->id,
            'idempotency_key' => 'adjust-retry-1',
            'adjustment_reason' => 'Physical recount',
        ]);
    }

    public function test_adjustment_rejects_conflicting_retry_key_reuse(): void
    {
        $data = $this->setupInventory();
        $batch = $this->receiveBatch($data);
        $service = app(InventoryService::class);
        $payload = [
            'supply_batch_id' => $batch->id,
            'quantity' => 1,
            'adjustment_reason' => 'Count correction',
            'idempotency_key' => 'adjust-conflict',
        ];
        $service->adjust($payload, $data['user']->id);

        try {
            $service->adjust(array_merge($payload, ['quantity' => 3]), $data['user']->id);
            $this->fail('Expected conflicting retry-key reuse to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $this->assertSame('11.00', (string) $batch->fresh()->quantity_available);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_adjustment_cannot_create_negative_stock_or_use_zero_quantity(): void
    {
        $data = $this->setupInventory();
        $batch = $this->receiveBatch($data, 'LOW', 2);
        $service = app(InventoryService::class);

        foreach (['-2.01', '0'] as $index => $quantity) {
            try {
                $service->adjust([
                    'supply_batch_id' => $batch->id,
                    'quantity' => $quantity,
                    'adjustment_reason' => 'Count correction',
                    'idempotency_key' => 'invalid-adjust-'.$index,
                ], $data['user']->id);
                $this->fail('Expected invalid adjustment quantity to fail.');
            } catch (ValidationException) {
                $this->assertSame('2.00', (string) $batch->fresh()->quantity_available);
            }
        }
    }

    public function test_adjustment_form_requires_reason_and_idempotency_key(): void
    {
        $data = $this->setupInventory();
        $batch = $this->receiveBatch($data);

        $this->actingAs($data['user'])
            ->post('/modules/aisms/adjustments', [
                'supply_batch_id' => $batch->id,
                'quantity' => 1,
                'adjustment_reason' => '   ',
            ])
            ->assertSessionHasErrors(['adjustment_reason', 'idempotency_key']);
        $this->assertSame('10.00', (string) $batch->fresh()->quantity_available);

        $this->actingAs($data['user'])
            ->post('/modules/aisms/adjustments', [
                'supply_batch_id' => $batch->id,
                'quantity' => 1,
                'adjustment_reason' => 'Damaged packaging write-off',
                'idempotency_key' => 'web-adjust-1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Stock adjustment posted.');
        $this->assertSame('11.00', (string) $batch->fresh()->quantity_available);
    }

    public function test_dashboard_filters_actual_movements_and_csv_contains_audit_details(): void
    {
        $data = $this->setupInventory();
        $batch = $this->receiveBatch($data, 'REPORT-BATCH', 5);
        $movement = app(InventoryService::class)->adjust([
            'supply_batch_id' => $batch->id,
            'quantity' => -1,
            'adjustment_reason' => 'Damaged stock count',
            'notes' => 'Recorded during inspection.',
            'idempotency_key' => 'report-adjust',
        ], $data['user']->id);

        $this->actingAs($data['user'])
            ->get('/modules/aisms?movement_type=adjustment&supply_item_id='.$data['item']->id.'&location_id='.$data['location']->id)
            ->assertOk()
            ->assertSee($movement->reference)
            ->assertSee('Damaged stock count')
            ->assertSee($data['user']->name);

        $response = $this->actingAs($data['user'])
            ->get('/modules/aisms/export?movement_type=adjustment&supply_item_id='.$data['item']->id.'&location_id='.$data['location']->id);
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($movement->reference, $response->streamedContent());
        $this->assertStringContainsString('Damaged stock count', $response->streamedContent());
        $this->assertStringContainsString('REPORT-BATCH', $response->streamedContent());
    }

    public function test_auditor_can_export_inventory_but_cannot_post_adjustment(): void
    {
        $data = $this->setupInventory(['modules.aisms.view', 'modules.aisms.export']);
        $batch = $this->receiveBatch($data);

        $this->actingAs($data['user'])->get('/modules/aisms/export')->assertOk();
        $this->actingAs($data['user'])
            ->post('/modules/aisms/adjustments', [
                'supply_batch_id' => $batch->id,
                'quantity' => 1,
                'adjustment_reason' => 'Unauthorized attempt',
                'idempotency_key' => 'unauthorized-adjust',
            ])
            ->assertForbidden();
        $this->assertSame('10.00', (string) $batch->fresh()->quantity_available);
    }

    public function test_unauthorized_user_cannot_access_inventory(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/modules/aisms')->assertForbidden();
    }

    public function test_authorized_user_can_render_inventory_workspace_and_adjustment_form(): void
    {
        $data = $this->setupInventory();

        $this->actingAs($data['user'])
            ->get('/modules/aisms')
            ->assertOk()
            ->assertSee('Agricultural Inventory')
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('Mandatory reason', false)
            ->assertSee('Filter transactions');
    }

    public function test_transaction_forms_are_disclosed_and_reopen_for_server_validation(): void
    {
        $data = $this->setupInventory();

        $this->actingAs($data['user'])
            ->get('/modules/aisms')
            ->assertOk()
            ->assertSee('data-aisms-open-form="receive-stock"', false)
            ->assertSee('data-aisms-form="receive"', false)
            ->assertSee('data-aisms-form="release"', false)
            ->assertSee('data-aisms-form="adjust"', false)
            ->assertSee('data-aisms-form="item"', false)
            ->assertDontSee('data-aisms-form="receive" open', false)
            ->assertSee(route('modules.aisms.receipts.store'), false)
            ->assertSee(route('modules.aisms.releases.store'), false)
            ->assertSee(route('modules.aisms.adjustments.store'), false)
            ->assertSee(route('modules.aisms.items.store'), false);

        $response = $this->actingAs($data['user'])
            ->from('/modules/aisms')
            ->followingRedirects()
            ->post('/modules/aisms/receipts', ['_aisms_form' => 'receive'])
            ->assertOk()
            ->assertSee('The supply item id field is required.');

        preg_match('/<details id="receive-stock"[^>]*>/', $response->getContent(), $receiveDetails);
        $this->assertNotEmpty($receiveDetails);
        $this->assertStringContainsString('open', $receiveDetails[0], $receiveDetails[0]);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_authorized_user_can_register_supply_with_catalog_requirements(): void
    {
        $data = $this->setupInventory();

        $this->actingAs($data['user'])->post('/modules/aisms/items', [
            'category_id' => $data['item']->category_id,
            'unit_id' => $data['item']->unit_id,
            'code' => 'SEED-2',
            'name' => 'Corn seed',
            'minimum_stock' => 8,
        ])->assertRedirect();

        $this->assertDatabaseHas('supply_items', ['code' => 'SEED-2', 'name' => 'Corn seed', 'minimum_stock' => '8.00']);
    }

    public function test_low_stock_stat_uses_live_usable_supply_balances(): void
    {
        $data = $this->setupInventory();
        app(InventoryService::class)->receive([
            'supply_item_id' => $data['item']->id,
            'location_id' => $data['location']->id,
            'batch_number' => 'LOW',
            'received_on' => today()->toDateString(),
            'quantity' => 2,
        ], $data['user']->id);

        $this->actingAs($data['user'])->get('/modules/aisms')
            ->assertOk()
            ->assertSee('data-aisms-stat="low-stock"', false)
            ->assertSee('text-agricultural-gold">1</p', false);
    }

    public function test_receipts_reject_inactive_locations(): void
    {
        $data = $this->setupInventory();
        $inactive = InventoryLocation::create(['name' => 'Closed Warehouse', 'is_active' => false]);

        $this->expectException(ValidationException::class);
        app(InventoryService::class)->receive([
            'supply_item_id' => $data['item']->id,
            'location_id' => $inactive->id,
            'batch_number' => 'CLOSED',
            'received_on' => today()->toDateString(),
            'quantity' => 1,
        ], $data['user']->id);
    }

    public function test_receipts_reject_expiration_before_receipt_date(): void
    {
        $data = $this->setupInventory();

        $this->expectException(ValidationException::class);
        app(InventoryService::class)->receive([
            'supply_item_id' => $data['item']->id,
            'location_id' => $data['location']->id,
            'batch_number' => 'INVALID-DATE',
            'received_on' => today()->toDateString(),
            'expires_on' => today()->subDay()->toDateString(),
            'quantity' => 1,
        ], $data['user']->id);
    }
}
