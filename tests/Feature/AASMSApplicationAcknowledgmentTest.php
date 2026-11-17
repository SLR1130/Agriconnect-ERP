<?php

namespace Tests\Feature;

use App\Models\AssistanceApplication;
use App\Models\AssistanceProgram;
use App\Models\AssistanceProgramDocumentRequirement;
use App\Models\AssistanceProgramRequirementVersion;
use App\Models\AssistanceStatusHistory;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Services\AssistanceApplicationSubmissionService;
use App\Services\ProgramRequirementVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class AASMSApplicationAcknowledgmentTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(string $code = 'QA-F-001', string $status = 'verified'): array
    {
        $role = Role::firstOrCreate(['slug' => 'farmer'], ['name' => 'Farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create([
            'user_id' => $user->id,
            'farmer_code' => $code,
            'barangay' => 'Synthetic QA Barangay',
            'registration_status' => $status,
        ]);

        return compact('user', 'farmer');
    }

    private function superAdmin(): User
    {
        $role = Role::create(['name' => 'Super Administrator', 'slug' => 'super_admin', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function program(array $attributes = []): AssistanceProgram
    {
        return AssistanceProgram::create(array_merge([
            'name' => 'Synthetic QA Seed Support',
            'category' => 'Synthetic QA',
            'description' => 'Synthetic test program only.',
            'status' => 'open',
            'opens_on' => null,
            'closes_on' => null,
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => false],
        ], $attributes));
    }

    public function test_farmer_submission_atomically_saves_reference_history_version_and_immutable_acknowledgment(): void
    {
        $account = $this->farmer();
        $program = $this->program();
        $key = (string) Str::uuid();

        $this->actingAs($account['user'])
            ->get(route('farmer.programs.show', $program))
            ->assertOk()
            ->assertSee('Program document checklist')
            ->assertSee('Online document upload is not available yet.')
            ->assertSee('submission_key', false);

        $response = $this->actingAs($account['user'])->post(route('farmer.assistance.apply', $program), [
            'purpose' => 'Synthetic QA application purpose.',
            'submission_key' => $key,
        ]);

        $application = AssistanceApplication::query()->where('program_id', $program->id)->where('farmer_id', $account['farmer']->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^AASMS-\d{4}-[A-F0-9]{16}$/', $application->application_reference);
        $this->assertSame('submitted', $application->status);
        $this->assertSame('farmer_portal', $application->submission_channel);
        $this->assertSame($key, $application->submission_key);
        $this->assertNotNull($application->submitted_at);
        $this->assertNotNull($application->submitted_business_date);
        $this->assertNotNull($application->requirement_version_id);
        $response->assertRedirect(route('farmer.applications.show', $application));

        $this->assertDatabaseHas('assistance_status_histories', [
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => 'submitted',
            'changed_by' => $account['user']->id,
        ]);
        $ack = $application->acknowledgment()->firstOrFail();
        $this->assertSame($application->application_reference, $ack->application_reference);
        $this->assertSame($application->id, data_get($ack->snapshot, 'application.id'));
        $this->assertSame($application->application_reference, data_get($ack->snapshot, 'application.reference'));
        $this->assertSame('submitted', data_get($ack->snapshot, 'application.status_at_submission'));
        $this->assertSame('presented_at_submission', data_get($ack->snapshot, 'requirements_checklist.state'));
        $this->assertSame([], data_get($ack->snapshot, 'requirements_checklist.items'));
        $this->assertSame('not_enabled_in_phase_6c_1', data_get($ack->snapshot, 'document_receipt.collection_status'));
        $this->assertNull(data_get($ack->snapshot, 'document_receipt.missing'));
        $this->assertSame(hash('sha256', json_encode($ack->snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $ack->snapshot_sha256);
        $this->assertDatabaseCount('assistance_application_acknowledgments', 1);

        $this->actingAs($account['user'])->get(route('farmer.applications.show', $application))
            ->assertOk()->assertSee($application->application_reference)->assertSee('Download acknowledgment');
        $print = $this->actingAs($account['user'])->get(route('farmer.applications.acknowledgment', $application))
            ->assertOk()
            ->assertSee('THIS DOCUMENT ACKNOWLEDGES RECEIPT OF AN APPLICATION. IT DOES NOT GUARANTEE APPROVAL OR ENTITLEMENT TO GOVERNMENT ASSISTANCE.')
            ->assertSee($application->application_reference)
            ->assertSee('No supporting files were received through this online application.');
        $this->assertStringContainsString('inline;', $print->headers->get('Content-Disposition'));

        $download = $this->actingAs($account['user'])->get(route('farmer.applications.acknowledgment', ['application' => $application, 'download' => 1]))->assertOk();
        $this->assertStringContainsString('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('private', $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));

        $anotherFarmer = $this->farmer('QA-F-002');
        $this->actingAs($anotherFarmer['user'])->get(route('farmer.applications.acknowledgment', $application))->assertNotFound();
        $noAasmsRole = User::factory()->create();
        $this->actingAs($noAasmsRole)->get(route('modules.aasms.applications.acknowledgment', $application))->assertForbidden();
        $this->actingAs($anotherFarmer['user'])->get('/farmer/applications/'.$application->application_reference)->assertNotFound();

        $originalSnapshot = $ack->snapshot;
        $program->update(['name' => 'Later edited program name', 'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => true]]);
        $this->assertSame($originalSnapshot, $ack->fresh()->snapshot);
        $this->expectException(LogicException::class);
        $ack->update(['snapshot_sha256' => str_repeat('0', 64)]);
    }

    public function test_retry_with_same_submission_key_returns_the_same_application_and_different_key_is_a_duplicate(): void
    {
        $account = $this->farmer();
        $program = $this->program();
        $key = (string) Str::uuid();
        $payload = ['purpose' => 'Synthetic retry test.', 'submission_key' => $key];

        $this->actingAs($account['user'])->post(route('farmer.assistance.apply', $program), $payload)->assertRedirect();
        $first = AssistanceApplication::firstOrFail();
        $this->actingAs($account['user'])->post(route('farmer.assistance.apply', $program), $payload)
            ->assertRedirect(route('farmer.applications.show', $first));
        $this->actingAs($account['user'])->post(route('farmer.assistance.apply', $program), ['submission_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('program');

        $this->assertDatabaseCount('assistance_applications', 1);
        $this->assertDatabaseCount('assistance_status_histories', 1);
        $this->assertDatabaseCount('assistance_application_acknowledgments', 1);
    }

    public function test_return_and_farmer_resubmission_keep_the_same_reference_acknowledgment_and_atomic_history(): void
    {
        $account = $this->farmer();
        $staff = $this->superAdmin();
        $program = $this->program();
        $this->actingAs($account['user'])->post(route('farmer.assistance.apply', $program), ['submission_key' => (string) Str::uuid()])->assertRedirect();
        $application = AssistanceApplication::firstOrFail()->load('acknowledgment');
        $reference = $application->application_reference;
        $snapshot = $application->acknowledgment->snapshot;

        $this->actingAs($staff)->post(route('modules.aasms.review', $application), [
            'status' => 'returned',
            'review_notes' => 'Synthetic QA correction request.',
        ])->assertRedirect();
        $this->actingAs($account['user'])->post(route('farmer.assistance.resubmit', $application), ['purpose' => 'Synthetic corrected response.'])->assertRedirect();

        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertSame($reference, $application->fresh()->application_reference);
        $this->assertSame($snapshot, $application->fresh()->acknowledgment->snapshot);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $application->id, 'from_status' => 'returned', 'to_status' => 'submitted']);
        $this->assertDatabaseCount('assistance_status_histories', 3);

        $this->actingAs($account['user'])->post(route('farmer.assistance.resubmit', $application), ['purpose' => 'Duplicate correction retry.'])->assertForbidden();
        $this->assertDatabaseCount('assistance_status_histories', 3);
        $this->assertDatabaseCount('assistance_application_acknowledgments', 1);
    }

    public function test_aasms_staff_can_publish_a_new_requirement_version_without_rewriting_prior_application_evidence(): void
    {
        $account = $this->farmer();
        $program = $this->program();
        $this->actingAs($account['user'])->post(route('farmer.assistance.apply', $program), ['submission_key' => (string) Str::uuid()])->assertRedirect();
        $oldApplication = AssistanceApplication::firstOrFail()->load('acknowledgment', 'requirementVersion');
        $oldVersionId = $oldApplication->requirement_version_id;
        $oldSnapshot = $oldApplication->acknowledgment->snapshot;
        $staff = $this->superAdmin();

        $this->actingAs($staff)->patch(route('modules.aasms.programs.update', $program), [
            'name' => $program->name,
            'category' => $program->category,
            'description' => $program->description,
            'opens_on' => '',
            'closes_on' => '',
            'requires_farm' => '1',
            'barangays' => '',
            'activities' => '',
            'farm_categories' => '',
            'requirements' => [[
                'key' => 'synthetic-qa-proof',
                'display_name' => 'Synthetic QA supporting document',
                'description' => 'Synthetic test content; not an official MAO requirement.',
                'instructions' => 'For isolated automated tests only.',
                'required' => 'required',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_mib' => '3',
            ]],
        ])->assertRedirect();

        $draft = AssistanceProgramRequirementVersion::query()->where('program_id', $program->id)->where('status', 'draft')->firstOrFail();
        $this->assertSame($oldVersionId, (int) $program->fresh()->currentRequirementVersion()->firstOrFail()->id);
        $this->assertSame('Synthetic QA supporting document', $draft->documentRequirements()->firstOrFail()->display_name);
        $this->assertSame(3 * 1024 * 1024, (int) $draft->documentRequirements()->firstOrFail()->max_size_bytes);
        $this->assertNull($program->fresh()->opens_on);

        $this->actingAs($staff)->post(route('modules.aasms.requirements.publish', [$program, $draft]))->assertRedirect();
        $this->assertSame('published', $draft->fresh()->status);
        $this->assertSame($staff->id, $draft->fresh()->published_by);
        $this->assertSame($oldVersionId, $oldApplication->fresh()->requirement_version_id);
        $this->assertSame($oldSnapshot, $oldApplication->fresh()->acknowledgment->snapshot);
    }

    public function test_requirements_editor_publishes_with_actor_and_new_farmer_submission_uses_that_version(): void
    {
        $staff = $this->superAdmin();
        $program = $this->program(['status' => 'closed']);
        $version = app(ProgramRequirementVersionService::class)->saveDraft($program, [
            'opens_on' => null,
            'closes_on' => null,
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => false],
            'requirements' => [[
                'key' => 'synthetic-id-proof',
                'display_name' => 'Synthetic QA identity example',
                'description' => 'Synthetic only.',
                'instructions' => 'No real documentation is requested.',
                'required' => 'optional',
                'accepted_mime_types' => ['image/jpeg'],
                'max_size_mib' => 2,
            ]],
        ], $staff->id);

        $this->actingAs($staff)->post(route('modules.aasms.requirements.publish', [$program, $version]))->assertRedirect();
        $published = $version->fresh();
        $this->assertSame('published', $published->status);
        $this->assertSame($staff->id, $published->published_by);
        $this->assertNotNull($published->published_at);
        $this->assertNull($program->fresh()->opens_on);
        $this->actingAs($staff)->post(route('modules.aasms.programs.toggle', $program))->assertRedirect();

        $account = $this->farmer('QA-F-003');
        $this->actingAs($account['user'])->get(route('farmer.programs.show', $program))->assertOk()->assertSee('Synthetic QA identity example');
        $this->actingAs($account['user'])->post(route('farmer.assistance.apply', $program), ['submission_key' => (string) Str::uuid()])->assertRedirect();
        $application = AssistanceApplication::query()->where('farmer_id', $account['farmer']->id)->firstOrFail();
        $this->assertSame($published->id, $application->requirement_version_id);
        $this->assertSame('Synthetic QA identity example', data_get($application->acknowledgment->snapshot, 'requirements_version.documents.0.name'));
        $this->assertSame('Synthetic QA identity example', data_get($application->acknowledgment->snapshot, 'requirements_checklist.items.0.name'));
        $this->assertSame($staff->name, data_get($application->acknowledgment->snapshot, 'requirements_version.publisher_name'));
        $this->actingAs($staff)->get(route('modules.aasms.dashboard', ['workspace' => 'applications']))
            ->assertOk()->assertSee($application->application_reference)->assertSee(route('modules.aasms.applications.show', $application), false);
        $this->actingAs($staff)->get(route('modules.aasms.applications.show', $application))
            ->assertOk()->assertSee($application->application_reference)->assertSee('Synthetic QA identity example');
        $this->actingAs($staff)->get(route('modules.aasms.applications.acknowledgment', $application))
            ->assertOk()->assertSee($application->application_reference);

        $this->expectException(LogicException::class);
        $published->update(['eligibility_rules' => ['requires_farm' => true]]);
    }
}
