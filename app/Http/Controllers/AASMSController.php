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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AASMSController extends Controller
{
    private function staff(Request $request, string $action = 'view'): void
    {
        abort_unless($request->user()->canModuleAction('aasms', $action), 403);
    }

    private function filters(Request $request)
    {
        return AssistanceDistribution::with(['application.farmer.user', 'application.program', 'batch.item', 'processor'])
            ->when($request->filled('reference'), fn ($q) => $q->where('reference', 'like', '%'.$request->reference.'%'))
            ->when($request->filled('application'), fn ($q) => $q->where('application_id', $request->application))
            ->when($request->filled('program_id'), fn ($q) => $q->whereHas('application', fn ($a) => $a->where('program_id', $request->program_id)))
            ->when($request->filled('farmer'), fn ($q) => $q->whereHas('application.farmer.user', fn ($u) => $u->where('name', 'like', '%'.$request->farmer.'%')))
            ->when($request->filled('supply_item_id'), fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('supply_item_id', $request->supply_item_id)))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('distributed_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('distributed_at', '<=', $request->to));
    }

    public function index(Request $request, AgriculturalBusinessCalendar $calendar): View
    {
        $this->staff($request, 'view');
        $programs = AssistanceProgram::with([
            'supplyItem', 'applications',
            'currentRequirementVersion.documentRequirements', 'currentRequirementVersion.publisher',
            'draftRequirementVersion.documentRequirements', 'draftRequirementVersion.creator',
        ])->latest()->get();
        $applicationCounts = AssistanceApplication::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $applications = AssistanceApplication::with([
            'program', 'farmer.user', 'farmer.farms', 'history', 'approvedSupplyItem',
            'requirementVersion', 'acknowledgment',
        ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $batches = SupplyBatch::with('item')->where('status', 'available')->where('quantity_available', '>', 0)
            ->where(fn ($query) => $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))
            ->get();
        $distributions = $this->filters($request)->latest()->paginate(15, ['*'], 'distribution_page')->withQueryString();

        return view('modules.aasms.dashboard', compact('programs', 'applications', 'batches', 'distributions') + [
            'items' => SupplyItem::where('status', 'active')->orderBy('name')->get(),
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
        ]);
    }

    public function storeProgram(ProgramRequest $request, ProgramRequirementVersionService $versions): RedirectResponse
    {
        $this->staff($request, 'create');
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

    public function updateProgram(ProgramRequest $request, AssistanceProgram $program, ProgramRequirementVersionService $versions): RedirectResponse
    {
        $this->staff($request, 'update');
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

    public function toggleProgram(Request $request, AssistanceProgram $program, ProgramRequirementVersionService $versions): RedirectResponse
    {
        $this->staff($request, 'publish');
        $oldStatus = $program->status;
        $newStatus = $oldStatus === 'open' ? 'closed' : 'open';
        if ($newStatus === 'open' && ! $program->currentRequirementVersion()->exists()) {
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

    public function publishRequirements(Request $request, AssistanceProgram $program, AssistanceProgramRequirementVersion $version, ProgramRequirementVersionService $versions): RedirectResponse
    {
        $this->staff($request, 'publish');
        abort_unless((int) $version->program_id === (int) $program->id, 404);
        $published = $versions->publish($version, $request->user()->id);

        return back()->with('status', "Requirements version {$published->version_number} published. Existing applications remain attached to their recorded version.");
    }

    public function showApplication(Request $request, AssistanceApplication $application): View
    {
        $this->staff($request, 'view');
        $application->load([
            'program', 'farmer.user', 'requirementVersion.documentRequirements',
            'acknowledgment', 'history', 'approvedSupplyItem', 'distributions.batch.item',
        ]);

        return view('modules.aasms.application', compact('application'));
    }

    private function programData(ProgramRequest $request): array
    {
        $data = $request->validated();
        $split = fn ($key) => collect(explode(',', (string) ($data[$key] ?? '')))->map(fn ($v) => trim($v))->filter()->values()->all();
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

    public function review(Request $request, AssistanceApplication $application, AssistanceService $service): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected,returned', 'review_notes' => 'required_if:status,rejected,returned|nullable|max:2000', 'approved_quantity' => 'nullable|numeric|min:.01', 'approved_supply_item_id' => 'nullable|exists:supply_items,id']);
        $permission = match ($data['status']) {
            'approved' => 'approve', 'rejected' => 'reject', default => 'review'
        };
        $this->staff($request, $permission);
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

    public function apply(Request $request, AssistanceProgram $program, AssistanceApplicationSubmissionService $submissions): RedirectResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
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

    public function resubmit(Request $request, AssistanceApplication $application, ProgramEligibilityService $eligibility): RedirectResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->first();
        abort_unless($farmer, 403);
        $data = $request->validate(['purpose' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($application, $farmer, $data, $eligibility, $request): void {
            $locked = AssistanceApplication::query()
                ->with(['program.currentRequirementVersion', 'farmer.farms'])
                ->lockForUpdate()
                ->findOrFail($application->id);
            abort_unless((int) $locked->farmer_id === (int) $farmer->id && $locked->status === 'returned', 403);
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
        });

        return back()->with('status', 'Application resubmitted for MAO review.');
    }

    public function export(Request $request)
    {
        $this->staff($request, 'export');
        $rows = $this->filters($request)->latest()->get();
        $safe = fn ($v) => (preg_match('/^[=+\-@]/', (string) $v) ? chr(39) : '').$v;

        return response()->streamDownload(function () use ($rows, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Application', 'Farmer', 'Program', 'Supply item', 'Quantity', 'Processed at']);
            foreach ($rows as $d) {
                fputcsv($out, [$safe($d->reference), $d->application_id, $safe($d->application->farmer->user->name), $safe($d->application->program->name), $safe($d->batch->item->name), $d->quantity, $d->distributed_at]);
            }fclose($out);
        }, 'aasms-distributions.csv', ['Content-Type' => 'text/csv']);
    }

    public function farmerPrograms(Request $request, AssistanceProgramCatalogService $catalog, ProgramEligibilityService $eligibility): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->with('farms')->firstOrFail();
        $catalogData = $catalog->forFarmer($request, $farmer, $eligibility);

        return view('farmer.assistance', [
            'farmer' => $farmer,
            'applications' => AssistanceApplication::with(['program.supplyItem', 'approvedSupplyItem', 'distributions.batch.item', 'history', 'acknowledgment', 'requirementVersion'])->where('farmer_id', $farmer->id)->latest()->get(),
            'notifications' => $request->user()->notifications()->latest()->take(10)->get(),
        ] + $catalogData);
    }
}
