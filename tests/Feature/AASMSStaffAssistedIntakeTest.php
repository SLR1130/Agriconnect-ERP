<?php

namespace Tests\Feature;

use App\Contracts\PrivateApplicationDocumentScanner;
use App\Models\AssistanceApplication;
use App\Models\AssistanceApplicationDocument;
use App\Models\AssistanceProgram;
use App\Models\AssistanceProgramRequirementVersion;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Services\ErpAuthorizationService;
use App\Services\ClamAvApplicationDocumentScanner;
use App\Services\ProgramRequirementVersionService;
use App\Services\StaffAssistedIntakePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AASMSStaffAssistedIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agricultural_staff_assisted.enabled' => true,
            'agricultural_staff_assisted.qa_isolated_database' => true,
            'agricultural_staff_assisted.synthetic_qa_attestations' => true,
            'agricultural_staff_assisted.identity_confirmation_methods' => ['synthetic_qa'],
            'agricultural_staff_assisted.consent_policy_version' => 'synthetic-qa-only-v1',
            'agricultural_documents.enabled' => false,
        ]);
    }

    private function aasmsModule(): Module
    {
        return Module::firstOrCreate(['slug' => 'aasms'], [
            'name' => 'Agricultural Assistance and Subsidy Management',
            'route_prefix' => 'aasms',
            'is_enabled' => true,
            'is_active' => true,
        ]);
    }

    private function staff(array $permissions = ['intake_assist']): User
    {
        $role = Role::firstOrCreate(['slug' => 'module_personnel'], [
            'name' => 'Module Personnel',
            'permissions' => ['dashboard.view'],
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        UserModuleAccess::create([
            'user_id' => $user->id,
            'module_id' => $this->aasmsModule()->id,
            'permissions' => $permissions,
        ]);

        return $user;
    }

    private function farmer(string $code = 'QA-FARMER-001', string $status = 'verified', bool $withFarm = true): array
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
        if ($withFarm) {
            Farm::create([
                'farmer_id' => $farmer->id,
                'name' => 'Synthetic QA Farm',
                'barangay' => 'Synthetic QA Barangay',
                'area' => 2.5,
                'area_unit' => 'hectare',
                'status' => 'active',
                'agricultural_activities' => ['rice'],
                'categories' => ['crops'],
            ]);
        }

        return compact('user', 'farmer');
    }

    private function program(array $overrides = []): AssistanceProgram
    {
        return AssistanceProgram::create(array_merge([
            'name' => 'Synthetic QA Seed Support',
            'category' => 'Synthetic QA only',
            'description' => 'Not an official government program. Used only in isolated tests.',
            'status' => 'open',
            'opens_on' => null,
            'closes_on' => null,
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => false],
        ], $overrides));
    }

    private function submissionPayload(array $farmer, AssistanceProgram $program, ?string $key = null): array
    {
        return [
            'farmer_id' => $farmer['farmer']->id,
            'program_id' => $program->id,
            'purpose' => 'Synthetic QA farmer purpose.',
            'submission_key' => $key ?: (string) Str::uuid(),
            'identity_confirmation_method' => 'synthetic_qa',
            'synthetic_qa_attestation' => '1',
        ];
    }

    private function intakeUrl(array $farmer, AssistanceProgram $program): string
    {
        return route('modules.aasms.intake', [
            'search' => $farmer['farmer']->farmer_code,
            'farmer_id' => $farmer['farmer']->id,
            'program_id' => $program->id,
        ]);
    }

    private function publishPdfRequirement(AssistanceProgram $program, User $publisher): AssistanceProgramRequirementVersion
    {
        $versions = app(ProgramRequirementVersionService::class);
        $draft = $versions->saveDraft($program, [
            'opens_on' => null,
            'closes_on' => null,
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => false],
            'requirements' => [[
                'key' => 'synthetic-qa-proof',
                'display_name' => 'Synthetic QA document',
                'description' => 'Test content only. Not an official MAO requirement.',
                'instructions' => 'Use generated test content only.',
                'required' => 'required',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_mib' => 2,
            ]],
        ], $publisher->id);

        return $versions->publish($draft, $publisher->id);
    }

    private function fakeScanner(string $status, bool $available = true): PrivateApplicationDocumentScanner
    {
        return new class($status, $available) implements PrivateApplicationDocumentScanner {
            public function __construct(private string $status, private bool $isAvailable) {}
            public function scan(string $absolutePath): array { return ['status' => $this->status, 'engine' => $this->isAvailable ? 'SyntheticQA' : null]; }
            public function available(): bool { return $this->isAvailable; }
        };
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('synthetic-qa.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    public function test_exact_intake_grant_renders_guided_workspace_and_submits_through_shared_aasms_service(): void
    {
        $staff = $this->staff(['view', 'intake_assist']);
        $farmer = $this->farmer();
        $program = $this->program();

        $this->actingAs($staff)->get(route('modules.aasms.intake'))
            ->assertOk()->assertSee('Find the FFRMS farmer record')->assertDontSee($farmer['user']->name);
        $this->actingAs($staff)->get($this->intakeUrl($farmer, $program))
            ->assertOk()
            ->assertSee('Find the FFRMS farmer record')
            ->assertSee('FFRMS verified')
            ->assertSee('Synthetic QA Farm')
            ->assertSee('Review before submission')
            ->assertSee('Submit through AASMS')
            ->assertSee('not a farmer signature', false)
            ->assertSee('name="_token"', false);

        $payload = $this->submissionPayload($farmer, $program);
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $payload)
            ->assertRedirect();

        $application = AssistanceApplication::query()->where('program_id', $program->id)->where('farmer_id', $farmer['farmer']->id)->firstOrFail();
        $application->load('acknowledgment');
        $this->assertSame('mao_staff_assisted', $application->submission_channel);
        $this->assertSame('submitted', $application->status);
        $this->assertNotEmpty($application->application_reference);
        $this->assertNotNull($application->requirement_version_id);
        $this->assertSame($staff->id, (int) data_get($application->acknowledgment->snapshot, 'intake.actor_user_id'));
        $this->assertSame($staff->name, data_get($application->acknowledgment->snapshot, 'intake.actor_name'));
        $this->assertSame('synthetic_qa', data_get($application->acknowledgment->snapshot, 'intake.identity_confirmation.method'));
        $this->assertSame('synthetic_qa_only', data_get($application->acknowledgment->snapshot, 'intake.consent_attestation.evidence_type'));
        $this->assertStringContainsString('No farmer signature', data_get($application->acknowledgment->snapshot, 'intake.consent_attestation.statement'));
        $this->assertSame(hash('sha256', json_encode($application->acknowledgment->snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), $application->acknowledgment->snapshot_sha256);
        $this->assertDatabaseHas('assistance_status_histories', [
            'application_id' => $application->id,
            'to_status' => 'submitted',
            'changed_by' => $staff->id,
        ]);
        $this->assertDatabaseHas('assistance_application_submission_snapshots', [
            'application_id' => $application->id,
            'submission_type' => 'initial',
            'created_by' => $staff->id,
        ]);

        $this->actingAs($staff)->get(route('modules.aasms.intake.applications.show', $application))
            ->assertOk()->assertSee($application->application_reference)->assertSee('Print acknowledgment');
        $this->actingAs($staff)->get(route('modules.aasms.intake.acknowledgment', $application))
            ->assertOk()
            ->assertSee($application->application_reference)
            ->assertSee($staff->name)
            ->assertSee('not a farmer signature')
            ->assertSee('DOES NOT GUARANTEE APPROVAL OR ENTITLEMENT');
        $this->actingAs($farmer['user'])->get(route('farmer.applications.show', $application))
            ->assertOk()->assertSee('MAO-assisted submission')->assertSee($staff->name);
        $this->actingAs($staff)->get(route('modules.aasms.dashboard', ['status' => 'submitted']))
            ->assertOk()->assertSee($application->application_reference);

        // Intake alone does not grant private evidence access or an MAO decision.
        $intakeOnly = $this->staff(['intake_assist']);
        $this->actingAs($intakeOnly)->get(route('modules.aasms.applications.documents.file', [$application, 999]))->assertForbidden();
        $this->actingAs($intakeOnly)->get(route('modules.aasms.applications.show', $application))->assertForbidden();
        $this->assertFalse($intakeOnly->canModuleAction('aasms', 'approve'));
        $this->assertFalse($intakeOnly->canModuleAction('aasms', 'review'));
        $this->assertFalse($intakeOnly->canModuleAction('aasms', 'documents_view_sensitive'));
    }

    public function test_generic_aasms_view_and_create_grants_do_not_authorize_intake_or_show_its_navigation(): void
    {
        $generic = $this->staff(['view', 'create']);
        $farmer = $this->farmer();
        $program = $this->program();

        $this->assertTrue($generic->canModuleAction('aasms', 'view'));
        $this->assertTrue($generic->canModuleAction('aasms', 'create'));
        $this->assertFalse($generic->canModuleAction('aasms', 'intake_assist'));
        $this->actingAs($generic)->get(route('modules.aasms.intake'))->assertForbidden();
        $this->actingAs($generic)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program))->assertForbidden();
        $this->actingAs($generic)->get(route('modules.aasms.dashboard'))->assertOk()->assertDontSee('Staff-assisted intake');
    }

    public function test_super_admin_permission_inspector_labels_the_effective_intake_assignment(): void
    {
        $this->staff(['intake_assist']);
        $adminRole = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator', 'permissions' => [], 'is_active' => true]);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin)->get(route('erp.users.index'))
            ->assertOk()
            ->assertSee('Assist farmer application intake (isolated QA only)');
    }

    public function test_only_verified_farmer_and_current_program_eligibility_allow_submission(): void
    {
        $staff = $this->staff();
        $unverified = $this->farmer('QA-PENDING-001', 'pending');
        $program = $this->program();

        $this->actingAs($staff)->get($this->intakeUrl($unverified, $program))
            ->assertOk()->assertSee('Do not submit yet')->assertDontSee('Submit through AASMS');
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($unverified, $program))
            ->assertSessionHasErrors('farmer_id');
        $this->assertDatabaseCount('assistance_applications', 0);

        $noFarm = $this->farmer('QA-NOFARM-001', 'verified', false);
        $farmRequired = $this->program(['name' => 'Synthetic farm-required support', 'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => true]]);
        $this->actingAs($staff)->get($this->intakeUrl($noFarm, $farmRequired))
            ->assertOk()->assertSee('A registered farm is required')->assertDontSee('Submit through AASMS');
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($noFarm, $farmRequired))
            ->assertSessionHasErrors('program');
        $this->assertDatabaseCount('assistance_applications', 0);
    }

    public function test_closed_period_and_unpublished_program_cannot_be_submitted_by_direct_post_or_guessed_url(): void
    {
        $staff = $this->staff();
        $farmer = $this->farmer();
        $upcoming = $this->program(['name' => 'Synthetic upcoming program', 'opens_on' => now()->addDay()->toDateString()]);
        $this->actingAs($staff)->get($this->intakeUrl($farmer, $upcoming))
            ->assertOk()->assertSee('This program is not accepting applications')->assertDontSee('Submit through AASMS');
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $upcoming))
            ->assertSessionHasErrors('program');

        $unpublished = $this->program(['name' => 'Synthetic unpublished program', 'status' => 'closed']);
        $this->actingAs($staff)->get(route('modules.aasms.intake', ['farmer_id' => $farmer['farmer']->id, 'program_id' => $unpublished->id]))->assertNotFound();
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), array_replace($this->submissionPayload($farmer, $unpublished), ['program_id' => $unpublished->id]))
            ->assertSessionHasErrors('program_id');
        $this->assertDatabaseCount('assistance_applications', 0);
    }

    public function test_submission_retry_is_idempotent_duplicate_is_blocked_and_acknowledgment_reference_stays_stable(): void
    {
        $staff = $this->staff();
        $farmer = $this->farmer();
        $program = $this->program();
        $key = (string) Str::uuid();
        $payload = $this->submissionPayload($farmer, $program, $key);

        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $payload)->assertRedirect();
        $first = AssistanceApplication::firstOrFail()->load('acknowledgment');
        $reference = $first->application_reference;
        $snapshotHash = $first->acknowledgment->snapshot_sha256;
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $payload)
            ->assertRedirect(route('modules.aasms.intake.applications.show', $first));
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program))
            ->assertSessionHasErrors('program');

        $this->assertDatabaseCount('assistance_applications', 1);
        $this->assertDatabaseCount('assistance_status_histories', 1);
        $this->assertDatabaseCount('assistance_application_acknowledgments', 1);
        $this->assertDatabaseCount('assistance_application_submission_snapshots', 1);
        $this->assertSame($reference, $first->fresh()->application_reference);
        $this->assertSame($snapshotHash, $first->fresh()->acknowledgment->snapshot_sha256);
        $indexes = Schema::getIndexes('assistance_applications');
        $this->assertTrue(collect($indexes)->contains(fn (array $index): bool =>
            ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['program_id', 'farmer_id']
        ), 'The database unique key is the final concurrency guard for one application per farmer/program.');
    }

    public function test_missing_or_invalid_synthetic_attestation_does_not_create_an_application(): void
    {
        $staff = $this->staff();
        $farmer = $this->farmer();
        $program = $this->program();
        $payload = $this->submissionPayload($farmer, $program);

        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), array_replace($payload, ['synthetic_qa_attestation' => null]))
            ->assertSessionHasErrors('synthetic_qa_attestation');
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), array_replace($payload, ['identity_confirmation_method' => 'government_id']))
            ->assertSessionHasErrors('identity_confirmation_method');
        $this->assertDatabaseCount('assistance_applications', 0);
    }

    public function test_intake_never_activates_when_the_explicit_isolated_qa_flag_is_off(): void
    {
        $staff = $this->staff();
        config(['agricultural_staff_assisted.enabled' => false]);

        $this->actingAs($staff)->get(route('modules.aasms.intake'))->assertStatus(503);
        $this->assertFalse(app(StaffAssistedIntakePolicy::class)->enabledForIsolatedQa());
    }

    public function test_online_farmer_and_staff_assisted_channels_share_references_requirement_versions_and_status_workflow(): void
    {
        $staff = $this->staff(['view', 'intake_assist']);
        $farmer = $this->farmer();
        $program = $this->program();

        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program))->assertRedirect();
        $staffApplication = AssistanceApplication::query()->where('farmer_id', $farmer['farmer']->id)->firstOrFail();

        $onlineFarmer = $this->farmer('QA-ONLINE-001');
        $this->actingAs($onlineFarmer['user'])->post(route('farmer.assistance.apply', $program), [
            'submission_key' => (string) Str::uuid(),
            'purpose' => 'Synthetic online QA purpose.',
        ])->assertRedirect();
        $onlineApplication = AssistanceApplication::query()->where('farmer_id', $onlineFarmer['farmer']->id)->firstOrFail();

        $this->assertSame('mao_staff_assisted', $staffApplication->submission_channel);
        $this->assertSame('farmer_portal', $onlineApplication->submission_channel);
        $this->assertSame($staffApplication->application_reference, $staffApplication->acknowledgment()->firstOrFail()->application_reference);
        $this->assertSame($onlineApplication->application_reference, $onlineApplication->acknowledgment()->firstOrFail()->application_reference);
        $this->assertSame($staffApplication->requirement_version_id, $onlineApplication->requirement_version_id);
        $this->assertDatabaseCount('assistance_applications', 2);

        $reviewer = User::factory()->create();
        $reviewer->roles()->attach(Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator', 'permissions' => [], 'is_active' => true]));
        $this->actingAs($reviewer)->post(route('modules.aasms.review', $staffApplication), [
            'status' => 'returned',
            'review_notes' => 'Synthetic QA correction request.',
        ])->assertRedirect();
        $ackBeforeResubmission = $staffApplication->fresh()->acknowledgment()->firstOrFail()->snapshot;
        $this->actingAs($farmer['user'])->post(route('farmer.assistance.resubmit', $staffApplication), ['purpose' => 'Synthetic corrected QA purpose.'])->assertRedirect();
        $this->assertSame('submitted', $staffApplication->fresh()->status);
        $this->assertSame('mao_staff_assisted', $staffApplication->fresh()->submission_channel);
        $this->assertSame($ackBeforeResubmission, $staffApplication->fresh()->acknowledgment()->firstOrFail()->snapshot);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $staffApplication->id, 'from_status' => 'returned', 'to_status' => 'submitted']);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $staffApplication->id, 'to_status' => 'submitted', 'changed_by' => $staff->id]);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $staffApplication->id, 'from_status' => 'submitted', 'to_status' => 'returned', 'changed_by' => $reviewer->id]);
        $this->assertDatabaseHas('assistance_status_histories', ['application_id' => $staffApplication->id, 'from_status' => 'returned', 'to_status' => 'submitted', 'changed_by' => $farmer['user']->id]);
    }

    public function test_idempotency_key_cannot_turn_an_online_application_into_a_staff_assisted_receipt(): void
    {
        $staff = $this->staff();
        $farmer = $this->farmer();
        $program = $this->program();
        $key = (string) Str::uuid();

        $this->actingAs($farmer['user'])->post(route('farmer.assistance.apply', $program), [
            'submission_key' => $key,
            'purpose' => 'Synthetic online application.',
        ])->assertRedirect();
        $online = AssistanceApplication::firstOrFail();

        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program, $key))
            ->assertSessionHasErrors('program');
        $this->assertDatabaseCount('assistance_applications', 1);
        $this->assertSame('farmer_portal', $online->fresh()->submission_channel);
        $this->assertDatabaseCount('assistance_application_acknowledgments', 1);
        $this->assertDatabaseCount('assistance_status_histories', 1);
    }

    public function test_staff_assisted_documents_use_the_private_scanner_and_keep_unavailable_files_quarantined(): void
    {
        $staff = $this->staff(['view', 'intake_assist']);
        $program = $this->program();
        $version = $this->publishPdfRequirement($program, $staff);
        $farmer = $this->farmer();
        Storage::fake('application-documents');
        config(['agricultural_documents.enabled' => true]);
        app()->instance(PrivateApplicationDocumentScanner::class, $this->fakeScanner('clean'));

        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program))->assertRedirect();
        $application = AssistanceApplication::firstOrFail();
        $requirement = $version->documentRequirements()->firstOrFail();
        $uploadRoute = route('modules.aasms.intake.documents.store', [$application, $requirement->id]);
        $this->actingAs($staff)->post($uploadRoute, ['document' => $this->pdf()])->assertRedirect();
        $clean = AssistanceApplicationDocument::query()->firstOrFail();
        $this->assertSame('clean', $clean->scan_status);
        $this->assertDatabaseHas('assistance_application_document_events', ['document_id' => $clean->id, 'event_type' => 'security_scan_result', 'to_scan_status' => 'clean']);

        // Existing sensitive-document access remains separate from intake.
        $this->actingAs($staff)->get(route('modules.aasms.applications.documents.file', [$application, $clean]))->assertForbidden();
        $this->actingAs($staff)->patch(route('modules.aasms.applications.documents.review', [$application, $clean]), [
            'review_status' => 'verified',
            'farmer_correction_notes' => '',
        ])->assertForbidden();

        $secondFarmer = $this->farmer('QA-DOC-UNAVAILABLE-001');
        app()->instance(PrivateApplicationDocumentScanner::class, $this->fakeScanner('clean', false));
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($secondFarmer, $program))->assertRedirect();
        $secondApplication = AssistanceApplication::query()->where('farmer_id', $secondFarmer['farmer']->id)->firstOrFail();
        $this->actingAs($staff)->post(route('modules.aasms.intake.documents.store', [$secondApplication, $requirement->id]), ['document' => $this->pdf()])
            ->assertRedirect()->assertSessionHas('status');
        $unavailable = AssistanceApplicationDocument::query()->where('application_id', $secondApplication->id)->firstOrFail();
        $this->assertSame('unavailable', $unavailable->scan_status);
        $this->actingAs($staff)->get(route('modules.aasms.applications.documents.file', [$secondApplication, $unavailable]))->assertForbidden();
        Storage::disk('application-documents')->assertExists($unavailable->storage_path);
    }

    public function test_opt_in_local_clamav_scans_a_staff_assisted_upload_before_mao_review(): void
    {
        if (! filter_var(env('AGRICULTURAL_DOCUMENT_REAL_SCANNER_QA', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set AGRICULTURAL_DOCUMENT_REAL_SCANNER_QA=true to opt into the local ClamAV assisted-intake integration test.');
        }

        $binary = env('AGRICULTURAL_DOCUMENT_SCANNER_BINARY');
        $databaseDirectory = env('AGRICULTURAL_DOCUMENT_SCANNER_DATABASE_DIRECTORY');
        if (! is_string($binary) || trim($binary) === '' || ! is_string($databaseDirectory) || trim($databaseDirectory) === '') {
            $this->markTestSkipped('Local ClamAV executable and signatures are not configured.');
        }

        config([
            'agricultural_documents.enabled' => true,
            'agricultural_documents.scanner_binary' => $binary,
            'agricultural_documents.scanner_database_directory' => $databaseDirectory,
            'agricultural_documents.signature_max_age_hours' => 36,
            'agricultural_documents.scan_timeout_seconds' => 120,
        ]);
        Storage::fake('application-documents');
        $scanner = app(ClamAvApplicationDocumentScanner::class);
        $this->assertTrue($scanner->available());
        $this->assertTrue($scanner->health()['ready']);
        app()->instance(PrivateApplicationDocumentScanner::class, $scanner);

        $staff = $this->staff(['view', 'intake_assist']);
        $program = $this->program();
        $version = $this->publishPdfRequirement($program, $staff);
        $farmer = $this->farmer();
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program))->assertRedirect();
        $application = AssistanceApplication::firstOrFail();
        $requirement = $version->documentRequirements()->firstOrFail();
        $this->actingAs($staff)->post(route('modules.aasms.intake.documents.store', [$application, $requirement->id]), ['document' => $this->pdf()])->assertRedirect();

        $document = AssistanceApplicationDocument::query()->firstOrFail();
        $this->assertSame('clean', $document->scan_status);
        $this->assertStringContainsString('ClamAV', (string) $document->scan_engine);
        $this->assertDatabaseHas('assistance_application_document_events', [
            'document_id' => $document->id,
            'event_type' => 'security_scan_result',
            'to_scan_status' => 'clean',
        ]);
        $this->assertDatabaseCount('assistance_status_histories', 1);
        $this->assertSame('submitted', $application->fresh()->status);
    }

    public function test_document_upload_flag_and_requirement_ownership_are_enforced_for_assisted_intake(): void
    {
        $staff = $this->staff();
        $program = $this->program();
        $version = $this->publishPdfRequirement($program, $staff);
        $farmer = $this->farmer();
        $this->actingAs($staff)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program))->assertRedirect();
        $application = AssistanceApplication::firstOrFail();
        $requirement = $version->documentRequirements()->firstOrFail();
        Storage::fake('application-documents');
        $this->actingAs($staff)->post(route('modules.aasms.intake.documents.store', [$application, $requirement->id]), ['document' => $this->pdf()])->assertStatus(503);
        $this->assertDatabaseCount('assistance_application_documents', 0);
        $this->assertSame([], Storage::disk('application-documents')->allFiles());
    }

    public function test_intake_staff_cannot_read_or_submit_another_farmer_owned_portal_application(): void
    {
        $staff = $this->staff();
        $owner = $this->farmer();
        $other = $this->farmer('QA-OTHER-001');
        $program = $this->program();
        $this->actingAs($owner['user'])->post(route('farmer.assistance.apply', $program), ['submission_key' => (string) Str::uuid()])->assertRedirect();
        $application = AssistanceApplication::firstOrFail();

        $this->actingAs($staff)->get(route('modules.aasms.intake.applications.show', $application))->assertNotFound();
        $this->actingAs($staff)->get(route('modules.aasms.intake.acknowledgment', $application))->assertNotFound();
        $this->actingAs($staff)->post(route('modules.aasms.intake.documents.store', [$application, 1]), ['document' => $this->pdf()])->assertNotFound();
        $this->assertDatabaseCount('assistance_applications', 1);
        $this->assertNotSame($owner['farmer']->id, $other['farmer']->id);
    }

    public function test_unauthorized_users_cannot_search_submit_or_print_staff_intake(): void
    {
        $unauthorized = User::factory()->create();
        $farmer = $this->farmer();
        $program = $this->program();

        $this->actingAs($unauthorized)->get(route('modules.aasms.intake'))->assertForbidden();
        $this->actingAs($unauthorized)->post(route('modules.aasms.intake.store'), $this->submissionPayload($farmer, $program))->assertForbidden();
        $this->assertDatabaseCount('assistance_applications', 0);
    }

    public function test_farmer_identity_cannot_be_used_as_the_assisted_intake_actor_even_with_an_accidental_staff_role(): void
    {
        $dualRole = $this->staff(['intake_assist']);
        $farmerRole = Role::firstOrCreate(['slug' => 'farmer'], ['name' => 'Farmer', 'permissions' => [], 'is_active' => true]);
        $dualRole->roles()->attach($farmerRole);

        $this->assertFalse($dualRole->canModuleAction('aasms', 'intake_assist'));
        $this->actingAs($dualRole)->get(route('modules.aasms.intake'))->assertForbidden();
    }
}
