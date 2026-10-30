<?php

namespace App\Http\Controllers;

use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentRequest;
use App\Models\Farmer;
use App\Models\User;
use App\Services\EquipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AEAMSController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isStaff = $this->canStaff($user, 'view');
        $canCreate = $this->canStaff($user, 'create');
        $canUpdate = $this->canStaff($user, 'update');
        $canReview = $this->canStaff($user, 'review');
        $canIssue = $this->canStaff($user, 'issue');
        $canReturn = $this->canStaff($user, 'return');
        $canMaintenance = $this->canStaff($user, 'maintenance');
        $farmer = $user->farmer;
        $isFarmer = $user->canErp('farmer.equipment.request') && $farmer?->isVerified();

        abort_unless($isStaff || $isFarmer, 403);

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $categoryId = $request->integer('category_id') ?: null;
        $assets = EquipmentAsset::query()
            ->with(['category', 'responsibleUser', 'maintenances' => fn ($query) => $query->whereIn('status', ['open', 'in_progress'])->latest()])
            ->when(! $isStaff, fn ($query) => $query->where('availability_status', 'available'))
            ->when(in_array($status, ['available', 'issued', 'maintenance', 'archived'], true), fn ($query) => $query->where('availability_status', $status))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($search, fn ($query, $value) => $query->where(function ($nested) use ($value) {
                $nested->where('asset_code', 'like', '%'.$value.'%')
                    ->orWhere('name', 'like', '%'.$value.'%')
                    ->orWhere('assigned_location', 'like', '%'.$value.'%');
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $requests = EquipmentRequest::query()
            ->with(['asset.category', 'farmer.user', 'requestedBy', 'reviewer'])
            ->when(! $isStaff, fn ($query) => $query->where('farmer_id', $farmer->id))
            ->latest()
            ->paginate(15, ['*'], 'requests_page')
            ->withQueryString();

        $equipmentCategories = EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get();
        $verifiedFarmers = $isStaff
            ? Farmer::query()->with('user')->where('registration_status', 'verified')->orderBy('farmer_code')->get()
            : collect();
        $personnelUsers = $isStaff
            ? User::query()->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'farmer'))->orderBy('name')->get()
            : collect();
        $maintenances = $isStaff
            ? EquipmentMaintenance::query()->with(['asset', 'reportedBy'])->whereIn('status', ['open', 'in_progress'])->latest()->get()
            : collect();

        return view('modules.aeams.dashboard', [
            'assets' => $assets,
            'requests' => $requests,
            'equipmentCategories' => $equipmentCategories,
            'verifiedFarmers' => $verifiedFarmers,
            'personnelUsers' => $personnelUsers,
            'maintenances' => $maintenances,
            'isStaff' => $isStaff,
            'isFarmer' => $isFarmer,
            'canCreate' => $canCreate,
            'canUpdate' => $canUpdate,
            'canReview' => $canReview,
            'canIssue' => $canIssue,
            'canReturn' => $canReturn,
            'canMaintenance' => $canMaintenance,
            'farmer' => $farmer,
            'stats' => [
                'assets' => EquipmentAsset::count(),
                'available' => EquipmentAsset::where('availability_status', 'available')->count(),
                'reserved' => EquipmentRequest::whereIn('status', ['pending', 'approved'])->count(),
                'issued' => EquipmentAsset::where('availability_status', 'issued')->count(),
                'maintenance' => EquipmentAsset::where('availability_status', 'maintenance')->count(),
                'pending' => EquipmentRequest::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function show(Request $request, EquipmentAsset $equipmentAsset): View
    {
        $user = $request->user();
        $isStaff = $this->canStaff($user, 'view');
        $farmer = $user->farmer;
        $isFarmer = $user->canErp('farmer.equipment.request') && $farmer?->isVerified();

        abort_unless($isStaff || $isFarmer, 403);

        $asset = $equipmentAsset->load(['category', 'responsibleUser']);
        $requests = $asset->requests()
            ->with(['farmer.user', 'requestedBy', 'reviewer'])
            ->when(! $isStaff, fn ($query) => $query->where('farmer_id', $farmer->id))
            ->latest()
            ->get();
        $transactions = $asset->transactions()
            ->with(['performedBy', 'request'])
            ->latest()
            ->get();

        return view('modules.aeams.show', compact('asset', 'requests', 'transactions', 'isStaff') + [
            'canUpdate' => $this->canStaff($user, 'update'),
            'equipmentCategories' => EquipmentCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'personnelUsers' => User::query()->whereDoesntHave('roles', fn ($query) => $query->where('slug', 'farmer'))->orderBy('name')->get(),
        ]);
    }

    public function storeAsset(Request $request, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'create');

        $data = $request->validate([
            'asset_code' => ['required', 'string', 'max:60', 'unique:equipment_assets,asset_code'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'acquired_on' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['required', 'in:good,fair,damaged'],
            'assigned_location' => ['nullable', 'string', 'max:150'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $service->registerAsset($data, $request->user());
        return back()->with('status', 'Equipment asset registered.');
    }

    public function updateAsset(Request $request, EquipmentAsset $equipmentAsset, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'exists:equipment_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'acquired_on' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'condition' => ['required', 'in:good,fair,damaged'],
            'assigned_location' => ['nullable', 'string', 'max:150'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
        ]);
        $service->updateAsset($equipmentAsset, $data, $request->user());
        return back()->with('status', 'Equipment asset details updated and audited.');
    }

    public function archiveAsset(Request $request, EquipmentAsset $equipmentAsset, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $service->archiveAsset($equipmentAsset, $request->user());
        return back()->with('status', 'Equipment asset archived without deleting its history.');
    }

    public function restoreAsset(Request $request, EquipmentAsset $equipmentAsset, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $service->restoreAsset($equipmentAsset, $request->user());
        return back()->with('status', 'Equipment asset restored to available inventory.');
    }

    public function export(Request $request)
    {
        $this->authorizeStaff($request, 'export');
        $assets = EquipmentAsset::with(['category', 'responsibleUser'])->withCount(['requests', 'transactions', 'maintenances'])->latest()->get();
        $safe = fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($assets, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Asset code', 'Name', 'Category', 'Condition', 'Availability', 'Location', 'Responsible', 'Requests', 'Transactions', 'Maintenance records']);
            foreach ($assets as $asset) fputcsv($out, [$safe($asset->asset_code), $safe($asset->name), $safe($asset->category?->name), $asset->condition, $asset->availability_status, $safe($asset->assigned_location), $safe($asset->responsibleUser?->name), $asset->requests_count, $asset->transactions_count, $asset->maintenances_count]);
            fclose($out);
        }, 'aeams-equipment-utilization.csv', ['Content-Type' => 'text/csv']);
    }

    public function storeRequest(Request $request, EquipmentService $service): RedirectResponse
    {
        abort_unless($request->user()->canErp('farmer.equipment.request'), 403);
        abort_unless($request->user()->farmer?->isVerified(), 403);

        $data = $request->validate([
            'equipment_asset_id' => ['required', 'exists:equipment_assets,id'],
            'intended_use' => ['required', 'string', 'max:2000'],
            'schedule_start' => ['required', 'date', 'after:now'],
            'schedule_end' => ['required', 'date', 'after:schedule_start'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $service->requestForUser($request->user(), $data);
        return back()->with('status', 'Equipment request submitted for MAO review.');
    }

    public function review(Request $request, EquipmentRequest $equipmentRequest, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'review');

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ]);

        $service->review($equipmentRequest, $data['status'], $data['review_notes'] ?? null, $request->user());
        return back()->with('status', 'Equipment request review saved.');
    }

    public function cancelRequest(Request $request, EquipmentRequest $equipmentRequest, EquipmentService $service): RedirectResponse
    {
        abort_unless($request->user()->canErp('farmer.equipment.request'), 403);
        abort_unless($request->user()->farmer?->isVerified() && (int) $equipmentRequest->requested_by === (int) $request->user()->id, 403);

        $service->cancel($equipmentRequest, $request->user());
        return back()->with('status', 'Equipment request cancelled.');
    }

    public function issue(Request $request, EquipmentRequest $equipmentRequest, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'issue');

        $data = $request->validate([
            'condition' => ['required', 'in:good,fair,damaged'],
        ]);

        $service->issue($equipmentRequest, $data['condition'], $request->user());
        return back()->with('status', 'Equipment released to the verified farmer.');
    }

    public function returnEquipment(Request $request, EquipmentRequest $equipmentRequest, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'return');

        $data = $request->validate([
            'condition' => ['required', 'in:good,fair,damaged'],
            'needs_maintenance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->return($equipmentRequest, $data['condition'], (bool) ($data['needs_maintenance'] ?? false), $data['notes'] ?? null, $request->user());
        return back()->with('status', 'Equipment return recorded.');
    }

    public function startMaintenance(Request $request, EquipmentAsset $equipmentAsset, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'maintenance');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $service->startMaintenance($equipmentAsset, $data, $request->user());
        return back()->with('status', 'Equipment moved to maintenance.');
    }

    public function completeMaintenance(Request $request, EquipmentMaintenance $equipmentMaintenance, EquipmentService $service): RedirectResponse
    {
        $this->authorizeStaff($request, 'maintenance');

        $data = $request->validate([
            'condition' => ['nullable', 'in:good,fair,damaged'],
        ]);

        $service->completeMaintenance($equipmentMaintenance, $data['condition'] ?? null, $request->user());
        return back()->with('status', 'Maintenance completed and equipment availability updated.');
    }

    private function authorizeStaff(Request $request, string $action): void
    {
        abort_unless($this->canStaff($request->user(), $action), 403);
    }

    private function canStaff(User $user, string $action): bool
    {
        return $user->canModuleAction('aeams', $action);
    }
}
