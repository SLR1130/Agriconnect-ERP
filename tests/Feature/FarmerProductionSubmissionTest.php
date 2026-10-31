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
        $this->actingAs($owner['user'])->get('/farmer/production/submissions/'.$submission->id)->assertOk()->assertSee('Please confirm the measured quantity.');
        $this->actingAs($owner['user'])->post('/farmer/production/submissions/'.$submission->id.'/resubmit', array_replace($payload, ['production_quantity' => 7]))->assertRedirect();
        $this->actingAs($staff)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'rejected', 'review_notes' => 'Unverifiable information.'])->assertRedirect();
        $this->actingAs($staff)->post('/modules/apms/submissions/'.$submission->id.'/review', ['decision' => 'approved'])->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('fishery_records', 0);
        $this->assertDatabaseHas('production_submissions', ['id' => $submission->id, 'status' => 'rejected']);
        $this->assertSame(4, $submission->history()->count());
    }

    public function test_existing_farmer_pages_render_while_phase_7b_tables_are_pending(): void
    {
        $owner = $this->farmer();
        Schema::dropIfExists('production_submission_histories');
        Schema::dropIfExists('production_submissions');
        Schema::dropIfExists('agricultural_announcements');

        $this->actingAs($owner['user'])->get('/farmer')->assertOk()->assertDontSee('All notices');
        $this->actingAs($owner['user'])->get('/farmer/services')->assertOk()->assertDontSee('All notices');
        $this->actingAs($owner['user'])->get('/farmer/production')->assertOk()->assertDontSee('Send crop information');
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
