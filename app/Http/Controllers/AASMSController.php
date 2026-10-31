<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProgramRequest;
use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\SupplyBatch;
use App\Models\SupplyItem;
use App\Services\AssistanceService;
use App\Services\InventoryService;
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

    public function index(Request $request): View
    {
        $this->staff($request, 'view');
        $programs = AssistanceProgram::with(['supplyItem', 'applications'])->latest()->get();
        $applicationCounts = AssistanceApplication::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $applications = AssistanceApplication::with(['program', 'farmer.user', 'farmer.farms', 'history', 'approvedSupplyItem'])
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
            'canReview' => $request->user()->canModuleAction('aasms', 'review'),
            'canApprove' => $request->user()->canModuleAction('aasms', 'approve'),
            'canReject' => $request->user()->canModuleAction('aasms', 'reject'),
            'canDistribute' => $request->user()->canModuleAction('aasms', 'distribute'),
            'canExport' => $request->user()->canModuleAction('aasms', 'export'),
        ]);
    }

    public function storeProgram(ProgramRequest $request): RedirectResponse
    {
        $this->staff($request, 'create');
        AssistanceProgram::create($this->programData($request) + ['status' => 'open']);

        return back()->with('status', 'Assistance program created.');
    }

    public function updateProgram(ProgramRequest $request, AssistanceProgram $program): RedirectResponse
    {
        $this->staff($request, 'update');
        $program->update($this->programData($request));

        return back()->with('status', 'Assistance program updated; historical applications were retained.');
    }

    public function toggleProgram(Request $request, AssistanceProgram $program): RedirectResponse
    {
        $this->staff($request, 'update');
        $program->update(['status' => $program->status === 'open' ? 'closed' : 'open']);

        return back()->with('status', 'Program availability updated.');
    }

    private function programData(ProgramRequest $request): array
    {
        $data = $request->validated();
        $split = fn ($key) => collect(explode(',', (string) ($data[$key] ?? '')))->map(fn ($v) => trim($v))->filter()->values()->all();
        $data['eligibility_rules'] = ['requires_verified_farmer' => true, 'requires_farm' => (bool) ($data['requires_farm'] ?? false), 'barangays' => $split('barangays'), 'activities' => $split('activities'), 'farm_categories' => $split('farm_categories'), 'previous_beneficiary_restricted' => (bool) ($data['previous_beneficiary_restricted'] ?? false)];
        unset($data['requires_farm'],$data['barangays'],$data['activities'],$data['farm_categories'],$data['previous_beneficiary_restricted']);

        return $data;
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

    public function apply(Request $request, AssistanceProgram $program, ProgramEligibilityService $eligibility): RedirectResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer;
        abort_unless($farmer && $farmer->registration_status === 'verified', 403);
        if (AssistanceApplication::where('program_id', $program->id)->where('farmer_id', $farmer->id)->exists()) {
            return back()->withErrors(['program' => 'Already applied.']);
        }
        $result = $eligibility->requireEligible($program, $farmer);
        $data = $request->validate(['purpose' => 'nullable|max:2000']);
        DB::transaction(function () use ($program, $farmer, $data, $result, $request): void {
            $application = AssistanceApplication::create(['program_id' => $program->id, 'farmer_id' => $farmer->id, 'purpose' => $data['purpose'] ?? null, 'eligibility_passed' => $result['passed'], 'eligibility_notes' => $result['notes'], 'status' => 'submitted']);
            $application->history()->create(['from_status' => null, 'to_status' => 'submitted', 'changed_by' => $request->user()->id, 'notes' => 'Farmer submitted application.']);
        });

        return back()->with('status', 'Assistance application submitted.');
    }

    public function resubmit(Request $request, AssistanceApplication $application, ProgramEligibilityService $eligibility): RedirectResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer;
        abort_unless($farmer && $application->farmer_id === $farmer->id && $application->status === 'returned', 403);
        $result = $eligibility->requireEligible($application->program, $farmer);
        $data = $request->validate(['purpose' => 'nullable|max:2000']);
        $application->update(['purpose' => $data['purpose'] ?? $application->purpose, 'status' => 'submitted', 'eligibility_passed' => $result['passed'], 'eligibility_notes' => $result['notes']]);
        $application->history()->create(['from_status' => 'returned', 'to_status' => 'submitted', 'changed_by' => $request->user()->id, 'notes' => 'Farmer resubmitted application.']);

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

    public function farmerPrograms(Request $request, ProgramEligibilityService $eligibility): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->with('farms')->firstOrFail();
        $programs = AssistanceProgram::with('supplyItem')->where('status', 'open')->latest()->get();

        return view('farmer.assistance', [
            'farmer' => $farmer,
            'programs' => $programs,
            'eligibilities' => $programs->mapWithKeys(fn ($program) => [$program->id => $eligibility->check($program, $farmer)]),
            'applications' => AssistanceApplication::with(['program.supplyItem', 'approvedSupplyItem', 'distributions.batch.item', 'history'])->where('farmer_id', $farmer->id)->latest()->get(),
            'notifications' => $request->user()->notifications()->latest()->take(10)->get(),
        ]);
    }
}
