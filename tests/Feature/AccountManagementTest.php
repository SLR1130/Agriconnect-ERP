<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Module, Role, User, UserModuleAccess};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug, string $name = null, array $permissions = []): User
    {
        $role = Role::create([
            'name' => $name ?: ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['name' => $name ?: ucwords(str_replace('_', ' ', $slug))]);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function modules(): array
    {
        $modules = [];
        foreach (['ffrms', 'apms', 'aisms', 'aasms', 'aeams', 'abpms'] as $slug) {
            $modules[$slug] = Module::create([
                'name' => strtoupper($slug),
                'slug' => $slug,
                'route_prefix' => 'modules/'.$slug,
                'is_active' => true,
                'is_enabled' => true,
            ]);
        }

        return $modules;
    }

    public function test_super_admin_creates_inactive_staff_account_with_one_time_password_setup(): void
    {
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        Role::create(['name' => 'Module Personnel', 'slug' => 'module_personnel', 'permissions' => ['dashboard.view'], 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/erp/users', [
            'name' => 'Inventory Delegate',
            'email' => 'delegate@example.test',
            'role' => 'module_personnel',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('setup_url');
        $user = User::where('email', 'delegate@example.test')->firstOrFail();
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->hasRole('module_personnel'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        $setupUrl = $response->getSession()->get('setup_url');
        parse_str((string) parse_url($setupUrl, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url($setupUrl, PHP_URL_PATH));

        $this->post('/account/setup', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'secure-delegate-password',
            'password_confirmation' => 'secure-delegate-password',
        ])->assertRedirect('/login');

        $this->assertTrue(User::findOrFail($user->id)->is_active);
        $this->assertTrue(Hash::check('secure-delegate-password', User::findOrFail($user->id)->password));
        $this->assertDatabaseHas('activity_logs', ['action_target' => (string) $user->id]);
        $this->assertNotEmpty($query['email'] ?? null);
    }

    public function test_duplicate_email_and_non_super_admin_account_mutations_are_rejected(): void
    {
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        $mao = $this->account('mao_admin', 'MAO Administrator', ['dashboard.view']);
        $existing = User::factory()->create(['email' => 'existing@example.test']);

        $this->actingAs($admin)->post('/erp/users', [
            'name' => 'Duplicate',
            'email' => $existing->email,
            'role' => 'module_personnel',
        ])->assertSessionHasErrors('email');

        $this->actingAs($mao)->get('/erp/users/create')->assertForbidden();
        $this->actingAs($mao)->post('/erp/users', [
            'name' => 'Blocked',
            'email' => 'blocked@example.test',
            'role' => 'module_personnel',
        ])->assertForbidden();
        $this->actingAs($mao)->patch('/erp/users/'.$existing->id.'/status', ['is_active' => false])->assertForbidden();
    }

    public function test_setup_tokens_are_single_use_and_expiring(): void
    {
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        Role::create(['name' => 'Module Personnel', 'slug' => 'module_personnel', 'permissions' => ['dashboard.view'], 'is_active' => true]);

        $create = $this->actingAs($admin)->post('/erp/users', [
            'name' => 'Token Delegate',
            'email' => 'token-delegate@example.test',
            'role' => 'module_personnel',
        ])->assertRedirect();

        $user = User::where('email', 'token-delegate@example.test')->firstOrFail();
        $setupUrl = (string) $create->getSession()->get('setup_url');
        $token = basename((string) parse_url($setupUrl, PHP_URL_PATH));
        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'secure-token-password',
            'password_confirmation' => 'secure-token-password',
        ];

        $this->post('/account/setup', $payload)->assertRedirect('/login');
        $this->post('/account/setup', $payload)->assertSessionHasErrors('email');
        $this->post('/account/setup', [
            ...$payload,
            'token' => 'not-a-real-token',
            'email' => 'token-delegate@example.test',
        ])->assertSessionHasErrors('email');

        $expired = User::factory()->create(['email' => 'expired-delegate@example.test', 'is_active' => false]);
        $expiredToken = Password::broker()->createToken($expired);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $expired->email]);
        $this->app['db']->table('password_reset_tokens')
            ->where('email', $expired->email)
            ->update(['created_at' => now()->subMinutes((int) config('auth.passwords.users.expire', 60) + 1)]);

        $this->post('/account/setup', [
            'token' => $expiredToken,
            'email' => $expired->email,
            'password' => 'secure-expired-password',
            'password_confirmation' => 'secure-expired-password',
        ])->assertSessionHasErrors('email');
        $this->assertFalse($expired->fresh()->is_active);

    }

    public function test_account_module_assignments_are_granular_and_effective(): void
    {
        $modules = $this->modules();
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        $personnel = $this->account('module_personnel', 'Module Personnel', ['dashboard.view']);

        $this->actingAs($admin)->put('/erp/users/'.$personnel->id.'/module-access', [
            'assignments' => [
                'aisms' => ['view', 'receive', 'release'],
                'apms' => ['view', 'create'],
            ],
        ])->assertRedirect();

        $personnel->refresh();
        $this->assertDatabaseHas('user_module_access', ['user_id' => $personnel->id, 'module_id' => $modules['aisms']->id]);
        $this->assertTrue($personnel->canModuleAction('aisms', 'receive'));
        $this->assertTrue($personnel->canModuleAction('apms', 'create'));
        $this->assertFalse($personnel->canModuleAction('ffrms', 'view'));
        $this->assertFalse($personnel->canModuleAction('apms', 'approve'));
        $this->actingAs($personnel)->get('/modules/apms')->assertOk();

        $this->actingAs($admin)->put('/erp/users/'.$personnel->id.'/module-access', [
            'assignments' => [],
        ])->assertRedirect();

        $personnel->refresh();
        $this->assertDatabaseHas('user_module_access', ['user_id' => $personnel->id, 'permissions' => '[]']);
        $this->assertFalse($personnel->canModuleAction('apms', 'create'));
        $this->actingAs($personnel)->get('/modules/apms')->assertForbidden();
    }

    public function test_explicit_module_personnel_assignments_override_legacy_aisms_defaults(): void
    {
        $modules = $this->modules();
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        $role = Role::create([
            'name' => 'Legacy Module Personnel',
            'slug' => 'module_personnel',
            'permissions' => ['dashboard.view', 'modules.aisms.view', 'modules.aisms.receive'],
            'is_active' => true,
        ]);
        $personnel = User::factory()->create(['name' => 'Legacy Delegate']);
        $personnel->roles()->attach($role);

        $this->assertTrue($personnel->canModuleAction('aisms', 'view'));

        $this->actingAs($admin)->put('/erp/users/'.$personnel->id.'/module-access', [
            'assignments' => ['aisms' => ['view']],
        ])->assertRedirect();

        $personnel->refresh();
        $this->assertTrue($personnel->canModuleAction('aisms', 'view'));
        $this->assertFalse($personnel->canModuleAction('aisms', 'receive'));

        $this->actingAs($admin)->put('/erp/users/'.$personnel->id.'/module-access', [
            'assignments' => [],
        ])->assertRedirect();

        $personnel->refresh();
        $this->assertFalse($personnel->canModuleAction('aisms', 'view'));
        $this->assertFalse($personnel->canModuleAction('aisms', 'receive'));
        $this->assertDatabaseHas('user_module_access', ['user_id' => $personnel->id, 'module_id' => $modules['aisms']->id, 'permissions' => '[]']);
    }

    public function test_reserved_approval_actions_and_finance_escalation_are_rejected(): void
    {
        $modules = $this->modules();
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        $personnel = $this->account('module_personnel', 'Module Personnel', ['dashboard.view']);
        $municipal = $this->account('municipal_agriculturist', 'Municipal Agriculturist', ['dashboard.view', 'modules.abpms.approve']);

        $this->actingAs($admin)->put('/erp/users/'.$personnel->id.'/module-access', [
            'assignments' => ['abpms' => ['view', 'approve']],
        ])->assertSessionHasErrors('assignments');
        $this->assertDatabaseCount('user_module_access', 0);
        $this->assertFalse($personnel->canModuleAction('abpms', 'approve'));
        $this->assertFalse($municipal->canModuleAction('abpms', 'approve'));
        $this->assertNotNull($modules['abpms']->id);
    }

    public function test_role_change_clears_delegated_assignments_and_writes_audit_record(): void
    {
        $modules = $this->modules();
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        $personnel = $this->account('module_personnel', 'Module Personnel', ['dashboard.view']);
        Role::create(['name' => 'Authorized Viewer', 'slug' => 'authorized_viewer', 'permissions' => ['dashboard.view'], 'is_active' => true]);
        UserModuleAccess::create(['user_id' => $personnel->id, 'module_id' => $modules['apms']->id, 'permissions' => ['view']]);

        $this->actingAs($admin)->put('/erp/users/'.$personnel->id.'/role', ['role' => 'authorized_viewer'])->assertRedirect();

        $personnel->refresh();
        $this->assertTrue($personnel->hasRole('authorized_viewer'));
        $this->assertDatabaseCount('user_module_access', 0);
        $this->assertFalse($personnel->canModuleAction('apms', 'view'));
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'updated', 'action_target' => (string) $personnel->id]);
    }

    public function test_last_active_super_admin_cannot_be_deactivated(): void
    {
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);

        $this->actingAs($admin)->patch('/erp/users/'.$admin->id.'/status', ['is_active' => false])->assertSessionHasErrors('is_active');
        $this->assertTrue(User::findOrFail($admin->id)->is_active);
    }

    public function test_super_admin_can_deactivate_and_reactivate_staff_role_policy_but_not_super_admin_policy(): void
    {
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        $personnel = $this->account('module_personnel', 'Module Personnel', ['dashboard.view']);
        $role = $personnel->roles()->firstOrFail();

        $this->actingAs($admin)->patch('/erp/roles/'.$role->id.'/status', ['is_active' => false])->assertRedirect();
        $role->refresh();
        $personnel->refresh();
        $this->assertFalse($role->is_active);
        $this->assertFalse($personnel->canErp('dashboard.view'));
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'deactivated', 'action_target' => 'role:'.$role->id]);

        $this->actingAs($admin)->patch('/erp/roles/'.$role->id.'/status', ['is_active' => true])->assertRedirect();
        $role->refresh();
        $personnel->refresh();
        $this->assertTrue($role->is_active);
        $this->assertTrue($personnel->canErp('dashboard.view'));

        $superRole = $admin->roles()->firstOrFail();
        $this->actingAs($admin)->patch('/erp/roles/'.$superRole->id.'/status', ['is_active' => false])->assertSessionHasErrors('is_active');
        $this->assertTrue(Role::findOrFail($superRole->id)->is_active);
    }

    public function test_inactive_account_and_inactive_role_remain_denied(): void
    {
        $modules = $this->modules();
        $personnel = $this->account('module_personnel', 'Module Personnel', ['dashboard.view']);
        UserModuleAccess::create(['user_id' => $personnel->id, 'module_id' => $modules['aisms']->id, 'permissions' => ['view']]);
        $personnel->update(['is_active' => false, 'suspended_at' => now()]);

        $this->assertFalse($personnel->canModuleAction('aisms', 'view'));
        $this->actingAs($personnel)->get('/modules/aisms')->assertRedirect('/login')->assertSessionHasErrors('email');

        $role = $personnel->roles()->firstOrFail();
        $role->update(['is_active' => false]);
        $personnel->update(['is_active' => true, 'suspended_at' => null]);
        $this->assertFalse($personnel->canErp('dashboard.view'));
    }

    public function test_super_admin_can_update_profile_without_touching_password(): void
    {
        $admin = $this->account('super_admin', 'Super Administrator', ['*']);
        $target = $this->account('module_personnel', 'Old Name', ['dashboard.view']);
        $target->update(['password' => Hash::make('original-password')]);

        $this->actingAs($admin)->put('/erp/users/'.$target->id, [
            'name' => 'Updated Name',
            'email' => 'updated@example.test',
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('updated@example.test', $target->email);
        $this->assertTrue(Hash::check('original-password', $target->password));
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'updated', 'action_target' => (string) $target->id]);
        $this->actingAs($admin)->get('/erp/users/'.$target->id)
            ->assertOk()
            ->assertSee('Account updated@example.test profile updated.');
    }
}
