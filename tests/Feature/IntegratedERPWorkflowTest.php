<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\CropProductionRecord;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\InventoryLocation;
use App\Models\Module;
use App\Models\ModuleAccess;
use App\Models\Role;
use App\Models\SupplyBatch;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegratedERPWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function staffAndFarmer(): array
    {
        $staffRole = Role::create([
            'name' => 'Integration MAO',
            'slug' => 'integration_mao',
            'permissions' => ['farmer.verify', 'modules.ffrms.*', 'modules.apms.*', 'modules.aasms.*'],
            'is_active' => true,
        ]);
        $farmerRole = Role::create([
            'name' => 'Integration Farmer',
            'slug' => 'farmer',
            'permissions' => ['farmer.profile.manage', 'farmer.farms.manage', 'farmer.assistance.apply'],
            'is_active' => true,
        ]);
        $staff = User::factory()->create();
        $staff->roles()->attach($staffRole);
        $farmerUser = User::factory()->create();
        $farmerUser->roles()->attach($farmerRole);

        return compact('staff', 'farmerUser');
    }

    private function verifiedFarmer(User $farmerUser, User $staff): Farmer
    {
        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'farmer_code' => 'INT-'.str_pad((string) $farmerUser->id, 5, '0', STR_PAD_LEFT),
            'barangay' => 'Poblacion',
            'registration_status' => 'pending',
        ]);
        $this->actingAs($staff)
            ->post('/modules/ffrms/registrations/'.$farmer->id.'/review', [
                'status' => 'verified',
                'reason' => 'Integration fixture verified by MAO.',
            ])
            ->assertRedirect();

        return $farmer->fresh();
    }

    public function test_farmer_registration_verification_farm_and_apms_share_one_authoritative_farmer(): void
    {
        $data = $this->staffAndFarmer();

        $this->actingAs($data['farmerUser'])
            ->post('/register', [
                'name' => 'Registered Integration Farmer',
                'email' => 'registered.integration@example.test',
                'password' => 'integration-password',
                'password_confirmation' => 'integration-password',
                'barangay' => 'San Isidro',
            ])
            ->assertRedirect('/farmer');
        $registeredUser = User::where('email', 'registered.integration@example.test')->firstOrFail();
        $registeredFarmer = $registeredUser->farmer;

        $this->actingAs($data['staff'])
            ->post('/modules/ffrms/registrations/'.$registeredFarmer->id.'/review', [
                'status' => 'verified',
                'reason' => 'Identity and registration documents reviewed.',
            ])
            ->assertRedirect();

        $this->actingAs($registeredUser)
            ->post('/farmer/farms', [
                'name' => 'Integrated Demonstration Farm',
                'barangay' => 'San Isidro',
                'area' => '2.50',
                'area_unit' => 'hectare',
                'ownership_type' => 'owner',
                'agricultural_activities' => 'rice, vegetable',
                'categories' => 'irrigated',
            ])
            ->assertRedirect();

        $farm = Farm::where('farmer_id', $registeredFarmer->id)->firstOrFail();
        $this->actingAs($data['staff'])
            ->post('/modules/apms/crops', [
                'farmer_id' => $registeredFarmer->id,
                'farm_id' => $farm->id,
                'crop_type' => 'Rice',
                'variety' => 'NSIC',
                'area_planted' => '1.50',
                'area_unit' => 'hectare',
                'yield_quantity' => '3200',
                'yield_unit' => 'kilogram',
                'status' => 'growing',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('farmers', [
            'id' => $registeredFarmer->id,
            'registration_status' => 'verified',
        ]);
        $this->assertDatabaseHas('crop_production_records', [
            'farmer_id' => $registeredFarmer->id,
            'farm_id' => $farm->id,
            'crop_type' => 'Rice',
        ]);
        $this->assertSame(1, Farm::where('farmer_id', $registeredFarmer->id)->count());
    }

    public function test_verified_farmer_assistance_approval_and_distribution_update_shared_inventory_once(): void
    {
        $data = $this->staffAndFarmer();
        $farmer = $this->verifiedFarmer($data['farmerUser'], $data['staff']);
        $this->actingAs($data['farmerUser'])->post('/farmer/farms', [
            'name' => 'Assistance Farm',
            'barangay' => 'Poblacion',
            'area' => '1.00',
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
        ])->assertRedirect();

        $category = SupplyCategory::firstOrCreate(['name' => 'Seeds'], ['is_active' => true]);
        $unit = UnitOfMeasurement::where('symbol', 'kg')->firstOrFail();
        $item = SupplyItem::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'INT-SEED-001',
            'name' => 'Integration rice seed',
            'minimum_stock' => 1,
            'status' => 'active',
        ]);
        $program = AssistanceProgram::create([
            'name' => 'Integration Seed Support',
            'status' => 'open',
            'supply_item_id' => $item->id,
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => true],
        ]);

        $this->actingAs($data['farmerUser'])
            ->post('/assistance/programs/'.$program->id.'/apply', ['purpose' => 'Seed support for the registered farm.'])
            ->assertRedirect();
        $application = AssistanceApplication::where('farmer_id', $farmer->id)->firstOrFail();

        $this->actingAs($data['staff'])
            ->post('/modules/aasms/applications/'.$application->id.'/review', [
                'status' => 'approved',
                'approved_quantity' => '3',
                'approved_supply_item_id' => $item->id,
            ])
            ->assertRedirect();

        $location = InventoryLocation::create(['name' => 'Integration Store', 'is_active' => true]);
        $movement = app(InventoryService::class)->receive([
            'supply_item_id' => $item->id,
            'location_id' => $location->id,
            'batch_number' => 'INT-AASMS-BATCH',
            'received_on' => now()->toDateString(),
            'quantity' => 5,
        ], $data['staff']->id);
        $batch = SupplyBatch::findOrFail($movement->supply_batch_id);
        $payload = [
            'supply_batch_id' => $batch->id,
            'quantity' => '3',
            'idempotency_key' => 'integration-distribution-1',
        ];

        $this->actingAs($data['staff'])
            ->post('/modules/aasms/applications/'.$application->id.'/distribute', $payload)
            ->assertRedirect();
        $this->actingAs($data['staff'])
            ->post('/modules/aasms/applications/'.$application->id.'/distribute', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('assistance_applications', ['id' => $application->id, 'status' => 'completed']);
        $this->assertDatabaseHas('assistance_distributions', ['application_id' => $application->id, 'quantity' => '3.00']);
        $this->assertSame(1, AssistanceDistribution::where('application_id', $application->id)->count());
        $this->assertSame('2.00', (string) $batch->fresh()->quantity_available);
    }

    public function test_verified_farmer_assistance_page_renders_application_form_for_each_open_program(): void
    {
        $data = $this->staffAndFarmer();
        $farmer = $this->verifiedFarmer($data['farmerUser'], $data['staff']);
        $program = AssistanceProgram::create([
            'name' => 'Visible Integration Assistance',
            'status' => 'open',
            'eligibility_rules' => ['requires_verified_farmer' => true],
        ]);

        $this->actingAs($data['farmerUser'])
            ->get('/farmer/assistance')
            ->assertOk()
            ->assertSee('Available assistance programs')
            ->assertSee('Submit application')
            ->assertSee(route('farmer.assistance.apply', $program), false)
            ->assertSee('Visible Integration Assistance');

        $this->actingAs($data['farmerUser'])
            ->from('/farmer/assistance')
            ->post('/assistance/programs/'.$program->id.'/apply', [
                'purpose' => 'Application submitted through the farmer assistance interface.',
            ])
            ->assertRedirect('/farmer/assistance')
            ->assertSessionHas('status', 'Assistance application submitted.');

        $this->assertDatabaseHas('assistance_applications', [
            'program_id' => $program->id,
            'farmer_id' => $farmer->id,
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('farmers', [
            'id' => $farmer->id,
            'registration_status' => 'verified',
        ]);
    }

    public function test_shared_module_access_permissions_work_for_module_personnel_and_do_not_grant_farmer_access(): void
    {
        $role = Role::create([
            'name' => 'Shared Module Personnel',
            'slug' => 'shared_module_personnel',
            'permissions' => ['dashboard.view'],
            'is_active' => true,
        ]);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);
        $farmerRole = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $farmer = User::factory()->create();
        $farmer->roles()->attach($farmerRole);

        foreach (['ffrms', 'apms', 'aisms', 'aasms'] as $slug) {
            $module = Module::create([
                'name' => strtoupper($slug),
                'slug' => $slug,
                'route_prefix' => 'modules/'.$slug,
                'is_enabled' => true,
                'is_active' => true,
            ]);
            ModuleAccess::create([
                'module_id' => $module->id,
                'role_id' => $role->id,
                'permissions' => ['view', 'create', 'update'],
            ]);
        }

        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertDontSee('Development Progress')->assertDontSee('In development');
        $this->actingAs($staff)->get('/modules/ffrms')->assertOk();
        $this->actingAs($staff)->get('/modules/apms')->assertOk();
        $this->actingAs($staff)->get('/modules/aisms')->assertOk();
        $this->actingAs($staff)->get('/modules/aasms')->assertOk();
        $this->actingAs($farmer)->get('/modules/abpms')->assertForbidden();
        $this->actingAs($farmer)->get('/modules/aisms')->assertForbidden();
    }
}
