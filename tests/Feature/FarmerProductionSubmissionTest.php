<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\ProductionSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmerProductionSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(string $status = 'verified'): array
    {
        $role = Role::firstOrCreate(['slug' => 'farmer'], ['name' => 'Farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => 'P7B-'.Str::random(8), 'registration_status' => $status]);
        $farm = Farm::create(['farmer_id' => $farmer->id, 'name' => 'Owner farm', 'barangay' => 'Poblacion', 'area_unit' => 'hectare', 'ownership_type' => 'owner']);

        return compact('user', 'farmer', 'farm');
    }

    private function reviewer(): User
    {
        $role = Role::firstOrCreate(['slug' => 'mao_admin'], ['name' => 'MAO Administrator', 'permissions' => ['modules.apms.view', 'modules.apms.update'], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_three_farmer_submissions_become_official_only_after_mao_review(): void
    {
        $owner = $this->farmer();
        $staff = $this->reviewer();
        $payloads = [
            'crop' => ['crop_type' => 'Rice', 'planted_on' => '2026-10-01', 'area_planted' => '1.25', 'area_unit' => 'hectare', 'yield_unit' => 'kilogram', 'status' => 'growing'],
            'livestock' => ['animal_type' => 'Goat', 'quantity' => 3, 'recorded_on' => '2026-10-30'],
            'fishery' => ['fishery_type' => 'Pond', 'species' => 'Tilapia', 'production_quantity' => '14.5', 'production_unit' => 'kilogram', 'recorded_on' => '2026-10-30'],
        ];
        foreach ($payloads as $category => $payload) {
            $this->actingAs($owner['user'])->post('/farmer/production/submissions/'.$category, $payload + ['farm_id' => $owner['farm']->id, 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        }
        $this->assertDatabaseCount('production_submissions', 3);
        $this->actingAs($owner['user'])->get('/farmer/production?section=livestock')->assertOk()->assertSee(route('farmer.production.submissions.store', 'livestock'), false)->assertDontSee(route('farmer.production.submissions.resubmit', ProductionSubmission::firstOrFail()), false);
        $this->actingAs($owner['user'])->get('/farmer/production?section=fisheries')->assertOk()->assertSee(route('farmer.production.submissions.store', 'fishery'), false);
        $this->assertDatabaseCount('crop_production_records', 0);
        $this->assertDatabaseCount('livestock_records', 0);
        $this->assertDatabaseCount('fishery_records', 0);
        $this->actingAs($staff)->get('/modules/apms/submissions')->assertOk()->assertSee('Owner farm');

        foreach (ProductionSubmission::all() as $submission) {
            $this->actingAs($staff)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'approved', 'review_notes' => 'Field record checked.'])->assertRedirect();
            $this->assertDatabaseHas('production_submissions', ['id' => $submission->id, 'status' => 'approved', 'reviewed_by' => $staff->id]);
            $this->assertDatabaseHas('production_submission_histories', ['production_submission_id' => $submission->id, 'to_status' => 'approved']);
        }
        $this->assertDatabaseCount('crop_production_records', 1);
        $this->assertDatabaseCount('livestock_records', 1);
        $this->assertDatabaseCount('fishery_records', 1);
        $this->assertDatabaseCount('notifications', 3);
        $this->actingAs($owner['user'])->get('/farmer/production')->assertOk()->assertSee('Rice');
        $this->actingAs($owner['user'])->get('/farmer/activities')->assertOk()->assertSee('Agricultural submission');
    }

    public function test_owner_scope_verification_and_retry_protection(): void
    {
        $owner = $this->farmer();
        $other = $this->farmer();
        $pending = $this->farmer('pending');
        $key = (string) Str::uuid();
        $payload = ['farm_id' => $owner['farm']->id, 'animal_type' => 'Cow', 'quantity' => 2, 'recorded_on' => '2026-10-30', 'idempotency_key' => $key];
        $this->actingAs($pending['user'])->post('/farmer/production/submissions/livestock', $payload)->assertForbidden();
        $this->actingAs($other['user'])->post('/farmer/production/submissions/livestock', $payload)->assertSessionHasErrors('farm_id');
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/livestock', $payload)->assertRedirect();
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/livestock', $payload)->assertRedirect();
        $this->assertDatabaseCount('production_submissions', 1);
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/livestock', array_replace($payload, ['quantity' => 9]))->assertSessionHasErrors('idempotency_key');
        $submission = ProductionSubmission::firstOrFail();
        $this->actingAs($other['user'])->get('/farmer/production/submissions/'.$submission->id)->assertNotFound();
        $this->actingAs($other['user'])->post('/farmer/production/submissions/'.$submission->id.'/resubmit', $payload)->assertNotFound();
        $this->assertDatabaseCount('livestock_records', 0);
    }

    public function test_registered_farm_links_to_an_owner_scoped_seasonal_report_with_the_farm_selected(): void
    {
        $owner = $this->farmer();
        $owner['farm']->update(['categories' => ['palay', 'tilapia']]);
        $reportUrl = route('farmer.production', [
            'section' => 'crops',
            'farm_id' => $owner['farm']->id,
            'report' => 1,
        ]);

        $this->actingAs($owner['user'])->get('/farmer/farms')
            ->assertOk()
            ->assertSee('Usual farm products')
            ->assertSee('Palay, Tilapia')
            ->assertSee("Report this season's production", false)
            ->assertSee(e($reportUrl.'#production-report-form'), false);

        $this->actingAs($owner['user'])->get($reportUrl)
            ->assertOk()
            ->assertSee('id="production-report-form"', false)
            ->assertSee('Report crop production for this season')
            ->assertSee('<select id="crop-type" name="crop_type"', false)
            ->assertSee('<option value="Palay"', false)
            ->assertSee('Other, enter below')
            ->assertSee('<option value="'.$owner['farm']->id.'" selected>Owner farm', false)
            ->assertSee('Send to MAO for review');

        $this->actingAs($owner['user'])->get('/farmer/production?section=livestock')
            ->assertOk()
            ->assertSee('<select id="livestock-animal" name="animal_type"', false)
            ->assertSee('Native chicken')
            ->assertSee('Other, enter below');

        $this->actingAs($owner['user'])->get('/farmer/production?section=fisheries')
            ->assertOk()
            ->assertSee('<select id="fishery-species" name="species"', false)
            ->assertSee('Tilapia');

        $other = $this->farmer();
        $other['farm']->update(['name' => 'Another farmer farm']);
        $foreignUrl = route('farmer.production', [
            'section' => 'crops',
            'farm_id' => $other['farm']->id,
            'report' => 1,
        ]);

        $this->actingAs($owner['user'])->get($foreignUrl)
            ->assertOk()
            ->assertDontSee('Other farm')
            ->assertDontSee('<option value="'.$other['farm']->id.'" selected', false)
            ->assertDontSee('id="production-report-form" class="farmer-disclosure mb-5 rounded-xl bg-sage p-4" open', false);
    }

    public function test_registered_farm_products_are_available_as_category_choices_without_replacing_other_options(): void
    {
        $owner = $this->farmer();
        $owner['farm']->update(['categories' => ['rice', 'tilapia', 'native chicken']]);

        $this->actingAs($owner['user'])->get('/farmer/production?section=crops&farm_id='.$owner['farm']->id.'&report=1')
            ->assertOk()
            ->assertSee('From this farm profile')
            ->assertSee('<option value="rice"', false)
            ->assertSee('<option value="Palay"', false)
            ->assertSee('Other, enter below');

        $this->actingAs($owner['user'])->get('/farmer/production?section=livestock&farm_id='.$owner['farm']->id.'&report=1')
            ->assertOk()
            ->assertSee('<option value="native chicken"', false)
            ->assertSee('Choose livestock');

        $this->actingAs($owner['user'])->get('/farmer/production?section=fisheries&farm_id='.$owner['farm']->id.'&report=1')
            ->assertOk()
            ->assertSee('<option value="tilapia"', false)
            ->assertSee('Tilapia');
    }

    public function test_unverified_farmer_cannot_open_a_seasonal_submission_form_from_a_farm_link(): void
    {
        $pending = $this->farmer('pending');
        $url = route('farmer.production', [
            'section' => 'crops',
            'farm_id' => $pending['farm']->id,
            'report' => 1,
        ]);

        $this->actingAs($pending['user'])->get($url)
            ->assertOk()
            ->assertSee('after the MAO verifies your farmer registration')
            ->assertDontSee('id="production-report-form"', false);
    }

    public function test_other_crop_livestock_and_fishery_choices_are_saved_as_the_entered_type(): void
    {
        $owner = $this->farmer();
        $key = fn () => (string) Str::uuid();
        $common = ['farm_id' => $owner['farm']->id];

        $this->actingAs($owner['user'])->post('/farmer/production/submissions/crop', $common + [
            'crop_type' => '__other__', 'crop_type_other' => 'Dragon fruit', 'planted_on' => '2026-10-01',
            'area_planted' => '0.5', 'area_unit' => 'hectare', 'yield_unit' => 'kilogram', 'status' => 'growing',
            'idempotency_key' => $key(),
        ])->assertRedirect();

        $this->actingAs($owner['user'])->post('/farmer/production/submissions/livestock', $common + [
            'animal_type' => '__other__', 'animal_type_other' => 'Native pig', 'quantity' => 2,
            'recorded_on' => '2026-10-30', 'idempotency_key' => $key(),
        ])->assertRedirect();

        $this->actingAs($owner['user'])->post('/farmer/production/submissions/fishery', $common + [
            'fishery_type' => 'Pond', 'species' => '__other__', 'species_other' => 'Mud crab',
            'production_quantity' => '4', 'production_unit' => 'kilogram', 'recorded_on' => '2026-10-30',
            'idempotency_key' => $key(),
        ])->assertRedirect();

        $this->assertDatabaseCount('production_submissions', 3);
        $this->assertSame('Dragon fruit', ProductionSubmission::where('category', 'crop')->firstOrFail()->payload['crop_type']);
        $this->assertSame('Native pig', ProductionSubmission::where('category', 'livestock')->firstOrFail()->payload['animal_type']);
        $this->assertSame('Mud crab', ProductionSubmission::where('category', 'fishery')->firstOrFail()->payload['species']);
    }

    public function test_farmer_home_surfaces_pending_and_returned_production_actions_only_for_the_owner(): void
    {
        $owner = $this->farmer();
        $other = $this->farmer();
        $pending = ProductionSubmission::create([
            'farmer_id' => $owner['farmer']->id,
            'farm_id' => $owner['farm']->id,
            'category' => 'crop',
            'payload' => ['crop_type' => 'Owner pending rice'],
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        ProductionSubmission::create([
            'farmer_id' => $owner['farmer']->id,
            'farm_id' => $owner['farm']->id,
            'category' => 'livestock',
            'payload' => ['animal_type' => 'Owner returned goats'],
            'status' => 'returned',
            'review_notes' => 'Confirm the updated count.',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        ProductionSubmission::create([
            'farmer_id' => $other['farmer']->id,
            'farm_id' => $other['farm']->id,
            'category' => 'crop',
            'payload' => ['crop_type' => 'Private other farmer rice'],
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($owner['user'])->get('/farmer')->assertOk()
            ->assertSee('Recent service activity')
            ->assertSee('Owner pending rice')
            ->assertSee('Owner returned goats')
            ->assertSee('MAO requested a correction')
            ->assertSee('Confirm the updated count.')
            ->assertSee(route('farmer.production.submissions.show', $pending), false)
            ->assertDontSee('Private other farmer rice');
    }

    public function test_crop_stage_controls_planting_and_harvest_date_requirements(): void
    {
        $owner = $this->farmer();
        $planned = [
            'farm_id' => $owner['farm']->id,
            'crop_type' => 'Rice',
            'planted_on' => today()->addDays(10)->toDateString(),
            'area_planted' => '1.25',
            'area_unit' => 'hectare',
            'yield_unit' => 'kilogram',
            'status' => 'planned',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($owner['user'])->get('/farmer/production?section=crops')
            ->assertOk()->assertSee('data-crop-stage', false)->assertSee('future planting date');
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/crop', $planned)->assertRedirect();
        $submission = ProductionSubmission::firstOrFail();
        $this->assertSame('planned', $submission->payload['status']);
        $this->assertSame(today()->addDays(10)->toDateString(), $submission->payload['planted_on']);

        $pastPlanned = array_replace($planned, [
            'planted_on' => today()->subDay()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/crop', $pastPlanned)
            ->assertSessionHasErrors('planted_on');

        $harvestedWithoutDate = array_replace($planned, [
            'status' => 'harvested',
            'planted_on' => today()->subDays(30)->toDateString(),
            'harvested_on' => null,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/crop', $harvestedWithoutDate)
            ->assertSessionHasErrors('harvested_on');
        $this->assertDatabaseCount('production_submissions', 1);
    }

    public function test_crop_submission_area_cannot_exceed_registered_farm_area_across_units(): void
    {
        $owner = $this->farmer();
        $owner['farm']->update(['area' => 7, 'area_unit' => 'hectare']);
        $cropSubmission = fn (string $area, string $unit): array => [
            'farm_id' => $owner['farm']->id,
            'crop_type' => 'Rice',
            'planted_on' => today()->subDays(10)->toDateString(),
            'area_planted' => $area,
            'area_unit' => $unit,
            'yield_unit' => 'kilogram',
            'status' => 'growing',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($owner['user'])
            ->from(route('farmer.production', [
                'section' => 'crops',
                'farm_id' => $owner['farm']->id,
                'report' => 1,
            ]))
            ->followingRedirects()
            ->post('/farmer/production/submissions/crop', $cropSubmission('8', 'hectare'))
            ->assertOk()
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('The crop area (8 hectares) cannot exceed this farm&#039;s registered area (7 hectares).', false);
        $this->assertDatabaseCount('production_submissions', 0);

        $this->actingAs($owner['user'])
            ->post('/farmer/production/submissions/crop', $cropSubmission('7', 'hectare'))
            ->assertRedirect();
        $this->actingAs($owner['user'])
            ->post('/farmer/production/submissions/crop', $cropSubmission('70000', 'square_meter'))
            ->assertRedirect();
        $this->actingAs($owner['user'])
            ->post('/farmer/production/submissions/crop', $cropSubmission('70001', 'square_meter'))
            ->assertSessionHasErrors('area_planted');

        $owner['farm']->update(['area' => 70000, 'area_unit' => 'square_meter']);
        $this->actingAs($owner['user'])
            ->post('/farmer/production/submissions/crop', $cropSubmission('7', 'hectare'))
            ->assertRedirect();
        $this->actingAs($owner['user'])
            ->post('/farmer/production/submissions/crop', $cropSubmission('7.01', 'hectare'))
            ->assertSessionHasErrors('area_planted');

        $this->assertDatabaseCount('production_submissions', 3);
    }

    public function test_crop_resubmission_cannot_exceed_the_current_registered_farm_area(): void
    {
        $owner = $this->farmer();
        $owner['farm']->update(['area' => 7, 'area_unit' => 'hectare']);
        $submission = ProductionSubmission::create([
            'farmer_id' => $owner['farmer']->id,
            'farm_id' => $owner['farm']->id,
            'category' => 'crop',
            'payload' => ['crop_type' => 'Rice', 'area_planted' => 6, 'area_unit' => 'hectare'],
            'status' => 'returned',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($owner['user'])
            ->post('/farmer/production/submissions/'.$submission->id.'/resubmit', [
                'farm_id' => $owner['farm']->id,
                'crop_type' => 'Rice',
                'planted_on' => today()->subDays(10)->toDateString(),
                'area_planted' => 8,
                'area_unit' => 'hectare',
                'yield_unit' => 'kilogram',
                'status' => 'growing',
            ])
            ->assertSessionHasErrors('area_planted');

        $this->assertDatabaseHas('production_submissions', ['id' => $submission->id, 'status' => 'returned']);
        $this->assertSame(6, $submission->fresh()->payload['area_planted']);
    }

    public function test_mao_cannot_approve_a_crop_submission_that_exceeds_the_registered_farm_area(): void
    {
        $owner = $this->farmer();
        $owner['farm']->update(['area' => 7, 'area_unit' => 'hectare']);
        $staff = $this->reviewer();
        $submission = ProductionSubmission::create([
            'farmer_id' => $owner['farmer']->id,
            'farm_id' => $owner['farm']->id,
            'category' => 'crop',
            'payload' => ['crop_type' => 'Rice', 'planted_on' => today()->subDays(10)->toDateString(), 'area_planted' => 8, 'area_unit' => 'hectare', 'yield_unit' => 'kilogram', 'status' => 'growing'],
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($staff)
            ->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'approved'])
            ->assertSessionHasErrors('area_planted');

        $this->assertDatabaseHas('production_submissions', ['id' => $submission->id, 'status' => 'pending', 'reviewed_by' => null]);
        $this->assertDatabaseCount('crop_production_records', 0);
        $this->assertDatabaseMissing('production_submission_histories', [
            'production_submission_id' => $submission->id,
            'to_status' => 'approved',
        ]);
    }

    public function test_return_resubmit_reject_and_invalid_review_transitions(): void
    {
        $owner = $this->farmer();
        $staff = $this->reviewer();
        $payload = ['farm_id' => $owner['farm']->id, 'fishery_type' => 'Pond', 'production_quantity' => 5, 'production_unit' => 'kilogram', 'recorded_on' => '2026-10-30'];
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/fishery', $payload + ['idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $submission = ProductionSubmission::firstOrFail();
        $this->actingAs($owner['user'])->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'approved'])->assertForbidden();
        $auditorRole = Role::firstOrCreate(['slug' => 'auditor'], ['name' => 'Auditor', 'permissions' => ['modules.apms.view'], 'is_active' => true]);
        $auditor = User::factory()->create();
        $auditor->roles()->attach($auditorRole);
        $this->actingAs($auditor)->get('/modules/apms/submissions')->assertOk();
        $this->actingAs($auditor)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($staff)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'returned'])->assertSessionHasErrors('review_notes');
        $this->actingAs($staff)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'returned', 'review_notes' => 'Please confirm the measured quantity.'])->assertRedirect();
        $this->actingAs($owner['user'])->get('/farmer/production/submissions/'.$submission->id)->assertOk()
            ->assertSee('The MAO requested a correction. Read the feedback and use the correction form below to resubmit.')
            ->assertSee('MAO returned the information for correction')
            ->assertSee('Please confirm the measured quantity.')->assertSee('<svg', false);
        $this->actingAs($owner['user'])->get('/farmer/activities?type=Agricultural%20submission&status=returned')
            ->assertOk()->assertSee('Returned')->assertSee('Please confirm the measured quantity.');
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/'.$submission->id.'/resubmit', array_replace($payload, ['production_quantity' => 7]))->assertRedirect();
        $this->actingAs($owner['user'])->get('/farmer/production/submissions/'.$submission->id)->assertOk()
            ->assertSee('Corrected information resubmitted')->assertSee('Pending');
        $this->actingAs($staff)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'rejected', 'review_notes' => 'Unverifiable information.'])->assertRedirect();
        $this->actingAs($staff)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'approved'])->assertSessionHasErrors('decision');
        $this->actingAs($owner['user'])->get('/farmer/notifications')->assertOk()
            ->assertSee('View production submission')
            ->assertSee(route('farmer.production.submissions.show', $submission), false);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('fishery_records', 0);
        $this->assertDatabaseHas('production_submissions', ['id' => $submission->id, 'status' => 'rejected']);
        $this->assertSame(4, $submission->history()->count());
    }

    public function test_apms_review_queue_filters_preserve_context_and_read_only_roles_cannot_decide(): void
    {
        $owner = $this->farmer();
        $staff = $this->reviewer();
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/crop', [
            'farm_id' => $owner['farm']->id,
            'crop_type' => 'Queue test rice',
            'planted_on' => '2026-10-10',
            'area_planted' => '1.50',
            'area_unit' => 'hectare',
            'yield_unit' => 'kilogram',
            'status' => 'growing',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $submission = ProductionSubmission::firstOrFail();

        $queue = route('modules.apms.submissions', ['status' => 'pending', 'search' => 'Owner farm']);
        $detail = route('modules.apms.submissions.show', ['submission' => $submission, 'status' => 'pending', 'search' => 'Owner farm']);

        $this->actingAs($staff)->get($queue)
            ->assertOk()
            ->assertSee('Production review queue')
            ->assertSee('Owner farm')
            ->assertSee('Queue test rice')
            ->assertSee('Submission #'.$submission->id)
            ->assertSee('Pending')
            ->assertSee(e($detail), false)
            ->assertSee('Review submission');

        $this->actingAs($staff)->get($detail)
            ->assertOk()
            ->assertSee('Submitted information')
            ->assertSee('Review history')
            ->assertSee('Decision')
            ->assertSee('data-apms-review-decision', false)
            ->assertSee('data-apms-review-notes', false)
            ->assertSee(e($queue), false);

        $auditorRole = Role::firstOrCreate(['slug' => 'auditor'], ['name' => 'Auditor', 'permissions' => ['modules.apms.view'], 'is_active' => true]);
        $auditor = User::factory()->create();
        $auditor->roles()->attach($auditorRole);
        $this->actingAs($auditor)->get($detail)
            ->assertOk()
            ->assertSee('Read-only review')
            ->assertDontSee('data-apms-review-form', false);
        $this->actingAs($auditor)->post(route('modules.apms.submissions.review', $submission), ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($staff)->post(route('modules.apms.submissions.review', $submission), [
            'decision' => 'approved',
            'review_notes' => 'Field record checked.',
            'queue_status' => 'pending',
            'queue_search' => 'Owner farm',
        ])->assertRedirect($detail);

        $this->assertDatabaseHas('production_submissions', ['id' => $submission->id, 'status' => 'approved', 'reviewed_by' => $staff->id]);
        $this->assertDatabaseHas('production_submission_histories', ['production_submission_id' => $submission->id, 'to_status' => 'approved']);
        $this->assertDatabaseHas('crop_production_records', ['farmer_id' => $owner['farmer']->id, 'farm_id' => $owner['farm']->id, 'crop_type' => 'Queue test rice']);
    }

    public function test_existing_farmer_pages_render_while_phase_7b_tables_are_pending(): void
    {
        $owner = $this->farmer();
        Schema::dropIfExists('production_submission_histories');
        Schema::dropIfExists('production_submissions');
        Schema::dropIfExists('agricultural_announcements');

        $this->actingAs($owner['user'])->get('/farmer')->assertOk()->assertDontSee('All notices');
        $this->actingAs($owner['user'])->get('/farmer/services')->assertOk()->assertDontSee('All notices');
        $this->actingAs($owner['user'])->get('/farmer/production')->assertOk()
            ->assertDontSee('Send crop information')
            ->assertSee('Seasonal production reporting is unavailable right now.');
        $this->actingAs($owner['user'])->get('/farmer/activities')->assertOk();
    }

    public function test_submission_cannot_be_deleted_while_review_history_exists(): void
    {
        $owner = $this->farmer();
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/livestock', [
            'farm_id' => $owner['farm']->id,
            'animal_type' => 'Goat',
            'quantity' => 2,
            'recorded_on' => '2026-10-30',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $submission = ProductionSubmission::firstOrFail();

        try {
            $submission->delete();
            $this->fail('A submission with status history must be protected from deletion.');
        } catch (QueryException) {
            // The database restriction must preserve both the submission and its audit history.
        }

        $this->assertDatabaseHas('production_submissions', ['id' => $submission->id]);
        $this->assertDatabaseHas('production_submission_histories', [
            'production_submission_id' => $submission->id,
            'to_status' => 'pending',
        ]);
    }
}
