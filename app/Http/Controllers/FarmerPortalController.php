<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\EquipmentRequest;
use App\Models\Farm;
use App\Models\Farmer;
use App\Services\FarmerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FarmerPortalController extends Controller
{
    public function create(): View
    {
        return view('farmer.register');
    }

    public function store(Request $request, FarmerRegistrationService $registrationService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:12'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
        ]);

        $user = $registrationService->register($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('farmer.portal')->with('status', 'Your account was created. Your farmer registration is pending MAO verification.');
    }

    public function dashboard(Request $request): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);

        $farmer = $request->user()->farmer()->with(['farms', 'registrationHistory.processor'])->firstOrFail();

        return view('farmer.portal', [
            'farmer' => $farmer,
            'programs' => AssistanceProgram::query()->with('supplyItem')->where('status', 'open')
                ->where(fn ($query) => $query->whereNull('opens_on')->orWhereDate('opens_on', '<=', today()))
                ->where(fn ($query) => $query->whereNull('closes_on')->orWhereDate('closes_on', '>=', today()))
                ->orderBy('closes_on')->take(3)->get(),
            'applications' => AssistanceApplication::query()->with('program')->where('farmer_id', $farmer->id)->latest()->take(3)->get(),
            'equipmentRequests' => EquipmentRequest::query()->with('asset')->where('farmer_id', $farmer->id)->latest()->take(3)->get(),
            'distributions' => AssistanceDistribution::query()->with(['application.program', 'batch.item'])
                ->whereHas('application', fn ($query) => $query->where('farmer_id', $farmer->id))->latest('distributed_at')->take(3)->get(),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'announcementsAvailable' => Schema::hasTable('agricultural_announcements'),
            'announcements' => Schema::hasTable('agricultural_announcements')
                ? AgriculturalAnnouncement::query()->visibleToFarmers()->latest('published_at')->take(3)->get()
                : collect(),
        ]);
    }

    public function farms(Request $request): View
    {
        $farmer = $this->ownedFarmer($request)->load(['farms', 'registrationHistory.processor']);

        return view('farmer.farms', compact('farmer'));
    }

    public function profile(Request $request): View
    {
        $farmer = $this->ownedFarmer($request);

        return view('farmer.profile', compact('farmer'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $farmer = $this->ownedFarmer($request);
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
        ]);
        if ($farmer->isVerified() && $data['barangay'] !== $farmer->barangay) {
            throw ValidationException::withMessages(['barangay' => 'Ask the MAO to review changes to a verified barangay. Your current verified record is unchanged.']);
        }
        $farmer->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function storeFarm(Request $request): RedirectResponse
    {
        $farmer = $this->ownedFarmer($request);
        $data = $this->farmData($request);
        $farmer->farms()->create($data);

        return back()->with('status', 'Farm record added.');
    }

    public function updateFarm(Request $request, Farm $farm): RedirectResponse
    {
        $farmer = $this->ownedFarmer($request);
        abort_unless($farm->farmer_id === $farmer->id, 403);
        $farm->update($this->farmData($request));

        return back()->with('status', 'Farm record updated.');
    }

    private function ownedFarmer(Request $request): Farmer
    {
        abort_unless($request->user()->hasRole('farmer'), 403);

        return $request->user()->farmer()->firstOrFail();
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
            'agricultural_activities' => ['nullable', 'string', 'max:1000'],
            'categories' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['agricultural_activities'] = $this->csv($data['agricultural_activities'] ?? null);
        $data['categories'] = $this->csv($data['categories'] ?? null);

        return $data;
    }

    private function csv(?string $value): array
    {
        return collect(explode(',', (string) $value))->map(fn (string $item) => trim($item))->filter()->values()->all();
    }
}
