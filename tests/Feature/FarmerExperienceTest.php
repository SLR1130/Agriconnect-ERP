<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceProgram;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\EquipmentRequest;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerRegistrationHistory;
use App\Models\PestDiseaseIncident;
use App\Models\Role;
use App\Models\User;
use App\Notifications\EquipmentRequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(string $status = 'verified'): array
    {
        $role = Role::firstOrCreate(['slug' => 'farmer'], [
            'name' => 'Farmer',
            'permissions' => ['farmer.profile.manage', 'farmer.farms.manage', 'farmer.assistance.apply', 'farmer.equipment.request'],
            'is_active' => true,
        ]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);
        $farmer = Farmer::create([
            'user_id' => $user->id,
            'farmer_code' => 'F-'.fake()->unique()->numerify('######'),
            'barangay' => 'Poblacion',
            'registration_status' => $status,
        ]);
        $farm = Farm::create(['farmer_id' => $farmer->id, 'name' => 'Rice Field', 'barangay' => 'Poblacion', 'area_unit' => 'hectare', 'ownership_type' => 'owner']);

        return compact('user', 'farmer', 'farm');
    }

    public function test_pending_farmer_can_discover_only_open_programs_but_cannot_apply(): void
    {
        $data = $this->farmer('pending');
        $open = AssistanceProgram::create(['name' => 'Rice Seed Support', 'status' => 'open', 'category' => 'Seeds']);
        $closed = AssistanceProgram::create(['name' => 'Internal Draft', 'status' => 'closed']);

        $this->actingAs($data['user'])->get('/farmer')->assertOk()->assertSee('Rice Seed Support')->assertSee('Registration pending')->assertDontSee('name="phone"', false);
        $this->actingAs($data['user'])->get('/farmer/services?search=Rice')->assertOk()->assertSee('Rice Seed Support')->assertDontSee('Internal Draft');
        $this->actingAs($data['user'])->get('/farmer/assistance')->assertOk()->assertSee('Rice Seed Support');
        $this->actingAs($data['user'])->get('/farmer/programs/'.$open->id)->assertOk()->assertSee('Farmer registration must be verified');
        $this->actingAs($data['user'])->get('/farmer/programs/'.$closed->id)->assertNotFound();
        $this->actingAs($data['user'])->post('/assistance/programs/'.$open->id.'/apply', [])->assertForbidden();
        $this->assertDatabaseCount('assistance_applications', 0);
    }

    public function test_service_discovery_filters_programs_by_application_dates_and_shows_profile_eligibility(): void
    {
        $data = $this->farmer('pending');
        $available = AssistanceProgram::create(['name' => 'Current Seed Support', 'status' => 'open', 'category' => 'Seeds']);
        $upcoming = AssistanceProgram::create([
            'name' => 'Upcoming Farm Grant', 'status' => 'open', 'category' => 'Grants',
            'opens_on' => today()->addDays(5), 'closes_on' => today()->addDays(15),
        ]);
        $ended = AssistanceProgram::create([
            'name' => 'Past Harvest Support', 'status' => 'open', 'category' => 'Harvest',
            'opens_on' => today()->subDays(20), 'closes_on' => today()->subDay(),
        ]);
        $closesToday = AssistanceProgram::create([
            'name' => 'Closing Today Support', 'status' => 'open', 'category' => 'Boundary',
            'opens_on' => today(), 'closes_on' => today(),
        ]);
        $closed = AssistanceProgram::create(['name' => 'Closed Support', 'status' => 'closed', 'category' => 'Boundary']);

        $this->actingAs($data['user'])->get('/farmer/services')
            ->assertOk()->assertSee('Current Seed Support')->assertSee('Closing Today Support')->assertSee('Farmer registration must be verified')
            ->assertDontSee('Upcoming Farm Grant')->assertDontSee('Past Harvest Support')->assertDontSee('Closed Support');
        $this->actingAs($data['user'])->get('/farmer/services?availability=upcoming')
            ->assertOk()->assertSee('Upcoming Farm Grant')->assertDontSee('Current Seed Support')->assertDontSee('Past Harvest Support');
        $this->actingAs($data['user'])->get('/farmer/services?availability=ended')
            ->assertOk()->assertSee('Past Harvest Support')->assertDontSee('Current Seed Support')->assertDontSee('Upcoming Farm Grant');
        $this->actingAs($data['user'])->get('/farmer/services?availability=all&category=Grants')
            ->assertOk()->assertSee('Upcoming Farm Grant')->assertDontSee('Current Seed Support')->assertDontSee('Past Harvest Support');
        $this->actingAs($data['user'])->get('/farmer/services?availability=ended')
            ->assertOk()->assertSee('Past Harvest Support')->assertDontSee('Closing Today Support');
        $this->actingAs($data['user'])->get('/farmer/programs/'.$closed->id)->assertNotFound();
        $this->assertDatabaseCount('assistance_programs', 5);
    }

    public function test_farmer_activity_tracker_searches_filters_and_paginates_owned_records(): void
    {
        $data = $this->farmer();
        for ($number = 1; $number <= 22; $number++) {
            $program = AssistanceProgram::create(['name' => sprintf('Portal tracking item %02d', $number), 'status' => 'open']);
            $application = AssistanceApplication::create([
                'program_id' => $program->id,
                'farmer_id' => $data['farmer']->id,
                'status' => $number % 2 === 0 ? 'approved' : 'submitted',
            ]);
            $timestamp = now()->addSeconds($number);
            $application->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();
        }

        $firstPage = $this->actingAs($data['user'])->get('/farmer/activities?type=Assistance%20application&search=tracking+item');
        $firstPage->assertOk()->assertSee('22 matching records')->assertSee('Portal tracking item 22')->assertDontSee('Portal tracking item 01')->assertSee('page=2');

        $secondPage = $this->actingAs($data['user'])->get('/farmer/activities?type=Assistance%20application&search=tracking+item&page=2');
        $secondPage->assertOk()->assertSee('Portal tracking item 01')->assertDontSee('Portal tracking item 22');

        $this->actingAs($data['user'])->get('/farmer/activities?status=approved&type=Assistance%20application')
            ->assertOk()->assertSee('Portal tracking item 22')->assertDontSee('Portal tracking item 21');
        $this->assertDatabaseCount('assistance_applications', 22);
    }

    public function test_verified_farmer_applies_once_and_other_applications_stay_private(): void
    {
        $data = $this->farmer();
        $other = $this->farmer();
        $program = AssistanceProgram::create(['name' => 'Seed Grant', 'status' => 'open']);

        $this->actingAs($data['user'])->get('/farmer/programs/'.$program->id)->assertOk()->assertSee('Submit application');
        $this->actingAs($data['user'])->post('/assistance/programs/'.$program->id.'/apply', ['purpose' => 'Planting'])->assertRedirect();
        $application = AssistanceApplication::firstOrFail();

        $this->assertDatabaseHas('assistance_applications', ['id' => $application->id, 'farmer_id' => $data['farmer']->id, 'purpose' => 'Planting', 'status' => 'submitted']);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $application->id, 'from_status' => null, 'to_status' => 'submitted', 'changed_by' => $data['user']->id]);
        $this->actingAs($data['user'])->get('/farmer/applications/'.$application->id)->assertOk()->assertSee('Seed Grant');
        $this->actingAs($data['user'])->post('/assistance/programs/'.$program->id.'/apply', [])->assertRedirect()->assertSessionHasErrors('program');
        $this->actingAs($other['user'])->get('/farmer/applications/'.$application->id)->assertNotFound();
        $this->assertDatabaseCount('assistance_applications', 1);
    }

    public function test_profile_and_farm_pages_show_correct_history_and_protect_verified_barangay(): void
    {
        $data = $this->farmer();
        FarmerRegistrationHistory::create(['farmer_id' => $data['farmer']->id, 'to_status' => 'verified', 'reason' => 'MAO identity review complete.']);

        $this->actingAs($data['user'])->get('/farmer/farms')->assertOk()->assertSee('MAO identity review complete.')->assertSee('Rice Field');
        $this->actingAs($data['user'])->get('/farmer/profile')->assertOk()->assertSee('Contact information');
        $this->actingAs($data['user'])->patch('/farmer/profile', ['phone' => '09123456789', 'address' => 'Market Road', 'barangay' => 'Poblacion'])->assertRedirect();
        $this->assertDatabaseHas('farmers', ['id' => $data['farmer']->id, 'phone' => '09123456789']);
        $this->actingAs($data['user'])->patch('/farmer/profile', ['barangay' => 'Another Barangay'])->assertSessionHasErrors('barangay');
        $this->assertDatabaseHas('farmers', ['id' => $data['farmer']->id, 'barangay' => 'Poblacion']);
    }

    public function test_farmer_incident_report_is_owned_and_does_not_grant_staff_record_editing(): void
    {
        $data = $this->farmer();
        $other = $this->farmer();
        $payload = ['farm_id' => $data['farm']->id, 'incident_type' => 'Rice blast', 'affected_crop_or_animal' => 'Rice', 'reported_on' => today()->toDateString(), 'severity' => 'medium', 'description' => 'Leaf lesions observed'];

        $this->actingAs($data['user'])->post('/farmer/production/incidents', $payload)->assertRedirect('/farmer/production?section=incidents');
        $this->assertDatabaseHas('pest_disease_incidents', ['farmer_id' => $data['farmer']->id, 'farm_id' => $data['farm']->id, 'reported_by' => $data['user']->id, 'status' => 'reported']);
        $this->actingAs($other['user'])->get('/farmer/production?section=incidents')->assertOk()->assertDontSee('Rice blast');
        $this->actingAs($other['user'])->post('/farmer/production/incidents', $payload)->assertSessionHasErrors('farm_id');
        $this->actingAs($data['user'])->post('/modules/apms/crops', [])->assertForbidden();
        $this->assertSame(1, PestDiseaseIncident::count());
    }

    public function test_equipment_view_hides_procurement_details_and_review_notifies_only_owner(): void
    {
        $data = $this->farmer();
        $other = $this->farmer();
        $category = EquipmentCategory::create(['name' => 'Farm machinery', 'is_active' => true]);
        $asset = EquipmentAsset::create(['asset_code' => 'PUMP-1', 'name' => 'Irrigation pump', 'category_id' => $category->id, 'description' => 'Purchased on PO-PRIVATE-001', 'acquisition_cost' => 9999, 'condition' => 'good', 'availability_status' => 'available']);
        $staffRole = Role::create(['name' => 'AEAMS Staff', 'slug' => 'aeams_staff', 'permissions' => ['modules.aeams.review'], 'is_active' => true]);
        $staff = User::factory()->create();
        $staff->roles()->attach($staffRole);

        $this->actingAs($data['user'])->get('/farmer/equipment')->assertOk()->assertSee('Irrigation pump')->assertDontSee('PO-PRIVATE-001')->assertDontSee('9999');
        $this->actingAs($data['user'])->get('/modules/aeams/equipment/'.$asset->id)->assertOk()->assertDontSee('PO-PRIVATE-001');
        $this->actingAs($data['user'])->from('/farmer/equipment')->post('/modules/aeams/requests', [
            'equipment_asset_id' => $asset->id, 'intended_use' => 'Field irrigation',
            'schedule_start' => now()->addDays(2)->format('Y-m-d H:i:s'), 'schedule_end' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertRedirect('/farmer/equipment')->assertSessionHas('status', 'Equipment request submitted for MAO review.');
        $this->actingAs($data['user'])->get('/farmer/equipment')->assertOk()->assertSee('Field irrigation');
        $equipmentRequest = EquipmentRequest::firstOrFail();
        $this->actingAs($data['user'])->get('/farmer/activities')->assertOk()->assertSee('Irrigation pump')->assertSee('Equipment request');
        $this->actingAs($other['user'])->get('/farmer/equipment-requests/'.$equipmentRequest->id)->assertNotFound();
        $this->actingAs($staff)->post('/modules/aeams/requests/'.$equipmentRequest->id.'/review', ['status' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('equipment_requests', ['id' => $equipmentRequest->id, 'status' => 'approved']);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($data['user']->id, (int) $data['user']->notifications()->firstOrFail()->notifiable_id);
    }

    public function test_notification_read_action_is_owner_scoped(): void
    {
        $data = $this->farmer();
        $other = $this->farmer();
        $data['user']->notify(new EquipmentRequestStatusChanged('EQ-1', 'approved'));
        $notice = $data['user']->notifications()->firstOrFail();

        $this->actingAs($data['user'])->get('/farmer/notifications')->assertOk()->assertSee('EQ-1');
        $this->actingAs($other['user'])->post('/farmer/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->assertNull($notice->fresh()->read_at);
        $this->actingAs($data['user'])->post('/farmer/notifications/'.$notice->id.'/read')->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
    }
}
