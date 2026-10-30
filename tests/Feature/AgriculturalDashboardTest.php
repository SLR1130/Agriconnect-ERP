<?php

namespace Tests\Feature;

use App\Models\AgriculturalSeason;
use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\CropProductionRecord;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\EquipmentRequest;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmMonitoringRecord;
use App\Models\FisheryRecord;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\LivestockRecord;
use App\Models\PestDiseaseIncident;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\ProcurementSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SupplyBatch;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Services\AgriculturalAnalyticsService;
use App\Services\BudgetService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgriculturalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_counts_farmer_and_production_records_and_excludes_expired_stock_from_usable_balance(): void
    {
        Carbon::setTestNow('2026-10-30 12:00:00');
        $admin = $this->staff('super_admin');
        $farmer = $this->farmer('verified', 'Bongabong');
        $pendingFarmer = $this->farmer('pending', 'Poblacion');
        Farm::create(['farmer_id' => $farmer->id, 'name' => 'North Farm', 'barangay' => 'Bongabong', 'area' => '2.50', 'classification' => 'Irrigated crop farm']);
        $season = AgriculturalSeason::create(['name' => 'Wet 2026', 'starts_on' => '2026-06-01', 'ends_on' => '2026-11-30', 'status' => 'open']);

        CropProductionRecord::create([
            'farmer_id' => $farmer->id, 'farm_id' => $farmer->farms()->first()->id, 'season_id' => $season->id,
            'crop_type' => 'Rice', 'harvested_on' => '2026-10-20', 'yield_quantity' => '123.50', 'yield_unit' => 'kg', 'status' => 'harvested',
        ]);
        CropProductionRecord::create([
            'farmer_id' => $farmer->id, 'farm_id' => $farmer->farms()->first()->id, 'season_id' => $season->id,
            'crop_type' => 'Corn', 'harvested_on' => '2026-10-21', 'yield_quantity' => '8.00', 'yield_unit' => 'sacks', 'status' => 'harvested',
        ]);
        LivestockRecord::create(['farmer_id' => $farmer->id, 'farm_id' => $farmer->farms()->first()->id, 'animal_type' => 'Carabao', 'quantity' => 4, 'recorded_on' => '2026-10-22', 'status' => 'active']);
        FisheryRecord::create(['farmer_id' => $farmer->id, 'farm_id' => $farmer->farms()->first()->id, 'fishery_type' => 'Pond', 'production_quantity' => '15.25', 'production_unit' => 'kg', 'recorded_on' => '2026-10-23']);
        PestDiseaseIncident::create(['farmer_id' => $farmer->id, 'farm_id' => $farmer->farms()->first()->id, 'incident_type' => 'Pest', 'affected_crop_or_animal' => 'Rice', 'reported_on' => '2026-10-24', 'severity' => 'high', 'status' => 'investigating']);
        PestDiseaseIncident::create(['farmer_id' => $farmer->id, 'farm_id' => $farmer->farms()->first()->id, 'incident_type' => 'Disease', 'affected_crop_or_animal' => 'Corn', 'reported_on' => '2026-10-24', 'severity' => 'low', 'status' => 'closed']);
        FarmMonitoringRecord::create(['farmer_id' => $farmer->id, 'farm_id' => $farmer->farms()->first()->id, 'visited_on' => '2026-10-25', 'monitoring_type' => 'field_visit', 'findings' => 'Healthy crop', 'status' => 'recorded']);

        $category = SupplyCategory::query()->firstOrFail();
        $kilogram = UnitOfMeasurement::query()->where('symbol', 'kg')->firstOrFail();
        $item = SupplyItem::create(['category_id' => $category->id, 'unit_id' => $kilogram->id, 'code' => 'SEED-01', 'name' => 'Rice Seeds', 'minimum_stock' => '12.00', 'status' => 'active']);
        $location = InventoryLocation::query()->firstOrFail();
        $usableBatch = SupplyBatch::create(['supply_item_id' => $item->id, 'location_id' => $location->id, 'batch_number' => 'USABLE-1', 'received_on' => '2026-10-01', 'expires_on' => '2027-10-01', 'quantity_received' => '10.00', 'quantity_available' => '10.00', 'status' => 'available']);
        SupplyBatch::create(['supply_item_id' => $item->id, 'location_id' => $location->id, 'batch_number' => 'EXPIRED-1', 'received_on' => '2025-01-01', 'expires_on' => '2026-09-01', 'quantity_received' => '5.00', 'quantity_available' => '5.00', 'status' => 'available']);
        SupplyBatch::create(['supply_item_id' => $item->id, 'location_id' => $location->id, 'batch_number' => 'HELD-1', 'received_on' => '2026-10-01', 'expires_on' => null, 'quantity_received' => '30.00', 'quantity_available' => '30.00', 'status' => 'quarantined']);
        InventoryMovement::create(['reference' => 'RCV-TEST-1', 'supply_batch_id' => $usableBatch->id, 'user_id' => $admin->id, 'movement_type' => 'receipt', 'quantity' => '10.00', 'status' => 'posted']);

        $analytics = app(AgriculturalAnalyticsService::class)->dashboard(
            $admin,
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-30'),
            $season->id,
        );

        $this->assertSame(2, $this->cardValue($analytics['sections']['ffrms'], 'Registered farmers'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['ffrms'], 'Verified farmers'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['ffrms'], 'Pending review'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['ffrms'], 'Registered farms'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['ffrms'], 'Active farms'));
        $this->assertSame([['label' => 'Irrigated crop farm', 'value' => 1, 'unit' => 'farms']], $analytics['sections']['ffrms']['breakdowns'][2]['rows']);
        $this->assertSame(2, $this->cardValue($analytics['sections']['apms'], 'Crop records'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['apms'], 'Open incidents'));
        $this->assertSame([['label' => 'kg', 'value' => 123.5, 'unit' => 'kg', 'detail' => '1 harvest records'], ['label' => 'sacks', 'value' => 8.0, 'unit' => 'sacks', 'detail' => '1 harvest records']], $analytics['sections']['apms']['breakdowns'][0]['rows']);
        $this->assertSame(1, $this->cardValue($analytics['sections']['aisms'], 'Low-stock items'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['aisms'], 'Expired batches'));
        $this->assertSame([['label' => 'kg', 'value' => 10.0, 'unit' => 'kg']], $analytics['sections']['aisms']['breakdowns'][0]['rows']);
        $this->assertSame('RCV-TEST-1', InventoryMovement::query()->firstOrFail()->reference);
        $this->assertSame($pendingFarmer->id, Farmer::query()->where('registration_status', 'pending')->value('id'));
    }

    public function test_assistance_equipment_and_finance_summaries_use_actual_statuses_and_shared_budget_ledger_rules(): void
    {
        Carbon::setTestNow('2026-10-30 12:00:00');
        $admin = $this->staff('super_admin');
        $farmer = $this->farmer('verified', 'Bongabong');
        $secondFarmer = $this->farmer('verified', 'Poblacion');
        $thirdFarmer = $this->farmer('verified', 'Masaguisi');
        $program = AssistanceProgram::create(['name' => 'Seed Support', 'category' => 'Crop', 'status' => 'open', 'opens_on' => '2026-10-01', 'closes_on' => '2026-11-30']);
        $item = $this->supplyItem('FERT-01', 'Fertilizer', 'kg');
        $submitted = AssistanceApplication::create(['program_id' => $program->id, 'farmer_id' => $farmer->id, 'purpose' => 'Planting', 'status' => 'submitted']);
        $completed = AssistanceApplication::create(['program_id' => $program->id, 'farmer_id' => $secondFarmer->id, 'purpose' => 'Planting', 'status' => 'completed', 'approved_quantity' => '10.00', 'approved_supply_item_id' => $item->id]);
        AssistanceApplication::create(['program_id' => $program->id, 'farmer_id' => $thirdFarmer->id, 'purpose' => 'Planting', 'status' => 'completed']);
        $batch = SupplyBatch::create(['supply_item_id' => $item->id, 'location_id' => InventoryLocation::query()->firstOrFail()->id, 'batch_number' => 'DIST-1', 'received_on' => '2026-10-01', 'quantity_received' => '20.00', 'quantity_available' => '10.00', 'status' => 'available']);
        AssistanceDistribution::create(['application_id' => $completed->id, 'supply_batch_id' => $batch->id, 'reference' => 'REL-AAS-1', 'operation_key' => 'DASH-1', 'quantity' => '10.00', 'status' => 'distributed', 'processed_by' => $admin->id, 'distributed_at' => now()]);

        $category = EquipmentCategory::create(['name' => 'Machinery', 'is_active' => true]);
        foreach (['available', 'issued', 'maintenance', 'archived'] as $index => $availability) {
            EquipmentAsset::create(['asset_code' => 'EQ-'.$index, 'name' => 'Equipment '.$index, 'category_id' => $category->id, 'acquired_on' => '2026-01-01', 'acquisition_cost' => '500.00', 'condition' => 'good', 'availability_status' => $availability]);
        }
        EquipmentRequest::create(['reference' => 'ER-RESERVED', 'farmer_id' => $farmer->id, 'requested_by' => $farmer->user_id, 'equipment_asset_id' => EquipmentAsset::query()->firstOrFail()->id, 'intended_use' => 'Tilling', 'schedule_start' => '2026-10-20 08:00:00', 'schedule_end' => '2026-11-02 17:00:00', 'status' => 'approved']);

        $period = BudgetPeriod::create(['year' => 2026, 'name' => 'FY 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);
        $allocation = BudgetAllocation::create(['reference' => 'BUD-TEST-1', 'budget_period_id' => $period->id, 'program_code' => 'AGRI', 'funding_category' => 'Operations', 'description' => 'Test allocation', 'authorized_amount' => '1000.00', 'status' => 'active', 'created_by' => $admin->id]);
        foreach ([
            ['commitment', 'debit', '200.00'],
            ['commitment_release', 'credit', '25.00'],
            ['expenditure', 'debit', '100.00'],
            ['adjustment', 'credit', '50.00'],
            ['adjustment', 'debit', '10.00'],
        ] as $index => [$entryType, $direction, $amount]) {
            BudgetTransaction::create(['budget_allocation_id' => $allocation->id, 'entry_type' => $entryType, 'direction' => $direction, 'amount' => $amount, 'reference' => 'LEDGER-'.$index, 'description' => 'Test ledger entry', 'performed_by' => $admin->id]);
        }
        $request = ProcurementRequest::create(['request_number' => 'PR-TEST-1', 'budget_allocation_id' => $allocation->id, 'requester_id' => $admin->id, 'requesting_office' => 'MAO', 'purpose' => 'Supplies', 'estimated_total' => '150.00', 'status' => 'submitted']);
        $requestItem = ProcurementRequestItem::create(['procurement_request_id' => $request->id, 'supply_item_id' => $item->id, 'item_type' => 'supply', 'description' => 'Fertilizer', 'quantity' => '5.00', 'estimated_unit_price' => '30.00', 'estimated_total' => '150.00']);
        $supplier = ProcurementSupplier::create(['name' => 'Local Supplier', 'is_active' => true]);
        $order = PurchaseOrder::create(['purchase_order_number' => 'PO-TEST-1', 'procurement_request_id' => $request->id, 'supplier_id' => $supplier->id, 'created_by' => $admin->id, 'total_amount' => '150.00', 'status' => 'approved']);
        PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'procurement_request_item_id' => $requestItem->id, 'supply_item_id' => $item->id, 'item_type' => 'supply', 'description' => 'Fertilizer', 'ordered_quantity' => '5.00', 'unit_price' => '30.00', 'line_total' => '150.00', 'received_quantity' => '2.00']);

        $analytics = app(AgriculturalAnalyticsService::class)->dashboard($admin, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-30'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['aasms'], 'Available programs'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['aasms'], 'Pending applications'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['aasms'], 'Completed distributions'));
        $this->assertSame([['label' => 'kg', 'value' => 10.0, 'unit' => 'kg', 'detail' => '1 approved or completed applications']], $analytics['sections']['aasms']['breakdowns'][1]['rows']);
        $this->assertSame(4, $this->cardValue($analytics['sections']['aeams'], 'Registered assets'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['aeams'], 'Reserved'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['aeams'], 'Issued'));
        $this->assertSame('₱1,000.00', $this->cardValue($analytics['sections']['abpms'], 'Authorized budget'));
        $this->assertSame("\u{20B1}175.00", $this->cardValue($analytics['sections']['abpms'], 'Committed'));
        $this->assertSame('₱100.00', $this->cardValue($analytics['sections']['abpms'], 'Expenditure'));
        $this->assertSame('₱765.00', $this->cardValue($analytics['sections']['abpms'], 'Available budget'));
        $this->assertSame(1, $this->cardValue($analytics['sections']['abpms'], 'Outstanding deliveries'));
        $this->assertSame(app(BudgetService::class)->summary($allocation), app(BudgetService::class)->summaries(collect([$allocation]))[$allocation->id]);
        $this->assertSame('submitted', $submitted->status);
    }

    public function test_role_dashboards_and_reports_follow_effective_module_permissions_and_omit_farmer_identity(): void
    {
        $admin = $this->staff('super_admin');
        $personnel = $this->staff('module_personnel', ['dashboard.view', 'modules.aisms.*']);
        $auditor = $this->staff('authorized_viewer', ['dashboard.view', 'reports.view', 'activity_logs.view', 'modules.*.view']);
        $farmer = $this->farmer('verified', 'Bongabong');
        $privateName = $farmer->user->name;

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('Administrator Command Center')
            ->assertSee('Agricultural operations')
            ->assertSee('Finance &amp; procurement', false)
            ->assertDontSee('Development Progress')
            ->assertDontSee('In development');
        $this->actingAs($personnel)->get('/mao/dashboard')->assertOk()
            ->assertSee('Resources &amp; assistance', false)
            ->assertSee('Inventory and supply')
            ->assertSee('Reports')
            ->assertDontSee('Budget and procurement')
            ->assertDontSee(route('modules.abpms.dashboard'), false);
        $this->actingAs($personnel)->get('/erp/reports?report=aisms')->assertOk()->assertSee('Inventory balance and stock movements');
        $this->actingAs($personnel)->get('/erp/reports?report=abpms')->assertForbidden();
        $this->actingAs($auditor)->get('/erp/reports?report=ffrms&from=2026-01-01&to=2026-12-31')->assertOk()
            ->assertSee('Farmer and farm registrations')
            ->assertDontSee($privateName)
            ->assertDontSee($farmer->user->email);
        $this->actingAs($farmer->user)->get('/admin/dashboard')->assertForbidden();
        $this->actingAs($farmer->user)->get('/erp/reports')->assertForbidden();
    }

    public function test_report_csv_is_filtered_and_escapes_formula_like_group_labels(): void
    {
        $admin = $this->staff('super_admin');
        $this->farmer('pending', '=HYPERLINK("https://example.invalid")');

        $response = $this->actingAs($admin)->get('/erp/reports?report=ffrms&from=2026-10-01&to=2026-10-30&format=csv');

        $response->assertOk()->assertDownload('agriconnect-ffrms-report-20261001-20261030.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Generated at', $csv);
    }

    public function test_empty_database_metrics_are_zero_and_reports_show_empty_results(): void
    {
        $admin = $this->staff('super_admin');
        $from = CarbonImmutable::parse('2026-10-01');
        $to = CarbonImmutable::parse('2026-10-30');
        $analytics = app(AgriculturalAnalyticsService::class);
        $dashboard = $analytics->dashboard($admin, $from, $to);

        foreach ($dashboard['sections'] as $section) {
            foreach ($section['cards'] as $card) {
                if (is_numeric($card['value'])) {
                    $this->assertSame(0, (int) $card['value'], $card['label']);
                }
            }
        }

        $report = $analytics->report($admin, 'ffrms', $from, $to);
        $this->assertSame([], $report['rows']);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('No records match the selected period.');
    }

    private function staff(string $slug, array $permissions = []): User
    {
        $role = Role::create(['name' => ucfirst(str_replace('_', ' ', $slug)), 'slug' => $slug, 'permissions' => $permissions, 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function farmer(string $status, string $barangay): Farmer
    {
        $user = User::factory()->create(['name' => 'Private Farmer '.fake()->unique()->numberBetween(100, 9999)]);

        return Farmer::create([
            'user_id' => $user->id,
            'farmer_code' => 'F-'.fake()->unique()->numerify('#####'),
            'registration_status' => $status,
            'barangay' => $barangay,
        ]);
    }

    private function supplyItem(string $code, string $name, string $unitSymbol): SupplyItem
    {
        return SupplyItem::create([
            'category_id' => SupplyCategory::query()->firstOrFail()->id,
            'unit_id' => UnitOfMeasurement::query()->where('symbol', $unitSymbol)->firstOrFail()->id,
            'code' => $code,
            'name' => $name,
            'minimum_stock' => '0.00',
            'status' => 'active',
        ]);
    }

    private function cardValue(array $section, string $label): int|string
    {
        return collect($section['cards'])->firstWhere('label', $label)['value'];
    }
}
