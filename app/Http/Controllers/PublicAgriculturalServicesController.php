<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalAnnouncement;
use App\Models\AgriculturalAnnouncementMedia;
use App\Models\AssistanceProgram;
use App\Services\AgriculturalAnnouncementPresenter;
use App\Services\AgriculturalBusinessCalendar;
use App\Services\AgriculturalSchemaCapabilities;
use App\Services\AssistanceApplicationDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicAgriculturalServicesController extends Controller
{
    public function home(AgriculturalBusinessCalendar $calendar, AgriculturalSchemaCapabilities $schema): View
    {
        $businessDate = $calendar->today();
        $date = $businessDate->toDateString();

        $programQuery = AssistanceProgram::query()
            ->where('status', 'open')
            ->with('supplyItem');

        if (\Illuminate\Support\Facades\Schema::hasTable('agricultural_announcements')) {
            $programQuery->with(['announcements' => fn ($query) => $query->visibleToFarmers()
                ->with($schema->announcementRevisionsReady() ? 'publishedRevision.media' : [])
                ->latest('published_at')]);
        }

        if ($schema->requirementVersionsReady()) {
            $programQuery->with('currentRequirementVersion');
        }

        $programs = \Illuminate\Support\Facades\Schema::hasTable('assistance_programs')
            ? $programQuery
                ->orderByRaw('CASE WHEN (opens_on IS NULL OR opens_on <= ?) AND (closes_on IS NULL OR closes_on >= ?) THEN 0 WHEN opens_on IS NOT NULL AND opens_on > ? THEN 1 ELSE 2 END', [$date, $date, $date])
                ->orderBy('closes_on')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get()
            : collect();

        $announcements = \Illuminate\Support\Facades\Schema::hasTable('agricultural_announcements')
            ? AgriculturalAnnouncement::query()->visibleToFarmers()
                ->with($schema->announcementRevisionsReady() ? ['program', 'publishedRevision.media'] : ['program'])
                ->latest('published_at')
                ->limit(3)
                ->get()
            : collect();

        if (! $schema->announcementRevisionsReady()) {
            $announcements->each(fn (AgriculturalAnnouncement $notice) => $notice->setRelation('publishedRevision', null));
            $programs->each(function (AssistanceProgram $program): void {
                if (! \Illuminate\Support\Facades\Schema::hasTable('agricultural_announcements')) {
                    $program->setRelation('announcements', collect());
                    return;
                }

                $program->announcements?->each(fn (AgriculturalAnnouncement $notice) => $notice->setRelation('publishedRevision', null));
            });
        }

        return view('home', compact('businessDate', 'programs', 'announcements'));
    }

    public function index(Request $request, AgriculturalBusinessCalendar $calendar, AgriculturalSchemaCapabilities $schema): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'availability' => ['nullable', Rule::in(['all', 'available', 'upcoming', 'ended'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $category = $filters['category'] ?? '';
        $availability = $filters['availability'] ?? 'all';
        $businessDate = $calendar->today();
        $date = $businessDate->toDateString();

        $programQuery = AssistanceProgram::query()->where('status', 'open')
            ->with('supplyItem');
        if (\Illuminate\Support\Facades\Schema::hasTable('agricultural_announcements')) {
            $programQuery->with(['announcements' => fn ($query) => $query->visibleToFarmers()
                ->with($schema->announcementRevisionsReady() ? 'publishedRevision.media' : [])
                ->latest('published_at')]);
        }
        $programQuery
            ->when($availability === 'available', fn ($query) => $query
                ->where(fn ($dates) => $dates->whereNull('opens_on')->orWhereDate('opens_on', '<=', $date))
                ->where(fn ($dates) => $dates->whereNull('closes_on')->orWhereDate('closes_on', '>=', $date)))
            ->when($availability === 'upcoming', fn ($query) => $query->whereDate('opens_on', '>', $date))
            ->when($availability === 'ended', fn ($query) => $query->whereNotNull('closes_on')->whereDate('closes_on', '<', $date))
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->when($category !== '', fn ($query) => $query->where('category', $category));

        $programs = $programQuery->orderBy('closes_on')->orderBy('name')->paginate(9, ['*'], 'programs_page')->withQueryString();
        if (! $schema->requirementVersionsReady()) {
            $programs->getCollection()->each(fn (AssistanceProgram $program) => $program->setRelation('currentRequirementVersion', null));
        }
        if (! $schema->announcementRevisionsReady()) {
            $programs->getCollection()->each(function (AssistanceProgram $program) use ($schema): void {
                if (! \Illuminate\Support\Facades\Schema::hasTable('agricultural_announcements')) {
                    $program->setRelation('announcements', collect());
                    return;
                }
                $program->announcements?->each(fn (AgriculturalAnnouncement $notice) => $notice->setRelation('publishedRevision', null));
            });
        }
        $announcementQuery = AgriculturalAnnouncement::query()->visibleToFarmers()
            ->with($schema->announcementRevisionsReady() ? ['program.supplyItem', 'publishedRevision.media'] : ['program.supplyItem'])
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('title', 'like', '%'.$search.'%')->orWhere('body', 'like', '%'.$search.'%')))
            ->when($category !== '' && $schema->announcementMetadataReady(), fn ($query) => $query->where('category', $category));
        $announcements = $announcementQuery->latest('published_at')->paginate(6, ['*'], 'announcements_page')->withQueryString();
        if (! $schema->announcementRevisionsReady()) {
            $announcements->getCollection()->each(fn (AgriculturalAnnouncement $notice) => $notice->setRelation('publishedRevision', null));
        }
        $announcementCategories = $schema->announcementMetadataReady()
            ? AgriculturalAnnouncement::query()->visibleToFarmers()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
            : collect();
        $categories = $announcementCategories
            ->merge(AssistanceProgram::query()->where('status', 'open')->whereNotNull('category')->distinct()->pluck('category'))
            ->unique()->sort()->values();

        $feedItems = $programs->getCollection()->map(function (AssistanceProgram $program): array {
            $linkedNotice = $program->announcements->first();
            $sortTimestamp = $linkedNotice?->published_at?->getTimestamp()
                ?? $program->created_at?->getTimestamp()
                ?? 0;

            return ['type' => 'program', 'record' => $program, 'sort_timestamp' => $sortTimestamp];
        })->concat($announcements->getCollection()->map(fn (AgriculturalAnnouncement $announcement): array => [
            'type' => 'announcement',
            'record' => $announcement,
            'sort_timestamp' => $announcement->published_at?->getTimestamp() ?? 0,
        ]))->sortByDesc('sort_timestamp')->values();

        return view('public.agricultural-services.index', compact(
            'search', 'category', 'availability', 'businessDate', 'programs', 'announcements', 'categories', 'feedItems',
        ));
    }

    public function announcement(
        AgriculturalAnnouncement $announcement,
        AgriculturalAnnouncementPresenter $presenter,
        AgriculturalBusinessCalendar $calendar,
        AgriculturalSchemaCapabilities $schema,
    ): View
    {
        abort_unless(AgriculturalAnnouncement::query()->visibleToFarmers()->whereKey($announcement->id)->exists(), 404);
        $announcement->load($schema->announcementRevisionsReady() ? ['program', 'publishedRevision.media'] : ['program']);
        if (! $schema->announcementRevisionsReady()) {
            $announcement->setRelation('publishedRevision', null);
        }
        $publication = $presenter->present($announcement, true);
        $businessDate = $calendar->today();
        $programAvailability = $publication['program']?->availabilityStateOn($businessDate);

        return view('public.agricultural-services.announcement', [
            'announcement' => $announcement,
            'publication' => $publication,
            'businessDate' => $businessDate,
            'programAvailability' => $programAvailability,
            'preview' => false,
        ]);
    }

    public function program(AssistanceProgram $program, AgriculturalBusinessCalendar $calendar, AgriculturalAnnouncementPresenter $presenter, AgriculturalSchemaCapabilities $schema, AssistanceApplicationDocumentService $documents): View
    {
        abort_unless($program->status === 'open', 404);
        $businessDate = $calendar->today();
        $program->load('supplyItem');
        if ($schema->requirementVersionsReady()) {
            $program->load(['currentRequirementVersion.documentRequirements', 'currentRequirementVersion.publisher']);
        } else {
            $program->setRelation('currentRequirementVersion', null);
        }
        $announcement = \Illuminate\Support\Facades\Schema::hasTable('agricultural_announcements')
            ? AgriculturalAnnouncement::query()->visibleToFarmers()
            ->where('assistance_program_id', $program->id)
            ->with($schema->announcementRevisionsReady() ? ['publishedRevision.media'] : [])
            ->latest('published_at')->first()
            : null;
        if ($announcement && ! $schema->announcementRevisionsReady()) {
            $announcement->setRelation('publishedRevision', null);
        }
        $publication = $announcement ? $presenter->present($announcement, true) : null;
        $requirements = $this->requirements($program);

        return view('public.agricultural-services.program', compact(
            'program', 'businessDate', 'announcement', 'publication', 'requirements',
        ) + ['maximumDocumentUploadBytes' => $documents->maximumUploadBytes()]);
    }

    public function media(AgriculturalAnnouncement $announcement, int $media, AgriculturalSchemaCapabilities $schema): StreamedResponse
    {
        abort_unless($schema->announcementRevisionsReady(), 404);
        $media = AgriculturalAnnouncementMedia::query()->findOrFail($media);
        abort_unless(AgriculturalAnnouncement::query()->visibleToFarmers()
            ->whereKey($announcement->id)
            ->whereHas('publishedRevision.media', fn ($query) => $query->whereKey($media->id))
            ->exists(), 404);
        abort_unless(Storage::disk('local')->exists($media->storage_path), 404);

        $path = $media->storage_path;

        return response()->stream(function () use ($path): void {
            $stream = Storage::disk('local')->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="agricultural-announcement-image"',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function requirements(AssistanceProgram $program): array
    {
        $rules = $program->eligibility_rules ?? [];
        $requirements = ['Verified farmer registration'];
        if (data_get($rules, 'requires_farm')) {
            $requirements[] = 'At least one registered farm';
        }
        foreach (['barangays' => 'Eligible barangays', 'activities' => 'Eligible farm activities', 'farm_categories' => 'Eligible farm categories'] as $key => $label) {
            $values = data_get($rules, $key, []);
            if ($values) {
                $requirements[] = $label.': '.implode(', ', $values);
            }
        }
        if (data_get($rules, 'previous_beneficiary_restricted')) {
            $requirements[] = 'Farmers who previously completed this program cannot apply again.';
        }

        return $requirements;
    }
}
