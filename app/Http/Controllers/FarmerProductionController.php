<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AgriculturalSeason;
use App\Models\CropProductionRecord;
use App\Models\Farmer;
use App\Models\FarmMonitoringRecord;
use App\Models\FisheryRecord;
use App\Models\LivestockRecord;
use App\Models\PestDiseaseIncident;
use App\Models\ProductionSubmission;
use App\Services\ProductionSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerProductionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->with('farms')->firstOrFail();
        $section = $request->string('section')->toString();
        abort_unless(in_array($section, ['', 'crops', 'livestock', 'fisheries', 'monitoring', 'incidents'], true), 404);
        $submissionsAvailable = Schema::hasTable('production_submissions');

        return view('farmer.production', [
            'farmer' => $farmer,
            'section' => $section ?: 'crops',
            'crops' => CropProductionRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'crops_page')->withQueryString(),
            'livestock' => LivestockRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'livestock_page')->withQueryString(),
            'fisheries' => FisheryRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'fisheries_page')->withQueryString(),
            'monitoring' => FarmMonitoringRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'monitoring_page')->withQueryString(),
            'incidents' => PestDiseaseIncident::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'incidents_page')->withQueryString(),
            'submissionsAvailable' => $submissionsAvailable,
            'submissions' => $submissionsAvailable
                ? ProductionSubmission::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(12, ['*'], 'submissions_page')->withQueryString()
                : new LengthAwarePaginator([], 0, 12, 1, ['path' => $request->url()]),
            'seasons' => AgriculturalSeason::query()->where('status', 'open')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function showSubmission(Request $request, ProductionSubmission $submission): View
    {
        $farmer = $this->farmer($request);
        abort_unless((int) $submission->farmer_id === (int) $farmer->id, 404);

        return view('farmer.production-submission', [
            'farmer' => $farmer->load('farms'),
            'submission' => $submission->load(['farm', 'history.actor']),
            'seasons' => AgriculturalSeason::query()->where('status', 'open')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function storeSubmission(Request $request, string $category, ProductionSubmissionService $service): RedirectResponse
    {
        $farmer = $this->farmer($request);
        abort_unless($farmer->isVerified(), 403);
        $data = $this->submissionData($request, $farmer, $category);
        $submission = $service->submit($farmer, $request->user(), $category, (int) $data['farm_id'],
            collect($data)->except(['farm_id', 'idempotency_key'])->all(), $data['idempotency_key']);

        return redirect()->route('farmer.production.submissions.show', $submission)->with('status', 'Your information was sent to the MAO for review.');
    }

    public function resubmitSubmission(Request $request, ProductionSubmission $submission, ProductionSubmissionService $service): RedirectResponse
    {
        $farmer = $this->farmer($request);
        abort_unless((int) $submission->farmer_id === (int) $farmer->id, 404);
        abort_unless($farmer->isVerified(), 403);
        $data = $this->submissionData($request, $farmer, $submission->category, false);
        $service->resubmit($submission, $request->user(), (int) $data['farm_id'], collect($data)->except('farm_id')->all());

        return redirect()->route('farmer.production.submissions.show', $submission)->with('status', 'Your corrected information was resubmitted.');
    }

    private function farmer(Request $request): Farmer
    {
        abort_unless($request->user()->hasRole('farmer'), 403);

        return $request->user()->farmer()->firstOrFail();
    }

    private function submissionData(Request $request, Farmer $farmer, string $category, bool $requireKey = true): array
    {
        abort_unless(in_array($category, ['crop', 'livestock', 'fishery'], true), 404);
        $common = ['farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where('farmer_id', $farmer->id)]];
        if ($requireKey) {
            $common['idempotency_key'] = ['required', 'uuid', 'max:100'];
        }
        $rules = match ($category) {
            'crop' => [
                'season_id' => ['nullable', 'exists:agricultural_seasons,id'], 'crop_type' => ['required', 'string', 'max:120'],
                'variety' => ['nullable', 'string', 'max:120'], 'planted_on' => ['required', 'date', 'before_or_equal:today'],
                'harvested_on' => ['nullable', 'date', 'after_or_equal:planted_on', 'before_or_equal:today'],
                'area_planted' => ['required', 'numeric', 'min:0.01', 'max:99999999'], 'area_unit' => ['required', Rule::in(['hectare', 'square_meter'])],
                'yield_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'yield_unit' => ['required', 'string', 'max:30'],
                'status' => ['required', Rule::in(['planned', 'growing', 'harvested'])], 'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'livestock' => [
                'animal_type' => ['required', 'string', 'max:120'], 'breed' => ['nullable', 'string', 'max:120'],
                'quantity' => ['required', 'integer', 'min:1', 'max:1000000000'], 'recorded_on' => ['required', 'date', 'before_or_equal:today'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'fishery' => [
                'fishery_type' => ['required', 'string', 'max:120'], 'species' => ['nullable', 'string', 'max:120'],
                'production_quantity' => ['required', 'numeric', 'min:0', 'max:999999999999'], 'production_unit' => ['required', 'string', 'max:30'],
                'recorded_on' => ['required', 'date', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:2000'],
            ],
        };

        return $request->validate($common + $rules);
    }

    public function reportIncident(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->firstOrFail();
        abort_unless($farmer->isVerified(), 403);
        $data = $request->validate([
            'farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where('farmer_id', $farmer->id)],
            'incident_type' => ['required', 'string', 'max:120'],
            'affected_crop_or_animal' => ['required', 'string', 'max:120'],
            'reported_on' => ['required', 'date', 'before_or_equal:today'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['required', 'string', 'max:3000'],
        ]);
        $incident = PestDiseaseIncident::create($data + [
            'farmer_id' => $farmer->id,
            'reported_by' => $request->user()->id,
            'status' => 'reported',
        ]);
        ActivityLog::log('created', 'Farmer pest or disease report submitted.', 'incident:'.$incident->id, $request->user()->id, 'apms');

        return redirect()->route('farmer.production', ['section' => 'incidents'])->with('status', 'Your report was sent to the MAO for review.');
    }
}
