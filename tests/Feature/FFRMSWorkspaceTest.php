<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\EquipmentRequest;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerAssociation;
use App\Models\FarmerRegistrationHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FFRMSWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions): User
    {
        $role = Role::create([
            'name' => 'FFRMS QA '.Str::random(5),
            'slug' => 'ffrms_qa_'.Str::lower(Str::random(8)),
            'permissions' => $permissions,
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function farmer(string $status = 'pending'): array
    {
        $user = User::factory()->create(['name' => 'Belen Santos']);
        $farmer = Farmer::create([
            'user_id' => $user->id,
            'farmer_code' => 'FFRMS-'.Str::upper(Str::random(7)),
            'phone' => '09171234567',
            'address' => 'Poblacion, Bongabong',
            'barangay' => 'Poblacion',
            'registration_status' => $status,
        ]);
        $farm = Farm::create([
            'farmer_id' => $farmer->id,
            'name' => 'Santos Rice Farm',
            'barangay' => 'Poblacion',
            'location_details' => 'Sitio Riverside',
            'area' => 1.25,
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'classification' => 'irrigated rice',
            'soil_type' => 'clay loam',
            'irrigation_type' => 'communal canal',
            'agricultural_activities' => ['rice', 'vegetables'],
            'categories' => ['food_crop'],
            'status' => 'active',
        ]);
        FarmerRegistrationHistory::create([
            'farmer_id' => $farmer->id,
            'to_status' => $status,
            'reason' => 'Registration documents received.',
        ]);

        return compact('user', 'farmer', 'farm');
    }

    public function test_registry_is_scannable_and_links_to_an_owner_record_detail_without_losing_filters(): void
    {
        $staff = $this->staff(['modules.ffrms.view', 'modules.ffrms.update', 'modules.ffrms.approve', 'modules.ffrms.review', 'modules.ffrms.reject', 'modules.ffrms.export']);
        $record = $this->farmer();

        $this->actingAs($staff)->get('/modules/ffrms?search=Belen&status=pending&page=1')
            ->assertOk()
            ->assertSee('Pending review')
            ->assertSee('Belen Santos')
            ->assertSee('data-label="Farmer"', false)
            ->assertSee('data-label="Registration"', false)
            ->assertSee('View details')
            ->assertSee('value="Belen"', false)
            ->assertSee('value="pending" selected', false)
            ->assertSee(e(route('modules.ffrms.dashboard', ['workspace' => 'farmers', 'record' => $record['farmer']->id, 'search' => 'Belen', 'status' => 'pending', 'page' => 1]).'#ffrms-record-detail'), false)
            ->assertDontSee('ffrms-record-content');

        $this->actingAs($staff)->get('/modules/ffrms?workspace=farmers&record='.$record['farmer']->id.'&search=Belen&status=pending&page=1')
            ->assertOk()
            ->assertSee('Back to filtered registry')
            ->assertSee('Santos Rice Farm')
            ->assertSee('Sitio Riverside')
            ->assertSee('Usual farm products')
            ->assertSee('Food Crop')
            ->assertSee('Registration documents received.')
            ->assertSee('Edit farm record')
            ->assertSee('Available decision')
            ->assertDontSee('ffrms-record-content')
            ->assertSee(e(route('modules.ffrms.dashboard', ['workspace' => 'farmers', 'search' => 'Belen', 'status' => 'pending', 'page' => 1])), false);
    }

    public function test_farmer_equipment_history_is_owner_scoped_and_requires_equipment_permission(): void
    {
        $record = $this->farmer('verified');
        $other = $this->farmer('verified');
        $category = EquipmentCategory::create(['name' => 'Equipment for history', 'is_active' => true]);
        $asset = EquipmentAsset::create(['asset_code' => 'HISTORY-1', 'name' => 'History tractor', 'category_id' => $category->id, 'condition' => 'good', 'availability_status' => 'available']);
        foreach ([[$record, 'OWNER-LOAN'], [$other, 'OTHER-LOAN']] as [$owner, $reference]) {
            EquipmentRequest::create([
                'reference' => $reference, 'farmer_id' => $owner['farmer']->id,
                'requested_by' => $owner['user']->id, 'equipment_asset_id' => $asset->id,
                'intended_use' => 'Farm work', 'schedule_start' => now()->subDays(3),
                'schedule_end' => now()->subDay(), 'status' => 'returned',
                'issued_at' => now()->subDays(3), 'returned_at' => now()->subDay(),
            ]);
        }

        $url = '/modules/ffrms?workspace=farmers&record='.$record['farmer']->id;
        $this->actingAs($this->staff(['modules.ffrms.view', 'modules.aeams.view']))->get($url)
            ->assertOk()->assertSee('Equipment borrowing history')->assertSee('OWNER-LOAN')->assertDontSee('OTHER-LOAN');
        $this->actingAs($this->staff(['modules.ffrms.view']))->get($url)
            ->assertOk()->assertDontSee('Equipment borrowing history')->assertDontSee('OWNER-LOAN');
    }

    public function test_module_titles_are_escaped_once_in_the_shared_topbar(): void
    {
        $staff = $this->staff(['modules.ffrms.view', 'modules.apms.view']);

        foreach ([
            ['/modules/ffrms', 'Farmer & Farm Registration'],
            ['/modules/apms', 'Agricultural Production & Field Operations'],
        ] as [$url, $title]) {
            $html = $this->actingAs($staff)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<div class="erp-context-title" aria-live="polite">', $html);
            $this->assertStringContainsString('<span class="erp-context-title-icon" aria-hidden="true">', $html);
            $this->assertStringContainsString(e($title), $html);
            $this->assertStringNotContainsString(e(e($title)), $html);
        }
    }

    public function test_association_empty_state_explains_the_next_step_and_links_to_registration(): void
    {
        $staff = $this->staff(['modules.ffrms.view', 'modules.ffrms.update']);

        $this->actingAs($staff)->get('/modules/ffrms?workspace=associations')
            ->assertOk()
            ->assertSee('No associations registered yet')
            ->assertSee('Register a farmer group')
            ->assertSee('href="#association-name"', false)
            ->assertSee('id="association-name"', false);
    }

    public function test_farmer_detail_shows_real_association_membership_and_restricted_review_actions(): void
    {
        $staff = $this->staff(['modules.ffrms.view', 'modules.ffrms.review']);
        $record = $this->farmer();
        $association = FarmerAssociation::create(['name' => 'Poblacion Farmers Cooperative', 'status' => 'active']);
        $association->farmers()->attach($record['farmer']->id, ['membership_status' => 'active', 'joined_at' => now()]);

        $this->actingAs($staff)->get('/modules/ffrms?record='.$record['farmer']->id)
            ->assertOk()
            ->assertSee('Poblacion Farmers Cooperative')
            ->assertSee('1 memberships')
            ->assertSee('Return for correction')
            ->assertDontSee('Verify registration')
            ->assertDontSee('Reject registration');

        $this->actingAs($staff)->post('/modules/ffrms/registrations/'.$record['farmer']->id.'/review', [
            'status' => 'verified',
            'reason' => 'This role may only return records.',
        ])->assertForbidden();

        $this->actingAs($staff)->post('/modules/ffrms/registrations/'.$record['farmer']->id.'/review', [
            'status' => 'returned',
            'reason' => 'Please attach the missing farm document.',
            'registry_search' => 'Belen',
            'registry_status' => 'pending',
            'registry_page' => '1',
        ])->assertRedirect(route('modules.ffrms.dashboard', [
            'workspace' => 'farmers',
            'record' => $record['farmer']->id,
            'search' => 'Belen',
            'status' => 'pending',
            'page' => 1,
        ]));

        $this->assertDatabaseHas('farmers', ['id' => $record['farmer']->id, 'registration_status' => 'returned', 'review_notes' => 'Please attach the missing farm document.']);
        $this->assertDatabaseHas('farmer_registration_histories', ['farmer_id' => $record['farmer']->id, 'to_status' => 'returned', 'processed_by' => $staff->id]);
    }

    public function test_farmer_and_unassigned_personnel_cannot_open_staff_record_details_or_decide_verification(): void
    {
        $record = $this->farmer();
        $farmerRole = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $record['user']->roles()->attach($farmerRole);
        $personnel = $this->staff(['modules.aisms.view']);

        $this->actingAs($record['user'])->get('/modules/ffrms?record='.$record['farmer']->id)->assertForbidden();
        $this->actingAs($personnel)->get('/modules/ffrms?record='.$record['farmer']->id)->assertForbidden();
        $this->actingAs($personnel)->post('/modules/ffrms/registrations/'.$record['farmer']->id.'/review', ['status' => 'verified'])->assertForbidden();
        $this->assertDatabaseHas('farmers', ['id' => $record['farmer']->id, 'registration_status' => 'pending']);
    }

    public function test_authorized_farmer_and_farm_corrections_are_validated_transactional_and_audited(): void
    {
        $editor = $this->staff(['modules.ffrms.view', 'modules.ffrms.update']);
        $record = $this->farmer('verified');

        $this->actingAs($editor)->patch('/modules/ffrms/registrations/'.$record['farmer']->id, [
            'name' => 'Belen Santos Cruz',
            'email' => 'belen.cruz@example.test',
            'phone' => '09175551234',
            'address' => 'New address, Bongabong',
            'barangay' => 'Poblacion',
            'registry_search' => 'Belen',
            'registry_status' => 'verified',
            'registry_page' => '2',
        ])->assertRedirect(route('modules.ffrms.dashboard', [
            'workspace' => 'farmers', 'record' => $record['farmer']->id, 'search' => 'Belen', 'status' => 'verified', 'page' => 2,
        ]))->assertSessionHas('status', 'Farmer information updated and audited.');

        $this->assertDatabaseHas('users', ['id' => $record['user']->id, 'name' => 'Belen Santos Cruz', 'email' => 'belen.cruz@example.test']);
        $this->assertDatabaseHas('farmers', ['id' => $record['farmer']->id, 'phone' => '09175551234', 'address' => 'New address, Bongabong']);

        $identityAudit = ActivityLog::query()->where('action_target', $record['farmer']->farmer_code)->latest('id')->firstOrFail();
        $this->assertSame('ffrms', $identityAudit->module);
        $this->assertSame($editor->id, $identityAudit->causer_id);
        $this->assertSame('Belen Santos', json_decode($identityAudit->old_values, true)['name']);
        $this->assertSame('Belen Santos Cruz', json_decode($identityAudit->new_values, true)['name']);
        $this->assertSame('belen.cruz@example.test', json_decode($identityAudit->new_values, true)['email']);

        $this->actingAs($editor)->patch('/modules/ffrms/farms/'.$record['farm']->id, [
            'name' => 'Santos Rice and Vegetable Farm',
            'barangay' => 'Poblacion',
            'location_details' => 'New access road',
            'area' => '2.50',
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
            'classification' => 'irrigated rice',
            'tenancy_details' => '',
            'soil_type' => 'clay loam',
            'irrigation_type' => 'communal canal',
            'agricultural_activities' => 'rice, vegetables',
            'categories' => 'food_crop',
            'registry_search' => 'Belen',
            'registry_status' => 'verified',
            'registry_page' => '2',
        ])->assertRedirect(route('modules.ffrms.dashboard', [
            'workspace' => 'farmers', 'record' => $record['farmer']->id, 'search' => 'Belen', 'status' => 'verified', 'page' => 2,
        ]))->assertSessionHas('status', 'Farm record updated and audited.');

        $this->assertDatabaseHas('farms', ['id' => $record['farm']->id, 'name' => 'Santos Rice and Vegetable Farm', 'location_details' => 'New access road', 'area' => 2.50]);
        $farmAudit = ActivityLog::query()->where('action_target', 'farm:'.$record['farm']->id)->latest('id')->firstOrFail();
        $this->assertSame('Sitio Riverside', json_decode($farmAudit->old_values, true)['location_details']);
        $this->assertSame('New access road', json_decode($farmAudit->new_values, true)['location_details']);
        $this->assertSame(['rice', 'vegetables'], json_decode($farmAudit->new_values, true)['agricultural_activities']);

        $this->actingAs($editor)->patch('/modules/ffrms/farms/'.$record['farm']->id, [
            'name' => 'Invalid area update',
            'barangay' => 'Poblacion',
            'area' => '0',
            'area_unit' => 'hectare',
            'ownership_type' => 'owner',
        ])->assertSessionHasErrors('area');

        $this->assertDatabaseHas('farms', ['id' => $record['farm']->id, 'name' => 'Santos Rice and Vegetable Farm', 'area' => 2.50]);

        $unassigned = $this->staff(['modules.aisms.view']);
        $this->actingAs($unassigned)->patch('/modules/ffrms/farms/'.$record['farm']->id, [
            'name' => 'Unauthorized update', 'barangay' => 'Poblacion', 'area_unit' => 'hectare', 'ownership_type' => 'owner',
        ])->assertForbidden();
        $this->assertDatabaseMissing('farms', ['id' => $record['farm']->id, 'name' => 'Unauthorized update']);
    }

    public function test_filtered_csv_is_authorized_searchable_and_escapes_spreadsheet_formulas(): void
    {
        $exporter = $this->staff(['modules.ffrms.view', 'modules.ffrms.export']);
        $record = $this->farmer('verified');
        $record['farmer']->update(['farmer_code' => '=1+1']);
        $other = $this->farmer('pending');

        $response = $this->actingAs($exporter)->get('/modules/ffrms/export?status=verified&search='.rawurlencode($record['user']->email));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('Belen Santos', $csv);
        $this->assertStringNotContainsString($other['farmer']->farmer_code, $csv);

        $unassigned = $this->staff(['modules.ffrms.view']);
        $this->actingAs($unassigned)->get('/modules/ffrms/export')->assertForbidden();
    }
}
