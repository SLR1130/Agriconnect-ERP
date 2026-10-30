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
