<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\WorkflowRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ErpCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_reports_runtime_status_instead_of_static_pending_text(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Empowering Agriculture Through Connected Digital Services')
            ->assertSee('Agricultural Services')
            ->assertDontSee('Database Setup');
    }

    public function test_setup_diagnostics_are_limited_to_authorized_administrators(): void
    {
        $this->get('/setup-test')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/setup-test')->assertForbidden();

        $role = Role::create([
            'name' => 'Test Super Administrator',
            'slug' => 'super_admin',
            'permissions' => [],
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/setup-test')
            ->assertOk()
            ->assertSee('Authorized diagnostics');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_dashboard_reads_the_database(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Dashboard Viewer',
            'slug' => 'dashboard_viewer',
            'permissions' => ['dashboard.view'],
            'is_active' => true,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('AgriConnect ERP')
            ->assertSee('Live records from')
            ->assertSee('Isolated demonstration database')
            ->assertDontSee(':memory:');
    }

    public function test_database_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_workflow_requests_have_a_shared_core_identifier(): void
    {
        $user = User::factory()->create();
        $request = WorkflowRequest::create([
            'requester_id' => $user->id,
            'type' => 'assistance_application',
            'title' => 'Seed request',
        ]);

        $this->assertNotEmpty($request->reference);
        $this->assertDatabaseHas('workflow_requests', [
            'id' => $request->id,
            'status' => 'pending',
        ]);
    }
}
