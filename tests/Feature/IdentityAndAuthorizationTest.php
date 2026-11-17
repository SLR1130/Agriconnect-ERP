<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdentityAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug, string $name, array $permissions = [], string $password = 'test-password'): User
    {
        $role = Role::create([
            'name' => $name,
            'slug' => $slug,
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'name' => $name,
            'password' => Hash::make($password),
        ]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_shared_layout_uses_the_current_user_identity_and_role(): void
    {
        $mao = $this->account('mao_admin', 'MAO Administrator', ['dashboard.view', 'reports.generate']);

        $this->actingAs($mao)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('MAO Administrator')
            ->assertSee('MAO Administrator', false)
            ->assertDontSee('Super Admin');
    }

    public function test_non_super_admin_cannot_open_or_see_super_admin_navigation(): void
    {
        $mao = $this->account('mao_admin', 'MAO Administrator', ['dashboard.view', 'reports.generate']);

        $this->actingAs($mao)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('User management')
            ->assertDontSee('Roles & permissions');

        foreach (['/erp/users', '/erp/roles', '/erp/modules', '/erp/settings'] as $path) {
            $this->actingAs($mao)->get($path)->assertForbidden();
        }
    }

    public function test_farmer_cannot_open_staff_overview_or_administration(): void
    {
        $farmer = $this->account('farmer', 'Verified Farmer', ['farmer.profile.manage']);
        Farmer::create([
            'user_id' => $farmer->id,
            'farmer_code' => 'FMR-TEST-001',
            'registration_status' => 'verified',
        ]);

        $this->actingAs($farmer)->get('/dashboard')->assertForbidden();
        $this->actingAs($farmer)->get('/erp/users')->assertForbidden();
        $this->actingAs($farmer)->get('/modules/aisms')->assertForbidden();
    }

    public function test_super_admin_can_open_administration_and_sees_identity(): void
    {
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Super Administrator')
            ->assertSee('User management');

        $this->actingAs($admin)->get('/erp/users')->assertOk();
        $this->actingAs($admin)->get('/erp/roles')->assertOk();
        $this->actingAs($admin)->get('/erp/settings')->assertOk();
    }

    public function test_module_personnel_only_reaches_an_explicitly_assigned_module(): void
    {
        $personnel = $this->account('module_personnel', 'Inventory Personnel', ['modules.aisms.view']);

        $this->actingAs($personnel)->get('/modules/aisms')->assertOk();
        $this->actingAs($personnel)->get('/modules/ffrms')->assertForbidden();
    }

    public function test_auditor_can_read_ffrms_but_cannot_execute_write_operations(): void
    {
        $auditor = $this->account('authorized_viewer', 'Authorized Auditor', ['modules.*.view', 'reports.view']);

        $this->actingAs($auditor)->get('/modules/ffrms')->assertOk();
        $this->actingAs($auditor)->post('/modules/ffrms/associations', [
            'name' => 'Unauthorized Association',
        ])->assertForbidden();
    }

    public function test_logout_then_login_switches_the_displayed_identity(): void
    {
        $mao = $this->account('mao_admin', 'MAO Administrator', ['dashboard.view'], 'mao-password');
        $admin = $this->account('super_admin', 'Super Administrator', ['*'], 'admin-password');

        $this->post('/login', ['email' => $mao->email, 'password' => 'mao-password'])->assertRedirect('/mao/dashboard');
        $this->get('/dashboard')->assertSee('MAO Administrator')->assertDontSee('Super Admin');
        $this->post('/logout')->assertRedirect('/login');

        $this->post('/login', ['email' => $admin->email, 'password' => 'admin-password'])->assertRedirect('/admin/dashboard');
        $this->get('/dashboard')->assertSee('Super Administrator')->assertDontSee('MAO Administrator');
    }
}
