<?php

namespace Tests\Feature;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\InventoryLocation;
use App\Models\ProcurementRequest;
use App\Models\ProcurementStatusHistory;
use App\Models\ProcurementSupplier;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ABPMSProcurementTest extends TestCase
{
    use RefreshDatabase;

    private function setupAbpms(): array
    {
        $managerRole = Role::create([
            'name' => 'ABPMS Manager',
            'slug' => 'abpms_manager',
            'permissions' => ['modules.abpms.*'],
            'is_active' => true,
        ]);
        $creatorRole = Role::create([
            'name' => 'ABPMS Creator',
            'slug' => 'abpms_creator',
            'permissions' => ['modules.abpms.view', 'modules.abpms.create', 'modules.abpms.update'],
            'is_active' => true,
        ]);
        $viewerRole = Role::create([
            'name' => 'ABPMS Viewer',
            'slug' => 'abpms_viewer',
            'permissions' => ['modules.abpms.view', 'modules.abpms.export'],
            'is_active' => true,
        ]);

        $manager = User::factory()->create();
        $manager->roles()->attach($managerRole);
        $creator = User::factory()->create();
        $creator->roles()->attach($creatorRole);
        $viewer = User::factory()->create();
        $viewer->roles()->attach($viewerRole);

        $period = BudgetPeriod::create([
            'year' => 2026,
            'name' => 'FY 2026 Agricultural Program',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'open',
        ]);
        $allocation = BudgetAllocation::create([
            'reference' => 'BA-2026-001',
            'budget_period_id' => $period->id,
            'program_code' => 'AISMS-2026',
            'funding_category' => 'Agricultural supplies',
            'description' => 'Seed and fertilizer procurement.',
            'authorized_amount' => '10000.00',
            'status' => 'active',
            'created_by' => $manager->id,
        ]);
        $supplier = ProcurementSupplier::create([
            'name' => 'Test Agricultural Cooperative',
            'contact_person' => 'Procurement Desk',
            'email' => 'supplier@example.test',
            'is_active' => true,
        ]);
        $category = SupplyCategory::create(['name' => 'ABPMS Seeds', 'is_active' => true]);
        $unit = UnitOfMeasurement::where('symbol', 'kg')->firstOrFail();
        $supplyItem = SupplyItem::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'ABPMS-SEED-001',
            'name' => 'Rice seed',
            'minimum_stock' => 5,
            'status' => 'active',
        ]);
        $location = InventoryLocation::create(['name' => 'ABPMS Warehouse', 'is_active' => true]);
        $equipmentCategory = EquipmentCategory::create(['name' => 'ABPMS Equipment', 'is_active' => true]);
        $equipment = EquipmentAsset::create([
            'asset_code' => 'ABPMS-ASSET-001',
            'name' => 'Irrigation pump',
            'category_id' => $equipmentCategory->id,
            'condition' => 'good',
            'availability_status' => 'available',
        ]);

        return compact('manager', 'creator', 'viewer', 'period', 'allocation', 'supplier', 'supplyItem', 'location', 'equipment');
    }

    private function requestPayload(array $data, string $key): array
    {
        return [
            'budget_allocation_id' => $data['allocation']->id,
            'requesting_office' => 'Municipal Agriculture Office',
            'purpose' => 'Procure supplies for verified farmer assistance.',
            'justification' => 'The existing stock level does not cover the approved program plan.',
            'idempotency_key' => $key,
            'items' => [[
                'supply_item_id' => $data['supplyItem']->id,
                'description' => 'Rice seed',
                'quantity' => '2',
                'estimated_unit_price' => '100',
            ]],
        ];
    }

    private function createApprovedOrder(array $data, string $key = 'po-key'): PurchaseOrder
    {
        $this->actingAs($data['creator'])
            ->post('/modules/abpms/requests', $this->requestPayload($data, 'request-'.uniqid()))
            ->assertRedirect();

        $procurementRequest = ProcurementRequest::latest('id')->firstOrFail();
        $this->actingAs($data['manager'])
            ->post('/modules/abpms/requests/'.$procurementRequest->id.'/review', ['status' => 'approved'])
            ->assertRedirect();

        $this->actingAs($data['creator'])
            ->post('/modules/abpms/requests/'.$procurementRequest->id.'/purchase-orders', [
                'supplier_id' => $data['supplier']->id,
                'idempotency_key' => $key,
            ])
            ->assertRedirect();

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    public function test_manager_can_create_period_and_allocation_and_dashboard_uses_real_records(): void
    {
        $data = $this->setupAbpms();

        $this->actingAs($data['manager'])
            ->get('/modules/abpms')
            ->assertOk()
            ->assertSee('Budget allocations')
            ->assertSee('BA-2026-001')
            ->assertSee('New procurement request');

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/periods', [
                'year' => 2027,
                'name' => 'FY 2027',
                'starts_on' => '2027-01-01',
                'ends_on' => '2027-12-31',
            ])
            ->assertRedirect();
        $newPeriod = BudgetPeriod::where('year', 2027)->firstOrFail();

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/allocations', [
                'budget_period_id' => $newPeriod->id,
                'program_code' => 'AASMS-2027',
                'funding_category' => 'Assistance supplies',
                'description' => 'Assistance allocation.',
                'authorized_amount' => '2500.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('budget_allocations', [
            'program_code' => 'AASMS-2027',
            'authorized_amount' => '2500.00',
            'created_by' => $data['manager']->id,
        ]);
    }

    public function test_budget_adjustment_and_expenditure_are_ledger_entries_and_idempotent(): void
    {
        $data = $this->setupAbpms();

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/allocations/'.$data['allocation']->id.'/adjust', [
                'direction' => 'credit',
                'amount' => '500',
                'description' => 'Supplemental agricultural fund.',
            ])
            ->assertRedirect();
        $this->actingAs($data['manager'])
            ->post('/modules/abpms/allocations/'.$data['allocation']->id.'/expenditure', [
                'amount' => '125',
                'reference' => 'EXP-ABPMS-001',
                'description' => 'Approved office expenditure.',
            ])
            ->assertRedirect();
        $this->actingAs($data['manager'])
            ->post('/modules/abpms/allocations/'.$data['allocation']->id.'/expenditure', [
                'amount' => '125',
                'reference' => 'EXP-ABPMS-001',
                'description' => 'Retry of the same approved office expenditure.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('budget_transactions', ['entry_type' => 'adjustment', 'direction' => 'credit', 'amount' => '500.00']);
        $this->assertDatabaseHas('budget_transactions', ['entry_type' => 'expenditure', 'reference' => 'EXP-ABPMS-001', 'amount' => '125.00']);
        $this->assertDatabaseCount('budget_transactions', 2);
    }

    public function test_creator_can_submit_request_and_duplicate_key_does_not_create_another_request(): void
    {
        $data = $this->setupAbpms();
        $payload = $this->requestPayload($data, 'request-idempotent');

        $this->actingAs($data['creator'])->post('/modules/abpms/requests', $payload)->assertRedirect();
        $this->actingAs($data['creator'])->post('/modules/abpms/requests', $payload)->assertRedirect();

        $this->assertDatabaseCount('procurement_requests', 1);
        $this->assertDatabaseCount('procurement_request_items', 1);
        $this->assertDatabaseHas('procurement_requests', ['idempotency_key' => 'request-idempotent', 'status' => 'submitted']);
    }

    public function test_viewer_can_read_but_cannot_create_or_change_budget_records(): void
    {
        $data = $this->setupAbpms();

        $this->actingAs($data['viewer'])->get('/modules/abpms')->assertOk()->assertSee('Budget allocations')->assertDontSee('Create budget period');
        $this->actingAs($data['viewer'])->post('/modules/abpms/periods', [
            'year' => 2027,
            'name' => 'Unauthorized',
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
        ])->assertForbidden();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/modules/abpms')->assertForbidden();
    }

    public function test_manager_review_records_approval_rejection_and_invalid_transition(): void
    {
        $data = $this->setupAbpms();
        $this->actingAs($data['creator'])->post('/modules/abpms/requests', $this->requestPayload($data, 'review-approve'))->assertRedirect();
        $approvedRequest = ProcurementRequest::where('idempotency_key', 'review-approve')->firstOrFail();

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/requests/'.$approvedRequest->id.'/review', ['status' => 'approved'])
            ->assertRedirect();
        $this->assertDatabaseHas('procurement_requests', ['id' => $approvedRequest->id, 'status' => 'approved', 'reviewed_by' => $data['manager']->id]);
        $this->assertDatabaseHas('procurement_status_histories', ['procurement_request_id' => $approvedRequest->id, 'from_status' => 'submitted', 'to_status' => 'approved', 'processed_by' => $data['manager']->id]);

        $this->actingAs($data['creator'])->post('/modules/abpms/requests', $this->requestPayload($data, 'review-reject'))->assertRedirect();
        $rejectedRequest = ProcurementRequest::where('idempotency_key', 'review-reject')->firstOrFail();
        $this->actingAs($data['manager'])
            ->post('/modules/abpms/requests/'.$rejectedRequest->id.'/review', ['status' => 'rejected', 'review_notes' => 'The justification needs correction.'])
            ->assertRedirect();
        $this->assertDatabaseHas('procurement_requests', ['id' => $rejectedRequest->id, 'status' => 'rejected', 'review_notes' => 'The justification needs correction.']);

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/requests/'.$rejectedRequest->id.'/review', ['status' => 'approved'])
            ->assertSessionHasErrors('status');
    }

    public function test_requester_cannot_approve_own_request_and_rejection_requires_reason(): void
    {
        $data = $this->setupAbpms();
        $this->actingAs($data['creator'])->post('/modules/abpms/requests', $this->requestPayload($data, 'self-review'))->assertRedirect();
        $request = ProcurementRequest::where('idempotency_key', 'self-review')->firstOrFail();

        $this->actingAs($data['creator'])
            ->post('/modules/abpms/requests/'.$request->id.'/review', ['status' => 'approved'])
            ->assertForbidden();

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/requests/'.$request->id.'/review', ['status' => 'rejected'])
            ->assertSessionHasErrors('review_notes');
    }

    public function test_purchase_order_approval_commits_budget_but_does_not_receive_inventory(): void
    {
        $data = $this->setupAbpms();
        $order = $this->createApprovedOrder($data);
        $this->actingAs($data['creator'])
            ->post('/modules/abpms/requests/'.$order->procurement_request_id.'/purchase-orders', [
                'supplier_id' => $data['supplier']->id,
                'idempotency_key' => 'po-key',
            ])
            ->assertRedirect();
        $this->assertDatabaseCount('purchase_orders', 1);

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-orders/'.$order->id.'/approve')
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'approved', 'approved_by' => $data['manager']->id]);
        $this->assertDatabaseHas('budget_transactions', ['purchase_order_id' => $order->id, 'entry_type' => 'commitment', 'direction' => 'debit', 'amount' => '200.00']);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_overcommitting_budget_is_rejected_without_creating_order_commitment(): void
    {
        $data = $this->setupAbpms();
        $order = $this->createApprovedOrder($data);
        $data['allocation']->update(['authorized_amount' => '100.00']);

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-orders/'.$order->id.'/approve')
            ->assertSessionHasErrors('budget_allocation_id');
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'draft']);
        $this->assertDatabaseCount('budget_transactions', 0);
    }

    public function test_cancelling_an_approved_order_releases_its_commitment(): void
    {
        $data = $this->setupAbpms();
        $order = $this->createApprovedOrder($data);
        $this->actingAs($data['manager'])->post('/modules/abpms/purchase-orders/'.$order->id.'/approve')->assertRedirect();
        $this->actingAs($data['manager'])->post('/modules/abpms/purchase-orders/'.$order->id.'/cancel')->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('budget_transactions', ['purchase_order_id' => $order->id, 'entry_type' => 'commitment', 'direction' => 'debit', 'amount' => '200.00']);
        $this->assertDatabaseHas('budget_transactions', ['purchase_order_id' => $order->id, 'entry_type' => 'commitment_release', 'direction' => 'credit', 'amount' => '200.00']);
    }

    public function test_receiving_updates_aisms_once_and_retry_does_not_double_stock(): void
    {
        $data = $this->setupAbpms();
        $order = $this->createApprovedOrder($data);
        $this->actingAs($data['manager'])->post('/modules/abpms/purchase-orders/'.$order->id.'/approve')->assertRedirect();
        $this->actingAs($data['manager'])->post('/modules/abpms/purchase-orders/'.$order->id.'/order')->assertRedirect();
        $item = $order->fresh('items')->items->firstOrFail();
        $payload = [
            'purchase_order_item_id' => $item->id,
            'quantity' => '2',
            'received_on' => '2026-10-29',
            'batch_number' => 'ABPMS-BATCH-001',
            'location_id' => $data['location']->id,
            'idempotency_key' => 'receipt-exactly-once',
            'notes' => 'Physical delivery received.',
        ];

        $this->actingAs($data['manager'])->post('/modules/abpms/receipts', $payload)->assertRedirect();
        $this->actingAs($data['manager'])->post('/modules/abpms/receipts', $payload)->assertRedirect();

        $this->assertDatabaseCount('procurement_receipts', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'receipt', 'quantity' => '2.00']);
        $this->assertDatabaseHas('supply_batches', ['batch_number' => 'ABPMS-BATCH-001', 'quantity_available' => '2.00']);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'received_quantity' => '2.00']);
    }

    public function test_equipment_link_requires_receipt_and_reuses_existing_aeams_asset(): void
    {
        $data = $this->setupAbpms();
        $requestPayload = $this->requestPayload($data, 'equipment-request');
        $requestPayload['items'][0]['supply_item_id'] = null;
        $requestPayload['items'][0]['description'] = 'Irrigation pump';
        $requestPayload['items'][0]['quantity'] = '1';
        $requestPayload['items'][0]['estimated_unit_price'] = '500';
        $this->actingAs($data['creator'])->post('/modules/abpms/requests', $requestPayload)->assertRedirect();
        $request = ProcurementRequest::where('idempotency_key', 'equipment-request')->firstOrFail();
        $this->actingAs($data['manager'])->post('/modules/abpms/requests/'.$request->id.'/review', ['status' => 'approved'])->assertRedirect();
        $this->actingAs($data['creator'])->post('/modules/abpms/requests/'.$request->id.'/purchase-orders', ['supplier_id' => $data['supplier']->id])->assertRedirect();
        $order = PurchaseOrder::latest('id')->firstOrFail();
        $this->actingAs($data['manager'])->post('/modules/abpms/purchase-orders/'.$order->id.'/approve')->assertRedirect();
        $item = $order->fresh('items')->items->firstOrFail();

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-order-items/'.$item->id.'/link-equipment', ['equipment_asset_id' => $data['equipment']->id])
            ->assertSessionHasErrors('equipment_asset_id');

        $this->actingAs($data['manager'])->post('/modules/abpms/receipts', [
            'purchase_order_item_id' => $item->id,
            'quantity' => '1',
            'received_on' => '2026-10-29',
            'idempotency_key' => 'equipment-receipt',
        ])->assertRedirect();
        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-order-items/'.$item->id.'/link-equipment', ['equipment_asset_id' => $data['equipment']->id])
            ->assertRedirect();
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'equipment_asset_id' => $data['equipment']->id]);
        $this->assertDatabaseHas('equipment_assets', ['id' => $data['equipment']->id, 'acquisition_cost' => '500.00']);
        $this->assertDatabaseCount('equipment_assets', 1);

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-order-items/'.$item->id.'/link-equipment', ['equipment_asset_id' => $data['equipment']->id])
            ->assertSessionHasErrors('equipment_asset_id');
    }

    public function test_auditor_can_export_budget_report_and_history_is_persisted(): void
    {
        $data = $this->setupAbpms();
        $this->actingAs($data['manager'])->post('/modules/abpms/allocations/'.$data['allocation']->id.'/adjust', [
            'direction' => 'credit',
            'amount' => '25',
            'description' => 'Audit fixture credit.',
        ])->assertRedirect();

        $this->actingAs($data['viewer'])->get('/modules/abpms/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('BA-2026-001');
        $this->assertDatabaseHas('budget_transactions', ['entry_type' => 'adjustment', 'direction' => 'credit', 'amount' => '25.00']);
    }

    public function test_supply_purchase_items_cannot_be_linked_to_aeams_assets(): void
    {
        $data = $this->setupAbpms();
        $order = $this->createApprovedOrder($data, 'supply-equipment-link');
        $item = $order->items()->firstOrFail();

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-orders/'.$order->id.'/approve')
            ->assertRedirect();
        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-orders/'.$order->id.'/order')
            ->assertRedirect();
        $this->actingAs($data['manager'])
            ->post('/modules/abpms/receipts', [
                'purchase_order_item_id' => $item->id,
                'quantity' => '2',
                'received_on' => '2026-10-29',
                'batch_number' => 'SUPPLY-LINK-BATCH',
                'location_id' => $data['location']->id,
                'idempotency_key' => 'supply-equipment-link-receipt',
            ])
            ->assertRedirect();

        $this->actingAs($data['manager'])
            ->post('/modules/abpms/purchase-order-items/'.$item->id.'/link-equipment', [
                'equipment_asset_id' => $data['equipment']->id,
            ])
            ->assertSessionHasErrors('equipment_asset_id');
        $this->assertDatabaseMissing('purchase_order_items', [
            'id' => $item->id,
            'equipment_asset_id' => $data['equipment']->id,
        ]);
    }
}
