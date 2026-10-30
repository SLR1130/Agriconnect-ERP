<?php

namespace Database\Seeders;

use App\Models\{
    AgriculturalSeason,
    AssistanceApplication,
    AssistanceProgram,
    BudgetAllocation,
    BudgetPeriod,
    CropProductionRecord,
    EquipmentAsset,
    EquipmentCategory,
    EquipmentRequest,
    Farm,
    FarmMonitoringRecord,
    Farmer,
    InventoryLocation,
    ProcurementSupplier,
    SupplyCategory,
    SupplyItem,
    UnitOfMeasurement,
    User
};
use App\Services\{
    AssistanceService,
    BudgetService,
    EquipmentService,
    FarmerRegistrationService,
    InventoryService,
    ProcurementService
};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Repeatable fixtures for an isolated demonstration database.
 *
 * Run this only after migrating a separate SQLite file. It is intentionally
 * not called by DatabaseSeeder and never targets the live MySQL database.
 */
class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ModuleSeeder::class,
            ModuleAccessSeeder::class,
            UserSeeder::class,
        ]);

        $director = User::where('email', 'director@agriconnect.local')->firstOrFail();
        $specialist = User::where('email', 'specialist@agriconnect.local')->firstOrFail();
        $modulePersonnel = User::where('email', 'developer@agriconnect.local')->firstOrFail();
        $auditor = User::where('email', 'auditor@agriconnect.local')->firstOrFail();
        $demoPassword = env('DEMO_PASSWORD');

        if (blank($demoPassword)) {
            throw new \RuntimeException('DEMO_PASSWORD must be supplied for the isolated demonstration farmer.');
        }

        $farmerUser = app(FarmerRegistrationService::class)->register([
            'name' => 'Demo Verified Farmer',
            'email' => 'demo.farmer@agriconnect.demo',
            'password' => $demoPassword,
            'phone' => '09000000001',
            'address' => 'Demonstration address',
            'barangay' => 'Demo Barangay',
        ]);
        $farmer = $farmerUser->farmer;
        app(FarmerRegistrationService::class)->transition(
            $farmer,
            'verified',
            'Isolated demonstration identity reviewed by the MAO.',
            $director,
        );
        $farmer = $farmer->fresh();

        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'DEMO Integrated Farm',
            'barangay' => 'Demo Barangay',
            'location_details' => 'Isolated demonstration location',
            'area' => 2.50,
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'classification' => 'irrigated',
            'agricultural_activities' => ['rice', 'vegetable'],
            'categories' => ['food_crop'],
            'status' => 'active',
        ]);
        $season = AgriculturalSeason::create([
            'name' => 'DEMO Wet Season 2026',
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-11-30',
            'status' => 'active',
        ]);
        CropProductionRecord::create([
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'season_id' => $season->id,
            'crop_type' => 'Rice',
            'variety' => 'DEMO NSIC',
            'planted_on' => '2026-06-15',
            'area_planted' => 1.50,
            'area_unit' => 'hectare',
            'yield_quantity' => 3200,
            'yield_unit' => 'kilogram',
            'status' => 'growing',
            'notes' => 'DEMO DATA: production monitoring record.',
            'recorded_by' => $specialist->id,
        ]);
        FarmMonitoringRecord::create([
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'visited_on' => now()->toDateString(),
            'monitoring_type' => 'DEMO field visit',
            'findings' => 'DEMO DATA: crop is growing within expected conditions.',
            'recommendations' => 'Continue scheduled monitoring.',
            'recorded_by' => $specialist->id,
        ]);

        $category = SupplyCategory::firstOrCreate(['name' => 'DEMO Seeds'], ['is_active' => true]);
        $unit = UnitOfMeasurement::firstOrCreate(['name' => 'Kilogram'], ['symbol' => 'kg']);
        $item = SupplyItem::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'DEMO-SEED-001',
            'name' => 'DEMO Rice Seed',
            'description' => 'DEMO DATA: shared AISMS/AASMS/APMS seed item.',
            'minimum_stock' => 20,
            'status' => 'active',
        ]);
        $location = InventoryLocation::create([
            'name' => 'DEMO Municipal Warehouse',
            'description' => 'DEMO DATA: isolated inventory location.',
            'is_active' => true,
        ]);
        $inventory = app(InventoryService::class);
        $inventory->receive([
            'supply_item_id' => $item->id,
            'location_id' => $location->id,
            'batch_number' => 'DEMO-AISMS-BATCH-001',
            'received_on' => now()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'quantity' => 120,
            'idempotency_key' => 'DEMO-AISMS-RECEIPT-001',
            'notes' => 'DEMO DATA: initial AISMS stock.',
        ], $modulePersonnel->id);

        $program = AssistanceProgram::create([
            'name' => 'DEMO Rice Seed Support',
            'category' => 'DEMO crop assistance',
            'description' => 'DEMO DATA: assistance program linked to the shared seed inventory.',
            'status' => 'open',
            'opens_on' => now()->toDateString(),
            'closes_on' => now()->addMonths(3)->toDateString(),
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => true],
            'supply_item_id' => $item->id,
            'allocation_limit' => 10,
        ]);
        $application = AssistanceApplication::create([
            'program_id' => $program->id,
            'farmer_id' => $farmer->id,
            'purpose' => 'DEMO DATA: seed support for the registered demonstration farm.',
            'eligibility_passed' => true,
            'eligibility_notes' => 'Verified demonstration farmer with a registered farm.',
            'status' => 'submitted',
        ]);
        $assistance = app(AssistanceService::class);
        $application = $assistance->status(
            $application,
            'approved',
            $director->id,
            'DEMO DATA: MAO allocation approved.',
            10,
            $item->id,
        );
        $demoBatch = $item->batches()->where('batch_number', 'DEMO-AISMS-BATCH-001')->firstOrFail();
        $assistance->distribute($application, [
            'supply_batch_id' => $demoBatch->id,
            'quantity' => 10,
            'idempotency_key' => 'DEMO-AASMS-DISTRIBUTION-001',
        ], $director->id, $inventory);

        $equipmentCategory = EquipmentCategory::create([
            'name' => 'DEMO Farm Equipment',
            'description' => 'DEMO DATA: equipment category.',
            'is_active' => true,
        ]);
        $equipment = app(EquipmentService::class);
        $asset = $equipment->registerAsset([
            'asset_code' => 'DEMO-AEAMS-001',
            'name' => 'DEMO Irrigation Pump',
            'category_id' => $equipmentCategory->id,
            'description' => 'DEMO DATA: returned equipment asset.',
            'condition' => 'good',
            'availability_status' => 'available',
            'assigned_location' => 'DEMO Municipal Warehouse',
        ], $director);
        $request = $equipment->requestForFarmer($farmer, $farmerUser, [
            'equipment_asset_id' => $asset->id,
            'intended_use' => 'DEMO irrigation support',
            'schedule_start' => now()->addDay()->setTime(8, 0)->toDateTimeString(),
            'schedule_end' => now()->addDay()->setTime(16, 0)->toDateTimeString(),
            'idempotency_key' => 'DEMO-AEAMS-REQUEST-001',
        ]);
        $request = $equipment->review($request, 'approved', 'DEMO DATA: request approved.', $director);
        $request = $equipment->issue($request, 'good', $director);
        $equipment->return($request, 'good', false, 'DEMO DATA: equipment returned in good condition.', $director);

        $reservationAsset = $equipment->registerAsset([
            'asset_code' => 'DEMO-AEAMS-002',
            'name' => 'DEMO Walk-Behind Tractor',
            'category_id' => $equipmentCategory->id,
            'description' => 'DEMO DATA: pending reservation asset.',
            'condition' => 'good',
            'availability_status' => 'available',
            'assigned_location' => 'DEMO Municipal Warehouse',
        ], $director);
        $equipment->requestForFarmer($farmer, $farmerUser, [
            'equipment_asset_id' => $reservationAsset->id,
            'intended_use' => 'DEMO land preparation reservation',
            'schedule_start' => now()->addDays(3)->setTime(8, 0)->toDateTimeString(),
            'schedule_end' => now()->addDays(3)->setTime(16, 0)->toDateTimeString(),
            'idempotency_key' => 'DEMO-AEAMS-REQUEST-002',
        ]);

        $budget = app(BudgetService::class);
        $period = $budget->createPeriod([
            'year' => 2026,
            'name' => 'DEMO FY 2026 Agricultural Operations',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'status' => 'open',
        ], $director);
        $allocation = $budget->createAllocation([
            'budget_period_id' => $period->id,
            'program_code' => 'DEMO-ERP-INTEGRATION',
            'funding_category' => 'DEMO agricultural supplies and equipment',
            'description' => 'DEMO DATA: shared budget for procurement workflows.',
            'authorized_amount' => '100000.00',
        ], $director);
        $supplier = ProcurementSupplier::create([
            'name' => 'DEMO Agricultural Cooperative',
            'contact_person' => 'DEMO Procurement Desk',
            'email' => 'demo.supplier@agriconnect.demo',
            'registration_reference' => 'DEMO-SUPPLIER-001',
            'is_active' => true,
        ]);
        $procurement = app(ProcurementService::class);
        $supplyRequest = $procurement->createRequest([
            'budget_allocation_id' => $allocation->id,
            'requesting_office' => 'DEMO Municipal Agriculture Office',
            'purpose' => 'DEMO replenish seed stock for verified farmer assistance.',
            'justification' => 'DEMO DATA: approved program plan requires additional stock.',
            'idempotency_key' => 'DEMO-PR-SUPPLY-001',
            'items' => [[
                'supply_item_id' => $item->id,
                'description' => 'DEMO Rice Seed',
                'quantity' => 40,
                'estimated_unit_price' => 50,
            ]],
        ], $modulePersonnel);
        $supplyRequest = $procurement->reviewRequest($supplyRequest, 'approved', 'DEMO DATA: supply request approved.', $director);
        $supplyOrder = $procurement->createPurchaseOrder($supplyRequest, [
            'supplier_id' => $supplier->id,
            'expected_delivery_on' => now()->addDays(7)->toDateString(),
            'idempotency_key' => 'DEMO-PO-SUPPLY-001',
        ], $modulePersonnel);
        $supplyOrder = $procurement->approvePurchaseOrder($supplyOrder, $director);
        $supplyOrder = $procurement->orderPurchaseOrder($supplyOrder, $director);
        $procurement->receive($supplyOrder->items->first(), [
            'quantity' => 25,
            'received_on' => now()->toDateString(),
            'batch_number' => 'DEMO-ABPMS-BATCH-001',
            'expires_on' => now()->addYear()->toDateString(),
            'location_id' => $location->id,
            'notes' => 'DEMO DATA: partial physical delivery; 25 of 40 received.',
            'idempotency_key' => 'DEMO-GRN-SUPPLY-001',
        ], $modulePersonnel);

        $procurementAsset = $equipment->registerAsset([
            'asset_code' => 'DEMO-ABPMS-ASSET-001',
            'name' => 'DEMO Procurement Generator',
            'category_id' => $equipmentCategory->id,
            'description' => 'DEMO DATA: asset linked after physical procurement receipt.',
            'condition' => 'new',
            'availability_status' => 'available',
            'assigned_location' => 'DEMO Municipal Warehouse',
        ], $director);
        $equipmentRequest = $procurement->createRequest([
            'budget_allocation_id' => $allocation->id,
            'requesting_office' => 'DEMO Municipal Agriculture Office',
            'purpose' => 'DEMO acquire equipment for field operations.',
            'justification' => 'DEMO DATA: approved equipment procurement plan.',
            'idempotency_key' => 'DEMO-PR-EQUIPMENT-001',
            'items' => [[
                'supply_item_id' => null,
                'description' => 'DEMO Procurement Generator',
                'quantity' => 1,
                'estimated_unit_price' => 3000,
            ]],
        ], $modulePersonnel);
        $equipmentRequest = $procurement->reviewRequest($equipmentRequest, 'approved', 'DEMO DATA: equipment request approved.', $director);
        $equipmentOrder = $procurement->createPurchaseOrder($equipmentRequest, [
            'supplier_id' => $supplier->id,
            'expected_delivery_on' => now()->addDays(10)->toDateString(),
            'idempotency_key' => 'DEMO-PO-EQUIPMENT-001',
        ], $modulePersonnel);
        $equipmentOrder = $procurement->approvePurchaseOrder($equipmentOrder, $director);
        $equipmentOrder = $procurement->orderPurchaseOrder($equipmentOrder, $director);
        $procurement->receive($equipmentOrder->items->first(), [
            'quantity' => 1,
            'received_on' => now()->toDateString(),
            'notes' => 'DEMO DATA: physical equipment receipt.',
            'idempotency_key' => 'DEMO-GRN-EQUIPMENT-001',
        ], $modulePersonnel);
        $procurement->linkEquipment($equipmentOrder->items->first(), $procurementAsset, $director);

        $this->command?->info('DEMO DATABASE READY');
        $this->command?->info('Farmer login: demo.farmer@agriconnect.demo');
        $this->command?->info('Auditor login: auditor@agriconnect.local');
        $this->command?->info('Demo farmer password is supplied through DEMO_PASSWORD.');
        $this->command?->info('Demo records are labeled with DEMO and isolated from agriconnect_erp.');
        $this->command?->info('Verified farmer ID: '.$farmer->id.' | Farm ID: '.$farm->id.' | Supply item ID: '.$item->id);
        $this->command?->info('Completed assistance application ID: '.$application->id.' | Partial PO: '.$supplyOrder->purchase_order_number);
        $this->command?->info('Auditor user ID: '.$auditor->id);
    }
}
