<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Services\BongabongGeography;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_a_pending_farmer_and_initial_farm(): void
    {
        Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);

        $this->post('/register', [
            'name' => 'Portal Farmer',
            'email' => 'farmer@example.test',
            'password' => 'secure-farmer-password',
            'password_confirmation' => 'secure-farmer-password',
            'barangay' => 'Poblacion',
            'farm_name' => 'Portal Farmer Farm',
            'farm_barangay' => 'Poblacion',
            'latitude' => '12.7464200',
            'longitude' => '121.4874100',
            'location_source' => 'manual',
        ])->assertRedirect('/farmer/farms');

        $user = User::where('email', 'farmer@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole('farmer'));
        $this->assertDatabaseHas('farmers', ['user_id' => $user->id, 'registration_status' => 'pending']);
        $this->assertDatabaseHas('farmer_registration_histories', ['farmer_id' => $user->farmer->id, 'to_status' => 'pending']);
        $this->assertDatabaseHas('farms', ['farmer_id' => $user->farmer->id, 'name' => 'Portal Farmer Farm', 'barangay' => 'Poblacion']);
    }

    public function test_farmer_farms_page_renders_the_accessible_four_step_registration_and_owner_edit_forms(): void
    {
        $role = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => 'FMR-WIZARD', 'barangay' => 'Poblacion', 'registration_status' => 'verified']);
        Farm::create(['farmer_id' => $farmer->id, 'name' => 'Demo Farm', 'barangay' => 'Poblacion', 'area' => 1.25, 'area_unit' => 'hectare', 'ownership_type' => 'owner', 'agricultural_activities' => ['rice'], 'categories' => ['palay']]);

        $response = $this->actingAs($user)->get('/farmer/farms')->assertOk()
            ->assertSee('Your registered farms')
            ->assertSee('data-farm-wizard', false)
            ->assertSee('data-farm-step-panel="1"', false)
            ->assertSee('data-farm-step-panel="2"', false)
            ->assertSee('data-farm-step-panel="4"', false)
            ->assertSee('data-farm-map', false)
            ->assertSee('data-farm-location-search', false)
            ->assertSee('data-farm-location-details', false)
            ->assertSee('data-farm-location-barangay', false)
            ->assertSee('data-farm-map-mode="pin"', false)
            ->assertSee('Draw farm outline')
            ->assertSee('Bongabong, Oriental Mindoro')
            ->assertSee('data-farm-google-map-link', false)
            ->assertSee('name="boundary_coordinates"', false)
            ->assertSee('name="barangay"', false)
            ->assertSee('OpenStreetMap contributors')
            ->assertSee('name="area_unit"', false)
            ->assertSee('name="ownership_type"', false)
            ->assertSee('name="agricultural_activities"', false)
            ->assertSee('name="categories"', false)
            ->assertSee('Usual farm products')
            ->assertSee('Report crops')
            ->assertSee('This is your farm profile, not a report for a specific season.')
            ->assertSee(route('farmer.production'), false)
            ->assertSee('Save changes')
            ->assertSee('<svg', false)
            ->assertSee('data-slot="icon"', false)
            ->assertSee('class="ui-guidance-copy"', false)
            ->assertSee('action="'.route('farmer.farms.store').'"', false)
            ->assertSee('action="'.route('farmer.farms.update', Farm::first()).'"', false);

        $this->assertGreaterThan(
            strpos($response->getContent(), 'data-farm-step-panel="2"'),
            strpos($response->getContent(), 'data-farm-map'),
            'The Google map belongs in the Location step, not the Basics step.'
        );

        $content = $response->getContent();
        $locationStep = strpos($content, 'data-farm-step-panel="2"');
        $farmDetailsStep = strpos($content, 'data-farm-step-panel="3"');
        $reviewStep = strpos($content, 'data-farm-step-panel="4"');
        $this->assertNotFalse($locationStep);
        $this->assertNotFalse($farmDetailsStep);
        $this->assertNotFalse($reviewStep);
        $this->assertStringContainsString('data-farm-map', substr($content, $locationStep, $farmDetailsStep - $locationStep));
        $this->assertStringNotContainsString('Farm area and ownership', substr($content, $locationStep, $farmDetailsStep - $locationStep));
        $this->assertStringContainsString('Farm area and ownership', substr($content, $farmDetailsStep, $reviewStep - $farmDetailsStep));
    }

    public function test_farmer_can_save_a_bongabong_pin_and_a_valid_approximate_farm_outline(): void
    {
        $role = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => 'FMR-MAP-SAVE', 'barangay' => 'Poblacion', 'registration_status' => 'verified']);
        $outline = [
            ['latitude' => 12.7460, 'longitude' => 121.4870],
            ['latitude' => 12.7460, 'longitude' => 121.4876],
            ['latitude' => 12.7465, 'longitude' => 121.4876],
            ['latitude' => 12.7465, 'longitude' => 121.4870],
        ];

        $this->actingAs($user)->from('/farmer/farms')->post('/farmer/farms', [
            'name' => 'Mapped Farm', 'barangay' => 'Poblacion', 'area' => '0.36', 'area_unit' => 'hectare', 'ownership_type' => 'owner',
            'latitude' => '12.7464200', 'longitude' => '121.4874100', 'location_source' => 'manual',
            'boundary_coordinates' => json_encode($outline),
        ])->assertRedirect('/farmer/farms')->assertSessionHasNoErrors();

        $farm = Farm::query()->where('farmer_id', $farmer->id)->where('name', 'Mapped Farm')->firstOrFail();
        $this->assertEquals(12.74642, (float) $farm->latitude);
        $this->assertEquals(121.48741, (float) $farm->longitude);
        $this->assertCount(4, $farm->boundary_coordinates);
        $this->assertTrue(app(BongabongGeography::class)->containsFarmOutline($farm->boundary_coordinates));

        $staffRole = Role::create(['name' => 'FFRMS Officer', 'slug' => 'ffrms_officer', 'permissions' => ['modules.ffrms.*'], 'is_active' => true]);
        $staff = User::factory()->create();
        $staff->roles()->attach($staffRole);
        $this->actingAs($staff)->get('/modules/ffrms?record='.$farmer->id)
            ->assertOk()
            ->assertSee('Mapped Farm')
            ->assertSee('0.36 hectare')
            ->assertSee('Farmer-provided outline, not a surveyed boundary')
            ->assertSee('data-ffrms-map-outline', false)
            ->assertSee('data-google-key=', false)
            ->assertSee('data-ffrms-map-message', false)
            ->assertSee('Google satellite map for Mapped Farm')
            ->assertDontSee('tile.openstreetmap.org')
            ->assertSee('12.746', false)
            ->assertSee('121.487', false);
        $this->actingAs($user)->get('/modules/ffrms?record='.$farmer->id)->assertForbidden();
    }

    public function test_super_admin_and_mao_staff_roles_get_the_google_farm_map_in_ffrms(): void
    {
        config(['services.google_maps.browser_key' => 'qa-google-maps-key']);

        $farmerRole = Role::create([
            'name' => 'Farmer',
            'slug' => 'farmer',
            'permissions' => [],
            'is_active' => true,
        ]);
        $farmerUser = User::factory()->create();
        $farmerUser->roles()->attach($farmerRole);
        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'farmer_code' => 'FMR-STAFF-GMAP',
            'barangay' => 'Poblacion',
            'registration_status' => 'verified',
        ]);
        Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'Staff QA Farm',
            'barangay' => 'Poblacion',
            'area' => 1.2,
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'latitude' => 12.74642,
            'longitude' => 121.48741,
        ]);

        foreach ([
            'super_admin' => ['*'],
            'mao_admin' => ['modules.ffrms.view'],
            'municipal_agriculturist' => ['modules.ffrms.view'],
            'authorized_viewer' => ['modules.*.view'],
        ] as $slug => $permissions) {
            $role = Role::create([
                'name' => str_replace('_', ' ', ucfirst($slug)),
                'slug' => $slug,
                'permissions' => $permissions,
                'is_active' => true,
            ]);
            $staff = User::factory()->create();
            $staff->roles()->attach($role);

            $this->actingAs($staff)->get('/modules/ffrms')
                ->assertOk()
                ->assertSee('data-google-key="qa-google-maps-key"', false)
                ->assertSee('data-ffrms-map-message', false)
                ->assertSee('Staff QA Farm')
                ->assertSee('Google satellite map of farm locations');

            $this->actingAs($staff)->get('/modules/ffrms?record='.$farmer->id)
                ->assertOk()
                ->assertSee('data-google-key="qa-google-maps-key"', false)
                ->assertSee('Google satellite map for Staff QA Farm');
        }

        $modulePersonnelRole = Role::create([
            'name' => 'Module Personnel',
            'slug' => 'module_personnel',
            'permissions' => [],
            'is_active' => true,
        ]);
        $ffrms = Module::create([
            'name' => 'Farmer and Farm Registration',
            'slug' => 'ffrms',
            'route_prefix' => '/modules/ffrms',
            'is_enabled' => true,
            'is_active' => true,
        ]);
        $modulePersonnel = User::factory()->create();
        $modulePersonnel->roles()->attach($modulePersonnelRole);
        UserModuleAccess::create([
            'user_id' => $modulePersonnel->id,
            'module_id' => $ffrms->id,
            'permissions' => ['view'],
        ]);

        $this->actingAs($modulePersonnel)->get('/modules/ffrms')
            ->assertOk()
            ->assertSee('data-google-key="qa-google-maps-key"', false)
            ->assertSee('Google satellite map of farm locations');
        $this->actingAs($modulePersonnel)->get('/modules/ffrms?record='.$farmer->id)
            ->assertOk()
            ->assertSee('data-google-key="qa-google-maps-key"', false)
            ->assertSee('Google satellite map for Staff QA Farm');
    }

    public function test_farmer_cannot_register_a_farm_pin_or_outline_outside_bongabong(): void
    {
        $role = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => 'FMR-MAP-BOUNDARY', 'barangay' => 'Poblacion', 'registration_status' => 'verified']);

        $this->actingAs($user)->from('/farmer/farms')->post('/farmer/farms', [
            'name' => 'Outside Farm', 'barangay' => 'Poblacion', 'area_unit' => 'hectare', 'ownership_type' => 'owner',
            'latitude' => '12.3450000', 'longitude' => '121.2340000', 'location_source' => 'manual',
        ])->assertRedirect('/farmer/farms')->assertSessionHasErrors('latitude');

        $outsideOutline = [
            ['latitude' => 12.3450, 'longitude' => 121.2340],
            ['latitude' => 12.3450, 'longitude' => 121.2350],
            ['latitude' => 12.3460, 'longitude' => 121.2350],
        ];
        $this->actingAs($user)->from('/farmer/farms')->post('/farmer/farms', [
            'name' => 'Outside Outline Farm', 'barangay' => 'Poblacion', 'area_unit' => 'hectare', 'ownership_type' => 'owner',
            'boundary_coordinates' => json_encode($outsideOutline),
        ])->assertRedirect('/farmer/farms')->assertSessionHasErrors('boundary_coordinates');

        $crossingOutline = [
            ['latitude' => 12.7460, 'longitude' => 121.4870],
            ['latitude' => 12.7465, 'longitude' => 121.4875],
            ['latitude' => 12.7460, 'longitude' => 121.4875],
            ['latitude' => 12.7465, 'longitude' => 121.4870],
        ];
        $this->assertFalse(app(BongabongGeography::class)->containsFarmOutline($crossingOutline));

        $this->assertDatabaseMissing('farms', ['farmer_id' => $farmer->id, 'name' => 'Outside Farm']);
        $this->assertDatabaseMissing('farms', ['farmer_id' => $farmer->id, 'name' => 'Outside Outline Farm']);
    }

    public function test_public_farmer_registration_only_accepts_a_bongabong_barangay(): void
    {
        Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);

        $this->from('/register')->post('/register', [
            'name' => 'Outside Municipality', 'email' => 'outside-town@example.test',
            'password' => 'secure-farmer-password', 'password_confirmation' => 'secure-farmer-password',
            'barangay' => 'Calapan City',
        ])->assertRedirect('/register')->assertSessionHasErrors('barangay');

        $this->assertDatabaseMissing('users', ['email' => 'outside-town@example.test']);
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
