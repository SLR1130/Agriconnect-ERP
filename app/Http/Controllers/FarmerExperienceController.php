<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceApplication;
use App\Models\EquipmentRequest;
use App\Models\AssistanceProgram;
use App\Models\Farmer;
use App\Services\ProgramEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerExperienceController extends Controller
{
    public function services(Request $request, ProgramEligibilityService $eligibility): View
    {
        $farmer = $this->farmer($request)->load('farms');
        $search = trim($request->string('search')->toString());
        $category = trim($request->string('category')->toString());
        $availability = $request->string('availability')->toString() ?: 'available';
        abort_unless(in_array($availability, ['available', 'upcoming', 'ended', 'all'], true), 404);
        $published = AssistanceProgram::query()->where('status', 'open');
        $programQuery = (clone $published)
            ->when($availability === 'available', fn ($query) => $query
                ->where(fn ($dates) => $dates->whereNull('opens_on')->orWhereDate('opens_on', '<=', today()))
                ->where(fn ($dates) => $dates->whereNull('closes_on')->orWhereDate('closes_on', '>=', today())))
            ->when($availability === 'upcoming', fn ($query) => $query->whereDate('opens_on', '>', today()))
            ->when($availability === 'ended', fn ($query) => $query->whereNotNull('closes_on')->whereDate('closes_on', '<', today()))
            ->when($search !== '', fn ($query) => $query->where(fn ($results) => $results->where('name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->when($category !== '', fn ($query) => $query->where('category', $category));
        $programs = $programQuery->with('supplyItem')->orderBy('closes_on')->orderBy('name')->paginate(9)->withQueryString();

        return view('farmer.services', [
            'farmer' => $farmer,
            'search' => $search,
            'category' => $category,
            'availability' => $availability,
            'announcementsAvailable' => Schema::hasTable('agricultural_announcements'),
            'categories' => (clone $published)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'programs' => $programs,
            'eligibilities' => $programs->getCollection()->mapWithKeys(fn ($program) => [
                $program->id => $eligibility->check($program, $farmer),
            ]),
            'announcements' => Schema::hasTable('agricultural_announcements')
                ? AgriculturalAnnouncement::query()->visibleToFarmers()->latest('published_at')->take(3)->get()
                : collect(),
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
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'completed', 'rejected'])],
            'type' => ['nullable', Rule::in([
                'Assistance application', 'Equipment request', 'Registration update', 'Farm record',
                'Assistance received', 'Crop record', 'Livestock record', 'Fisheries record',
                'Pest or disease report', 'Agricultural submission', 'Notification',
            ])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filter = $filters['status'] ?? '';
        $type = $filters['type'] ?? '';
        $search = trim($filters['search'] ?? '');
        $activitySources = [];
        $activitySources[] = DB::table('assistance_applications')
            ->join('assistance_programs', 'assistance_programs.id', '=', 'assistance_applications.program_id')
            ->where('assistance_applications.farmer_id', $farmer->id)
            ->selectRaw("'Assistance application' AS activity_type")
            ->selectRaw("assistance_applications.id AS source_id, assistance_programs.name AS title_primary, NULL AS title_secondary, assistance_applications.status AS activity_status, CASE assistance_applications.status WHEN 'approved' THEN 'approved' WHEN 'completed' THEN 'completed' WHEN 'rejected' THEN 'rejected' ELSE 'pending' END AS activity_group, assistance_applications.updated_at AS activity_at, NULL AS related_id");
        $activitySources[] = DB::table('equipment_requests')
            ->join('equipment_assets', 'equipment_assets.id', '=', 'equipment_requests.equipment_asset_id')
            ->where('equipment_requests.farmer_id', $farmer->id)
            ->selectRaw("'Equipment request' AS activity_type")
            ->selectRaw("equipment_requests.id AS source_id, equipment_assets.name AS title_primary, NULL AS title_secondary, equipment_requests.status AS activity_status, CASE WHEN equipment_requests.status IN ('approved', 'issued') THEN 'approved' WHEN equipment_requests.status = 'returned' THEN 'completed' WHEN equipment_requests.status IN ('rejected', 'cancelled') THEN 'rejected' ELSE 'pending' END AS activity_group, equipment_requests.updated_at AS activity_at, NULL AS related_id");
        $activitySources[] = DB::table('farmer_registration_histories')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Registration update' AS activity_type")
            ->selectRaw("id AS source_id, 'Farmer registration' AS title_primary, reason AS title_secondary, to_status AS activity_status, CASE to_status WHEN 'verified' THEN 'approved' WHEN 'rejected' THEN 'rejected' ELSE 'pending' END AS activity_group, created_at AS activity_at, NULL AS related_id");
        $activitySources[] = DB::table('farms')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Farm record' AS activity_type")
            ->selectRaw("id AS source_id, name AS title_primary, NULL AS title_secondary, 'registered' AS activity_status, 'completed' AS activity_group, created_at AS activity_at, NULL AS related_id");
        $activitySources[] = DB::table('assistance_distributions')
            ->join('assistance_applications', 'assistance_applications.id', '=', 'assistance_distributions.application_id')
            ->join('assistance_programs', 'assistance_programs.id', '=', 'assistance_applications.program_id')
            ->where('assistance_applications.farmer_id', $farmer->id)
            ->selectRaw("'Assistance received' AS activity_type")
            ->selectRaw("assistance_distributions.id AS source_id, assistance_programs.name AS title_primary, NULL AS title_secondary, 'distributed' AS activity_status, 'completed' AS activity_group, COALESCE(assistance_distributions.distributed_at, assistance_distributions.created_at) AS activity_at, assistance_distributions.application_id AS related_id");
        $activitySources[] = DB::table('crop_production_records')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Crop record' AS activity_type")
            ->selectRaw("id AS source_id, crop_type AS title_primary, NULL AS title_secondary, status AS activity_status, 'completed' AS activity_group, created_at AS activity_at, NULL AS related_id");
        $activitySources[] = DB::table('livestock_records')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Livestock record' AS activity_type")
            ->selectRaw("id AS source_id, animal_type AS title_primary, NULL AS title_secondary, status AS activity_status, 'completed' AS activity_group, created_at AS activity_at, NULL AS related_id");
        $activitySources[] = DB::table('fishery_records')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Fisheries record' AS activity_type")
            ->selectRaw("id AS source_id, fishery_type AS title_primary, NULL AS title_secondary, 'recorded' AS activity_status, 'completed' AS activity_group, created_at AS activity_at, NULL AS related_id");
        $activitySources[] = DB::table('pest_disease_incidents')
            ->where('farmer_id', $farmer->id)
            ->selectRaw("'Pest or disease report' AS activity_type")
            ->selectRaw("id AS source_id, incident_type AS title_primary, NULL AS title_secondary, status AS activity_status, CASE WHEN status IN ('resolved', 'closed') THEN 'completed' ELSE 'pending' END AS activity_group, created_at AS activity_at, NULL AS related_id");

        if (Schema::hasTable('production_submissions')) {
            $activitySources[] = DB::table('production_submissions')
                ->join('farms', 'farms.id', '=', 'production_submissions.farm_id')
                ->where('production_submissions.farmer_id', $farmer->id)
                ->selectRaw("'Agricultural submission' AS activity_type")
                ->selectRaw("production_submissions.id AS source_id, production_submissions.category AS title_primary, farms.name AS title_secondary, production_submissions.status AS activity_status, CASE production_submissions.status WHEN 'approved' THEN 'approved' WHEN 'rejected' THEN 'rejected' ELSE 'pending' END AS activity_group, production_submissions.updated_at AS activity_at, NULL AS related_id");
        }

        $activitySources[] = DB::table('notifications')
            ->where('notifiable_type', $request->user()->getMorphClass())
            ->where('notifiable_id', $request->user()->getKey())
            ->selectRaw("'Notification' AS activity_type")
            ->selectRaw("id AS source_id, data AS title_primary, NULL AS title_secondary, CASE WHEN read_at IS NULL THEN 'unread' ELSE 'read' END AS activity_status, CASE WHEN read_at IS NULL THEN 'pending' ELSE 'completed' END AS activity_group, created_at AS activity_at, NULL AS related_id");

        $activityUnion = array_shift($activitySources);
        foreach ($activitySources as $source) {
            $activityUnion->unionAll($source);
        }

        $activityQuery = DB::query()->fromSub($activityUnion, 'farmer_activity')
            ->select('farmer_activity.*')
            ->when($filter !== '', fn ($query) => $query->where('activity_group', $filter))
            ->when($type !== '', fn ($query) => $query->where('activity_type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(fn ($matches) => $matches
                    ->where('title_primary', 'like', $term)
                    ->orWhere('title_secondary', 'like', $term)
                    ->orWhere('activity_status', 'like', $term)
                    ->orWhere('activity_type', 'like', $term));
            })
            ->orderByDesc('activity_at')
            ->orderByDesc('source_id')
            ->orderBy('activity_type');

        $paginatedActivities = $activityQuery->paginate(15)->withQueryString();
        $paginatedActivities->getCollection()->transform(function (object $row): array {
            $title = $row->title_primary;
            if ($row->activity_type === 'Notification') {
                $payload = json_decode((string) $title, true);
                $title = data_get($payload, 'message', 'Agricultural service update');
            } elseif ($row->activity_type === 'Agricultural submission') {
                $title = ucfirst((string) $title).' information'.($row->title_secondary ? ' · '.$row->title_secondary : '');
            }

            $url = match ($row->activity_type) {
                'Assistance application' => route('farmer.applications.show', $row->source_id),
                'Equipment request' => route('farmer.equipment-requests.show', $row->source_id),
                'Registration update' => route('farmer.farms'),
                'Farm record' => route('farmer.farms').'#farm-'.$row->source_id,
                'Assistance received' => route('farmer.applications.show', $row->related_id),
                'Crop record' => route('farmer.production', ['section' => 'crops']),
                'Livestock record' => route('farmer.production', ['section' => 'livestock']),
                'Fisheries record' => route('farmer.production', ['section' => 'fisheries']),
                'Pest or disease report' => route('farmer.production', ['section' => 'incidents']),
                'Agricultural submission' => route('farmer.production.submissions.show', $row->source_id),
                'Notification' => route('farmer.notifications').'#notification-'.$row->source_id,
                default => route('farmer.activities'),
            };

            return [
                'title' => $title,
                'detail' => $row->activity_type === 'Registration update' ? $row->title_secondary : null,
                'type' => $row->activity_type,
                'status' => $row->activity_status,
                'group' => $row->activity_group,
                'date' => $row->activity_at ? Carbon::parse($row->activity_at) : null,
                'url' => $url,
            ];
        });

        return view('farmer.activities', [
            'farmer' => $farmer,
            'filter' => $filter,
            'type' => $type,
            'search' => $search,
            'activities' => $paginatedActivities,
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
