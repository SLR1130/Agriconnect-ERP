<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AgriculturalSeason;
use App\Models\CropProductionRecord;
use App\Models\Farm;
use App\Models\FarmMonitoringRecord;
use App\Models\FisheryRecord;
use App\Models\LivestockRecord;
use App\Models\PestDiseaseIncident;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class APMSController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeModule($request, 'view');
        $recordSearch = trim($request->string('record_search')->toString());
        $seasonSearch = trim($request->string('season_search')->toString());
        $farms = Farm::query()->whereHas('farmer', fn ($query) => $query->where('registration_status', 'verified'))
            ->with('farmer.user')->orderBy('name')->get();
        $seasons = AgriculturalSeason::query()->orderByDesc('starts_on')->get();

        $crops = CropProductionRecord::query()->with(['farm', 'farmer.user', 'season'])
            ->when($recordSearch !== '', function (Builder $query) use ($recordSearch): void {
                $query->where(function (Builder $searchQuery) use ($recordSearch): void {
                    $like = '%'.$recordSearch.'%';
                    $searchQuery->where('crop_type', 'like', $like)
                        ->orWhere('variety', 'like', $like)
                        ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->where('name', 'like', $like))
                        ->orWhereHas('farmer.user', fn (Builder $userQuery) => $userQuery->where('name', 'like', $like));
                });
            })
            ->when($request->filled('crop_status'), fn (Builder $query) => $query->where('status', $request->string('crop_status')->toString()))
            ->latest()->paginate(10, ['*'], 'crops')->withQueryString();

        $livestock = LivestockRecord::query()->with('farm')
            ->when($recordSearch !== '', function (Builder $query) use ($recordSearch): void {
                $query->where(function (Builder $searchQuery) use ($recordSearch): void {
                    $like = '%'.$recordSearch.'%';
                    $searchQuery->where('animal_type', 'like', $like)
                        ->orWhere('breed', 'like', $like)
                        ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->where('name', 'like', $like));
                });
            })
            ->when($request->filled('livestock_status'), fn (Builder $query) => $query->where('status', $request->string('livestock_status')->toString()))
            ->latest()->paginate(10, ['*'], 'livestock_page')->withQueryString();

        $fisheries = FisheryRecord::query()->with('farm')
            ->when($recordSearch !== '', function (Builder $query) use ($recordSearch): void {
                $query->where(function (Builder $searchQuery) use ($recordSearch): void {
                    $like = '%'.$recordSearch.'%';
                    $searchQuery->where('fishery_type', 'like', $like)
                        ->orWhere('species', 'like', $like)
                        ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->where('name', 'like', $like));
                });
            })
            ->latest()->paginate(10, ['*'], 'fisheries_page')->withQueryString();

        $incidents = PestDiseaseIncident::query()->with('farm')
            ->when($recordSearch !== '', function (Builder $query) use ($recordSearch): void {
                $query->where(function (Builder $searchQuery) use ($recordSearch): void {
                    $like = '%'.$recordSearch.'%';
                    $searchQuery->where('incident_type', 'like', $like)
                        ->orWhere('affected_crop_or_animal', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->where('name', 'like', $like));
                });
            })
            ->when($request->filled('incident_status'), fn (Builder $query) => $query->where('status', $request->string('incident_status')->toString()))
            ->when($request->filled('severity'), fn (Builder $query) => $query->where('severity', $request->string('severity')->toString()))
            ->latest()->paginate(10, ['*'], 'incidents_page')->withQueryString();

        $monitoring = FarmMonitoringRecord::query()->with('farm')
            ->when($recordSearch !== '', function (Builder $query) use ($recordSearch): void {
                $query->where(function (Builder $searchQuery) use ($recordSearch): void {
                    $like = '%'.$recordSearch.'%';
                    $searchQuery->where('monitoring_type', 'like', $like)
                        ->orWhere('findings', 'like', $like)
                        ->orWhere('recommendations', 'like', $like)
                        ->orWhereHas('farm', fn (Builder $farmQuery) => $farmQuery->where('name', 'like', $like));
                });
            })
            ->when($request->filled('monitoring_status'), fn (Builder $query) => $query->where('status', $request->string('monitoring_status')->toString()))
            ->latest()->paginate(10, ['*'], 'monitoring_page')->withQueryString();

        $recentActivity = collect([
            ['type' => 'Crop', 'records' => CropProductionRecord::with('farm')->latest()->limit(3)->get(), 'title' => 'crop_type', 'date' => 'planted_on'],
            ['type' => 'Livestock', 'records' => LivestockRecord::with('farm')->latest()->limit(3)->get(), 'title' => 'animal_type', 'date' => 'recorded_on'],
            ['type' => 'Fisheries', 'records' => FisheryRecord::with('farm')->latest()->limit(3)->get(), 'title' => 'fishery_type', 'date' => 'recorded_on'],
            ['type' => 'Incident', 'records' => PestDiseaseIncident::with('farm')->latest()->limit(3)->get(), 'title' => 'incident_type', 'date' => 'reported_on'],
            ['type' => 'Field visit', 'records' => FarmMonitoringRecord::with('farm')->latest()->limit(3)->get(), 'title' => 'monitoring_type', 'date' => 'visited_on'],
        ])->flatMap(fn (array $group) => $group['records']->map(fn ($record) => [
            'type' => $group['type'],
            'title' => $record->{$group['title']},
            'farm' => $record->farm?->name,
            'date' => $record->{$group['date']} ?: $record->created_at,
        ]))->sortByDesc(fn (array $record) => $record['date']?->timestamp ?? 0)->take(8)->values();

        $allowedTabs = ['overview', 'production', 'monitoring', 'seasons'];
        $allowedRecordTypes = ['crops', 'livestock', 'fisheries'];
        $allowedMonitoringTypes = ['incidents', 'visits'];

        return view('apms.index', [
            'farms' => $farms,
            'seasons' => $seasons,
            'crops' => $crops,
            'livestock' => $livestock,
            'fisheries' => $fisheries,
            'incidents' => $incidents,
            'monitoring' => $monitoring,
            'seasonRecords' => AgriculturalSeason::query()->when($seasonSearch !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$seasonSearch.'%'))->orderByDesc('starts_on')->paginate(10, ['*'], 'seasons_page')->withQueryString(),
            'recentActivity' => $recentActivity,
            'selectedTab' => in_array($request->query('tab'), $allowedTabs, true) ? $request->query('tab') : 'overview',
            'selectedRecordType' => in_array($request->query('record_type'), $allowedRecordTypes, true) ? $request->query('record_type') : 'crops',
            'selectedMonitoringType' => in_array($request->query('monitoring_type'), $allowedMonitoringTypes, true) ? $request->query('monitoring_type') : 'incidents',
            'recordSearch' => $recordSearch,
            'seasonSearch' => $seasonSearch,
            'stats' => [
                'crops' => CropProductionRecord::count(),
                'livestock' => LivestockRecord::count(),
                'fisheries' => FisheryRecord::count(),
                'monitoring' => FarmMonitoringRecord::count(),
                'incidents' => PestDiseaseIncident::whereIn('status', ['reported', 'investigating'])->count(),
            ],
            'canCreate' => $request->user()->canModuleAction('apms', 'create'),
            'canUpdate' => $request->user()->canModuleAction('apms', 'update'),
            'canExport' => $request->user()->canModuleAction('apms', 'export'),
        ]);
    }

    public function season(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on']]);
        $season = AgriculturalSeason::create($data);
        ActivityLog::log('created', 'Agricultural season created.', 'season:'.$season->id, $request->user()->id, 'apms');

        return back()->with('status', 'Agricultural season created.');
    }

    public function updateSeason(Request $request, AgriculturalSeason $season): RedirectResponse
    {
        $this->authorizeModule($request, 'update');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'status' => ['required', Rule::in(['open', 'closed'])]]);
        $old = $season->only(array_keys($data));
        $season->update($data);
        ActivityLog::log('updated', 'Agricultural season updated.', 'season:'.$season->id, $request->user()->id, 'apms', $old, $season->fresh()->only(array_keys($data)));

        return back()->with('status', 'Agricultural season updated.');
    }

    public function crop(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $this->cropData($request);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $record = CropProductionRecord::create($data + ['recorded_by' => $request->user()->id]);
        ActivityLog::log('created', 'Crop production record created.', 'crop:'.$record->id, $request->user()->id, 'apms');

        return back()->with('status', 'Crop production record saved.');
    }

    public function updateCrop(Request $request, CropProductionRecord $record): RedirectResponse
    {
        $this->authorizeModule($request, 'update');
        $data = $this->cropData($request);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $old = $record->only(array_keys($data));
        $record->update($data);
        ActivityLog::log('updated', 'Crop production record updated.', 'crop:'.$record->id, $request->user()->id, 'apms', $old, $record->fresh()->only(array_keys($data)));

        return back()->with('status', 'Crop production record updated.');
    }

    public function livestock(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $this->livestockData($request);
        unset($data['status']);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $record = LivestockRecord::create($data + ['recorded_by' => $request->user()->id]);
        ActivityLog::log('created', 'Livestock record created.', 'livestock:'.$record->id, $request->user()->id, 'apms');

        return back()->with('status', 'Livestock record saved.');
    }

    public function updateLivestock(Request $request, LivestockRecord $record): RedirectResponse
    {
        $this->authorizeModule($request, 'update');
        $data = $this->livestockData($request);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $old = $record->only(array_keys($data));
        $record->update($data);
        ActivityLog::log('updated', 'Livestock record updated.', 'livestock:'.$record->id, $request->user()->id, 'apms', $old, $record->fresh()->only(array_keys($data)));

        return back()->with('status', 'Livestock record updated.');
    }

    public function fishery(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $this->fisheryData($request);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $record = FisheryRecord::create($data + ['recorded_by' => $request->user()->id]);
        ActivityLog::log('created', 'Fishery record created.', 'fishery:'.$record->id, $request->user()->id, 'apms');

        return back()->with('status', 'Fisheries record saved.');
    }

    public function updateFishery(Request $request, FisheryRecord $record): RedirectResponse
    {
        $this->authorizeModule($request, 'update');
        $data = $this->fisheryData($request);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $old = $record->only(array_keys($data));
        $record->update($data);
        ActivityLog::log('updated', 'Fishery record updated.', 'fishery:'.$record->id, $request->user()->id, 'apms', $old, $record->fresh()->only(array_keys($data)));

        return back()->with('status', 'Fisheries record updated.');
    }

    public function incident(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $this->incidentData($request);
        unset($data['status'], $data['action_taken']);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $record = PestDiseaseIncident::create($data + ['reported_by' => $request->user()->id]);
        ActivityLog::log('created', 'Pest or disease incident recorded.', 'incident:'.$record->id, $request->user()->id, 'apms');

        return back()->with('status', 'Pest or disease incident reported.');
    }

    public function updateIncident(Request $request, PestDiseaseIncident $record): RedirectResponse
    {
        $this->authorizeModule($request, 'update');
        $data = $this->incidentData($request, true);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $old = $record->only(array_keys($data));
        $record->update($data);
        ActivityLog::log('updated', 'Pest or disease incident updated.', 'incident:'.$record->id, $request->user()->id, 'apms', $old, $record->fresh()->only(array_keys($data)));

        return back()->with('status', 'Pest or disease incident updated.');
    }

    public function monitoring(Request $request): RedirectResponse
    {
        $this->authorizeModule($request, 'create');
        $data = $this->monitoringData($request);
        unset($data['status']);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $record = FarmMonitoringRecord::create($data + ['recorded_by' => $request->user()->id]);
        ActivityLog::log('created', 'Farm monitoring record created.', 'monitoring:'.$record->id, $request->user()->id, 'apms');

        return back()->with('status', 'Farm monitoring record saved.');
    }

    public function updateMonitoring(Request $request, FarmMonitoringRecord $record): RedirectResponse
    {
        $this->authorizeModule($request, 'update');
        $data = $this->monitoringData($request, true);
        $this->validateFarmOwner($data['farmer_id'], $data['farm_id']);
        $old = $record->only(array_keys($data));
        $record->update($data);
        ActivityLog::log('updated', 'Farm monitoring record updated.', 'monitoring:'.$record->id, $request->user()->id, 'apms', $old, $record->fresh()->only(array_keys($data)));

        return back()->with('status', 'Farm monitoring record updated.');
    }

    public function export(Request $request)
    {
        $this->authorizeModule($request, 'export');
        $dateRange = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $safe = fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value;
        $crops = $this->applyDateRange(CropProductionRecord::with(['farm.farmer.user', 'season']), 'planted_on', $dateRange['from'] ?? null, $dateRange['to'] ?? null)->latest()->get();
        $livestock = $this->applyDateRange(LivestockRecord::with(['farm.farmer.user']), 'recorded_on', $dateRange['from'] ?? null, $dateRange['to'] ?? null)->latest()->get();
        $fisheries = $this->applyDateRange(FisheryRecord::with(['farm.farmer.user']), 'recorded_on', $dateRange['from'] ?? null, $dateRange['to'] ?? null)->latest()->get();
        $incidents = $this->applyDateRange(PestDiseaseIncident::with(['farm.farmer.user']), 'reported_on', $dateRange['from'] ?? null, $dateRange['to'] ?? null)->latest()->get();
        $monitoring = $this->applyDateRange(FarmMonitoringRecord::with(['farm.farmer.user']), 'visited_on', $dateRange['from'] ?? null, $dateRange['to'] ?? null)->latest()->get();

        return response()->streamDownload(function () use ($crops, $livestock, $fisheries, $incidents, $monitoring, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Record type', 'Farmer', 'Farm', 'Subject', 'Date', 'Status', 'Details']);
            foreach ($crops as $row) {
                fputcsv($out, ['crop', $safe($row->farm->farmer->user->name), $safe($row->farm->name), $safe($row->crop_type), $row->planted_on, $row->status, $safe($row->yield_quantity.' '.$row->yield_unit)]);
            }
            foreach ($livestock as $row) {
                fputcsv($out, ['livestock', $safe($row->farm->farmer->user->name), $safe($row->farm->name), $safe($row->animal_type), $row->recorded_on, $row->status, $safe($row->quantity.' head')]);
            }
            foreach ($fisheries as $row) {
                fputcsv($out, ['fishery', $safe($row->farm->farmer->user->name), $safe($row->farm->name), $safe($row->fishery_type), $row->recorded_on, 'recorded', $safe($row->production_quantity.' '.$row->production_unit)]);
            }
            foreach ($incidents as $row) {
                fputcsv($out, ['incident', $safe($row->farm->farmer->user->name), $safe($row->farm->name), $safe($row->incident_type), $row->reported_on, $row->status, $safe($row->severity)]);
            }
            foreach ($monitoring as $row) {
                fputcsv($out, ['monitoring', $safe($row->farm->farmer->user->name), $safe($row->farm->name), $safe($row->monitoring_type), $row->visited_on, $row->status, $safe($row->findings)]);
            }
            fclose($out);
        }, 'apms-production.csv', ['Content-Type' => 'text/csv']);
    }

    private function cropData(Request $request): array
    {
        return $request->validate(['farmer_id' => ['required', 'exists:farmers,id'], 'farm_id' => ['required', 'exists:farms,id'], 'season_id' => ['nullable', 'exists:agricultural_seasons,id'], 'crop_type' => ['required', 'string', 'max:120'], 'variety' => ['nullable', 'string', 'max:120'], 'planted_on' => ['nullable', 'date'], 'harvested_on' => ['nullable', 'date', 'after_or_equal:planted_on'], 'area_planted' => ['nullable', 'numeric', 'min:0.01'], 'area_unit' => ['required', Rule::in(['hectare', 'square_meter'])], 'yield_quantity' => ['nullable', 'numeric', 'min:0'], 'yield_unit' => ['required', 'string', 'max:30'], 'status' => ['required', Rule::in(['planned', 'growing', 'harvested', 'cancelled'])], 'notes' => ['nullable', 'string', 'max:2000']]);
    }

    private function livestockData(Request $request): array
    {
        return $request->validate(['farmer_id' => ['required', 'exists:farmers,id'], 'farm_id' => ['required', 'exists:farms,id'], 'animal_type' => ['required', 'string', 'max:120'], 'breed' => ['nullable', 'string', 'max:120'], 'quantity' => ['required', 'integer', 'min:1'], 'recorded_on' => ['required', 'date'], 'status' => ['nullable', Rule::in(['active', 'sold', 'deceased'])], 'notes' => ['nullable', 'string', 'max:2000']]);
    }

    private function fisheryData(Request $request): array
    {
        return $request->validate(['farmer_id' => ['required', 'exists:farmers,id'], 'farm_id' => ['required', 'exists:farms,id'], 'fishery_type' => ['required', 'string', 'max:120'], 'species' => ['nullable', 'string', 'max:120'], 'production_quantity' => ['nullable', 'numeric', 'min:0'], 'production_unit' => ['required', 'string', 'max:30'], 'recorded_on' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
    }

    private function incidentData(Request $request, bool $update = false): array
    {
        return $request->validate([
            'farmer_id' => ['required', 'exists:farmers,id'], 'farm_id' => ['required', 'exists:farms,id'],
            'incident_type' => ['required', 'string', 'max:120'], 'affected_crop_or_animal' => ['required', 'string', 'max:120'],
            'reported_on' => ['required', 'date'], 'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => [$update ? 'required' : 'nullable', Rule::in(['reported', 'investigating', 'resolved', 'closed'])],
            'description' => ['nullable', 'string', 'max:3000'], 'action_taken' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function monitoringData(Request $request, bool $update = false): array
    {
        return $request->validate([
            'farmer_id' => ['required', 'exists:farmers,id'], 'farm_id' => ['required', 'exists:farms,id'],
            'visited_on' => ['required', 'date'], 'monitoring_type' => ['required', 'string', 'max:120'],
            'findings' => ['required', 'string', 'max:4000'], 'recommendations' => ['nullable', 'string', 'max:4000'],
            'status' => [$update ? 'required' : 'nullable', Rule::in(['recorded', 'follow_up', 'closed'])],
        ]);
    }

    private function authorizeModule(Request $request, string $action): void
    {
        abort_unless($request->user()->canModuleAction('apms', $action), 403);
    }

    private function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $builder) => $builder->whereDate($column, '>=', $from))
            ->when($to, fn (Builder $builder) => $builder->whereDate($column, '<=', $to));
    }

    private function validateFarmOwner(int $farmerId, int $farmId): void
    {
        abort_unless(Farm::query()->whereKey($farmId)->where('farmer_id', $farmerId)->whereHas('farmer', fn ($query) => $query->where('registration_status', 'verified'))->exists(), 422);
    }
}
