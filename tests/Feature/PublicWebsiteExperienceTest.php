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
            'barangay' => 'QA Barangay',
            'password' => 'qa-registration-password-123',
            'password_confirmation' => 'qa-registration-password-123',
        ])->assertRedirect(route('farmer.portal'))
            ->assertSessionHas('status');

        $registered = User::query()->where('email', 'public-registration-qa@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('qa-registration-password-123', $registered->password));
        $this->assertTrue($registered->hasRole('farmer'));
        $this->assertDatabaseHas('farmers', [
            'user_id' => $registered->id,
            'barangay' => 'QA Barangay',
            'registration_status' => 'pending',
        ]);
    }

    public function test_registration_validation_returns_errors_without_creating_an_account(): void
    {
        $response = $this->from(route('farmer.register'))->post(route('farmer.register.store'), [
            'name' => 'Invalid QA Registration',
            'email' => 'invalid-public-registration@example.test',
            'barangay' => '',
            'password' => 'too-short',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertRedirect(route('farmer.register'))
            ->assertSessionHasErrors(['barangay', 'password']);
        $this->assertDatabaseMissing('users', ['email' => 'invalid-public-registration@example.test']);
    }
}
