<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalSeason;
use App\Models\CropProductionRecord;
use App\Models\Farm;
use App\Models\FarmMonitoringRecord;
use App\Models\Farmer;
use App\Models\FisheryRecord;
use App\Models\LivestockRecord;
use App\Models\PestDiseaseIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class APMSController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeModule($request, 'view');
        $search = $request->string('search')->toString();
        $farms = Farm::query()->whereHas('farmer', fn ($query) => $query->where('registration_status', 'verified'))
            ->with('farmer.user')->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->orderBy('name')->get();
        $seasons = AgriculturalSeason::query()->orderByDesc('starts_on')->get();

        return view('apms.index', [
            'farms' => $farms,
            'seasons' => $seasons,
            'crops' => CropProductionRecord::query()->with(['farm','farmer.user'])->latest()->paginate(10, ['*'], 'crops')->withQueryString(),
            'livestock' => LivestockRecord::query()->with('farm')->latest()->limit(10)->get(),
            'fisheries' => FisheryRecord::query()->with('farm')->latest()->limit(10)->get(),
            'incidents' => PestDiseaseIncident::query()->with('farm')->latest()->limit(10)->get(),
            'monitoring' => FarmMonitoringRecord::query()->with('farm')->latest()->limit(10)->get(),
            'stats' => [
                'crops' => CropProductionRecord::count(),
                'livestock' => LivestockRecord::count(),
                'fisheries' => FisheryRecord::count(),
                'incidents' => PestDiseaseIncident::where('status', 'reported')->count(),
            ],
            'canCreate' => $request->user()->canModuleAction('apms', 'create'),
        ]);
    }

    public function season(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $request->validate(['name' => ['required','string','max:150'], 'starts_on' => ['required','date'], 'ends_on' => ['required','date','after_or_equal:starts_on']]);
        AgriculturalSeason::create($data);
        return back()->with('status', 'Agricultural season created.');
    }

    public function crop(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $request->validate(['farmer_id' => ['required','exists:farmers,id'], 'farm_id' => ['required','exists:farms,id'], 'season_id' => ['nullable','exists:agricultural_seasons,id'], 'crop_type' => ['required','string','max:120'], 'variety' => ['nullable','string','max:120'], 'planted_on' => ['nullable','date'], 'harvested_on' => ['nullable','date','after_or_equal:planted_on'], 'area_planted' => ['nullable','numeric','min:0.01'], 'area_unit' => ['required',Rule::in(['hectare','square_meter'])], 'yield_quantity' => ['nullable','numeric','min:0'], 'yield_unit' => ['required','string','max:30'], 'status' => ['required',Rule::in(['planned','growing','harvested','cancelled'])], 'notes' => ['nullable','string','max:2000']]);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        CropProductionRecord::create($data + ['recorded_by' => $request->user()->id]);
        return back()->with('status', 'Crop production record saved.');
    }

    public function livestock(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $request->validate(['farmer_id' => ['required','exists:farmers,id'], 'farm_id' => ['required','exists:farms,id'], 'animal_type' => ['required','string','max:120'], 'breed' => ['nullable','string','max:120'], 'quantity' => ['required','integer','min:1'], 'recorded_on' => ['required','date'], 'notes' => ['nullable','string','max:2000']]);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        LivestockRecord::create($data + ['recorded_by' => $request->user()->id]);
        return back()->with('status', 'Livestock record saved.');
    }

    public function fishery(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $request->validate(['farmer_id' => ['required','exists:farmers,id'], 'farm_id' => ['required','exists:farms,id'], 'fishery_type' => ['required','string','max:120'], 'species' => ['nullable','string','max:120'], 'production_quantity' => ['nullable','numeric','min:0'], 'production_unit' => ['required','string','max:30'], 'recorded_on' => ['required','date'], 'notes' => ['nullable','string','max:2000']]);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        FisheryRecord::create($data + ['recorded_by' => $request->user()->id]);
        return back()->with('status', 'Fisheries record saved.');
    }

    public function incident(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $request->validate(['farmer_id' => ['required','exists:farmers,id'], 'farm_id' => ['required','exists:farms,id'], 'incident_type' => ['required','string','max:120'], 'affected_crop_or_animal' => ['required','string','max:120'], 'reported_on' => ['required','date'], 'severity' => ['required',Rule::in(['low','medium','high','critical'])], 'description' => ['nullable','string','max:3000']]);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        PestDiseaseIncident::create($data + ['reported_by' => $request->user()->id]);
        return back()->with('status', 'Pest or disease incident reported.');
    }

    public function monitoring(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $request->validate(['farmer_id' => ['required','exists:farmers,id'], 'farm_id' => ['required','exists:farms,id'], 'visited_on' => ['required','date'], 'monitoring_type' => ['required','string','max:120'], 'findings' => ['required','string','max:4000'], 'recommendations' => ['nullable','string','max:4000']]);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        FarmMonitoringRecord::create($data + ['recorded_by' => $request->user()->id]);
        return back()->with('status', 'Farm monitoring record saved.');
    }

    private function authorizeModule(Request $request, string $action): void
    {
        abort_unless($request->user()->canModuleAction('apms', $action), 403);
    }

    private function validateFarmOwner(int $farmerId, int $farmId): void
    {
        abort_unless(Farm::query()->whereKey($farmId)->where('farmer_id', $farmerId)->whereHas('farmer', fn ($query) => $query->where('registration_status', 'verified'))->exists(), 422);
    }
}
