<?php

namespace App\Http\Controllers;

use App\Models\AssistanceApplication;
use App\Models\AssistanceDistribution;
use App\Models\AssistanceProgram;
use App\Models\CropProductionRecord;
use App\Models\EquipmentRequest;
use App\Models\Farmer;
use App\Models\FisheryRecord;
use App\Models\LivestockRecord;
use App\Models\PestDiseaseIncident;
use App\Services\ProgramEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FarmerExperienceController extends Controller
{
    public function services(Request $request): View
    {
        $farmer = $this->farmer($request);
        $search = trim($request->string('search')->toString());
        $category = trim($request->string('category')->toString());
        $published = AssistanceProgram::query()->where('status', 'open');

        return view('farmer.services', [
            'farmer' => $farmer,
            'search' => $search,
            'category' => $category,
            'categories' => (clone $published)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'programs' => $published->with('supplyItem')
                ->when($search !== '', fn ($query) => $query->where(fn ($results) => $results->where('name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->orderBy('closes_on')->orderBy('name')->paginate(9)->withQueryString(),
        ]);
    }

    public function program(Request $request, AssistanceProgram $program, ProgramEligibilityService $eligibility): View
    {
        $farmer = $this->farmer($request);
        abort_unless($program->status === 'open', 404);

        return view('farmer.program', [
            'farmer' => $farmer,
            'program' => $program->load('supplyItem'),
            'eligibility' => $eligibility->check($program, $farmer->load('farms')),
            'application' => AssistanceApplication::query()->where('farmer_id', $farmer->id)->where('program_id', $program->id)->first(),
        ]);
    }

    public function application(Request $request, AssistanceApplication $application): View
    {
        $farmer = $this->farmer($request);
        abort_unless((int) $application->farmer_id === (int) $farmer->id, 404);

        return view('farmer.application', [
            'farmer' => $farmer,
            'application' => $application->load(['program.supplyItem', 'approvedSupplyItem', 'history', 'distributions.batch.item']),
        ]);
    }

    public function equipmentRequest(Request $request, EquipmentRequest $equipmentRequest): View
    {
        $farmer = $this->farmer($request);
        abort_unless((int) $equipmentRequest->farmer_id === (int) $farmer->id, 404);

        return view('farmer.equipment-request', [
            'farmer' => $farmer,
            'equipmentRequest' => $equipmentRequest->load(['asset.category', 'transactions' => fn ($query) => $query->orderBy('created_at')]),
        ]);
    }

    public function activities(Request $request): View
    {
        $farmer = $this->farmer($request);
        $filter = $request->string('status')->toString();
        abort_unless(in_array($filter, ['', 'pending', 'approved', 'completed', 'rejected'], true), 404);
        $activities = collect();

        foreach (AssistanceApplication::query()->with('program')->where('farmer_id', $farmer->id)->latest()->take(50)->get() as $application) {
            $activities->push([
                'title' => $application->program->name,
                'type' => 'Assistance application',
                'status' => $application->status,
                'group' => match ($application->status) {
                    'approved' => 'approved', 'completed' => 'completed', 'rejected' => 'rejected', default => 'pending'
                },
                'date' => $application->updated_at,
                'url' => route('farmer.applications.show', $application),
            ]);
        }
        foreach (EquipmentRequest::query()->with('asset')->where('farmer_id', $farmer->id)->latest()->take(50)->get() as $equipmentRequest) {
            $activities->push([
                'title' => $equipmentRequest->asset->name,
                'type' => 'Equipment request',
                'status' => $equipmentRequest->status,
                'group' => match ($equipmentRequest->status) {
                    'approved', 'issued' => 'approved', 'returned' => 'completed', 'rejected', 'cancelled' => 'rejected', default => 'pending'
                },
                'date' => $equipmentRequest->updated_at,
                'url' => route('farmer.equipment-requests.show', $equipmentRequest),
            ]);
        }
        foreach ($farmer->registrationHistory()->latest()->take(20)->get() as $history) {
            $activities->push([
                'title' => 'Farmer registration',
                'type' => 'Registration update',
                'status' => $history->to_status,
                'group' => match ($history->to_status) {
                    'verified' => 'approved', 'rejected' => 'rejected', default => 'pending'
                },
                'date' => $history->created_at,
                'url' => route('farmer.farms'),
            ]);
        }
        foreach ($farmer->farms()->latest()->take(30)->get() as $farm) {
            $activities->push([
                'title' => $farm->name,
                'type' => 'Farm record',
                'status' => 'registered',
                'group' => 'completed',
                'date' => $farm->created_at,
                'url' => route('farmer.farms').'#farm-'.$farm->id,
            ]);
        }
        foreach (AssistanceDistribution::query()->with('application.program')->whereHas('application', fn ($query) => $query->where('farmer_id', $farmer->id))->latest('distributed_at')->take(30)->get() as $distribution) {
            $activities->push([
                'title' => $distribution->application->program->name,
                'type' => 'Assistance received',
                'status' => 'distributed',
                'group' => 'completed',
                'date' => $distribution->distributed_at,
                'url' => route('farmer.applications.show', $distribution->application_id),
            ]);
        }
        foreach (CropProductionRecord::query()->where('farmer_id', $farmer->id)->latest()->take(30)->get() as $crop) {
            $activities->push(['title' => $crop->crop_type, 'type' => 'Crop record', 'status' => $crop->status, 'group' => 'completed', 'date' => $crop->created_at, 'url' => route('farmer.production', ['section' => 'crops'])]);
        }
        foreach (LivestockRecord::query()->where('farmer_id', $farmer->id)->latest()->take(30)->get() as $animal) {
            $activities->push(['title' => $animal->animal_type, 'type' => 'Livestock record', 'status' => $animal->status, 'group' => 'completed', 'date' => $animal->created_at, 'url' => route('farmer.production', ['section' => 'livestock'])]);
        }
        foreach (FisheryRecord::query()->where('farmer_id', $farmer->id)->latest()->take(30)->get() as $fishery) {
            $activities->push(['title' => $fishery->fishery_type, 'type' => 'Fisheries record', 'status' => 'recorded', 'group' => 'completed', 'date' => $fishery->created_at, 'url' => route('farmer.production', ['section' => 'fisheries'])]);
        }
        foreach (PestDiseaseIncident::query()->where('farmer_id', $farmer->id)->latest()->take(30)->get() as $incident) {
            $activities->push(['title' => $incident->incident_type, 'type' => 'Pest or disease report', 'status' => $incident->status, 'group' => in_array($incident->status, ['resolved', 'closed'], true) ? 'completed' : 'pending', 'date' => $incident->created_at, 'url' => route('farmer.production', ['section' => 'incidents'])]);
        }

        return view('farmer.activities', [
            'farmer' => $farmer,
            'filter' => $filter,
            'activities' => $activities->when($filter !== '', fn ($rows) => $rows->where('group', $filter))->sortByDesc('date')->take(100)->values(),
        ]);
    }

    public function notifications(Request $request): View
    {
        $farmer = $this->farmer($request);

        return view('farmer.notifications', [
            'farmer' => $farmer,
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
        ]);
    }

    public function markNotificationRead(Request $request, string $notification): RedirectResponse
    {
        $this->farmer($request);
        $notice = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $notice->markAsRead();

        return back()->with('status', 'Notification marked as read.');
    }

    private function farmer(Request $request): Farmer
    {
        abort_unless($request->user()?->hasRole('farmer'), 403);

        return $request->user()->farmer()->firstOrFail();
    }
}
