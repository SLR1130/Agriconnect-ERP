<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Services\FarmerRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_only_a_pending_farmer_account(): void
    {
        Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);

        $this->post('/register', [
            'name' => 'Portal Farmer',
            'email' => 'farmer@example.test',
            'password' => 'secure-farmer-password',
            'password_confirmation' => 'secure-farmer-password',
            'barangay' => 'Poblacion',
        ])->assertRedirect('/farmer');

        $user = User::where('email', 'farmer@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('farmer'));
        $this->assertDatabaseHas('farmers', ['user_id' => $user->id, 'registration_status' => 'pending']);
        $this->assertDatabaseHas('farmer_registration_histories', ['farmer_id' => $user->farmer->id, 'to_status' => 'pending']);
    }

    public function test_farmer_farms_page_renders_the_accessible_four_step_registration_and_owner_edit_forms(): void
    {
        $role = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => 'FMR-WIZARD', 'barangay' => 'Poblacion', 'registration_status' => 'verified']);
        Farm::create(['farmer_id' => $farmer->id, 'name' => 'Demo Farm', 'barangay' => 'Poblacion', 'area' => 1.25, 'area_unit' => 'hectare', 'ownership_type' => 'owner', 'agricultural_activities' => ['rice'], 'categories' => ['palay']]);

        $this->actingAs($user)->get('/farmer/farms')->assertOk()
            ->assertSee('Your registered farms')
            ->assertSee('data-farm-wizard', false)
            ->assertSee('data-farm-step-panel="1"', false)
            ->assertSee('data-farm-step-panel="4"', false)
            ->assertSee('name="area_unit"', false)
            ->assertSee('name="ownership_type"', false)
            ->assertSee('name="agricultural_activities"', false)
            ->assertSee('name="categories"', false)
            ->assertSee('Save changes')
            ->assertSee('<svg', false)
            ->assertSee('data-slot="icon"', false)
            ->assertSee('class="ui-guidance-copy"', false)
            ->assertSee('action="'.route('farmer.farms.store').'"', false)
            ->assertSee('action="'.route('farmer.farms.update', Farm::first()).'"', false);
    }

    public function test_farmer_can_submit_the_farm_registration_form_and_list_fields_are_persisted(): void
    {
        $role = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => 'FMR-CREATE', 'barangay' => 'Poblacion', 'registration_status' => 'pending']);

        $this->actingAs($user)->from('/farmer/farms')->post('/farmer/farms', [
            'name' => 'Riverside Farm', 'barangay' => 'Poblacion', 'location_details' => 'Near the bridge',
            'area' => '1.25', 'area_unit' => 'hectare', 'ownership_type' => 'tenant',
            'agricultural_activities' => 'rice, vegetables', 'categories' => 'palay, tomato',
        ])->assertRedirect('/farmer/farms')->assertSessionHas('status', 'Farm record added.');

        $farm = Farm::query()->where('farmer_id', $farmer->id)->where('name', 'Riverside Farm')->firstOrFail();
        $this->assertSame(['rice', 'vegetables'], $farm->agricultural_activities);
        $this->assertSame(['palay', 'tomato'], $farm->categories);
        $this->assertSame('tenant', $farm->ownership_type);
    }

    public function test_invalid_farm_input_returns_field_errors_without_creating_a_record(): void
    {
        $role = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => 'FMR-INVALID', 'barangay' => 'Poblacion', 'registration_status' => 'pending']);

        $this->actingAs($user)->from('/farmer/farms')->post('/farmer/farms', [
            'name' => 'Invalid area farm', 'barangay' => 'Poblacion', 'area' => '0',
            'area_unit' => 'hectare', 'ownership_type' => 'owner',
        ])->assertRedirect('/farmer/farms')->assertSessionHasErrors('area')->assertSessionHas('_old_input.name', 'Invalid area farm');

        $this->assertDatabaseMissing('farms', ['farmer_id' => $farmer->id, 'name' => 'Invalid area farm']);
    }

    public function test_farmer_cannot_update_another_farmers_farm(): void
    {
        $role = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $firstUser->roles()->attach($role);
        $secondUser->roles()->attach($role);
        $firstFarmer = Farmer::create(['user_id' => $firstUser->id, 'farmer_code' => 'FMR-001', 'registration_status' => 'verified']);
        $secondFarmer = Farmer::create(['user_id' => $secondUser->id, 'farmer_code' => 'FMR-002', 'registration_status' => 'verified']);
        $farm = Farm::create(['farmer_id' => $secondFarmer->id, 'name' => 'Private Farm', 'barangay' => 'Poblacion']);

        $this->actingAs($firstUser)->patch('/farmer/farms/'.$farm->id, [
            'name' => 'Changed', 'barangay' => 'Poblacion', 'area_unit' => 'hectare', 'ownership_type' => 'owner',
        ])->assertForbidden();

        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'name' => 'Private Farm']);
    }

    public function test_authorized_staff_can_verify_a_farmer_and_create_history(): void
    {
        $staffRole = Role::create(['name' => 'FFRMS Officer', 'slug' => 'ffrms_officer', 'permissions' => ['farmer.verify'], 'is_active' => true]);
        $staff = User::factory()->create();
        $staff->roles()->attach($staffRole);
        $farmerUser = User::factory()->create();
        $farmer = Farmer::create(['user_id' => $farmerUser->id, 'farmer_code' => 'FMR-003', 'registration_status' => 'pending']);

        $this->actingAs($staff)->post('/modules/ffrms/registrations/'.$farmer->id.'/review', [
            'status' => 'verified', 'reason' => 'Office review complete.',
        ])->assertRedirect();

        $this->assertDatabaseHas('farmers', ['id' => $farmer->id, 'registration_status' => 'verified', 'verified_by' => $staff->id]);
        $this->assertDatabaseHas('farmer_registration_histories', ['farmer_id' => $farmer->id, 'from_status' => 'pending', 'to_status' => 'verified']);
        $this->assertDatabaseCount('notifications', 1);
    }
}
