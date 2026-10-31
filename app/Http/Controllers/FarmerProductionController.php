<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CropProductionRecord;
use App\Models\FarmMonitoringRecord;
use App\Models\FisheryRecord;
use App\Models\LivestockRecord;
use App\Models\PestDiseaseIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('farmer.production', [
            'farmer' => $farmer,
            'section' => $section ?: 'crops',
            'crops' => CropProductionRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'crops_page')->withQueryString(),
            'livestock' => LivestockRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'livestock_page')->withQueryString(),
            'fisheries' => FisheryRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'fisheries_page')->withQueryString(),
            'monitoring' => FarmMonitoringRecord::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'monitoring_page')->withQueryString(),
            'incidents' => PestDiseaseIncident::query()->with('farm')->where('farmer_id', $farmer->id)->latest()->paginate(10, ['*'], 'incidents_page')->withQueryString(),
        ]);
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
