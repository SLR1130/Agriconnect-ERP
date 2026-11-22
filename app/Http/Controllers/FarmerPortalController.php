<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\Farm;
use App\Models\Farmer;
use App\Services\AgriculturalAnnouncementPresenter;
use App\Services\AgriculturalBusinessCalendar;
use App\Services\AgriculturalSchemaCapabilities;
use App\Services\BongabongGeography;
use App\Services\FarmerActivityFeedService;
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
    public function create(BongabongGeography $geography): View
    {
        return view('farmer.register', [
            'barangays' => $geography->barangays(),
            'farmLocationsReady' => $this->farmLocationColumnsReady(),
            'farmBoundaryReady' => Schema::hasColumn('farms', 'boundary_coordinates'),
            'bongabongBoundary' => $geography->boundaryForMap(),
        ]);
    }

    public function store(Request $request, FarmerRegistrationService $registrationService, BongabongGeography $geography): RedirectResponse
    {
        if (! $this->farmLocationColumnsReady()) {
            throw ValidationException::withMessages([
                'farm_location' => 'Farm map pin saving is not available in this database yet. Your account was not created. Please contact the MAO before registering.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:12'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100', Rule::in($geography->barangays())],
            'farm_name' => ['required', 'string', 'max:255'],
            'farm_barangay' => ['required', 'string', 'max:100', Rule::in($geography->barangays())],
            'farm_location_details' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_source' => ['required', Rule::in(['gps', 'manual'])],
            'location_accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'boundary_coordinates' => ['nullable', 'string', 'max:24000'],
        ]);

        if (! $geography->containsPoint((float) $data['latitude'], (float) $data['longitude'])) {
            throw ValidationException::withMessages([
                'latitude' => 'Choose a farm pin inside the mapped Bongabong municipal area. The outline is approximate and the MAO will verify the location.',
            ]);
        }

        $farmBoundaryReady = Schema::hasColumn('farms', 'boundary_coordinates');
        $boundaryCoordinates = null;
        if ($request->filled('boundary_coordinates')) {
            if (! $farmBoundaryReady) {
                throw ValidationException::withMessages([
                    'boundary_coordinates' => 'Farm outline saving is not active in this database. Remove the outline and submit the location pin only.',
                ]);
            }

            $boundaryCoordinates = json_decode((string) $data['boundary_coordinates'], true);
            if (! is_array($boundaryCoordinates) || ! $geography->containsFarmOutline($boundaryCoordinates)) {
                throw ValidationException::withMessages([
                    'boundary_coordinates' => 'Draw a valid outline with 3 to 80 points inside Bongabong. Avoid crossing lines.',
                ]);
            }
        }

        $data['farm'] = [
            'name' => $data['farm_name'],
            'barangay' => $data['farm_barangay'],
            'location_details' => $data['farm_location_details'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'location_accuracy_meters' => $data['location_source'] === 'gps' ? ($data['location_accuracy_meters'] ?? null) : null,
            'location_source' => $data['location_source'],
        ];
        if ($farmBoundaryReady) {
            $data['farm']['boundary_coordinates'] = $boundaryCoordinates;
        }

        $user = $registrationService->register($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('farmer.farms')->with('status', 'Your farmer account and first farm were submitted. The map pin is farmer-provided and still needs MAO verification.');
    }

    public function dashboard(Request $request, AgriculturalBusinessCalendar $calendar, FarmerActivityFeedService $activityFeed, AgriculturalAnnouncementPresenter $announcementPresenter, AgriculturalSchemaCapabilities $schema): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);

        $farmer = $request->user()->farmer()->with(['farms', 'registrationHistory.processor'])->firstOrFail();
        $businessDate = $calendar->today()->toDateString();
        $activityQuery = $activityFeed->query($farmer);
        $needsAttention = $activityFeed->present((clone $activityQuery)->reorder()
            ->whereIn('activity_type', ['Assistance application', 'Agricultural submission'])
            ->where('activity_status', 'returned')
            ->orderByDesc('activity_at')
            ->limit(4)
            ->get());
        $attentionKeys = $needsAttention->map(fn (array $activity) => $activity['type'].':'.$activity['record_id'])->all();
        $recentActivities = $activityFeed->present((clone $activityQuery)->reorder()->limit(12)->get())
            ->reject(fn (array $activity) => in_array($activity['type'].':'.$activity['record_id'], $attentionKeys, true))
            ->take(5)
            ->values();
        $announcementRelations = ['supplyItem'];
        if (Schema::hasTable('agricultural_announcements')) {
            $announcementRelations['announcements'] = fn ($query) => $query->visibleToFarmers()
                ->with($schema->announcementRevisionsReady() ? 'publishedRevision.media' : [])
                ->latest('published_at');
        }
        $programs = AssistanceProgram::query()->with($announcementRelations)
            ->where('status', 'open')
            ->where(fn ($query) => $query->whereNull('opens_on')->orWhereDate('opens_on', '<=', $businessDate))
            ->where(fn ($query) => $query->whereNull('closes_on')->orWhereDate('closes_on', '>=', $businessDate))
            ->orderBy('closes_on')->take(3)->get();
        $announcementsAvailable = Schema::hasTable('agricultural_announcements');
        $announcements = $announcementsAvailable
            ? AgriculturalAnnouncement::query()->visibleToFarmers()->with($schema->announcementRevisionsReady() ? ['program', 'publishedRevision.media'] : ['program'])->latest('published_at')->take(3)->get()
            : collect();
        if (! $schema->announcementRevisionsReady()) {
            $announcements->each(fn (AgriculturalAnnouncement $notice) => $notice->setRelation('publishedRevision', null));
            $programs->each(function (AssistanceProgram $program): void {
                if (! Schema::hasTable('agricultural_announcements')) {
                    $program->setRelation('announcements', collect());

                    return;
                }
                $program->announcements?->each(fn (AgriculturalAnnouncement $notice) => $notice->setRelation('publishedRevision', null));
            });
        }

        return view('farmer.portal', [
            'farmer' => $farmer,
            'programs' => $programs,
            'needsAttention' => $needsAttention,
            'recentActivities' => $recentActivities,
            'distributions' => AssistanceDistribution::query()->with(['application.program', 'batch.item'])
                ->whereHas('application', fn ($query) => $query->where('farmer_id', $farmer->id))->latest('distributed_at')->take(3)->get(),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
            'announcementsAvailable' => $announcementsAvailable,
            'announcements' => $announcements,
            'announcementPresentations' => $announcements->mapWithKeys(fn (AgriculturalAnnouncement $notice) => [$notice->id => $announcementPresenter->present($notice, true)]),
        ]);
    }

    public function farms(Request $request, BongabongGeography $geography): View
    {
        $farmer = $this->ownedFarmer($request)->load(['farms', 'registrationHistory.processor']);

        return view('farmer.farms', [
            'farmer' => $farmer,
            'farmLocationsReady' => $this->farmLocationColumnsReady(),
            'farmBoundaryReady' => Schema::hasColumn('farms', 'boundary_coordinates'),
            'productionSubmissionsAvailable' => Schema::hasTable('production_submissions')
                && Schema::hasTable('production_submission_histories'),
            'barangays' => $geography->barangays(),
            'bongabongBoundary' => $geography->boundaryForMap(),
        ]);
    }

    public function profile(Request $request, BongabongGeography $geography): View
    {
        $farmer = $this->ownedFarmer($request);

        return view('farmer.profile', ['farmer' => $farmer, 'barangays' => $geography->barangays()]);
    }

    public function updateProfile(Request $request, BongabongGeography $geography): RedirectResponse
    {
        $farmer = $this->ownedFarmer($request);
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100', Rule::in([$farmer->barangay, ...$geography->barangays()])],
        ]);
        if ($farmer->isVerified() && $data['barangay'] !== $farmer->barangay) {
            throw ValidationException::withMessages(['barangay' => 'Ask the MAO to review changes to a verified barangay. Your current verified record is unchanged.']);
        }
        $farmer->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function storeFarm(Request $request, BongabongGeography $geography): RedirectResponse
    {
        $farmer = $this->ownedFarmer($request);
        $data = $this->farmData($request, $geography);
        $farmer->farms()->create($data);

        return back()->with('status', 'Farm record added.');
    }

    public function updateFarm(Request $request, Farm $farm, BongabongGeography $geography): RedirectResponse
    {
        $farmer = $this->ownedFarmer($request);
        abort_unless($farm->farmer_id === $farmer->id, 403);
        $farm->update($this->farmData($request, $geography, $farm));

        return back()->with('status', 'Farm record updated.');
    }

    private function ownedFarmer(Request $request): Farmer
    {
        abort_unless($request->user()->hasRole('farmer'), 403);

        return $request->user()->farmer()->firstOrFail();
    }

    private function farmData(Request $request, BongabongGeography $geography, ?Farm $existingFarm = null): array
    {
        $farmLocationsReady = $this->farmLocationColumnsReady();
        if (! $farmLocationsReady && ($request->filled('latitude') || $request->filled('longitude'))) {
            throw ValidationException::withMessages([
                'location' => 'Exact map pins are waiting for a database update. You can save the farm using its barangay and location details for now.',
            ]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100', Rule::in(array_values(array_unique([$existingFarm?->barangay, ...$geography->barangays()])))],
            'location_details' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'numeric', 'min:0.01'],
            'area_unit' => ['required', Rule::in(['hectare', 'square_meter'])],
            'ownership_type' => ['required', Rule::in(['owner', 'tenant', 'leaseholder', 'other'])],
            'agricultural_activities' => ['nullable', 'string', 'max:1000'],
            'categories' => ['nullable', 'string', 'max:1000'],
        ];
        if ($farmLocationsReady) {
            $rules += [
                'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
                'location_accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:100000'],
                'location_source' => ['nullable', 'required_with:latitude,longitude', Rule::in(['gps', 'manual'])],
            ];
        }
        $data = $request->validate($rules);
        $data['agricultural_activities'] = $this->csv($data['agricultural_activities'] ?? null);
        $data['categories'] = $this->csv($data['categories'] ?? null);
        if ($farmLocationsReady) {
            $data['latitude'] = $data['latitude'] ?? null;
            $data['longitude'] = $data['longitude'] ?? null;
            $data['location_accuracy_meters'] = $data['location_accuracy_meters'] ?? null;
            $data['location_source'] = $data['location_source'] ?? null;
            if ($data['latitude'] === null && $data['longitude'] === null) {
                $data['location_source'] = null;
                $data['location_accuracy_meters'] = null;
            } elseif ($data['location_source'] === 'manual') {
                $data['location_accuracy_meters'] = null;
            }
            if ($data['latitude'] !== null && ! $geography->containsPoint((float) $data['latitude'], (float) $data['longitude'])) {
                throw ValidationException::withMessages(['latitude' => 'Choose a point inside the mapped Bongabong municipal area. The outline is approximate; the MAO will verify the location.']);
            }
        }

        if ($request->filled('boundary_coordinates')) {
            if (! Schema::hasColumn('farms', 'boundary_coordinates')) {
                throw ValidationException::withMessages(['boundary_coordinates' => 'Farm-outline saving is not active in this database yet. Your farm record was not changed.']);
            }
            $outline = json_decode((string) $request->input('boundary_coordinates'), true);
            if (! is_array($outline) || ! $geography->containsFarmOutline($outline)) {
                throw ValidationException::withMessages(['boundary_coordinates' => 'Draw a valid outline with 3 to 80 points inside the mapped Bongabong area. Avoid crossing lines.']);
            }
            $data['boundary_coordinates'] = array_values($outline);
        } elseif (Schema::hasColumn('farms', 'boundary_coordinates') && $request->exists('boundary_coordinates')) {
            $data['boundary_coordinates'] = null;
        }

        return $data;
    }

    private function farmLocationColumnsReady(): bool
    {
        return collect(['latitude', 'longitude', 'location_accuracy_meters', 'location_source'])
            ->every(fn (string $column): bool => Schema::hasColumn('farms', $column));
    }

    private function csv(?string $value): array
    {
        return collect(explode(',', (string) $value))->map(fn (string $item) => trim($item))->filter()->values()->all();
    }
}
