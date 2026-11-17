<?php

namespace App\Http\Controllers;

use App\Models\AssistanceApplication;
use App\Models\AssistanceProgram;
use App\Models\AssistanceProgramDocumentRequirement;
use App\Models\Farmer;
use App\Services\AgriculturalBusinessCalendar;
use App\Services\AgriculturalSchemaCapabilities;
use App\Services\AssistanceApplicationDocumentService;
use App\Services\AssistanceApplicationSubmissionService;
use App\Services\ProgramEligibilityService;
use App\Services\StaffAssistedIntakePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AASMSIntakeController extends Controller
{
    public function index(
        Request $request,
        AgriculturalBusinessCalendar $calendar,
        ProgramEligibilityService $eligibility,
        AgriculturalSchemaCapabilities $schema,
        StaffAssistedIntakePolicy $policy,
    ): View {
        $this->authorizeIntake($request, $policy);
        abort_unless($schema->applicationSubmissionReady(), 503, 'Application requirements and acknowledgments are not available in this database yet.');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'farmer_id' => ['nullable', 'integer'],
            'program_search' => ['nullable', 'string', 'max:100'],
            'program_id' => ['nullable', 'integer'],
        ]);
        $search = trim($filters['search'] ?? '');
        $programSearch = trim($filters['program_search'] ?? '');

        $farmers = Farmer::query()
            ->with('user')
            ->when($search === '', fn ($query) => $query->whereRaw('1 = 0'))
            ->when($search !== '', fn ($query) => $query->where(function ($matches) use ($search): void {
                $matches->where('farmer_code', 'like', '%'.$search.'%')
                    ->orWhere('barangay', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%'));
            }))
            ->orderBy('farmer_code')
            ->paginate(8, ['*'], 'farmer_page')
            ->withQueryString();

        $selectedFarmer = null;
        if (! empty($filters['farmer_id'])) {
            $selectedFarmer = Farmer::query()
                ->with([
                    'user',
                    'farms',
                    'registrationHistory' => fn ($history) => $history->with('processor')->latest()->limit(5),
                ])
                ->findOrFail($filters['farmer_id']);
        }

        $programs = AssistanceProgram::query()
            ->where('status', 'open')
            ->with('currentRequirementVersion.documentRequirements')
            ->when($programSearch !== '', fn ($query) => $query->where(function ($matches) use ($programSearch): void {
                $matches->where('name', 'like', '%'.$programSearch.'%')
                    ->orWhere('category', 'like', '%'.$programSearch.'%');
            }))
            ->orderBy('closes_on')
            ->orderBy('name')
            ->paginate(8, ['*'], 'program_page')
            ->withQueryString();

        $selectedProgram = null;
        $eligibilityResult = null;
        $existingApplication = null;
        if (! empty($filters['program_id'])) {
            // Keep unpublished programs inaccessible even when a caller guesses
            // a program ID or manually changes the query string.
            $selectedProgram = AssistanceProgram::query()
                ->where('status', 'open')
                ->with('currentRequirementVersion.documentRequirements')
                ->findOrFail($filters['program_id']);

            if ($selectedFarmer) {
                $selectedFarmer->loadMissing('farms');
                $eligibilityResult = $eligibility->check($selectedProgram, $selectedFarmer, $calendar->today());
                $existingApplication = AssistanceApplication::query()
                    ->where('farmer_id', $selectedFarmer->id)
                    ->where('program_id', $selectedProgram->id)
                    ->first();
            }
        }

        $canViewApplication = $request->user()->canModuleAction('aasms', 'view');
        $canReviewFarmer = $request->user()->canModuleAction('ffrms', 'review')
            || $request->user()->canErp('farmer.verify');

        return view('modules.aasms.intake', [
            'search' => $search,
            'programSearch' => $programSearch,
            'farmers' => $farmers,
            'selectedFarmer' => $selectedFarmer,
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'eligibilityResult' => $eligibilityResult,
            'existingApplication' => $existingApplication,
            'businessDate' => $calendar->today(),
            'submissionKey' => (string) Str::uuid(),
            'canViewApplication' => $canViewApplication,
            'canReviewFarmer' => $canReviewFarmer,
        ]);
    }

    public function store(
        Request $request,
        ProgramEligibilityService $eligibility,
        AgriculturalBusinessCalendar $calendar,
        StaffAssistedIntakePolicy $policy,
        AssistanceApplicationSubmissionService $submissions,
    ): RedirectResponse {
        $this->authorizeIntake($request, $policy);
        $data = $request->validate([
            'farmer_id' => ['required', 'integer', 'exists:farmers,id'],
            'program_id' => ['required', 'integer', Rule::exists('assistance_programs', 'id')->where('status', 'open')],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'submission_key' => ['required', 'uuid'],
            'identity_confirmation_method' => ['required', Rule::in($policy->identityConfirmationMethods())],
            'synthetic_qa_attestation' => ['required', 'accepted'],
        ]);

        $farmer = Farmer::query()->with(['user', 'farms'])->findOrFail($data['farmer_id']);
        if (! $farmer->isVerified()) {
            return back()->withInput()->withErrors([
                'farmer_id' => 'This farmer is not verified in FFRMS. Complete the existing farmer verification process before assisting with an application.',
            ]);
        }

        $program = AssistanceProgram::query()->where('status', 'open')->findOrFail($data['program_id']);
        $eligibility->requireEligible($program, $farmer, $calendar->today());

        $application = $submissions->submitStaffAssisted(
            $program,
            $farmer,
            $data['purpose'] ?? '',
            $data['submission_key'],
            $request->user(),
            $data['identity_confirmation_method'],
            (bool) $data['synthetic_qa_attestation'],
        );

        return redirect()->route('modules.aasms.intake.applications.show', $application)
            ->with('status', 'The isolated QA application was recorded through the shared AASMS submission workflow. Print its acknowledgment below.');
    }

    public function showApplication(
        Request $request,
        AssistanceApplication $application,
        StaffAssistedIntakePolicy $policy,
        AssistanceApplicationDocumentService $documents,
        AgriculturalSchemaCapabilities $schema,
    ): View {
        $this->authorizeIntake($request, $policy);
        abort_unless($application->submission_channel === 'mao_staff_assisted', 404);
        abort_unless($schema->applicationSubmissionReady(), 503);

        $application->load(['farmer.user', 'program', 'requirementVersion.documentRequirements', 'acknowledgment']);
        $canViewDocuments = $request->user()->canModuleAction('aasms', 'view')
            && $request->user()->canModuleAction('aasms', 'documents_view_sensitive');
        if ($canViewDocuments && $schema->documentWorkflowReady()) {
            $application->load('documents.events');
        }

        return view('modules.aasms.intake-confirmation', [
            'application' => $application,
            'uploadEnabled' => $documents->uploadEnabled(),
            'canViewDocuments' => $canViewDocuments,
            'maxUploadKilobytes' => $documents->maximumUploadKilobytes(),
        ]);
    }

    public function uploadDocument(
        Request $request,
        AssistanceApplication $application,
        int $requirement,
        StaffAssistedIntakePolicy $policy,
        AssistanceApplicationDocumentService $documents,
    ): RedirectResponse {
        $this->authorizeIntake($request, $policy);
        abort_unless($application->submission_channel === 'mao_staff_assisted', 404);
        $documents->assertUploadEnabled();

        $requirement = AssistanceProgramDocumentRequirement::query()
            ->where('requirement_version_id', $application->requirement_version_id)
            ->findOrFail($requirement);
        $data = $request->validate([
            'document' => [
                'required', 'file',
                'max:'.$documents->maximumUploadKilobytes((int) $requirement->max_size_bytes),
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],
        ]);

        $document = $documents->upload($application, $requirement, $data['document'], $request->user());
        $message = match ($document->scan_status) {
            'clean' => 'The file was privately received and passed the security scan. It still requires the existing authorized MAO document review.',
            'infected' => 'The file was quarantined and rejected by the security scan. It cannot be opened or reviewed.',
            'unavailable' => 'The file remains in private quarantine because an approved malware scanner is unavailable.',
            default => 'The file remains in private quarantine because its security scan did not complete successfully.',
        };

        return redirect()->route('modules.aasms.intake.applications.show', $application)->with('status', $message);
    }

    private function authorizeIntake(Request $request, StaffAssistedIntakePolicy $policy): void
    {
        abort_unless($request->user()->canModuleAction('aasms', 'intake_assist'), 403);
        $policy->assertEnabledForIsolatedQa();
    }
}
