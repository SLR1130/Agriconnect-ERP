<?php

namespace Tests\Feature;

use App\Contracts\PrivateApplicationDocumentScanner;
use App\Models\AssistanceApplication;
use App\Models\AssistanceApplicationDocument;
use App\Models\AssistanceApplicationSubmissionSnapshot;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AASMSApplicationDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function farmer(string $code = 'DOC-QA-001'): array
    {
        $role = Role::firstOrCreate(['slug' => 'farmer'], ['name' => 'Farmer', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $farmer = Farmer::create(['user_id' => $user->id, 'farmer_code' => $code, 'barangay' => 'Synthetic Test Barangay', 'registration_status' => 'verified']);
        Farm::create(['farmer_id' => $farmer->id, 'name' => 'Synthetic QA farm', 'barangay' => 'Synthetic Test Barangay', 'area' => 2.5, 'area_unit' => 'hectare', 'status' => 'active']);

        return compact('user', 'farmer');
    }

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Administrator', 'permissions' => [], 'is_active' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function applicationWithRequiredPdf(array $farmer): array
    {
        $program = AssistanceProgram::create([
            'name' => 'Synthetic QA Document Support',
            'category' => 'Synthetic QA only',
            'description' => 'Not an official government program.',
            'status' => 'closed',
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => false],
        ]);
        $staff = $this->superAdmin();
        $version = app(ProgramRequirementVersionService::class)->saveDraft($program, [
            'opens_on' => null,
            'closes_on' => null,
            'eligibility_rules' => ['requires_verified_farmer' => true, 'requires_farm' => false],
            'requirements' => [[
                'key' => 'synthetic-proof',
                'display_name' => 'Synthetic test proof',
                'description' => 'Synthetic test requirement, not approved for public use.',
                'instructions' => 'Use only generated sample content.',
                'required' => 'required',
                'accepted_mime_types' => ['application/pdf'],
                'max_size_mib' => 2,
            ]],
        ], $staff->id);
        app(ProgramRequirementVersionService::class)->publish($version, $staff->id);
        $program->update(['status' => 'open']);

        $this->actingAs($farmer['user'])->get(route('farmer.programs.show', $program))
            ->assertOk()->assertSee('Program document checklist')->assertSee('Synthetic test proof')
            ->assertSee('Required')->assertSee('Submit application');

        $this->actingAs($farmer['user'])->post(route('farmer.assistance.apply', $program), [
            'purpose' => 'Synthetic test purpose.',
            'submission_key' => (string) Str::uuid(),
        ])->assertRedirect();

        return [
            'application' => AssistanceApplication::query()
                ->where('farmer_id', $farmer['farmer']->id)
                ->where('program_id', $program->id)
                ->with('acknowledgment')->firstOrFail(),
            'requirement' => $version->documentRequirements()->firstOrFail(),
            'staff' => $staff,
            'program' => $program,
        ];
    }

    private function scanner(string $status, bool $available = true): PrivateApplicationDocumentScanner
    {
        return new class($status, $available) implements PrivateApplicationDocumentScanner {
            public function __construct(private string $status, private bool $isAvailable) {}
            public function scan(string $absolutePath): array { return ['status' => $this->status, 'engine' => $this->isAvailable ? 'SyntheticTestScanner' : null]; }
            public function available(): bool { return $this->isAvailable; }
        };
    }

    private function timedOutScanner(): PrivateApplicationDocumentScanner
    {
        return new class implements PrivateApplicationDocumentScanner {
            public function available(): bool { return true; }
            public function scan(string $absolutePath): array
            {
                $process = new Process([PHP_BINARY, '-v']);
                $process->setTimeout(1);
                throw new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL);
            }
        };
    }

    private function pdf(string $name = 'proof.pdf', string $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_document_uploads_are_disabled_by_default_and_reject_direct_posts(): void
    {
        config(['agricultural_documents.enabled' => false]);
        Storage::fake('application-documents');
        $farmer = $this->farmer();
        $data = $this->applicationWithRequiredPdf($farmer);
        $documents = app(\App\Services\AssistanceApplicationDocumentService::class);

        $this->assertFalse($documents->uploadEnabled());
        $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$data['application'], $data['requirement']]), [
            'document' => $this->pdf(),
        ])->assertStatus(503);
        $this->assertDatabaseCount('assistance_application_documents', 0);
        $this->assertSame([], Storage::disk('application-documents')->allFiles());
    }

    public function test_production_clamav_adapter_reports_missing_binary_as_unavailable(): void
    {
        config(['agricultural_documents.scanner_binary' => null]);
        $scanner = app(ClamAvApplicationDocumentScanner::class);

        $this->assertFalse($scanner->available());
        $this->assertSame(['status' => 'unavailable', 'engine' => null], $scanner->scan('not-opened-while-scanner-is-missing'));
    }

    public function test_upload_is_private_opaque_and_stays_quarantined_when_scanner_is_unavailable(): void
    {
        config(['agricultural_documents.enabled' => true]);
        Storage::fake('application-documents');
        // A misbehaving adapter must not turn unavailable into a clean result.
        app()->instance(PrivateApplicationDocumentScanner::class, $this->scanner('clean', false));
        $farmer = $this->farmer();
        $data = $this->applicationWithRequiredPdf($farmer);

        $response = $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$data['application'], $data['requirement']]), [
            'document' => $this->pdf('..\\private\r\nproof.pdf'),
        ])->assertRedirect(route('farmer.applications.show', $data['application']))
            ->assertSessionHas('status');

        $document = AssistanceApplicationDocument::query()->firstOrFail();
        $this->assertSame('unavailable', $document->scan_status);
        $privateRoot = strtolower(str_replace('\\', '/', config('filesystems.disks.application-documents.root')));
        $publicRoot = strtolower(str_replace('\\', '/', public_path())).'/';
        $this->assertStringContainsString('/storage/app/private/', $privateRoot);
        $this->assertFalse(str_starts_with($privateRoot, $publicRoot));
        $this->assertStringNotContainsString('/', $document->original_filename);
        $this->assertStringNotContainsString('\\', $document->original_filename);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $document->original_filename);
        $this->assertStringNotContainsString('..', $document->original_filename);
        $this->assertNotSame('private/proof.pdf', $document->storage_path);
        $this->assertStringNotContainsString($document->storage_path, $response->headers->all()['location'][0] ?? '');
        Storage::disk('application-documents')->assertExists($document->storage_path);
        $this->assertDatabaseHas('assistance_application_document_events', ['document_id' => $document->id, 'event_type' => 'security_scan_result', 'to_scan_status' => 'unavailable']);
        $this->assertSame(1, AssistanceApplicationSubmissionSnapshot::query()->where('application_id', $data['application']->id)->count());

        $this->actingAs($farmer['user'])->get(route('farmer.applications.documents.file', [$data['application'], $document]))->assertStatus(423);
        $this->actingAs($farmer['user'])->get(route('farmer.applications.show', $data['application']))
            ->assertOk()->assertSee('Quarantined')->assertDontSee('Download private file')
            ->assertSee('data-private-document-upload', false)
            ->assertSee('data-document-upload-progress', false)
            ->assertSee('Uploading to private quarantine. Keep this page open')
            ->assertDontSee($document->storage_path);
        $this->actingAs(User::factory()->create())->post(route('farmer.applications.documents.upload', [$data['application'], $data['requirement']]), [
            'document' => $this->pdf(),
        ])->assertForbidden();
        $this->assertDatabaseCount('assistance_application_documents', 1);
        $other = $this->farmer('DOC-QA-002');
        $this->actingAs($other['user'])->get(route('farmer.applications.documents.file', [$data['application'], $document]))->assertNotFound();
        $this->assertDatabaseCount('assistance_application_document_access_logs', 0);
    }

    public function test_mime_spoof_oversize_and_unowned_requirement_are_rejected(): void
    {
        config(['agricultural_documents.enabled' => true]);
        Storage::fake('application-documents');
        app()->instance(PrivateApplicationDocumentScanner::class, $this->scanner('clean'));
        $farmer = $this->farmer();
        $data = $this->applicationWithRequiredPdf($farmer);

        $spoofPath = tempnam(sys_get_temp_dir(), 'agri-spoof-');
        file_put_contents($spoofPath, '<html>not a pdf</html>');
        $spoof = new UploadedFile($spoofPath, 'spoof.pdf', 'text/html', UPLOAD_ERR_OK, true);
        $this->assertSame('text/html', $spoof->getMimeType());
        $spoofResponse = $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$data['application'], $data['requirement']]), [
            'document' => $spoof,
        ]);
        @unlink($spoofPath);
        $this->assertTrue($spoofResponse->getSession()->has('errors'), 'Spoofed upload should return validation errors; HTTP '.$spoofResponse->status().' at '.$spoofResponse->headers->get('Location').'.');
        $spoofResponse->assertSessionHasErrors('document');

        $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$data['application'], $data['requirement']]), [
            'document' => $this->pdf('too-large.pdf', "%PDF-1.4\n".str_repeat('x', 2 * 1024 * 1024 + 32)),
        ])->assertSessionHasErrors('document');

        $otherFarmer = $this->farmer('DOC-QA-003');
        $otherData = $this->applicationWithRequiredPdf($otherFarmer);
        $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$data['application'], $otherData['requirement']]), [
            'document' => $this->pdf(),
        ])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('assistance_application_documents', 0);
    }

    public function test_scanner_failure_and_infected_results_never_make_a_file_available(): void
    {
        config(['agricultural_documents.enabled' => true]);
        Storage::fake('application-documents');
        $farmer = $this->farmer();
        $data = $this->applicationWithRequiredPdf($farmer);

        app()->instance(PrivateApplicationDocumentScanner::class, $this->scanner('failed'));
        $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$data['application'], $data['requirement']]), ['document' => $this->pdf()])->assertRedirect();
        $failed = AssistanceApplicationDocument::query()->firstOrFail();
        $this->assertSame('failed', $failed->scan_status);
        $this->actingAs($farmer['user'])->get(route('farmer.applications.documents.file', [$data['application'], $failed]))->assertStatus(423);

        $secondFarmer = $this->farmer('DOC-QA-002');
        $secondData = $this->applicationWithRequiredPdf($secondFarmer);
        app()->instance(PrivateApplicationDocumentScanner::class, $this->scanner('infected'));
        $infectedResponse = $this->actingAs($secondFarmer['user'])->post(route('farmer.applications.documents.upload', [$secondData['application'], $secondData['requirement']]), ['document' => $this->pdf('infected.pdf')]);
        $this->assertContains($infectedResponse->status(), [301, 302, 303, 307, 308], 'Infected upload should redirect after quarantine; got HTTP '.$infectedResponse->status().'.');
        $infected = AssistanceApplicationDocument::query()->where('application_id', $secondData['application']->id)->firstOrFail();
        $this->assertSame('infected', $infected->scan_status);
        $this->actingAs($secondFarmer['user'])->get(route('farmer.applications.documents.file', [$secondData['application'], $infected]))->assertStatus(423);
        $this->assertDatabaseCount('assistance_application_document_access_logs', 0);
    }

    public function test_scanner_timeout_is_recorded_as_failed_and_remains_quarantined(): void
    {
        config(['agricultural_documents.enabled' => true]);
        Storage::fake('application-documents');
        app()->instance(PrivateApplicationDocumentScanner::class, $this->timedOutScanner());
        $farmer = $this->farmer();
        $data = $this->applicationWithRequiredPdf($farmer);

        $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$data['application'], $data['requirement']]), [
            'document' => $this->pdf(),
        ])->assertRedirect();

        $document = AssistanceApplicationDocument::query()->firstOrFail();
        $this->assertSame('failed', $document->scan_status);
        $this->assertNotNull($document->scan_completed_at);
        $this->assertDatabaseHas('assistance_application_document_events', [
            'document_id' => $document->id,
            'event_type' => 'security_scan_result',
            'to_scan_status' => 'failed',
        ]);
        $this->actingAs($farmer['user'])->get(route('farmer.applications.documents.file', [$data['application'], $document]))->assertStatus(423);
        $this->assertDatabaseCount('assistance_application_document_access_logs', 0);
    }

    public function test_document_review_permissions_correction_replacement_resubmission_and_acknowledgment_integrity(): void
    {
        config(['agricultural_documents.enabled' => true]);
        Storage::fake('application-documents');
        app()->instance(PrivateApplicationDocumentScanner::class, $this->scanner('clean'));
        $farmer = $this->farmer();
        $data = $this->applicationWithRequiredPdf($farmer);
        $application = $data['application']->fresh(['acknowledgment']);
        $reference = $application->application_reference;
        $originalAcknowledgment = $application->acknowledgment->snapshot;

        $this->actingAs($data['staff'])->post(route('modules.aasms.review', $application), [
            'status' => 'approved', 'review_notes' => '',
        ])->assertSessionHasErrors('documents');
        $this->assertSame('submitted', $application->fresh()->status);

        $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$application, $data['requirement']]), ['document' => $this->pdf()])->assertRedirect();
        $first = AssistanceApplicationDocument::query()->firstOrFail();
        $this->assertSame('clean', $first->scan_status);
        $this->actingAs($data['staff'])->get(route('modules.aasms.applications.show', $application))
            ->assertOk()->assertSee('Synthetic test proof')->assertSee('Required')
            ->assertSee('Associated farms')->assertSee('Synthetic QA farm')
            ->assertSee($first->original_filename)->assertSee('Security scan passed')
            ->assertSee('Document decision')->assertSee('Record document review');

        $viewerRole = Role::create(['name' => 'Synthetic Document Viewer', 'slug' => 'municipal_agriculturist', 'permissions' => ['modules.aasms.view'], 'is_active' => true]);
        $viewer = User::factory()->create();
        $viewer->roles()->attach($viewerRole);
        $this->actingAs($viewer)->get(route('modules.aasms.applications.documents.file', [$application, $first]))->assertForbidden();
        $this->actingAs($viewer)->patch(route('modules.aasms.applications.documents.review', [$application, $first]), [
            'review_status' => 'verified', 'farmer_correction_notes' => '', 'internal_review_notes' => '',
        ])->assertForbidden();
        $this->actingAs($viewer)->get(route('modules.aasms.applications.show', $application))->assertOk()->assertSee('separate AASMS private-document permission');

        $ffrmsModule = Module::firstOrCreate(['slug' => 'ffrms'], ['name' => 'Farmer Registration', 'route_prefix' => 'ffrms', 'is_enabled' => true]);
        $ffrmsPersonnel = User::factory()->create();
        $ffrmsPersonnel->roles()->attach($personnelRole = Role::firstOrCreate(['slug' => 'module_personnel'], ['name' => 'Module Personnel', 'permissions' => ['dashboard.view'], 'is_active' => true]));
        UserModuleAccess::create(['user_id' => $ffrmsPersonnel->id, 'module_id' => $ffrmsModule->id, 'permissions' => ['view']]);
        $this->actingAs($ffrmsPersonnel)->get(route('modules.aasms.applications.documents.file', [$application, $first]))->assertForbidden();

        $this->actingAs($data['staff'])->patch(route('modules.aasms.applications.documents.review', [$application, $first]), [
            'review_status' => 'correction_requested',
            'farmer_correction_notes' => 'Synthetic correction request: upload a clearer scan.',
            'internal_review_notes' => 'Synthetic internal note; not visible to farmer.',
        ])->assertRedirect();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $farmer['user']->id]);
        $this->assertSame('correction_requested', $first->fresh()->review_status);
        $this->assertDatabaseHas('assistance_application_document_events', ['document_id' => $first->id, 'internal_note' => 'Synthetic internal note; not visible to farmer.']);

        $this->actingAs($data['staff'])->post(route('modules.aasms.review', $application), [
            'status' => 'returned', 'review_notes' => 'Correct the supporting document and submit it again.',
        ])->assertRedirect();
        $this->actingAs($farmer['user'])->get(route('farmer.applications.show', $application))
            ->assertOk()->assertSee('Synthetic correction request')->assertDontSee('Synthetic internal note; not visible to farmer.');

        $this->actingAs($farmer['user'])->post(route('farmer.applications.documents.upload', [$application, $data['requirement']]), ['document' => $this->pdf('replacement.pdf')])->assertRedirect();
        $second = AssistanceApplicationDocument::query()->where('version_number', 2)->firstOrFail();
        $this->assertSame($first->id, $second->supersedes_document_id);
        $this->assertSame($first->sha256, $first->fresh()->sha256);
        $this->actingAs($farmer['user'])->post(route('farmer.assistance.resubmit', $application), [])->assertSessionHasErrors('documents');
        $this->assertSame('returned', $application->fresh()->status);
        $this->assertSame($originalAcknowledgment, $application->fresh()->acknowledgment->snapshot);

        $this->actingAs($data['staff'])->patch(route('modules.aasms.applications.documents.review', [$application, $second]), [
            'review_status' => 'verified', 'farmer_correction_notes' => '', 'internal_review_notes' => 'Verified synthetic replacement.',
        ])->assertRedirect();
        $this->actingAs($farmer['user'])->get(route('farmer.applications.documents.file', [$application, $second]))->assertOk();
        $this->assertDatabaseHas('assistance_application_document_access_logs', ['document_id' => $second->id, 'actor_id' => $farmer['user']->id, 'access_action' => 'download']);

        $this->actingAs($farmer['user'])->post(route('farmer.assistance.resubmit', $application), ['purpose' => 'Synthetic corrected response.'])->assertRedirect();
        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertSame($reference, $application->fresh()->application_reference);
        $this->assertSame($originalAcknowledgment, $application->fresh()->acknowledgment->snapshot);
        $snapshots = AssistanceApplicationSubmissionSnapshot::query()->where('application_id', $application->id)->orderBy('attempt_number')->get();
        $this->assertCount(2, $snapshots);
        $this->assertSame('initial', $snapshots[0]->submission_type);
        $this->assertSame('resubmission', $snapshots[1]->submission_type);
        $this->assertSame(2, data_get($snapshots[1]->snapshot, 'documents.0.document_version'));
        $this->assertTrue(data_get($snapshots[1]->snapshot, 'original_acknowledgment_unchanged'));
        $this->assertDatabaseCount('assistance_status_histories', 3);

        $this->actingAs($data['staff'])->post(route('modules.aasms.review', $application), [
            'status' => 'approved', 'review_notes' => '',
        ])->assertRedirect();
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseCount('assistance_status_histories', 4);

        $this->expectException(LogicException::class);
        $second->delete();
    }

    public function test_sensitive_document_permissions_require_exact_grants_and_never_follow_generic_or_auditor_wildcards(): void
    {
        $authorization = app(ErpAuthorizationService::class);
        $viewerRole = Role::create(['name' => 'Synthetic Agriculturist', 'slug' => 'municipal_agriculturist', 'permissions' => ['modules.aasms.view', 'modules.aasms.*'], 'is_active' => true]);
        $viewer = User::factory()->create();
        $viewer->roles()->attach($viewerRole);
        $this->assertTrue($authorization->canModule($viewer, 'aasms', 'view'));
        $this->assertFalse($authorization->canModule($viewer, 'aasms', 'documents_view_sensitive'));
        $this->assertFalse($authorization->canModule($viewer, 'aasms', 'documents_review'));

        $auditorRole = Role::create(['name' => 'Synthetic Auditor', 'slug' => 'authorized_viewer', 'permissions' => ['modules.aasms.documents_view_sensitive', 'modules.aasms.documents_review'], 'is_active' => true]);
        $auditor = User::factory()->create();
        $auditor->roles()->attach($auditorRole);
        $this->assertFalse($authorization->canModule($auditor, 'aasms', 'documents_view_sensitive'));
        $this->assertFalse($authorization->canModule($auditor, 'aasms', 'documents_review'));

        $personnelRole = Role::create(['name' => 'Synthetic Module Personnel', 'slug' => 'module_personnel', 'permissions' => ['dashboard.view'], 'is_active' => true]);
        $personnel = User::factory()->create();
        $personnel->roles()->attach($personnelRole);
        $module = Module::firstOrCreate(['slug' => 'aasms'], ['name' => 'Assistance Management', 'route_prefix' => 'aasms', 'is_enabled' => true]);
        UserModuleAccess::create(['user_id' => $personnel->id, 'module_id' => $module->id, 'permissions' => ['view', 'documents_view_sensitive']]);
        $this->assertTrue($authorization->canModule($personnel, 'aasms', 'documents_view_sensitive'));
        $this->assertFalse($authorization->canModule($personnel, 'aasms', 'documents_review'));
    }
}
