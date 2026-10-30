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
        $farmers = Farmer::query()->with(['user', 'farms', 'registrationHistory' => fn ($query) => $query->with('processor')->latest()])->withCount('farms')
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('registration_status', $status))
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('farmer_code', 'like', '%'.$search.'%')
                        ->orWhere('barangay', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest()->paginate(15)->withQueryString();

        $associations = FarmerAssociation::query()->withCount('members')->latest()->get();
        return view('ffrms.registrations', compact('farmers', 'associations') + [
            'canReview' => $request->user()->canModuleAction('ffrms', 'review') || $request->user()->canErp('farmer.verify'),
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
            $old = $farmer->only(['phone', 'address', 'barangay']);
            $farmer->update(collect($data)->only(['phone', 'address', 'barangay'])->all());
            $farmer->user()->update(collect($data)->only(['name', 'email'])->all());
            ActivityLog::log('updated', 'Farmer identity and contact information corrected.', $farmer->farmer_code, $request->user()->id, 'ffrms', $old, $farmer->fresh()->only(['phone', 'address', 'barangay']));
        });

        return back()->with('status', 'Farmer information updated and audited.');
    }

    public function updateFarm(Request $request, Farm $farm): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $this->farmData($request);
        $old = $farm->only(array_keys($data));
        $farm->update($data);
        ActivityLog::log('updated', 'Farm record corrected.', 'farm:'.$farm->id, $request->user()->id, 'ffrms', $old, $farm->fresh()->only(array_keys($data)));

        return back()->with('status', 'Farm record updated and audited.');
    }

    public function export(Request $request)
    {
        $this->authorizeStaff($request, 'export');
        $rows = Farmer::query()->with(['user'])->withCount('farms')
            ->when($request->filled('status'), fn ($query) => $query->where('registration_status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($nested) => $nested->where('farmer_code', 'like', '%'.$search.'%')->orWhere('barangay', 'like', '%'.$search.'%')->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%')));
            })->latest()->get();
        $safe = fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($rows, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Farmer code', 'Name', 'Email', 'Phone', 'Barangay', 'Status', 'Farm count']);
            foreach ($rows as $farmer) {
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

        return back()->with('status', 'Farmer registration updated.');
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
