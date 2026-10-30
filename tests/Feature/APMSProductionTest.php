<?php

namespace Tests\Feature;

use App\Models\CropProductionRecord;
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

    public function test_apms_workspace_keeps_the_selected_area_and_filters_production_records(): void
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
            'farmer_code' => 'APMS-FILTER-001',
            'registration_status' => 'verified',
        ]);
        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'North Field',
            'barangay' => 'Demo Barangay',
            'area' => 2,
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'status' => 'active',
        ]);

        CropProductionRecord::create([
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'crop_type' => 'Filtered Rice Crop',
            'area_unit' => 'hectare',
            'yield_unit' => 'kilogram',
            'status' => 'planned',
            'recorded_by' => $staff->id,
        ]);
        CropProductionRecord::create([
            'farmer_id' => $farmer->id,
            'farm_id' => $farm->id,
            'crop_type' => 'Hidden Corn Crop',
            'area_unit' => 'hectare',
            'yield_unit' => 'kilogram',
            'status' => 'planned',
            'recorded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)
            ->get('/modules/apms?tab=production&record_type=crops&record_search=Filtered&crop_status=planned')
            ->assertOk()
            ->assertSee('data-initial-tab="production"', false)
            ->assertSee('data-initial-record-type="crops"', false)
            ->assertSee('Filtered Rice Crop')
            ->assertSee('aria-selected="true"', false)
            ->assertSee('name="crop_type"', false)
            ->assertSee('data-apms-open-editor="crop"', false);

        $html = $response->getContent();
        $panelStart = strpos($html, 'id="apms-record-panel-crops"');
        $panelEnd = strpos($html, '</section>', $panelStart);
        $this->assertNotFalse($panelStart);
        $this->assertNotFalse($panelEnd);
        $cropPanel = substr($html, $panelStart, $panelEnd - $panelStart);
        $this->assertStringContainsString('Filtered Rice Crop', $cropPanel);
        $this->assertStringNotContainsString('Hidden Corn Crop', $cropPanel);
    }

    public function test_apms_csv_date_range_filters_records_by_their_event_date(): void
    {
        $role = Role::create([
            'name' => 'APMS Reporter',
            'slug' => 'apms_reporter',
            'permissions' => ['modules.apms.*'],
            'is_active' => true,
        ]);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);
        $farmerUser = User::factory()->create();
        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'farmer_code' => 'APMS-EXPORT-001',
            'registration_status' => 'verified',
        ]);
        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'Export Farm',
            'barangay' => 'Demo Barangay',
            'area' => 1,
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'status' => 'active',
        ]);

        foreach ([['In Range Rice', '2026-05-10'], ['Out of Range Corn', '2026-07-10']] as [$cropType, $plantedOn]) {
            CropProductionRecord::create([
                'farmer_id' => $farmer->id,
                'farm_id' => $farm->id,
                'crop_type' => $cropType,
                'planted_on' => $plantedOn,
                'area_unit' => 'hectare',
                'yield_unit' => 'kilogram',
                'status' => 'planned',
                'recorded_by' => $staff->id,
            ]);
        }

        $response = $this->actingAs($staff)->get('/modules/apms/export?from=2026-05-01&to=2026-05-31');

        $response->assertOk();
        $this->assertStringContainsString('In Range Rice', $response->streamedContent());
        $this->assertStringNotContainsString('Out of Range Corn', $response->streamedContent());
    }

    public function test_crop_pagination_preserves_the_selected_workspace_and_filters(): void
    {
        $role = Role::create([
            'name' => 'APMS Pagination Staff',
            'slug' => 'apms_pagination_staff',
            'permissions' => ['modules.apms.*'],
            'is_active' => true,
        ]);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);
        $farmerUser = User::factory()->create();
        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'farmer_code' => 'APMS-PAGE-001',
            'registration_status' => 'verified',
        ]);
        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'Pagination Farm',
            'barangay' => 'Demo Barangay',
            'area' => 2,
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'status' => 'active',
        ]);

        foreach (range(1, 11) as $number) {
            CropProductionRecord::create([
                'farmer_id' => $farmer->id,
                'farm_id' => $farm->id,
                'crop_type' => sprintf('Pagination Crop %02d', $number),
                'area_unit' => 'hectare',
                'yield_unit' => 'kilogram',
                'status' => 'planned',
                'recorded_by' => $staff->id,
            ]);
        }

        $firstPage = $this->actingAs($staff)
            ->get('/modules/apms?tab=production&record_type=crops&crop_status=planned')
            ->assertOk();
        $firstPanel = $this->cropPanel($firstPage->getContent());
        $this->assertStringContainsString('Pagination Crop 01', $firstPanel);
        $this->assertStringNotContainsString('Pagination Crop 11', $firstPanel);
        $this->assertStringContainsString('crop_status=planned&amp;crops=2', $firstPage->getContent());

        $secondPage = $this->get('/modules/apms?tab=production&record_type=crops&crop_status=planned&crops=2')
            ->assertOk()
            ->assertSee('data-initial-tab="production"', false)
            ->assertSee('data-initial-record-type="crops"', false);
        $secondPanel = $this->cropPanel($secondPage->getContent());
        $this->assertStringContainsString('Pagination Crop 11', $secondPanel);
        $this->assertStringNotContainsString('Pagination Crop 01', $secondPanel);
    }

    public function test_invalid_crop_submission_returns_with_workspace_and_form_errors(): void
    {
        $role = Role::create([
            'name' => 'APMS Validation Staff',
            'slug' => 'apms_validation_staff',
            'permissions' => ['modules.apms.*'],
            'is_active' => true,
        ]);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);

        $this->actingAs($staff)
            ->from('/modules/apms?tab=production&record_type=crops')
            ->followingRedirects()
            ->post('/modules/apms/crops', [
                '_apms_form' => 'crop',
                'farmer_id' => '',
                'farm_id' => '',
                'crop_type' => '',
                'area_unit' => 'hectare',
                'yield_unit' => 'kilogram',
                'status' => 'planned',
            ])
            ->assertOk()
            ->assertSee('data-initial-tab="production"', false)
            ->assertSee('data-error-form="crop"', false)
            ->assertSee('The farmer id field is required.')
            ->assertSee('The crop type field is required.');
    }

    private function cropPanel(string $html): string
    {
        $start = strpos($html, 'id="apms-record-panel-crops"');
        $end = $start === false ? false : strpos($html, '</section>', $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }
}
