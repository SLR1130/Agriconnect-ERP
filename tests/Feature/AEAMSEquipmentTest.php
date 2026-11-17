<?php

namespace Tests\Feature;

use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentRequest;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AEAMSEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        $staffRole = Role::create([
            'name' => 'AEAMS Staff',
            'slug' => 'aeams_staff',
            'permissions' => ['modules.aeams.*'],
            'is_active' => true,
        ]);
        $staff = User::factory()->create();
        $staff->roles()->attach($staffRole);

        $farmerRole = Role::create([
            'name' => 'Farmer',
            'slug' => 'farmer',
            'permissions' => ['farmer.equipment.request'],
            'is_active' => true,
        ]);
        $farmerUser = User::factory()->create();
        $farmerUser->roles()->attach($farmerRole);
        $farmer = Farmer::create([
            'user_id' => $farmerUser->id,
            'farmer_code' => 'F-'.fake()->unique()->numerify('####'),
            'registration_status' => 'verified',
            'verified_at' => now(),
        ]);

        $category = EquipmentCategory::firstOrCreate(['name' => 'Farm Machinery'], ['is_active' => true]);
        $asset = EquipmentAsset::create([
            'asset_code' => 'TRACTOR-001',
            'name' => 'Farm tractor',
            'category_id' => $category->id,
            'condition' => 'good',
            'availability_status' => 'available',
            'assigned_location' => 'Municipal warehouse',
        ]);

        return compact('staff', 'farmerUser', 'farmer', 'category', 'asset');
    }

    private function window(int $startDays = 2, int $endDays = 3): array
    {
        return [
            'schedule_start' => now()->addDays($startDays)->startOfDay()->format('Y-m-d H:i:s'),
            'schedule_end' => now()->addDays($endDays)->startOfDay()->format('Y-m-d H:i:s'),
        ];
    }

    private function submitRequest(array $data, User $user, EquipmentAsset $asset, string $key = null)
    {
        return $this->actingAs($user)->post('/modules/aeams/requests', [
            'equipment_asset_id' => $asset->id,
            'intended_use' => 'Land preparation for the next planting cycle.',
            ...$data,
            ...($key ? ['idempotency_key' => $key] : []),
        ]);
    }

    public function test_staff_can_register_equipment_and_dashboard_uses_real_catalog(): void
    {
        $data = $this->setupData();

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/equipment', [
                'asset_code' => 'PUMP-002',
                'name' => 'Irrigation pump',
                'category_id' => $data['category']->id,
                'condition' => 'good',
                'assigned_location' => 'Equipment shed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment_assets', [
            'asset_code' => 'PUMP-002',
            'name' => 'Irrigation pump',
            'availability_status' => 'available',
        ]);
        $this->actingAs($data['staff'])
            ->get('/modules/aeams')
            ->assertOk()
            ->assertSee('Equipment catalog')
            ->assertSee('PUMP-002');
    }

    public function test_only_verified_farmer_can_submit_owner_scoped_request(): void
    {
        $data = $this->setupData();

        $this->submitRequest($this->window(), $data['farmerUser'], $data['asset'], 'request-owner')
            ->assertRedirect();

        $this->assertDatabaseHas('equipment_requests', [
            'farmer_id' => $data['farmer']->id,
            'requested_by' => $data['farmerUser']->id,
            'idempotency_key' => 'request-owner',
        ]);
        $request = EquipmentRequest::where('idempotency_key', 'request-owner')->firstOrFail();
        $this->actingAs($data['farmerUser'])
            ->get('/modules/aeams/equipment/'.$data['asset']->id)
            ->assertOk()
            ->assertSee($data['asset']->name)
            ->assertSee($request->reference);
        $this->actingAs($data['farmerUser'])
            ->post('/modules/aeams/requests/'.$request->id.'/cancel')
            ->assertRedirect();
        $this->assertDatabaseHas('equipment_requests', ['id' => $request->id, 'status' => 'cancelled']);

        $unverifiedUser = User::factory()->create();
        $unverifiedRole = Role::create([
            'name' => 'Unverified Farmer',
            'slug' => 'unverified_farmer',
            'permissions' => ['farmer.equipment.request'],
            'is_active' => true,
        ]);
        $unverifiedUser->roles()->attach($unverifiedRole);
        Farmer::create([
            'user_id' => $unverifiedUser->id,
            'farmer_code' => 'PENDING-001',
            'registration_status' => 'pending',
        ]);

        $this->submitRequest($this->window(5, 6), $unverifiedUser, $data['asset'])
            ->assertForbidden();
    }

    public function test_overlapping_active_reservation_is_rejected(): void
    {
        $data = $this->setupData();

        $this->submitRequest($this->window(), $data['farmerUser'], $data['asset'], 'first-request')
            ->assertRedirect();

        $secondFarmerUser = User::factory()->create();
        $secondFarmer = Farmer::create([
            'user_id' => $secondFarmerUser->id,
            'farmer_code' => 'F-'.fake()->unique()->numerify('####'),
            'registration_status' => 'verified',
            'verified_at' => now(),
        ]);
        $secondFarmerUser->roles()->attach(Role::where('slug', 'farmer')->firstOrFail());

        $this->submitRequest($this->window(2, 4), $secondFarmerUser, $data['asset'])
            ->assertSessionHasErrors('schedule_start');

        $this->assertDatabaseCount('equipment_requests', 1);
        $this->assertSame('pending', EquipmentRequest::firstOrFail()->status);
    }

    public function test_staff_can_approve_or_reject_pending_requests_with_history(): void
    {
        $data = $this->setupData();
        $this->submitRequest($this->window(), $data['farmerUser'], $data['asset'], 'approve-me');
        $request = EquipmentRequest::where('idempotency_key', 'approve-me')->firstOrFail();

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/requests/'.$request->id.'/review', ['status' => 'approved'])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'reviewed_by' => $data['staff']->id,
        ]);
        $this->assertDatabaseHas('equipment_transactions', [
            'equipment_request_id' => $request->id,
            'action' => 'approved',
        ]);

        $secondAsset = EquipmentAsset::create([
            'asset_code' => 'TRACTOR-002',
            'name' => 'Second tractor',
            'category_id' => $data['category']->id,
            'condition' => 'good',
            'availability_status' => 'available',
        ]);
        $this->submitRequest($this->window(5, 6), $data['farmerUser'], $secondAsset, 'reject-me');
        $secondRequest = EquipmentRequest::where('idempotency_key', 'reject-me')->firstOrFail();

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/requests/'.$secondRequest->id.'/review', [
                'status' => 'rejected',
                'review_notes' => 'Schedule requires additional coordination.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment_requests', [
            'id' => $secondRequest->id,
            'status' => 'rejected',
            'review_notes' => 'Schedule requires additional coordination.',
        ]);
    }

    public function test_issue_and_return_are_stateful_and_duplicate_issue_is_blocked(): void
    {
        $data = $this->setupData();
        $this->submitRequest($this->window(), $data['farmerUser'], $data['asset'], 'issue-flow');
        $request = EquipmentRequest::where('idempotency_key', 'issue-flow')->firstOrFail();

        $this->actingAs($data['staff'])->post('/modules/aeams/requests/'.$request->id.'/review', ['status' => 'approved']);
        $this->actingAs($data['staff'])->post('/modules/aeams/requests/'.$request->id.'/issue', ['condition' => 'good'])->assertRedirect();
        $this->assertSame('issued', $request->fresh()->status);
        $this->assertSame('issued', $data['asset']->fresh()->availability_status);

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/requests/'.$request->id.'/issue', ['condition' => 'good'])
            ->assertSessionHasErrors('status');

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/requests/'.$request->id.'/return', [
                'condition' => 'fair',
                'needs_maintenance' => '1',
                'notes' => 'Hydraulic inspection required.',
            ])
            ->assertRedirect();

        $this->assertSame('returned', $request->fresh()->status);
        $this->assertSame('maintenance', $data['asset']->fresh()->availability_status);
        $this->assertDatabaseHas('equipment_transactions', ['equipment_request_id' => $request->id, 'action' => 'issued']);
        $this->assertDatabaseHas('equipment_transactions', ['equipment_request_id' => $request->id, 'action' => 'returned']);
    }

    public function test_maintenance_completion_restores_availability(): void
    {
        $data = $this->setupData();

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/equipment/'.$data['asset']->id.'/maintenance', [
                'description' => 'Replace worn belt.',
                'cost' => 1200,
            ])
            ->assertRedirect();

        $maintenance = EquipmentMaintenance::firstOrFail();
        $this->assertSame('maintenance', $data['asset']->fresh()->availability_status);

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/equipment/'.$data['asset']->id.'/maintenance', [
                'description' => 'Duplicate maintenance attempt.',
            ])
            ->assertSessionHasErrors('equipment_asset_id');

        $this->actingAs($data['staff'])
            ->post('/modules/aeams/maintenance/'.$maintenance->id.'/complete', ['condition' => 'good'])
            ->assertRedirect();

        $this->assertSame('completed', $maintenance->fresh()->status);
        $this->assertSame('available', $data['asset']->fresh()->availability_status);
        $this->assertDatabaseHas('equipment_transactions', ['action' => 'maintenance_completed']);
    }

    public function test_unauthorized_users_cannot_access_aeams_or_manage_assets(): void
    {
        $data = $this->setupData();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get('/modules/aeams')->assertForbidden();
        $this->actingAs($data['farmerUser'])->get('/modules/aeams')->assertOk()->assertSee('Request equipment');
        $this->actingAs($data['farmerUser'])
            ->post('/modules/aeams/equipment', [
                'asset_code' => 'NOPE-001',
                'name' => 'Unauthorized asset',
                'category_id' => $data['category']->id,
                'condition' => 'good',
            ])
            ->assertForbidden();
    }

    public function test_staff_can_filter_equipment_requests_by_status_and_search(): void
    {
        $data = $this->setupData();
        $this->submitRequest($this->window(), $data['farmerUser'], $data['asset'], 'filter-this-request')
            ->assertRedirect();
        $equipmentRequest = EquipmentRequest::where('idempotency_key', 'filter-this-request')->firstOrFail();

        $this->actingAs($data['staff'])
            ->get('/modules/aeams?workspace=requests&request_status=pending&request_search=Farm%20tractor')
            ->assertOk()
            ->assertSee($equipmentRequest->reference)
            ->assertSee('data-erp-workspace-tab="requests"', false)
            ->assertSee('Farm tractor');

        $this->actingAs($data['staff'])
            ->get('/modules/aeams?workspace=requests&request_status=rejected&request_search=Farm%20tractor')
            ->assertOk()
            ->assertDontSee($equipmentRequest->reference);

        $this->actingAs($data['staff'])
            ->get('/modules/aeams?workspace=requests&request_search=does-not-match')
            ->assertOk()
            ->assertSee('No equipment requests match these filters.');
    }
}
