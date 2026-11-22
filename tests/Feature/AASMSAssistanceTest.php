<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\Farmer;
use App\Models\InventoryLocation;
use App\Models\Role;
use App\Models\SupplyBatch;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Services\AssistanceService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AASMSAssistanceTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        $role = Role::create(['name' => 'MAO', 'slug' => 'mao', 'permissions' => ['modules.aasms.*'], 'is_active' => true]);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);
        $farmerRole = Role::create(['name' => 'Farmer', 'slug' => 'farmer', 'permissions' => [], 'is_active' => true]);
        $fu = User::factory()->create();
        $fu->roles()->attach($farmerRole);
        $farmer = Farmer::create(['user_id' => $fu->id, 'farmer_code' => 'F-1', 'registration_status' => 'verified']);
        $program = AssistanceProgram::create(['name' => 'Seed Support', 'status' => 'open']);
        $app = AssistanceApplication::create(['program_id' => $program->id, 'farmer_id' => $farmer->id, 'status' => 'approved']);
        $cat = SupplyCategory::firstOrCreate(['name' => 'Seeds'], ['is_active' => true]);
        $unit = UnitOfMeasurement::firstOrCreate(['name' => 'Kilogram'], ['symbol' => 'kg']);
        $loc = InventoryLocation::create(['name' => 'Store', 'is_active' => true]);
        $item = SupplyItem::create(['category_id' => $cat->id, 'unit_id' => $unit->id, 'code' => 'S-1', 'name' => 'Seed', 'minimum_stock' => 0, 'status' => 'active']);
        $batch = app(InventoryService::class)->receive(['supply_item_id' => $item->id, 'location_id' => $loc->id, 'batch_number' => 'B', 'received_on' => now()->toDateString(), 'quantity' => 5], $staff->id);

        return compact('staff', 'fu', 'program', 'app', 'batch');
    }

    public function test_farmer_application_and_staff_distribution_are_persisted(): void
    {
        $x = $this->setupData();
        $this->actingAs($x['fu'])->post('/assistance/programs/'.$x['program']->id.'/apply', [])->assertRedirect();
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', ['supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 2])->assertRedirect();
        $this->assertDatabaseHas('assistance_distributions', ['application_id' => $x['app']->id, 'quantity' => 2]);
        $this->assertSame('3.00', (string) SupplyBatch::first()->fresh()->quantity_available);

        $inventoryViewer = Role::create(['name' => 'AISMS History Viewer', 'slug' => 'aasms_inventory_history_viewer', 'permissions' => ['modules.aisms.view'], 'is_active' => true]);
        $x['staff']->roles()->attach($inventoryViewer);
        $this->actingAs($x['staff'])->get('/modules/aisms')
            ->assertOk()
            ->assertSee('Open AASMS application #'.$x['app']->id)
            ->assertSee(route('modules.aasms.applications.show', $x['app']->id));
    }

    public function test_program_workspace_explains_authoring_flow_and_renders_a_clear_creator(): void
    {
        $x = $this->setupData();

        $this->actingAs($x['staff'])->get('/modules/aasms?workspace=programs')
            ->assertOk()
            ->assertSee('Create assistance program')
            ->assertSee('Run each assistance service from setup to handover')
            ->assertSee('Define a service farmers can apply for')
            ->assertSee('Create program draft')
            ->assertSee('Service identity')
            ->assertSee('Schedule and resource')
            ->assertSee('Supporting documents')
            ->assertSee('Save private program')
            ->assertSee('Existing applications keep their original requirements version');
    }

    public function test_distribution_history_and_export_filter_by_the_farmers_current_barangay(): void
    {
        $x = $this->setupData();
        $x['app']->farmer->update(['barangay' => 'Labasan']);
        $secondUser = User::factory()->create(['name' => 'Second Farmer']);
        $secondFarmer = Farmer::create(['user_id' => $secondUser->id, 'farmer_code' => 'F-2', 'registration_status' => 'verified', 'barangay' => 'Dayhagan']);
        $secondApplication = AssistanceApplication::create(['program_id' => $x['program']->id, 'farmer_id' => $secondFarmer->id, 'status' => 'approved']);
        $unknownUser = User::factory()->create(['name' => 'Barangay Not Recorded']);
        $unknownFarmer = Farmer::create(['user_id' => $unknownUser->id, 'farmer_code' => 'F-3', 'registration_status' => 'verified']);
        $unknownApplication = AssistanceApplication::create(['program_id' => $x['program']->id, 'farmer_id' => $unknownFarmer->id, 'status' => 'approved']);

        foreach ([$x['app'], $secondApplication, $unknownApplication] as $application) {
            $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$application->id.'/distribute', [
                'supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $page = $this->actingAs($x['staff'])->get('/modules/aasms?workspace=distributions&barangay=Labasan')
            ->assertOk()->assertSee('Current barangay: Labasan')->assertSee('matching records')->assertSee('Open application #')
            ->assertSee('name="barangay"', false);
        $this->assertSame(1, $page->viewData('distributions')->total());
        $this->assertSame($x['app']->id, $page->viewData('distributions')->first()->application_id);
        $this->assertSame('at distribution', $page->viewData('distributionBarangaySummaryBasis'));
        $this->assertSame(3, $page->viewData('distributionBarangaySummary')->sum('distribution_count'));
        $this->assertSame(1, $page->viewData('distributionBarangaySummary')->where('barangay', 'Labasan')->first()->distribution_count);
        $this->assertSame(1, $page->viewData('distributionBarangaySummary')->where('barangay', 'Not recorded')->first()->distribution_count);
        $this->assertSame(1, $this->actingAs($x['staff'])
            ->get('/modules/aasms?workspace=distributions&distribution_barangay=__not_recorded__')
            ->assertOk()->viewData('distributions')->total());
        $this->actingAs($x['staff'])->get('/modules/aasms?workspace=distributions&distribution_barangay=InventedBarangay')
            ->assertSessionHasErrors('distribution_barangay');
        $csv = $this->actingAs($x['staff'])->get('/modules/aasms/export?barangay=Labasan')
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Labasan', $csv);
        $this->assertStringNotContainsString('Second Farmer', $csv);
        $this->assertDatabaseCount('assistance_distributions', 3);

        $this->actingAs($x['fu'])->get('/modules/aasms/export?barangay=Labasan')->assertForbidden();
    }

    public function test_distribution_preserves_farmer_barangay_at_the_time_of_handover(): void
    {
        $x = $this->setupData();
        $farmer = $x['app']->farmer;
        $farmer->update(['barangay' => 'Labasan']);

        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', [
            'supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $distribution = AssistanceDistribution::sole();
        $this->assertSame('Labasan', $distribution->farmer_barangay_at_distribution);
        $farmer->update(['barangay' => 'Dayhagan']);
        $this->assertSame('Labasan', $distribution->fresh()->farmer_barangay_at_distribution);

        $page = $this->actingAs($x['staff'])->get('/modules/aasms?workspace=distributions&distribution_barangay=Labasan')
            ->assertOk()->assertSee('At distribution: Labasan')->assertSee('Current barangay: Dayhagan');
        $this->assertSame(1, $page->viewData('distributions')->total());
        $this->assertSame(0, $this->actingAs($x['staff'])
            ->get('/modules/aasms?workspace=distributions&distribution_barangay=Dayhagan')
            ->assertOk()->viewData('distributions')->total());
        $csv = $this->actingAs($x['staff'])->get('/modules/aasms/export?distribution_barangay=Labasan')->assertOk()->streamedContent();
        $this->assertStringContainsString('Labasan', $csv);
        $this->assertStringContainsString('Dayhagan', $csv);
    }

    public function test_farmer_registration_assistance_history_requires_aasms_view_access(): void
    {
        $x = $this->setupData();
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', [
            'supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $ffrmsRole = Role::create(['name' => 'FFRMS Viewer', 'slug' => 'ffrms_history_viewer', 'permissions' => ['modules.ffrms.view'], 'is_active' => true]);
        $x['staff']->roles()->attach($ffrmsRole);
        $ffrmsOnly = User::factory()->create();
        $ffrmsOnly->roles()->attach($ffrmsRole);
        $url = '/modules/ffrms?workspace=farmers&record='.$x['app']->farmer_id;
        $reference = AssistanceDistribution::sole()->reference;

        $this->actingAs($x['staff'])->get($url)
            ->assertOk()->assertSee('Assistance received')->assertSee($reference);
        $this->actingAs($ffrmsOnly)->get($url)
            ->assertOk()->assertDontSee('Assistance received')->assertDontSee($reference);
    }

    public function test_only_approved_application_can_be_distributed(): void
    {
        $x = $this->setupData();
        $x['app']->update(['status' => 'submitted']);
        $this->expectException(ValidationException::class);
        app(AssistanceService::class)->distribute($x['app'], ['supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 1], $x['staff']->id, app(InventoryService::class));
    }

    public function test_unauthorized_staff_cannot_open_aasms(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/modules/aasms')->assertForbidden();
    }

    public function test_mao_can_approve_selected_pending_application_and_history_is_saved(): void
    {
        $x = $this->setupData();
        $x['app']->update(['status' => 'submitted']);
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/review', ['status' => 'approved'])->assertRedirect();
        $this->assertDatabaseHas('assistance_applications', ['id' => $x['app']->id, 'status' => 'approved', 'reviewed_by' => $x['staff']->id]);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $x['app']->id, 'from_status' => 'submitted', 'to_status' => 'approved']);
    }

    public function test_assistance_review_retry_does_not_duplicate_history_or_notifications_and_notification_links_to_owned_application(): void
    {
        $x = $this->setupData();
        $x['app']->update(['status' => 'submitted']);
        $staleApplication = AssistanceApplication::findOrFail($x['app']->id);

        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/review', ['status' => 'approved'])->assertRedirect();
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/review', ['status' => 'approved'])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('assistance_applications', ['id' => $x['app']->id, 'status' => 'approved']);
        $this->assertDatabaseCount('assistance_status_histories', 1);
        $this->assertDatabaseCount('notifications', 1);
        $notification = $x['fu']->notifications()->firstOrFail();
        $this->assertSame('assistance_application_status', data_get($notification->data, 'type'));
        $this->assertSame($x['app']->id, (int) data_get($notification->data, 'application_id'));
        $this->actingAs($x['fu'])->get('/farmer/notifications')->assertOk()
            ->assertSee('View assistance application')
            ->assertSee(route('farmer.applications.show', $x['app']), false);

        try {
            app(AssistanceService::class)->status($staleApplication, 'approved', $x['staff']->id);
            $this->fail('A stale application instance must not repeat an already committed transition.');
        } catch (ValidationException $exception) {
            $this->assertSame('Invalid application transition.', $exception->errors()['status'][0]);
        }

        $this->assertDatabaseCount('assistance_status_histories', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_mao_can_reject_or_return_only_with_a_reason(): void
    {
        $x = $this->setupData();
        $x['app']->update(['status' => 'submitted']);
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/review', ['status' => 'rejected', 'review_notes' => 'Missing requirement'])->assertRedirect();
        $this->assertDatabaseHas('assistance_applications', ['id' => $x['app']->id, 'status' => 'rejected']);
        $second = AssistanceApplication::create(['program_id' => $x['program']->id, 'farmer_id' => Farmer::create(['user_id' => User::factory()->create()->id, 'farmer_code' => 'F-2', 'registration_status' => 'verified'])->id, 'status' => 'submitted']);
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$second->id.'/review', ['status' => 'returned', 'review_notes' => 'Attach proof'])->assertRedirect();
        $this->assertDatabaseHas('assistance_applications', ['id' => $second->id, 'status' => 'returned']);
    }

    public function test_review_form_uses_each_application_id_and_unauthorized_review_is_blocked(): void
    {
        $x = $this->setupData();
        $x['app']->update(['status' => 'submitted']);
        $this->actingAs($x['staff'])->get('/modules/aasms')->assertOk()->assertSee('/modules/aasms/applications/'.$x['app']->id.'/review');
        $this->actingAs($x['fu'])->post('/modules/aasms/applications/'.$x['app']->id.'/review', ['status' => 'approved'])->assertForbidden();
    }

    public function test_distribution_deducts_stock_once_for_a_retry(): void
    {
        $x = $this->setupData();
        app(InventoryService::class)->receive(['supply_item_id' => SupplyBatch::find($x['batch']->supply_batch_id)->supply_item_id, 'location_id' => SupplyBatch::find($x['batch']->supply_batch_id)->location_id, 'batch_number' => 'B', 'received_on' => now()->toDateString(), 'quantity' => 95], $x['staff']->id);
        $payload = ['supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 20, 'idempotency_key' => 'distribution-retry'];
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', $payload)->assertRedirect();
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', $payload)->assertRedirect();
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', array_replace($payload, ['quantity' => 21]))->assertSessionHasErrors('idempotency_key');
        $this->assertSame('80.00', (string) SupplyBatch::find($x['batch']->supply_batch_id)->fresh()->quantity_available);
        $this->assertDatabaseCount('assistance_distributions', 1);
        $this->assertDatabaseCount('inventory_movements', 3);
        $this->assertDatabaseHas('assistance_distributions', ['application_id' => $x['app']->id, 'quantity' => 20]);
    }

    public function test_distribution_form_is_scoped_to_approved_application(): void
    {
        $x = $this->setupData();
        $this->actingAs($x['staff'])->get('/modules/aasms')->assertOk()->assertSee('/modules/aasms/applications/'.$x['app']->id.'/distribute');
    }

    public function test_distribution_respects_approved_entitlement(): void
    {
        $x = $this->setupData();
        $x['app']->update(['approved_quantity' => 1]);
        $this->expectException(ValidationException::class);
        app(AssistanceService::class)->distribute($x['app'], ['supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 2], $x['staff']->id, app(InventoryService::class));
    }

    public function test_completed_distribution_records_one_status_transition_on_retry(): void
    {
        $x = $this->setupData();
        $payload = [
            'supply_batch_id' => $x['batch']->supply_batch_id,
            'quantity' => 2,
            'idempotency_key' => 'completion-history-retry',
        ];

        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', $payload)->assertRedirect();
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', $payload)->assertRedirect();

        $this->assertDatabaseHas('assistance_status_histories', [
            'application_id' => $x['app']->id,
            'from_status' => 'approved',
            'to_status' => 'completed',
            'changed_by' => $x['staff']->id,
        ]);
        $this->assertDatabaseCount('assistance_status_histories', 1);
        $this->assertDatabaseCount('assistance_distributions', 1);
        $this->assertSame('3.00', (string) SupplyBatch::first()->fresh()->quantity_available);
        $this->assertDatabaseCount('notifications', 1);
        $notification = $x['fu']->notifications()->firstOrFail();
        $this->assertSame('assistance_distribution_completed', data_get($notification->data, 'type'));
        $this->assertSame($x['app']->id, (int) data_get($notification->data, 'application_id'));
    }

    public function test_farmer_assistance_page_is_owner_scoped(): void
    {
        $x = $this->setupData();
        $this->actingAs($x['staff'])->post('/modules/aasms/applications/'.$x['app']->id.'/distribute', ['supply_batch_id' => $x['batch']->supply_batch_id, 'quantity' => 1, 'idempotency_key' => 'owner-history'])->assertRedirect();
        $reference = AssistanceDistribution::first()->reference;
        $this->actingAs($x['fu'])->get('/farmer/assistance')->assertOk()->assertSee('Seed Support')->assertSee($reference);
        $this->actingAs($x['fu'])->get('/farmer/applications/'.$x['app']->id)->assertOk()
            ->assertSee('Distributions received')->assertSee($reference)->assertSee('Assistance distribution completed')
            ->assertSee('1.00')->assertSee('<svg', false);
    }

    public function test_dashboard_counts_are_not_limited_to_the_current_page_and_hides_closed_review_controls(): void
    {
        $x = $this->setupData();
        $x['app']->update(['status' => 'approved']);
        $secondUser = User::factory()->create();
        $secondFarmer = Farmer::create(['user_id' => $secondUser->id, 'farmer_code' => 'F-2', 'registration_status' => 'verified']);
        AssistanceApplication::create(['program_id' => $x['program']->id, 'farmer_id' => $secondFarmer->id, 'status' => 'submitted']);
        $response = $this->actingAs($x['staff'])->get('/modules/aasms?status=approved')->assertOk();
        $response->assertSee('data-aasms-stat="pending-applications"', false)->assertSee('data-aasms-stat="approved-applications"', false)->assertSee('data-aasms-stat="pending-applications" class="mt-2 text-3xl font-bold text-agricultural-gold">1</p', false)->assertSee('data-aasms-stat="approved-applications" class="mt-2 text-3xl font-bold text-emerald-green">1</p', false)->assertDontSee(route('modules.aasms.review', $x['app']), false);
    }
}
