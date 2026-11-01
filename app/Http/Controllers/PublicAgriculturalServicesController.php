<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalAnnouncement;
use App\Models\AgriculturalAnnouncementMedia;
use App\Models\AssistanceProgram;
use App\Services\AgriculturalAnnouncementPresenter;
use App\Services\AgriculturalBusinessCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicAgriculturalServicesController extends Controller
{
    public function index(Request $request, AgriculturalBusinessCalendar $calendar): View
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
            ->with(['announcements' => fn ($query) => $query->visibleToFarmers()->with('publishedRevision.media')->latest('published_at')])
            ->when($availability === 'available', fn ($query) => $query
                ->where(fn ($dates) => $dates->whereNull('opens_on')->orWhereDate('opens_on', '<=', $date))
                ->where(fn ($dates) => $dates->whereNull('closes_on')->orWhereDate('closes_on', '>=', $date)))
            ->when($availability === 'upcoming', fn ($query) => $query->whereDate('opens_on', '>', $date))
            ->when($availability === 'ended', fn ($query) => $query->whereNotNull('closes_on')->whereDate('closes_on', '<', $date))
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('name', 'like', '%'.$search.'%')->orWhere('description', 'like', '%'.$search.'%')))
            ->when($category !== '', fn ($query) => $query->where('category', $category));

        $programs = $programQuery->orderBy('closes_on')->orderBy('name')->paginate(9, ['*'], 'programs_page')->withQueryString();
        $announcementQuery = AgriculturalAnnouncement::query()->visibleToFarmers()
            ->with(['program', 'publishedRevision.media'])
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('title', 'like', '%'.$search.'%')->orWhere('body', 'like', '%'.$search.'%')))
            ->when($category !== '', fn ($query) => $query->where('category', $category));
        $announcements = $announcementQuery->latest('published_at')->paginate(6, ['*'], 'announcements_page')->withQueryString();
        $categories = AgriculturalAnnouncement::query()->visibleToFarmers()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
            ->merge(AssistanceProgram::query()->where('status', 'open')->whereNotNull('category')->distinct()->pluck('category'))
            ->unique()->sort()->values();

        return view('public.agricultural-services.index', compact(
            'search', 'category', 'availability', 'businessDate', 'programs', 'announcements', 'categories',
        ));
    }

    public function announcement(AgriculturalAnnouncement $announcement, AgriculturalAnnouncementPresenter $presenter): View
    {
        abort_unless(AgriculturalAnnouncement::query()->visibleToFarmers()->whereKey($announcement->id)->exists(), 404);
        $announcement->load(['program', 'publishedRevision.media']);

        return view('public.agricultural-services.announcement', [
            'announcement' => $announcement,
            'publication' => $presenter->present($announcement, true),
            'preview' => false,
        ]);
    }

    public function program(AssistanceProgram $program, AgriculturalBusinessCalendar $calendar, AgriculturalAnnouncementPresenter $presenter): View
    {
        abort_unless($program->status === 'open', 404);
        $businessDate = $calendar->today();
        $announcement = AgriculturalAnnouncement::query()->visibleToFarmers()
            ->where('assistance_program_id', $program->id)
            ->with(['publishedRevision.media'])
            ->latest('published_at')->first();
        $publication = $announcement ? $presenter->present($announcement, true) : null;
        $requirements = $this->requirements($program);

        return view('public.agricultural-services.program', compact(
            'program', 'businessDate', 'announcement', 'publication', 'requirements',
        ));
    }

    public function media(AgriculturalAnnouncement $announcement, AgriculturalAnnouncementMedia $media): StreamedResponse
    {
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
