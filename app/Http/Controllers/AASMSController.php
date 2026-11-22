<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProgramRequest;
use App\Models\ActivityLog;
use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\AssistanceProgramRequirementVersion;
use App\Models\SupplyBatch;
use App\Models\SupplyItem;
use App\Services\AssistanceService;
use App\Services\AssistanceApplicationSubmissionService;
use App\Services\AssistanceProgramCatalogService;
use App\Services\AgriculturalBusinessCalendar;
use App\Services\InventoryService;
use App\Services\ProgramRequirementVersionService;
use App\Services\ProgramEligibilityService;
use App\Services\AgriculturalSchemaCapabilities;
use App\Services\AssistanceApplicationDocumentService;
use App\Services\ClaimTicketFeatureGate;
use App\Services\BongabongGeography;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AASMSController extends Controller
{
    private function staff(Request $request, string $action = 'view'): void
    {
        abort_unless($request->user()->canModuleAction('aasms', $action), 403);
    }

    private function filters(Request $request, array $barangays)
    {
        $filter = $request->validate([
            'barangay' => ['nullable', 'string', Rule::in($barangays)],
            'distribution_barangay' => ['nullable', 'string', Rule::in([...$barangays, '__not_recorded__'])],
        ]);
        $barangay = trim($filter['barangay'] ?? '');
        $distributionBarangay = trim($filter['distribution_barangay'] ?? '');
        if ($distributionBarangay !== '' && ! Schema::hasColumn('assistance_distributions', 'farmer_barangay_at_distribution')) {
            abort(503, 'Historical distribution barangay is not yet available.');
        }

        return AssistanceDistribution::with(['application.farmer.user', 'application.program', 'batch.item', 'processor'])
            ->when($request->filled('reference'), fn ($q) => $q->where('reference', 'like', '%'.$request->reference.'%'))
            ->when($request->filled('application'), fn ($q) => $q->where('application_id', $request->application))
            ->when($request->filled('program_id'), fn ($q) => $q->whereHas('application', fn ($a) => $a->where('program_id', $request->program_id)))
            ->when($request->filled('farmer'), fn ($q) => $q->whereHas('application.farmer.user', fn ($u) => $u->where('name', 'like', '%'.$request->farmer.'%')))
            ->when($barangay !== '', fn ($q) => $q->whereHas('application.farmer', fn ($farmers) => $farmers->where('barangay', $barangay)))
            ->when($distributionBarangay !== '', function ($q) use ($distributionBarangay): void {
                if ($distributionBarangay === '__not_recorded__') {
                    $q->where(fn ($unknown) => $unknown->whereNull('farmer_barangay_at_distribution')->orWhere('farmer_barangay_at_distribution', ''));
                } else {
                    $q->where('farmer_barangay_at_distribution', $distributionBarangay);
                }
            })
            ->when($request->filled('supply_item_id'), fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('supply_item_id', $request->supply_item_id)))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('distributed_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('distributed_at', '<=', $request->to));
    }

    public function index(Request $request, AgriculturalBusinessCalendar $calendar, AgriculturalSchemaCapabilities $schema, \App\Services\StaffAssistedIntakePolicy $intakePolicy, ClaimTicketFeatureGate $claimTickets, BongabongGeography $geography): View
    {
        $this->staff($request, 'view');
        $requirementsReady = $schema->requirementVersionsReady();
        $acknowledgmentsReady = $schema->acknowledgmentsReady();
        $programRelations = ['supplyItem', 'applications'];
        if ($requirementsReady) {
            $programRelations = array_merge($programRelations, [
                'currentRequirementVersion.documentRequirements', 'currentRequirementVersion.publisher',
                'draftRequirementVersion.documentRequirements', 'draftRequirementVersion.creator',
            ]);
        }
        $programs = AssistanceProgram::with($programRelations)->latest()->get();
        if (! $requirementsReady) {
            $programs->each(fn (AssistanceProgram $program) => $program
                ->setRelation('currentRequirementVersion', null)
                ->setRelation('draftRequirementVersion', null));
        }
        $applicationCounts = AssistanceApplication::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $applicationRelations = [
            'program', 'farmer.user', 'farmer.farms', 'history', 'approvedSupplyItem',
        ];
        if ($requirementsReady) {
            $applicationRelations[] = 'requirementVersion.documentRequirements';
            $applicationRelations[] = 'requirementVersion.publisher';
        }
        if ($acknowledgmentsReady) {
            $applicationRelations[] = 'acknowledgment';
        }
        $applications = AssistanceApplication::with($applicationRelations)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();
        if (! $requirementsReady || ! $acknowledgmentsReady) {
            $applications->getCollection()->each(function (AssistanceApplication $application) use ($requirementsReady, $acknowledgmentsReady): void {
                if (! $requirementsReady) {
                    $application->setRelation('requirementVersion', null);
                }
                if (! $acknowledgmentsReady) {
                    $application->setRelation('acknowledgment', null);
                }
            });
        }
        $batches = SupplyBatch::with('item')->where('status', 'available')->where('quantity_available', '>', 0)
            ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
            ->get();
        $barangays = $geography->barangays();
        $distributionBarangayReady = Schema::hasColumn('assistance_distributions', 'farmer_barangay_at_distribution');
        $distributions = $this->filters($request, $barangays)->latest()->paginate(15, ['*'], 'distribution_page')->withQueryString();
        $barangayExpression = $distributionBarangayReady
            ? "COALESCE(NULLIF(ad.farmer_barangay_at_distribution, ''), 'Not recorded')"
            : "COALESCE(NULLIF(f.barangay, ''), 'Not recorded')";
        $barangaySummaryQuery = DB::table('assistance_distributions as ad')
            ->selectRaw($barangayExpression.' as barangay, COUNT(*) as distribution_count, SUM(ad.quantity) as quantity_total');
        if (! $distributionBarangayReady) {
            $barangaySummaryQuery
                ->join('assistance_applications as aa', 'aa.id', '=', 'ad.application_id')
                ->join('farmers as f', 'f.id', '=', 'aa.farmer_id');
        }
        $distributionBarangaySummary = $barangaySummaryQuery
            ->groupByRaw($barangayExpression)
            ->orderBy('barangay')
            ->get();

        return view('modules.aasms.dashboard', compact('programs', 'applications', 'batches', 'distributions', 'barangays', 'distributionBarangaySummary') + [
            'items' => SupplyItem::where('status', 'active')->orderBy('name')->get(),
            'eligibilityOptions' => config('agricultural_options.eligibility', []),
            'pending' => (int) $applicationCounts->get('submitted', 0), 'approved' => (int) $applicationCounts->get('approved', 0), 'rejected' => (int) $applicationCounts->get('rejected', 0), 'completed' => (int) $applicationCounts->get('completed', 0),
            'canCreate' => $request->user()->canModuleAction('aasms', 'create'),
            'canUpdate' => $request->user()->canModuleAction('aasms', 'update'),
            'canPublish' => $request->user()->canModuleAction('aasms', 'publish'),
            'businessDate' => $calendar->today(),
            'canReview' => $request->user()->canModuleAction('aasms', 'review'),
            'canApprove' => $request->user()->canModuleAction('aasms', 'approve'),
            'canReject' => $request->user()->canModuleAction('aasms', 'reject'),
            'canDistribute' => $request->user()->canModuleAction('aasms', 'distribute'),
            'canExport' => $request->user()->canModuleAction('aasms', 'export'),
            'distributionBarangayReady' => $distributionBarangayReady,
            'distributionBarangaySummaryBasis' => $distributionBarangayReady ? 'at distribution' : 'current farmer record (historical location unavailable)',
            'canIntakeAssist' => $request->user()->canModuleAction('aasms', 'intake_assist'),
            'staffAssistedIntakeEnabled' => $intakePolicy->enabledForIsolatedQa(),
            'requirementsSchemaReady' => $requirementsReady,
            'acknowledgmentsSchemaReady' => $acknowledgmentsReady,
            'canManagePrograms' => $requirementsReady && $request->user()->canModuleAction('aasms', 'create'),
            'canEditPrograms' => $requirementsReady && $request->user()->canModuleAction('aasms', 'update'),
            'claimTicketsAvailable' => $claimTickets->enabled()
                && $request->user()->canModuleAction('aasms', 'view')
                && $request->user()->canErp('modules.aasms.claim_ticket_view'),
        ]);
    }

    public function storeProgram(ProgramRequest $request, ProgramRequirementVersionService $versions, AgriculturalSchemaCapabilities $schema): RedirectResponse
    {
        $this->staff($request, 'create');
        abort_unless($schema->requirementVersionsReady(), 503, 'Program requirement versioning is unavailable until its database migration is activated.');
        $data = $this->programData($request);
        $draft = DB::transaction(function () use ($data, $versions, $request): AssistanceProgramRequirementVersion {
            $program = AssistanceProgram::create([
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'closed',
                'opens_on' => $data['opens_on'] ?? null,
                'closes_on' => $data['closes_on'] ?? null,
                'eligibility_rules' => $data['eligibility_rules'],
                'supply_item_id' => $data['supply_item_id'] ?? null,
                'allocation_limit' => $data['allocation_limit'] ?? null,
            ]);

            return $versions->saveDraft($program, $this->versionData($data), $request->user()->id);
        });

        return back()->with('status', "Private program saved with requirements version {$draft->version_number} as a draft. Publish the requirements version before opening the program.");
    }

    public function updateProgram(ProgramRequest $request, AssistanceProgram $program, ProgramRequirementVersionService $versions, AgriculturalSchemaCapabilities $schema): RedirectResponse
    {
        $this->staff($request, 'update');
        abort_unless($schema->requirementVersionsReady(), 503, 'Program requirement versioning is unavailable until its database migration is activated.');
        $data = $this->programData($request);
        $draft = DB::transaction(function () use ($data, $program, $versions, $request): AssistanceProgramRequirementVersion {
            $program->update([
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'description' => $data['description'] ?? null,
                'supply_item_id' => $data['supply_item_id'] ?? null,
                'allocation_limit' => $data['allocation_limit'] ?? null,
            ]);

            return $versions->saveDraft($program, $this->versionData($data), $request->user()->id);
        });

        return back()->with('status', "Program details saved. Schedule, eligibility, and document requirements are in draft version {$draft->version_number}; published rules remain effective until that version is published.");
    }

    public function toggleProgram(Request $request, AssistanceProgram $program, ProgramRequirementVersionService $versions, AgriculturalSchemaCapabilities $schema): RedirectResponse
    {
        $this->staff($request, 'publish');
        $oldStatus = $program->status;
        $newStatus = $oldStatus === 'open' ? 'closed' : 'open';
        if ($schema->requirementVersionsReady() && $newStatus === 'open' && ! $program->currentRequirementVersion()->exists()) {
            // Programs that predate versioned authoring receive an explicitly
            // labelled legacy snapshot. A staff-created draft must be reviewed
            // and published first; opening the program cannot publish it for them.
            if ($program->draftRequirementVersion()->exists()) {
                return back()->withErrors(['requirements' => 'Publish the draft requirements version before making this program available to farmers.']);
            }

            $versions->ensureLegacySnapshot($program);
        }
        $program->update(['status' => $newStatus]);
        ActivityLog::log(
            $newStatus === 'open' ? 'published' : 'unpublished',
            'Assistance program '.$program->name.' '.($newStatus === 'open' ? 'published for public discovery.' : 'hidden from public discovery.'),
            'assistance_program:'.$program->id,
            $request->user()->id,
            'aasms',
            ['status' => $oldStatus],
            ['status' => $newStatus],
        );

        return back()->with('status', $newStatus === 'open' ? 'Program published. Application availability still follows its published dates.' : 'Program hidden from public discovery. Existing applications were retained.');
    }

    public function publishRequirements(Request $request, AssistanceProgram $program, int $version, ProgramRequirementVersionService $versions, AgriculturalSchemaCapabilities $schema): RedirectResponse
    {
        $this->staff($request, 'publish');
        abort_unless($schema->requirementVersionsReady(), 503, 'Program requirement versioning is unavailable until its database migration is activated.');
        $version = AssistanceProgramRequirementVersion::query()->findOrFail($version);
        abort_unless((int) $version->program_id === (int) $program->id, 404);
        $published = $versions->publish($version, $request->user()->id);

        return back()->with('status', "Requirements version {$published->version_number} published. Existing applications remain attached to their recorded version.");
    }

    public function showApplication(Request $request, AssistanceApplication $application, AgriculturalSchemaCapabilities $schema, AssistanceApplicationDocumentService $documents): View
    {
        $this->staff($request, 'view');
        $relations = ['program', 'farmer.user', 'farmer.farms', 'history', 'approvedSupplyItem', 'distributions.batch.item'];
        if ($schema->requirementVersionsReady()) {
            $relations[] = 'requirementVersion.documentRequirements';
            $relations[] = 'requirementVersion.publisher';
        }
        if ($schema->acknowledgmentsReady()) {
            $relations[] = 'acknowledgment';
        }
        $canViewPrivateDocuments = $request->user()->canModuleAction('aasms', 'view')
            && $request->user()->canModuleAction('aasms', 'documents_view_sensitive');
        $canReviewDocuments = $canViewPrivateDocuments
            && $request->user()->canModuleAction('aasms', 'documents_review');
        if ($schema->documentWorkflowReady() && $canViewPrivateDocuments) {
            $relations[] = 'documents.requirement';
            $relations[] = 'documents.events';
            $relations[] = 'documents.reviewer';
            $relations[] = 'submissionSnapshots';
        }
        $application->load($relations);
        if (! $schema->requirementVersionsReady()) {
            $application->setRelation('requirementVersion', null);
        }
        if (! $schema->acknowledgmentsReady()) {
            $application->setRelation('acknowledgment', null);
        }
        if (! $schema->documentWorkflowReady() || ! $canViewPrivateDocuments) {
            $application->setRelation('documents', collect());
        }

        return view('modules.aasms.application', [
            'application' => $application,
            'requirementsSchemaReady' => $schema->requirementVersionsReady(),
            'documentWorkflowReady' => $schema->documentWorkflowReady(),
            'canViewPrivateDocuments' => $canViewPrivateDocuments,
            'canReviewDocuments' => $canReviewDocuments,
            'documentUploadsEnabled' => $documents->uploadEnabled(),
            'documentScannerAvailable' => $documents->scannerAvailable(),
        ]);
    }

    private function programData(ProgramRequest $request): array
    {
        $data = $request->validated();
        $split = fn ($key) => collect(is_array($data[$key] ?? null) ? $data[$key] : explode(',', (string) ($data[$key] ?? '')))
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $data['eligibility_rules'] = ['requires_verified_farmer' => true, 'requires_farm' => (bool) ($data['requires_farm'] ?? false), 'barangays' => $split('barangays'), 'activities' => $split('activities'), 'farm_categories' => $split('farm_categories'), 'previous_beneficiary_restricted' => (bool) ($data['previous_beneficiary_restricted'] ?? false)];
        unset($data['requires_farm'],$data['barangays'],$data['activities'],$data['farm_categories'],$data['previous_beneficiary_restricted']);

        $data['requirements'] = collect($data['requirements'] ?? [])
            ->filter(fn (array $requirement): bool => filled($requirement['display_name'] ?? null))
            ->values()
            ->all();

        return $data;
    }

    private function versionData(array $data): array
    {
        return [
            'opens_on' => $data['opens_on'] ?? null,
            'closes_on' => $data['closes_on'] ?? null,
            'eligibility_rules' => $data['eligibility_rules'],
            'requirements' => $data['requirements'] ?? [],
        ];
    }

    public function review(Request $request, AssistanceApplication $application, AssistanceService $service, AssistanceApplicationDocumentService $documents): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected,returned', 'review_notes' => 'required_if:status,rejected,returned|nullable|max:2000', 'approved_quantity' => 'nullable|numeric|min:.01', 'approved_supply_item_id' => 'nullable|exists:supply_items,id']);
        $permission = match ($data['status']) {
            'approved' => 'approve', 'rejected' => 'reject', default => 'review'
        };
        $this->staff($request, $permission);
        if ($data['status'] === 'approved') {
            $documents->assertRequiredDocumentsReady($application, 'approved');
        }
        $service->status($application, $data['status'], $request->user()->id, $data['review_notes'] ?? null, isset($data['approved_quantity']) ? (float) $data['approved_quantity'] : null, $data['approved_supply_item_id'] ?? null);

        return back()->with('status', 'Application reviewed.');
    }

    public function distribute(Request $request, AssistanceApplication $application, AssistanceService $service, InventoryService $inventory): RedirectResponse
    {
        $this->staff($request, 'distribute');
        $data = $request->validate(['supply_batch_id' => 'required|exists:supply_batches,id', 'quantity' => 'required|numeric|min:.01', 'idempotency_key' => 'nullable|string|max:100']);
        $service->distribute($application, $data, $request->user()->id, $inventory);

        return back()->with('status', 'Assistance distributed and inventory released.');
    }

    public function apply(Request $request, AssistanceProgram $program, AssistanceApplicationSubmissionService $submissions, AgriculturalSchemaCapabilities $schema): RedirectResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        abort_unless($schema->applicationSubmissionReady(), 503, 'Online applications are temporarily unavailable while the assistance requirements schema is being activated. Your account and application data were not changed.');
        $farmer = $request->user()->farmer()->first();
        abort_unless($farmer && $farmer->registration_status === 'verified', 403);
        $data = $request->validate([
            'purpose' => ['nullable', 'string', 'max:2000'],
            'submission_key' => ['nullable', 'uuid'],
        ]);
        $submissionKey = $data['submission_key'] ?? (string) \Illuminate\Support\Str::uuid();
        $application = $submissions->submit($program, $farmer, $data['purpose'] ?? '', $submissionKey, $request->user()->id);

        return redirect()->route('farmer.applications.show', $application)
            ->with('status', 'Your application was received. Keep the reference shown below for tracking.');
    }

    public function resubmit(Request $request, AssistanceApplication $application, ProgramEligibilityService $eligibility, AgriculturalSchemaCapabilities $schema, AssistanceApplicationDocumentService $documents): RedirectResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->first();
        abort_unless($farmer, 403);
        $data = $request->validate(['purpose' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($application, $farmer, $data, $eligibility, $request, $schema, $documents): void {
            $relations = ['farmer.farms'];
            if ($schema->requirementVersionsReady()) {
                $relations[] = 'program.currentRequirementVersion';
            } else {
                $relations[] = 'program';
            }
            $locked = AssistanceApplication::query()
                ->with($relations)
                ->lockForUpdate()
                ->findOrFail($application->id);
            abort_unless((int) $locked->farmer_id === (int) $farmer->id && $locked->status === 'returned', 403);
            $documents->assertRequiredDocumentsReady($locked, 'resubmitted');
            $result = $eligibility->requireEligible($locked->program, $locked->farmer);
            $previous = $locked->status;
            $locked->update([
                'purpose' => $data['purpose'] ?? $locked->purpose,
                'status' => 'submitted',
                'eligibility_passed' => $result['passed'],
                'eligibility_notes' => $result['notes'],
            ]);
            $locked->history()->create([
                'from_status' => $previous,
                'to_status' => 'submitted',
                'changed_by' => $request->user()->id,
                'notes' => 'Farmer resubmitted application.',
            ]);
            $locked->refresh()->load('program');
            $documents->recordResubmissionSnapshot($locked, $request->user(), now()->utc());
        });

        return back()->with('status', 'Application resubmitted for MAO review.');
    }

    public function export(Request $request, BongabongGeography $geography)
    {
        $this->staff($request, 'export');
        $rows = $this->filters($request, $geography->barangays())->latest()->get();
        $safe = fn ($v) => (preg_match('/^[=+\-@]/', (string) $v) ? chr(39) : '').$v;

        return response()->streamDownload(function () use ($rows, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Application', 'Farmer', 'Current farmer barangay', 'Farmer barangay at distribution', 'Program', 'Supply item', 'Quantity', 'Processed at']);
            foreach ($rows as $d) {
                fputcsv($out, [$safe($d->reference), $d->application_id, $safe($d->application->farmer->user->name), $safe($d->application->farmer->barangay ?? ''), $safe($d->farmer_barangay_at_distribution ?? ''), $safe($d->application->program->name), $safe($d->batch->item->name), $d->quantity, $d->distributed_at]);
            }fclose($out);
        }, 'aasms-distributions.csv', ['Content-Type' => 'text/csv']);
    }

    public function farmerPrograms(Request $request, AssistanceProgramCatalogService $catalog, ProgramEligibilityService $eligibility, AgriculturalSchemaCapabilities $schema): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->with('farms')->firstOrFail();
        $catalogData = $catalog->forFarmer($request, $farmer, $eligibility);

        $applicationRelations = ['program.supplyItem', 'approvedSupplyItem', 'distributions.batch.item', 'history'];
        if ($schema->acknowledgmentsReady()) {
            $applicationRelations[] = 'acknowledgment';
        }
        if ($schema->requirementVersionsReady()) {
            $applicationRelations[] = 'requirementVersion';
        }
        $applications = AssistanceApplication::with($applicationRelations)->where('farmer_id', $farmer->id)->latest()->get();
        $applications->each(function (AssistanceApplication $application) use ($schema): void {
            if (! $schema->acknowledgmentsReady()) {
                $application->setRelation('acknowledgment', null);
            }
            if (! $schema->requirementVersionsReady()) {
                $application->setRelation('requirementVersion', null);
            }
        });

        return view('farmer.assistance', [
            'farmer' => $farmer,
            'applications' => $applications,
            'notifications' => $request->user()->notifications()->latest()->take(10)->get(),
            'applicationSubmissionReady' => $schema->applicationSubmissionReady(),
            'requirementsSchemaReady' => $schema->requirementVersionsReady(),
        ] + $catalogData);
    }
}
