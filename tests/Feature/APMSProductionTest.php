<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class APMSProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_and_monitoring_records_are_persisted_and_visible(): void
    {
        $role = Role::create([
            'name' => 'APMS Personnel',
            'slug' => 'apms_personnel',
            'permissions' => ['modules.apms.*'],
            'is_active' => true,
        ]);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);
        $farmerUser = User::factory()->create();
        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'farmer_code' => 'APMS-F-001',
            'registration_status' => 'verified',
        ]);
        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'APMS Demo Farm',
            'barangay' => 'Demo Barangay',
            'area' => 2,
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($staff)->post('/modules/apms/livestock', [
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'animal_type' => 'Cattle',
            'breed' => 'Native',
            'quantity' => 3,
            'recorded_on' => '2026-10-30',
        ])->assertRedirect();
        $this->actingAs($staff)->post('/modules/apms/fisheries', [
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'fishery_type' => 'Freshwater pond',
            'species' => 'Tilapia',
            'production_quantity' => 25,
            'production_unit' => 'kilogram',
            'recorded_on' => '2026-10-30',
        ])->assertRedirect();
        $this->actingAs($staff)->post('/modules/apms/incidents', [
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'incident_type' => 'Pest report',
            'affected_crop_or_animal' => 'Rice',
            'reported_on' => '2026-10-30',
            'severity' => 'medium',
            'description' => 'Field observation recorded.',
        ])->assertRedirect();
        $this->actingAs($staff)->post('/modules/apms/monitoring', [
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'visited_on' => '2026-10-30',
            'monitoring_type' => 'Field visit',
            'findings' => 'Crop condition is stable.',
            'recommendations' => 'Continue scheduled monitoring.',
        ])->assertRedirect();

        $this->assertDatabaseHas('livestock_records', ['animal_type' => 'Cattle', 'farm_id' => $farm->id]);
        $this->assertDatabaseHas('fishery_records', ['species' => 'Tilapia', 'farm_id' => $farm->id]);
        $this->assertDatabaseHas('pest_disease_incidents', ['incident_type' => 'Pest report', 'farm_id' => $farm->id]);
        $this->assertDatabaseHas('farm_monitoring_records', ['monitoring_type' => 'Field visit', 'farm_id' => $farm->id]);

        $this->actingAs($staff)->get('/modules/apms')
            ->assertOk()
            ->assertSee('Cattle')
            ->assertSee('Tilapia')
            ->assertSee('Pest report')
            ->assertSee('Field visit');
    }
}
