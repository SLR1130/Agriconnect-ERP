<?php

namespace Tests\Feature;

use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceProgram;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicWebsiteExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_only_open_programs_and_current_public_announcements(): void
    {
        $publisher = User::factory()->create();
        AssistanceProgram::query()->create([
            'name' => 'QA Open Rice Support',
            'category' => 'Crop support',
            'description' => 'A published program available for its stated schedule.',
            'status' => 'open',
            'opens_on' => null,
            'closes_on' => null,
            'eligibility_rules' => ['requires_verified_farmer' => true],
        ]);
        AssistanceProgram::query()->create([
            'name' => 'QA Unpublished Program Must Stay Hidden',
            'category' => 'Draft category',
            'description' => 'Internal draft.',
            'status' => 'draft',
            'eligibility_rules' => [],
        ]);

        AgriculturalAnnouncement::query()->create([
            'title' => 'QA Published Agricultural Notice',
            'body' => 'Official public information for farmers.',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'expires_at' => null,
            'office' => 'Bongabong Municipal Agriculture Office',
            'created_by' => $publisher->id,
        ]);
        AgriculturalAnnouncement::query()->create([
            'title' => 'QA Draft Notice Must Stay Hidden',
            'body' => 'This draft is not public.',
            'status' => 'draft',
            'published_at' => null,
            'expires_at' => null,
            'created_by' => $publisher->id,
        ]);
        AgriculturalAnnouncement::query()->create([
            'title' => 'QA Expired Notice Must Stay Hidden',
            'body' => 'This notice has expired.',
            'status' => 'published',
            'published_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
            'created_by' => $publisher->id,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Serbisyong pang-agrikultura, mas malinaw at mas madaling maabot.')
            ->assertSee('Find a program')
            ->assertSee('Register as a farmer')
            ->assertSee('What do you need help with?')
            ->assertSee('Assistance and subsidies')
            ->assertSee('Production services')
            ->assertSee('Equipment services')
            ->assertSee('Farm records and activities')
            ->assertSee('Add your farm')
            ->assertSee('place its location pin in My Farms')
            ->assertSee('data-home-reveal', false)
            ->assertSee('<svg', false)
            ->assertSee('QA Open Rice Support')
            ->assertSee('Applications open')
            ->assertSee('QA Published Agricultural Notice')
            ->assertDontSee('QA Unpublished Program Must Stay Hidden')
            ->assertDontSee('QA Draft Notice Must Stay Hidden')
            ->assertDontSee('QA Expired Notice Must Stay Hidden')
            ->assertSee(route('farmer.register'), false)
            ->assertSee(route('login'), false)
            ->assertSee('CC BY-SA 4.0');
    }

    public function test_homepage_has_clear_empty_states_when_no_public_records_exist(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('No programs to feature right now')
            ->assertSee('No new public notices')
            ->assertDontSee('There are no records in the database');
    }

    public function test_login_and_registration_pages_render_the_existing_csrf_protected_contracts(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('data-password-toggle', false);

        $this->get(route('farmer.register'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('action="'.route('farmer.register.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="address"', false)
            ->assertSee('name="barangay"', false)
            ->assertSee('name="farm_name"', false)
            ->assertSee('name="farm_barangay"', false)
            ->assertSee('data-farm-location-details', false)
            ->assertSee('data-farm-location-barangay', false)
            ->assertSee('data-farm-location-search', false)
            ->assertSee('Search runs only when you press this button.')
            ->assertSee('name="latitude"', false)
            ->assertSee('name="longitude"', false)
            ->assertSee('data-farm-map', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('Create farmer account');
    }

    public function test_existing_farmer_login_redirect_and_registration_post_remain_functional(): void
    {
        $farmerRole = Role::query()->firstOrCreate(['slug' => 'farmer'], [
            'name' => 'Farmer',
            'permissions' => [],
            'is_active' => true,
            'is_default' => true,
        ]);
        $existingUser = User::factory()->create([
            'email' => 'public-login-qa@example.test',
            'password' => Hash::make('qa-login-password-123'),
        ]);
        $existingUser->roles()->attach($farmerRole);
        Farmer::query()->create([
            'user_id' => $existingUser->id,
            'farmer_code' => 'WEB-QA-'.$existingUser->id,
            'registration_status' => 'verified',
        ]);

        $this->post(route('login.store'), [
            'email' => 'public-login-qa@example.test',
            'password' => 'qa-login-password-123',
        ])->assertRedirect(route('farmer.portal'));

        auth()->logout();
        $this->post(route('farmer.register.store'), [
            'name' => 'Public QA Farmer',
            'email' => 'public-registration-qa@example.test',
            'phone' => '09170000000',
            'address' => 'QA Sitio',
            'barangay' => 'Poblacion',
            'farm_name' => 'Public QA Farm',
            'farm_barangay' => 'Formon',
            'farm_location_details' => 'QA valley road',
            'latitude' => '12.7464200',
            'longitude' => '121.4874100',
            'location_source' => 'manual',
            'password' => 'qa-registration-password-123',
            'password_confirmation' => 'qa-registration-password-123',
        ])->assertRedirect(route('farmer.farms'))
            ->assertSessionHas('status');

        $registered = User::query()->where('email', 'public-registration-qa@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('qa-registration-password-123', $registered->password));
        $this->assertTrue($registered->hasRole('farmer'));
        $this->assertDatabaseHas('farmers', [
            'user_id' => $registered->id,
            'barangay' => 'Poblacion',
            'registration_status' => 'pending',
        ]);
        $farm = $registered->farmer->farms()->firstOrFail();
        $this->assertSame('Public QA Farm', $farm->name);
        $this->assertSame('Formon', $farm->barangay);
        $this->assertEquals(12.74642, (float) $farm->latitude);
        $this->assertEquals(121.48741, (float) $farm->longitude);
    }

    public function test_registration_validation_returns_errors_without_creating_an_account(): void
    {
        $response = $this->followingRedirects()->from(route('farmer.register'))->post(route('farmer.register.store'), [
            'name' => 'Invalid QA Registration',
            'email' => 'invalid-public-registration@example.test',
            'barangay' => '',
            'password' => 'too-short',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertOk()
            ->assertSee('Please correct the marked information.');
        $this->assertDatabaseMissing('users', ['email' => 'invalid-public-registration@example.test']);

        $response
            ->assertSee('data-registration-wizard', false)
            ->assertSee('data-initial-step="1"', false)
            ->assertSee('href="#barangay"', false)
            ->assertSee('href="#farm_name"', false)
            ->assertSee('value="Invalid QA Registration"', false);
    }

    public function test_farmer_registration_includes_the_leaflet_map_and_first_farm_fields(): void
    {
        $this->get(route('farmer.register'))
            ->assertOk()
            ->assertSee('data-registration-wizard', false)
            ->assertSee('data-registration-panel="1"', false)
            ->assertSee('data-registration-panel="2"', false)
            ->assertSee('data-registration-panel="3"', false)
            ->assertSee('data-registration-indicator="1"', false)
            ->assertSee('data-registration-indicator="2"', false)
            ->assertSee('data-registration-indicator="3"', false)
            ->assertSee('data-registration-next', false)
            ->assertSee('data-registration-back', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="barangay"', false)
            ->assertSee('name="address"', false)
            ->assertSee('name="farm_name"', false)
            ->assertSee('name="farm_barangay"', false)
            ->assertSee('name="latitude"', false)
            ->assertSee('name="longitude"', false)
            ->assertSee('data-farm-map', false)
            ->assertSee('data-farm-location', false)
            ->assertSee('Place farm pin')
            ->assertSee('Use my current location')
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('The pin is a location reference, not a land survey.')
            ->assertDontSee('Your farm map pin comes after account setup.')
            ->assertDontSee('Submit production reports')
            ->assertDontSee('public-registration-steps', false);
    }

    public function test_registration_rejects_a_farm_pin_outside_bongabong_without_creating_an_account(): void
    {
        $this->from(route('farmer.register'))->post(route('farmer.register.store'), [
            'name' => 'Outside Boundary Farmer',
            'email' => 'outside-boundary@example.test',
            'phone' => '09170000000',
            'address' => 'Poblacion',
            'barangay' => 'Poblacion',
            'farm_name' => 'Outside Farm',
            'farm_barangay' => 'Poblacion',
            'latitude' => '12.3450000',
            'longitude' => '121.2340000',
            'location_source' => 'manual',
            'password' => 'qa-registration-password-123',
            'password_confirmation' => 'qa-registration-password-123',
        ])->assertRedirect(route('farmer.register'))->assertSessionHasErrors('latitude');

        $this->assertDatabaseMissing('users', ['email' => 'outside-boundary@example.test']);
        $this->assertDatabaseMissing('farms', ['name' => 'Outside Farm']);
    }
}
