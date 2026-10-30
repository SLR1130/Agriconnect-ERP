<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceProgram;
use App\Models\CropProductionRecord;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreOperationsCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $module): User
    {
        $role = Role::create([
            'name' => strtoupper($module).' Officer',
            'slug' => $module.'_officer',
            'permissions' => ['modules.'.$module.'.*'],
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function farmerAndFarm(): array
    {
        $farmerUser = User::factory()->create();
        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'farmer_code' => 'CORE-'.fake()->unique()->numerify('####'), 'registration_status' => 'verified']);
        $farm = Farm::create(['farmer_id' => $farmer->id, 'name' => 'Core Demo Farm', 'barangay' => 'Poblacion', 'area' => 1.5, 'area_unit' => 'hectare', 'ownership_type' => 'owner', 'status' => 'active']);

        return compact('farmerUser', 'farmer', 'farm');
    }

    public function test_ffrms_staff_can_correct_farmer_and_farm_records_with_audit_and_csv(): void
    {
        $staff = $this->staff('ffrms');
        $data = $this->farmerAndFarm();

        $this->actingAs($staff)->get('/modules/ffrms')->assertOk()->assertSee('Correct farmer information')->assertSee('Edit farm record');
        $this->actingAs($staff)->patch('/modules/ffrms/registrations/'.$data['farmer']->id, [
            'name' => 'Corrected Farmer', 'email' => $data['farmerUser']->email, 'phone' => '09170000000', 'address' => 'Updated address', 'barangay' => 'East Barangay',
        ])->assertRedirect();
        $this->actingAs($staff)->patch('/modules/ffrms/farms/'.$data['farm']->id, [
            'name' => 'Updated Farm', 'barangay' => 'East Barangay', 'location_details' => 'Block 2', 'area' => 2, 'area_unit' => 'hectare', 'ownership_type' => 'owner', 'classification' => 'Rice', 'tenancy_details' => '', 'soil_type' => 'Loam', 'irrigation_type' => 'Canal', 'agricultural_activities' => 'rice, vegetables', 'categories' => 'crop',
        ])->assertRedirect();

        $this->assertDatabaseHas('farmers', ['id' => $data['farmer']->id, 'phone' => '09170000000', 'barangay' => 'East Barangay']);
        $this->assertDatabaseHas('farms', ['id' => $data['farm']->id, 'name' => 'Updated Farm', 'area' => 2]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'ffrms', 'action_type' => 'updated']);
        $this->actingAs($staff)->get('/modules/ffrms/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($data['farmerUser'])->patch('/modules/ffrms/registrations/'.$data['farmer']->id, [])->assertForbidden();
    }

    public function test_apms_updates_are_validated_audited_and_exported_without_deleting_history(): void
    {
        $staff = $this->staff('apms');
        $data = $this->farmerAndFarm();
        $this->actingAs($staff)->post('/modules/apms/crops', [
            'farmer_id' => $data['farmer']->id, 'farm_id' => $data['farm']->id, 'crop_type' => 'Rice', 'variety' => 'Rc 222', 'planted_on' => '2026-06-01', 'area_planted' => 1, 'area_unit' => 'hectare', 'yield_quantity' => 100, 'yield_unit' => 'kilogram', 'status' => 'growing',
        ])->assertRedirect();
        $crop = CropProductionRecord::firstOrFail();

        $this->actingAs($staff)->patch('/modules/apms/crops/'.$crop->id, [
            'farmer_id' => $data['farmer']->id, 'farm_id' => $data['farm']->id, 'crop_type' => 'Corn', 'variety' => 'Hybrid', 'planted_on' => '2026-06-01', 'area_planted' => 1.25, 'area_unit' => 'hectare', 'yield_quantity' => 150, 'yield_unit' => 'kilogram', 'status' => 'harvested', 'notes' => 'Updated production record',
        ])->assertRedirect();

        $this->assertDatabaseHas('crop_production_records', ['id' => $crop->id, 'crop_type' => 'Corn', 'status' => 'harvested']);
        $this->assertDatabaseHas('activity_logs', ['module' => 'apms', 'action_type' => 'updated']);
        $this->actingAs($staff)->get('/modules/apms')->assertOk()->assertSee('Corn');
        $this->actingAs($staff)->get('/modules/apms/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_apms_browser_forms_render_authoritative_farmer_and_farm_selectors(): void
    {
        $staff = $this->staff('apms');
        $this->farmerAndFarm();

        $this->actingAs($staff)
            ->get('/modules/apms')
            ->assertOk()
            ->assertSee('name="farmer_id"', false)
            ->assertSee('name="farm_id"', false)
            ->assertDontSee("@include('apms.farm-fields')", false);
    }

    public function test_aasms_program_update_exposes_and_persists_eligibility_without_touching_applications(): void
    {
        $staff = $this->staff('aasms');
        $program = AssistanceProgram::create(['name' => 'Seed Support', 'category' => 'Seeds', 'status' => 'open', 'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => false]]);
        $data = $this->farmerAndFarm();
        $application = AssistanceApplication::create(['program_id' => $program->id, 'farmer_id' => $data['farmer']->id, 'status' => 'submitted', 'eligibility_passed' => true]);

        $this->actingAs($staff)->get('/modules/aasms')->assertOk()->assertSee('Edit program and eligibility')->assertSee('Eligible barangays');
        $this->actingAs($staff)->patch('/modules/aasms/programs/'.$program->id, [
            'name' => 'Seed Support Updated', 'category' => 'Seeds', 'description' => 'Updated rules', 'opens_on' => '2026-11-01', 'closes_on' => '2026-12-31', 'allocation_limit' => 50, 'requires_farm' => '1', 'barangays' => 'Poblacion, East Barangay', 'activities' => 'rice', 'farm_categories' => 'crop', 'previous_beneficiary_restricted' => '1',
        ])->assertRedirect();

        $updated = $program->fresh();
        $this->assertSame('Seed Support Updated', $updated->name);
        $this->assertTrue(data_get($updated->eligibility_rules, 'requires_farm'));
        $this->assertSame(['Poblacion', 'East Barangay'], data_get($updated->eligibility_rules, 'barangays'));
        $this->assertDatabaseHas('assistance_applications', ['id' => $application->id, 'program_id' => $program->id, 'status' => 'submitted']);
    }

    public function test_aeams_asset_edit_archive_restore_and_utilization_report_are_authorized(): void
    {
        $staff = $this->staff('aeams');
        $category = EquipmentCategory::firstOrCreate(['name' => 'Core Equipment'], ['is_active' => true]);
        $asset = EquipmentAsset::create(['asset_code' => 'CORE-A-001', 'name' => 'Water pump', 'category_id' => $category->id, 'condition' => 'good', 'availability_status' => 'available']);

        $this->actingAs($staff)->get('/modules/aeams/equipment/'.$asset->id)->assertOk()->assertSee('Asset controls');
        $this->actingAs($staff)->patch('/modules/aeams/equipment/'.$asset->id, ['name' => 'Updated water pump', 'category_id' => $category->id, 'condition' => 'fair', 'assigned_location' => 'Warehouse A'])->assertRedirect();
        $this->assertDatabaseHas('equipment_assets', ['id' => $asset->id, 'name' => 'Updated water pump', 'condition' => 'fair']);
        $this->actingAs($staff)->post('/modules/aeams/equipment/'.$asset->id.'/archive')->assertRedirect();
        $this->assertDatabaseHas('equipment_assets', ['id' => $asset->id, 'availability_status' => 'archived']);
        $this->actingAs($staff)->post('/modules/aeams/equipment/'.$asset->id.'/restore')->assertRedirect();
        $this->assertDatabaseHas('equipment_assets', ['id' => $asset->id, 'availability_status' => 'available']);
        $this->assertDatabaseHas('equipment_transactions', ['equipment_asset_id' => $asset->id, 'action' => 'archived']);
        $this->actingAs($staff)->get('/modules/aeams/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->patch('/modules/aeams/equipment/'.$asset->id, [])->assertForbidden();
    }
}
