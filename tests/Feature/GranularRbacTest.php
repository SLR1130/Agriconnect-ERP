<?php

namespace Tests\Feature;

use App\Models\{AssistanceApplication, AssistanceProgram, Farmer, Module, ModuleAccess, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GranularRbacTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug, array $permissions = []): User
    {
        $role = Role::create([
            'name' => str_replace('_', ' ', ucfirst($slug)),
            'slug' => $slug,
            'permissions' => $permissions,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function assignModules(Role $role, array $permissions = ['view']): void
    {
        foreach (['ffrms', 'apms', 'aisms', 'aasms', 'aeams', 'abpms'] as $slug) {
            $module = Module::create([
                'name' => strtoupper($slug),
                'slug' => $slug,
                'route_prefix' => 'modules/'.$slug,
                'is_active' => true,
                'is_enabled' => true,
            ]);

            ModuleAccess::create([
                'role_id' => $role->id,
                'module_id' => $module->id,
                'permissions' => $permissions,
            ]);
        }
    }

    public function test_effective_policy_distinguishes_mao_admin_from_municipal_agriculturist(): void
    {
        $mao = $this->account('mao_admin', [
            'dashboard.view',
            'modules.aasms.view',
            'modules.aasms.approve',
            'modules.abpms.view',
            'modules.abpms.approve',
        ]);
        $municipal = $this->account('municipal_agriculturist', [
            'dashboard.view',
            'modules.aasms.view',
            'modules.aasms.approve',
            'modules.apms.create',
        ]);

        $this->assertTrue($mao->canModuleAction('aasms', 'approve'));
        $this->assertTrue($mao->canModuleAction('abpms', 'approve'));
        $this->assertFalse($municipal->canModuleAction('aasms', 'approve'));
        $this->assertFalse($municipal->canModuleAction('abpms', 'approve'));
        $this->assertTrue($municipal->canModuleAction('apms', 'create'));
        $this->assertFalse($mao->isSuperAdministrator());
    }

    public function test_legacy_operational_role_with_module_assignment_can_open_shared_staff_dashboard(): void
    {
        $municipal = $this->account('municipal_agriculturist', ['modules.ffrms.*']);
        $this->assignModules($municipal->roles()->firstOrFail(), ['view']);

        $this->assertTrue($municipal->canErp('dashboard.view'));
        $this->actingAs($municipal)->get('/mao/dashboard')->assertOk();
    }

    public function test_module_personnel_scope_ignores_stale_generic_wildcards_outside_explicit_module(): void
    {
        $personnel = $this->account('module_personnel', [
            'dashboard.view',
            'modules.*.view',
            'modules.*.create',
            'modules.*.update',
            'modules.aisms.receive',
            'modules.aisms.release',
            'modules.aisms.adjust',
        ]);
        $role = $personnel->roles()->firstOrFail();
        $this->assignModules($role, ['view', 'create', 'update']);

        $this->assertTrue($personnel->canModuleAction('aisms', 'view'));
        $this->assertTrue($personnel->canModuleAction('aisms', 'receive'));
        $this->assertFalse($personnel->canModuleAction('ffrms', 'view'));
        $this->assertFalse($personnel->canModuleAction('abpms', 'view'));
    }

    public function test_auditor_can_view_but_cannot_write_or_approve(): void
    {
        $auditor = $this->account('authorized_viewer', ['dashboard.view', 'modules.*.view', 'reports.view']);
        $this->assignModules($auditor->roles()->firstOrFail(), ['view']);

        $this->assertTrue($auditor->canModuleAction('aisms', 'view'));
        $this->assertTrue($auditor->canModuleAction('aisms', 'export'));
        $this->assertFalse($auditor->canModuleAction('aisms', 'receive'));
        $this->assertFalse($auditor->canModuleAction('aasms', 'approve'));

        $this->actingAs($auditor)
            ->get('/modules/aisms')
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertDontSee('Post receipt');
    }

    public function test_super_admin_only_administration_routes_are_not_granted_by_operational_permissions(): void
    {
        $mao = $this->account('mao_admin', ['dashboard.view', 'modules.view', 'reports.generate']);
        $admin = $this->account('super_admin', ['*']);

        foreach (['/erp/users', '/erp/roles', '/erp/modules', '/erp/settings'] as $path) {
            $this->actingAs($mao)->get($path)->assertForbidden();
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_inactive_super_admin_role_cannot_open_administration(): void
    {
        $admin = $this->account('super_admin', ['*']);
        $admin->roles()->firstOrFail()->update(['is_active' => false]);
        $admin->refresh();

        $this->assertFalse($admin->isSuperAdministrator());
        $this->assertFalse($admin->canErp('dashboard.view'));
        $this->actingAs($admin)->get('/erp/users')->assertForbidden();
    }

    public function test_super_admin_account_inspector_distinguishes_effective_access_from_legacy_assignments(): void
    {
        $personnel = $this->account('module_personnel', [
            'dashboard.view',
            'modules.*.view',
            'modules.*.create',
            'modules.*.update',
            'modules.aisms.receive',
            'modules.aisms.release',
            'modules.aisms.adjust',
        ]);
        $this->assignModules($personnel->roles()->firstOrFail(), ['view', 'create', 'update']);
        $admin = $this->account('super_admin', ['*']);

        $this->actingAs($admin)->get('/erp/users')
            ->assertOk()
            ->assertSee('Effective agricultural permissions')
            ->assertSee('stored role and module assignments (reference only)')
            ->assertSee('data-effective-account-id="'.$personnel->id.'" data-effective-module="aisms"', false)
            ->assertDontSee('data-effective-account-id="'.$personnel->id.'" data-effective-module="ffrms"', false);

        $this->actingAs($admin)->get('/erp/roles')
            ->assertOk()
            ->assertSee('Effective grants')
            ->assertSee('stored database grants (legacy/reference only)')
            ->assertSee('data-effective-role-id="'.$personnel->roles()->firstOrFail()->id.'" data-effective-module="aisms"', false)
            ->assertDontSee('data-effective-role-id="'.$personnel->roles()->firstOrFail()->id.'" data-effective-module="ffrms"', false);
    }

    public function test_role_aware_dashboards_and_sidebar_expose_only_assigned_modules(): void
    {
        $municipal = $this->account('municipal_agriculturist', [
            'dashboard.view',
            'modules.ffrms.view',
            'modules.ffrms.review',
            'modules.apms.view',
            'modules.apms.create',
            'modules.aasms.view',
            'modules.aasms.review',
            'reports.view',
            'farmer.verify',
        ]);
        $personnel = $this->account('module_personnel', [
            'dashboard.view',
            'modules.aisms.view',
            'modules.aisms.receive',
        ]);

        $this->actingAs($municipal)->get('/mao/dashboard')
            ->assertOk()
            ->assertSee('Farmer &amp; Farm Registration', false)
            ->assertSee('Production &amp; Monitoring', false)
            ->assertSee('Assistance &amp; Subsidies', false)
            ->assertDontSee('Inventory &amp; Supply', false)
            ->assertDontSee('Budget &amp; Procurement', false)
            ->assertDontSee('User management');

        $this->actingAs($personnel)->get('/mao/dashboard')
            ->assertOk()
            ->assertSee('Inventory &amp; Supply', false)
            ->assertDontSee('Farmer &amp; Farm Registration', false)
            ->assertDontSee('Budget &amp; Procurement', false);
    }

    public function test_municipal_agriculturist_cannot_approve_an_assistance_application(): void
    {
        $municipal = $this->account('municipal_agriculturist', [
            'dashboard.view',
            'modules.aasms.view',
            'modules.aasms.approve',
        ]);
        $farmerUser = $this->account('farmer', ['farmer.profile.manage']);
        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'farmer_code' => 'F-RBAC-001',
            'registration_status' => 'verified',
        ]);
        $program = AssistanceProgram::create(['name' => 'RBAC Program', 'status' => 'open']);
        $application = AssistanceApplication::create([
            'program_id' => $program->id,
            'farmer_id' => $farmer->id,
            'status' => 'submitted',
        ]);

        $this->actingAs($municipal)
            ->post('/modules/aasms/applications/'.$application->id.'/review', ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('assistance_applications', [
            'id' => $application->id,
            'status' => 'submitted',
        ]);
    }
}
