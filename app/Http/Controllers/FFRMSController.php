<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Farmer;
use App\Models\FarmerAssociation;
use App\Models\Farm;
use App\Services\FarmerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FFRMSController extends Controller
{
    public function registrations(Request $request): View
    {
        $this->authorizeStaff($request, 'view');
        $status = $request->string('status')->toString();
        abort_unless(in_array($status, ['', 'pending', 'verified', 'returned', 'rejected'], true), 404);
        $search = trim($request->string('search')->toString());

        $farmers = Farmer::query()->with(['user', 'farms', 'registrationHistory' => fn ($query) => $query->with('processor')->latest()])->withCount('farms')
            ->when($status !== '', fn ($query) => $query->where('registration_status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('farmer_code', 'like', '%'.$search.'%')
                        ->orWhere('barangay', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
                });
            })
            ->latest()->paginate(15)->withQueryString();

        $associations = FarmerAssociation::query()->withCount('members')->latest()->get();
        $selectedFarmer = $request->filled('record')
            ? Farmer::query()->with(['user', 'verifier', 'farms', 'registrationHistory' => fn ($query) => $query->with('processor')->latest(), 'associations'])->withCount('farms')->findOrFail($request->integer('record'))
            : null;
        $registrationCounts = Farmer::query()
            ->selectRaw('registration_status, COUNT(*) as aggregate')
            ->groupBy('registration_status')
            ->pluck('aggregate', 'registration_status');

        return view('ffrms.registrations', compact('farmers', 'associations') + [
            'selectedFarmer' => $selectedFarmer,
            'registrationCounts' => $registrationCounts,
            'canVerify' => $request->user()->canModuleAction('ffrms', 'approve') || $request->user()->canErp('farmer.verify'),
            'canReturn' => $request->user()->canModuleAction('ffrms', 'review'),
            'canReject' => $request->user()->canModuleAction('ffrms', 'reject'),
            'canManageAssociations' => $request->user()->canModuleAction('ffrms', 'update'),
            'canUpdate' => $request->user()->canModuleAction('ffrms', 'update'),
            'canExport' => $request->user()->canModuleAction('ffrms', 'export'),
        ]);
    }

    public function updateFarmer(Request $request, Farmer $farmer): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($farmer->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($farmer, $data, $request) {
            $user = $farmer->user()->firstOrFail();
            $old = [
                'name' => $user->name,
                'email' => $user->email,
                ...$farmer->only(['phone', 'address', 'barangay']),
            ];
            $farmer->update(collect($data)->only(['phone', 'address', 'barangay'])->all());
            $user->update(collect($data)->only(['name', 'email'])->all());
            ActivityLog::log('updated', 'Farmer identity and contact information corrected.', $farmer->farmer_code, $request->user()->id, 'ffrms', $old, [
                'name' => $user->fresh()->name,
                'email' => $user->fresh()->email,
                ...$farmer->fresh()->only(['phone', 'address', 'barangay']),
            ]);
        });

        return $this->redirectToFarmer($request, $farmer, 'Farmer information updated and audited.');
    }

    public function updateFarm(Request $request, Farm $farm): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $this->farmData($request);
        DB::transaction(function () use ($farm, $data, $request) {
            $old = $farm->only(array_keys($data));
            $farm->update($data);
            ActivityLog::log('updated', 'Farm record corrected.', 'farm:'.$farm->id, $request->user()->id, 'ffrms', $old, $farm->fresh()->only(array_keys($data)));
        });

        return $this->redirectToFarmer($request, $farm->farmer()->firstOrFail(), 'Farm record updated and audited.');
    }

    public function export(Request $request)
    {
        $this->authorizeStaff($request, 'export');
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'verified', 'returned', 'rejected'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $rows = Farmer::query()->with(['user'])->withCount('farms')
            ->when(! empty($filters['status']), fn ($query) => $query->where('registration_status', $filters['status']))
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = trim($filters['search']);
                $query->where(fn ($nested) => $nested
                    ->where('farmer_code', 'like', '%'.$search.'%')
                    ->orWhere('barangay', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')));
            })->orderBy('farmers.id');
        $safe = fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($rows, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Farmer code', 'Name', 'Email', 'Phone', 'Barangay', 'Status', 'Farm count']);
            foreach ($rows->lazyById(500) as $farmer) {
                fputcsv($out, [$safe($farmer->farmer_code), $safe($farmer->user->name), $safe($farmer->user->email), $safe($farmer->phone), $safe($farmer->barangay), $farmer->registration_status, $farmer->farms_count]);
            }
            fclose($out);
        }, 'ffrms-farmers.csv', ['Content-Type' => 'text/csv']);
    }

    public function storeAssociation(Request $request): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'registration_reference' => ['nullable', 'string', 'max:100'], 'barangay' => ['nullable', 'string', 'max:100']]);
        FarmerAssociation::create($data + ['status' => 'active', 'created_by' => $request->user()->id]);
        return back()->with('status', 'Farmer association registered.');
    }

    public function addMember(Request $request, FarmerAssociation $association): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $request->validate(['farmer_id' => ['required', 'exists:farmers,id']]);
        $association->farmers()->syncWithoutDetaching([$data['farmer_id'] => ['membership_status' => 'active', 'joined_at' => now()]]);
        return back()->with('status', 'Farmer added to association.');
    }

    public function review(Request $request, Farmer $farmer, FarmerRegistrationService $registrationService): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:verified,returned,rejected'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->authorizeReview($request, $data['status']);

        $registrationService->transition($farmer, $data['status'], $data['reason'] ?? null, $request->user());

        return $this->redirectToFarmer($request, $farmer, 'Farmer registration updated.');
    }

    private function redirectToFarmer(Request $request, Farmer $farmer, string $message): RedirectResponse
    {
        $query = ['workspace' => 'farmers', 'record' => $farmer->id];
        $search = $request->input('registry_search');
        $status = $request->input('registry_status');
        $page = filter_var($request->input('registry_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (is_string($search) && trim($search) !== '') {
            $query['search'] = mb_substr(trim($search), 0, 100);
        }
        if (is_string($status) && in_array($status, ['pending', 'verified', 'returned', 'rejected'], true)) {
            $query['status'] = $status;
        }
        if ($page !== false && $page !== null) {
            $query['page'] = $page;
        }

        return redirect()->route('modules.ffrms.dashboard', $query)->with('status', $message);
    }

    private function authorizeStaff(Request $request, string $action = 'view'): void
    {
        abort_unless($request->user()->canModuleAction('ffrms', $action), 403);
    }

    private function authorizeReview(Request $request, string $status): void
    {
        $permission = match ($status) {
            'verified' => 'approve',
            'rejected' => 'reject',
            default => 'review',
        };

        $allowed = $request->user()->canModuleAction('ffrms', $permission)
            || ($status === 'verified' && $request->user()->canErp('farmer.verify'));

        abort_unless($allowed, 403);
    }

    private function farmData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'location_details' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'numeric', 'min:0.01'],
            'area_unit' => ['required', Rule::in(['hectare', 'square_meter'])],
            'ownership_type' => ['required', Rule::in(['owner', 'tenant', 'leaseholder', 'other'])],
            'classification' => ['nullable', 'string', 'max:100'],
            'tenancy_details' => ['nullable', 'string', 'max:255'],
            'soil_type' => ['nullable', 'string', 'max:100'],
            'irrigation_type' => ['nullable', 'string', 'max:100'],
            'agricultural_activities' => ['nullable', 'string', 'max:1000'],
            'categories' => ['nullable', 'string', 'max:1000'],
        ]);
        $csv = fn (?string $value) => collect(explode(',', (string) $value))->map(fn ($item) => trim($item))->filter()->values()->all();
        $data['agricultural_activities'] = $csv($data['agricultural_activities'] ?? null);
        $data['categories'] = $csv($data['categories'] ?? null);

        return $data;
    }
}
