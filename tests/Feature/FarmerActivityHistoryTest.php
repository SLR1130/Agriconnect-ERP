<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\EquipmentRequest;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\SupplyBatch;
use App\Models\SupplyItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmerActivityHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_history_searches_older_than_fifty_records_in_every_activity_source(): void
    {
        $role = Role::firstOrCreate(['slug' => 'farmer'], [
            'name' => 'Farmer',
            'permissions' => ['farmer.profile.manage'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $farmer = Farmer::create([
            'user_id' => $user->id,
            'farmer_code' => 'FH-'.fake()->unique()->numerify('#####'),
            'barangay' => 'Poblacion',
            'registration_status' => 'verified',
        ]);
        $farm = Farm::create([
            'farmer_id' => $farmer->id, 'name' => 'History Base Farm', 'barangay' => 'Poblacion',
            'area_unit' => 'hectare', 'ownership_type' => 'owner',
        ]);
        $base = now()->startOfDay()->subDays(51);
        $timestamps = [];
        for ($number = 1; $number <= 51; $number++) {
            $timestamps[$number] = $base->copy()->addDays($number - 1)->toDateTimeString();
        }

        $distributionApplications = [];
        for ($number = 1; $number <= 102; $number++) {
            $isDistribution = $number > 51;
            $name = $isDistribution
                ? sprintf('distribution-history-%02d', $number - 51)
                : sprintf('application-history-%02d', $number);
            $program = AssistanceProgram::create(['name' => $name, 'status' => 'open']);
            $application = AssistanceApplication::create([
                'program_id' => $program->id,
                'farmer_id' => $farmer->id,
                'status' => 'submitted',
            ]);
            $timestamp = $timestamps[$isDistribution ? $number - 51 : $number];
            DB::table('assistance_applications')->where('id', $application->id)->update(['created_at' => $timestamp, 'updated_at' => $timestamp]);
            if ($isDistribution) {
                $distributionApplications[] = $application->id;
            }
        }

        $category = EquipmentCategory::firstOrCreate(['name' => 'Activity history equipment'], ['is_active' => true]);
        for ($number = 1; $number <= 51; $number++) {
            $timestamp = $timestamps[$number];
            $asset = EquipmentAsset::create([
                'asset_code' => sprintf('HISTORY-ASSET-%03d', $number), 'name' => sprintf('equipment-history-%02d', $number), 'category_id' => $category->id,
                'condition' => 'good', 'availability_status' => 'available',
            ]);
            $request = EquipmentRequest::create([
                'reference' => sprintf('EH-%03d', $number), 'farmer_id' => $farmer->id,
                'requested_by' => $user->id, 'equipment_asset_id' => $asset->id,
                'intended_use' => 'History pagination verification',
                'schedule_start' => now()->addDays(2), 'schedule_end' => now()->addDays(3),
                'status' => 'pending', 'idempotency_key' => 'equipment-history-'.$number,
            ]);
            DB::table('equipment_requests')->where('id', $request->id)->update(['created_at' => $timestamp, 'updated_at' => $timestamp]);
        }

        $supplyItem = SupplyItem::create([
            'category_id' => DB::table('supply_categories')->value('id'),
            'unit_id' => DB::table('units_of_measurement')->value('id'),
            'code' => 'HISTORY-SUPPLY', 'name' => 'History Supply', 'minimum_stock' => 0, 'status' => 'active',
        ]);
        $batch = SupplyBatch::create([
            'supply_item_id' => $supplyItem->id, 'location_id' => DB::table('inventory_locations')->value('id'),
            'batch_number' => 'HISTORY-BATCH', 'received_on' => today(), 'quantity_received' => 1000,
            'quantity_available' => 1000, 'status' => 'available',
        ]);
        foreach ($distributionApplications as $index => $applicationId) {
            $number = $index + 1;
            AssistanceDistribution::create([
                'application_id' => $applicationId, 'supply_batch_id' => $batch->id,
                'reference' => sprintf('DH-%03d', $number), 'quantity' => 1, 'status' => 'distributed',
                'distributed_at' => Carbon::parse($timestamps[$number]),
            ]);
            DB::table('assistance_distributions')->where('application_id', $applicationId)->update([
                'created_at' => $timestamps[$number], 'updated_at' => $timestamps[$number],
            ]);
        }

        $tables = [
            'farmer_registration_histories' => fn (int $n, string $at) => [
                'farmer_id' => $farmer->id, 'to_status' => 'verified', 'reason' => sprintf('registration-history-%02d', $n), 'created_at' => $at, 'updated_at' => $at,
            ],
            'farms' => fn (int $n, string $at) => [
                'farmer_id' => $farmer->id, 'name' => sprintf('farm-history-%02d', $n), 'barangay' => 'Poblacion', 'area_unit' => 'hectare', 'ownership_type' => 'owner', 'status' => 'active', 'created_at' => $at, 'updated_at' => $at,
            ],
            'crop_production_records' => fn (int $n, string $at) => [
                'farmer_id' => $farmer->id, 'farm_id' => $farm->id, 'crop_type' => sprintf('crop-history-%02d', $n), 'status' => 'planted', 'area_unit' => 'hectare', 'yield_unit' => 'kilogram', 'created_at' => $at, 'updated_at' => $at,
            ],
            'livestock_records' => fn (int $n, string $at) => [
                'farmer_id' => $farmer->id, 'farm_id' => $farm->id, 'animal_type' => sprintf('livestock-history-%02d', $n), 'quantity' => 1, 'recorded_on' => substr($at, 0, 10), 'status' => 'active', 'created_at' => $at, 'updated_at' => $at,
            ],
            'fishery_records' => fn (int $n, string $at) => [
                'farmer_id' => $farmer->id, 'farm_id' => $farm->id, 'fishery_type' => sprintf('fishery-history-%02d', $n), 'recorded_on' => substr($at, 0, 10), 'production_unit' => 'kilogram', 'created_at' => $at, 'updated_at' => $at,
            ],
            'pest_disease_incidents' => fn (int $n, string $at) => [
                'farmer_id' => $farmer->id, 'farm_id' => $farm->id, 'incident_type' => sprintf('incident-history-%02d', $n), 'affected_crop_or_animal' => 'Rice', 'reported_on' => substr($at, 0, 10), 'severity' => 'low', 'status' => 'reported', 'created_at' => $at, 'updated_at' => $at,
            ],
            'production_submissions' => fn (int $n, string $at) => [
                'farmer_id' => $farmer->id, 'farm_id' => $farm->id, 'category' => sprintf('crop-history-%02d', $n), 'payload' => json_encode(['crop_type' => sprintf('submission-history-%02d', $n)]), 'status' => 'pending', 'idempotency_key' => 'submission-history-'.$n, 'created_at' => $at, 'updated_at' => $at,
            ],
        ];
        foreach ($tables as $table => $makeRow) {
            foreach (array_chunk(array_map(fn ($number) => $makeRow($number, $timestamps[$number]), range(1, 51)), 25) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        foreach (range(1, 51) as $number) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => 'database', 'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->id, 'data' => json_encode(['message' => sprintf('notification-history-%02d', $number)]),
                'created_at' => $timestamps[$number], 'updated_at' => $timestamps[$number],
            ]);
        }

        $searches = [
            'Assistance application' => 'application-history-01',
            'Equipment request' => 'equipment-history-01',
            'Registration update' => 'registration-history-01',
            'Farm record' => 'farm-history-01',
            'Assistance received' => 'distribution-history-01',
            'Crop record' => 'crop-history-01',
            'Livestock record' => 'livestock-history-01',
            'Fisheries record' => 'fishery-history-01',
            'Pest or disease report' => 'incident-history-01',
            'Agricultural submission' => 'crop-history-01',
            'Notification' => 'notification-history-01',
        ];

        foreach ($searches as $type => $search) {
            $response = $this->actingAs($user)->get('/farmer/activities?type='.rawurlencode($type).'&search='.rawurlencode($search));
            $response->assertOk()->assertSee('1 matching record')->assertSee($search);
        }

        $this->actingAs($user)->get('/farmer/activities?type=Notification&status=pending&search=notification-history')
            ->assertOk()->assertSee('51 matching records')->assertSee('page=4');
        $this->actingAs($user)->get('/farmer/activities?type=Notification&status=completed')
            ->assertOk()->assertSee('0 matching records');
        $this->assertDatabaseCount('assistance_distributions', 51);
        $this->assertDatabaseCount('equipment_requests', 51);
    }
}
