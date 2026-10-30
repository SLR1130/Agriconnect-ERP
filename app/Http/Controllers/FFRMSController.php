<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\FarmerAssociation;
use App\Services\FarmerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FFRMSController extends Controller
{
    public function registrations(Request $request): View
    {
        $this->authorizeStaff($request, 'view');
        $farmers = Farmer::query()->with('user')->withCount('farms')
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
        ]);
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
}
