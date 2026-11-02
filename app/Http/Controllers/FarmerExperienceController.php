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
use App\Services\AgriculturalSchemaCapabilities;
use App\Services\AssistanceApplicationDocumentService;
use App\Services\ClaimTicketFeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerExperienceController extends Controller
{
    public function services(Request $request, AssistanceProgramCatalogService $catalog, ProgramEligibilityService $eligibility, AgriculturalAnnouncementPresenter $presenter, AgriculturalSchemaCapabilities $schema): View
    {
        $farmer = $this->farmer($request)->load('farms');
        $catalogData = $catalog->forFarmer($request, $farmer, $eligibility);
        $announcementsAvailable = Schema::hasTable('agricultural_announcements');
        $announcements = $announcementsAvailable
            ? AgriculturalAnnouncement::query()->visibleToFarmers()->with($schema->announcementRevisionsReady() ? ['program', 'publishedRevision.media'] : ['program'])->latest('published_at')->take(3)->get()
            : collect();
        if (! $schema->announcementRevisionsReady()) {
            $announcements->each(fn (AgriculturalAnnouncement $notice) => $notice->setRelation('publishedRevision', null));
        }

        return view('farmer.services', [
            'farmer' => $farmer,
            'announcementsAvailable' => $announcementsAvailable,
            'announcements' => $announcements,
            'announcementPresentations' => $announcements->mapWithKeys(fn (AgriculturalAnnouncement $notice) => [$notice->id => $presenter->present($notice, true)]),
        ] + $catalogData);
    }

    public function program(Request $request, AssistanceProgram $program, ProgramEligibilityService $eligibility, AgriculturalBusinessCalendar $calendar, AgriculturalAnnouncementPresenter $presenter, AgriculturalSchemaCapabilities $schema, AssistanceApplicationDocumentService $documents): View
    {
        $farmer = $this->farmer($request);
        abort_unless($program->status === 'open', 404);
        $businessDate = $calendar->today();

        $announcement = Schema::hasTable('agricultural_announcements')
            ? AgriculturalAnnouncement::query()->visibleToFarmers()
                ->where('assistance_program_id', $program->id)
                ->with($schema->announcementRevisionsReady() ? 'publishedRevision.media' : [])
                ->latest('published_at')->first()
            : null;
        if ($announcement && ! $schema->announcementRevisionsReady()) {
            $announcement->setRelation('publishedRevision', null);
        }

        $program->load(['supplyItem']);
        if ($schema->requirementVersionsReady()) {
            $program->load(['currentRequirementVersion.documentRequirements', 'currentRequirementVersion.publisher']);
        } else {
            $program->setRelation('currentRequirementVersion', null);
        }

        return view('farmer.program', [
            'farmer' => $farmer,
            'program' => $program,
            'businessDate' => $businessDate,
            'eligibility' => $eligibility->check($program, $farmer->load('farms'), $businessDate),
            'application' => AssistanceApplication::query()->where('farmer_id', $farmer->id)->where('program_id', $program->id)->first(),
            'submissionKey' => old('submission_key') ?: (string) Str::uuid(),
            'announcement' => $announcement,
            'publication' => $announcement ? $presenter->present($announcement, true) : null,
            'applicationSubmissionReady' => $schema->applicationSubmissionReady(),
            'maximumDocumentUploadBytes' => $documents->maximumUploadBytes(),
        ]);
    }

    public function application(Request $request, AssistanceApplication $application, AgriculturalSchemaCapabilities $schema, AssistanceApplicationDocumentService $documents, ClaimTicketFeatureGate $claimTicketFeature): View
    {
        $farmer = $this->farmer($request);
        abort_unless((int) $application->farmer_id === (int) $farmer->id, 404);

        $relations = [
            'program.supplyItem', 'approvedSupplyItem',
            'history' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
            'distributions.batch.item',
        ];
        if ($schema->requirementVersionsReady()) {
            $relations[] = 'requirementVersion.documentRequirements';
        }
        if ($schema->acknowledgmentsReady()) {
            $relations[] = 'acknowledgment';
        }
        if ($schema->documentWorkflowReady()) {
            $relations[] = 'documents.requirement';
            $relations[] = 'documents.events';
            $relations[] = 'submissionSnapshots';
        }
        $application->load($relations);
        if (! $schema->requirementVersionsReady()) {
            $application->setRelation('requirementVersion', null);
        }
        if (! $schema->acknowledgmentsReady()) {
            $application->setRelation('acknowledgment', null);
        }
        if (! $schema->documentWorkflowReady()) {
            $application->setRelation('documents', collect());
        }

        $latestDocuments = $application->documents
            ->sortByDesc('version_number')
            ->unique('requirement_id')
            ->keyBy('requirement_id');
        $claimTickets = $claimTicketFeature->enabled()
            ? $application->claimTickets()->with('approvedSupplyItem')->get()
            : collect();

        return view('farmer.application', [
            'farmer' => $farmer,
            'application' => $application,
            'latestDocuments' => $latestDocuments,
            'documentWorkflowReady' => $schema->documentWorkflowReady(),
            'documentUploadsEnabled' => $documents->uploadEnabled(),
            'scannerAvailable' => $documents->scannerAvailable(),
            'maximumDocumentUploadBytes' => $documents->maximumUploadBytes(),
            'outstandingRequiredDocuments' => $documents->outstandingRequiredDocuments($application),
            'claimTickets' => $claimTickets,
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
            'assistance' => ['assistance_application_status', 'assistance_document_review', 'assistance_distribution_completed'],
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
