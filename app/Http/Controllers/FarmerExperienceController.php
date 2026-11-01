<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceApplication;
use App\Models\AssistanceProgram;
use App\Models\EquipmentRequest;
use App\Models\Farmer;
use App\Services\AgriculturalBusinessCalendar;
use App\Services\AgriculturalAnnouncementPresenter;
use App\Services\AssistanceProgramCatalogService;
use App\Services\FarmerActivityFeedService;
use App\Services\ProgramEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerExperienceController extends Controller
{
    public function services(Request $request, AssistanceProgramCatalogService $catalog, ProgramEligibilityService $eligibility, AgriculturalAnnouncementPresenter $presenter): View
    {
        $farmer = $this->farmer($request)->load('farms');
        $catalogData = $catalog->forFarmer($request, $farmer, $eligibility);
        $announcements = Schema::hasTable('agricultural_announcements')
            ? AgriculturalAnnouncement::query()->visibleToFarmers()->with(['program', 'publishedRevision.media'])->latest('published_at')->take(3)->get()
            : collect();

        return view('farmer.services', [
            'farmer' => $farmer,
            'announcementsAvailable' => Schema::hasTable('agricultural_announcements'),
            'announcements' => $announcements,
            'announcementPresentations' => $announcements->mapWithKeys(fn (AgriculturalAnnouncement $notice) => [$notice->id => $presenter->present($notice, true)]),
        ] + $catalogData);
    }

    public function program(Request $request, AssistanceProgram $program, ProgramEligibilityService $eligibility, AgriculturalBusinessCalendar $calendar, AgriculturalAnnouncementPresenter $presenter): View
    {
        $farmer = $this->farmer($request);
        abort_unless($program->status === 'open', 404);
        $businessDate = $calendar->today();

        $announcement = AgriculturalAnnouncement::query()->visibleToFarmers()
            ->where('assistance_program_id', $program->id)
            ->with('publishedRevision.media')
            ->latest('published_at')->first();

        return view('farmer.program', [
            'farmer' => $farmer,
            'program' => $program->load('supplyItem'),
            'businessDate' => $businessDate,
            'eligibility' => $eligibility->check($program, $farmer->load('farms'), $businessDate),
            'application' => AssistanceApplication::query()->where('farmer_id', $farmer->id)->where('program_id', $program->id)->first(),
            'announcement' => $announcement,
            'publication' => $announcement ? $presenter->present($announcement, true) : null,
        ]);
    }

    public function application(Request $request, AssistanceApplication $application): View
    {
        $farmer = $this->farmer($request);
        abort_unless((int) $application->farmer_id === (int) $farmer->id, 404);

        return view('farmer.application', [
            'farmer' => $farmer,
            'application' => $application->load([
                'program.supplyItem',
                'approvedSupplyItem',
                'history' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
                'distributions.batch.item',
            ]),
        ]);
    }

    public function equipmentRequest(Request $request, EquipmentRequest $equipmentRequest): View
    {
        $farmer = $this->farmer($request);
        abort_unless((int) $equipmentRequest->farmer_id === (int) $farmer->id, 404);

        return view('farmer.equipment-request', [
            'farmer' => $farmer,
            'equipmentRequest' => $equipmentRequest->load(['asset.category', 'transactions' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]),
        ]);
    }

    public function activities(Request $request, FarmerActivityFeedService $feed): View
    {
        $farmer = $this->farmer($request);
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', Rule::in([
                'Assistance application', 'Equipment request', 'Registration update', 'Farm record',
                'Assistance received', 'Crop record', 'Livestock record', 'Fisheries record',
                'Pest or disease report', 'Agricultural submission',
            ])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? '';
        $type = $filters['type'] ?? '';
        $search = trim($filters['search'] ?? '');
        $baseQuery = $feed->query($farmer);
        $statusOptions = DB::query()
            ->fromSub((clone $baseQuery)->reorder(), 'activity_status_rows')
            ->whereNotNull('activity_status')
            ->select('activity_status')
            ->distinct()
            ->orderBy('activity_status')
            ->pluck('activity_status')
            ->mapWithKeys(fn (string $value) => [$value => str($value)->replace('_', ' ')->headline()->toString()]);

        $activityQuery = $baseQuery
            ->when($status !== '', fn ($query) => $query->where('activity_status', $status))
            ->when($type !== '', fn ($query) => $query->where('activity_type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(fn ($matches) => $matches
                    ->where('title_primary', 'like', $term)
                    ->orWhere('title_secondary', 'like', $term)
                    ->orWhere('activity_status', 'like', $term)
                    ->orWhere('activity_type', 'like', $term)
                    ->orWhere('event_status', 'like', $term)
                    ->orWhere('event_notes', 'like', $term));
            })
            ;

        $paginatedActivities = $activityQuery->paginate(15)->withQueryString();
        $paginatedActivities->setCollection($feed->present($paginatedActivities->getCollection()));

        return view('farmer.activities', [
            'farmer' => $farmer,
            'filter' => $status,
            'type' => $type,
            'search' => $search,
            'statusOptions' => $statusOptions,
            'activities' => $paginatedActivities,
        ]);
    }

    public function notifications(Request $request): View
    {
        $farmer = $this->farmer($request);
        $filters = $request->validate([
            'read' => ['nullable', Rule::in(['unread', 'read'])],
            'category' => ['nullable', Rule::in(['assistance', 'production', 'equipment', 'registration', 'announcements', 'other'])],
        ]);
        $readFilter = $filters['read'] ?? '';
        $categoryFilter = $filters['category'] ?? '';
        $categoryTypes = [
            'assistance' => ['assistance_application_status', 'assistance_distribution_completed'],
            'production' => ['production_submission_review'],
            'equipment' => ['equipment_request_status'],
            'registration' => ['farmer_registration_status'],
            'announcements' => ['agricultural_announcement'],
        ];
        $knownTypes = collect($categoryTypes)->flatten()->all();
        $notifications = $request->user()->notifications()
            ->when($readFilter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($readFilter === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when(isset($categoryTypes[$categoryFilter]), fn ($query) => $query->whereIn('data->type', $categoryTypes[$categoryFilter]))
            ->when($categoryFilter === 'other', fn ($query) => $query->where(function ($types) use ($knownTypes) {
                $types->whereNull('data->type')->orWhereNotIn('data->type', $knownTypes);
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('farmer.notifications', [
            'farmer' => $farmer,
            'notifications' => $notifications,
            'readFilter' => $readFilter,
            'categoryFilter' => $categoryFilter,
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
