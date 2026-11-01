<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalAnnouncement;
use App\Models\AgriculturalAnnouncementMedia;
use App\Models\AgriculturalAnnouncementRevision;
use App\Models\AssistanceProgram;
use App\Services\AgriculturalAnnouncementPresenter;
use App\Services\AgriculturalPublicationService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function staffIndex(Request $request): View
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view'), 403);

        return view('modules.aasms.announcements', [
            'announcements' => AgriculturalAnnouncement::query()
                ->with(['program', 'currentRevision.media', 'publishedRevision.media', 'revisions.creator', 'revisions.publisher'])
                ->latest()->paginate(12)->withQueryString(),
            'programs' => AssistanceProgram::query()->where('status', 'open')->orderBy('name')->get(),
            'canCreate' => $request->user()->canModuleAction('aasms', 'create'),
            'canUpdate' => $request->user()->canModuleAction('aasms', 'update'),
            'canPublish' => $request->user()->canModuleAction('aasms', 'publish'),
        ]);
    }

    public function store(Request $request, AgriculturalPublicationService $publication): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'create'), 403);
        $announcement = $publication->createDraft($this->validatedContent($request), $request->user());

        return redirect()->route('modules.aasms.announcements')->with('status', 'Private announcement draft saved. Preview it and request publication review.')->with('created_announcement', $announcement->id);
    }

    public function update(Request $request, AgriculturalAnnouncement $announcement, AgriculturalPublicationService $publication): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'update'), 403);
        if (! in_array($announcement->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['announcement' => 'Archived announcements cannot be edited. Create a new announcement if updated information is needed.']);
        }

        $revision = $publication->saveRevision($announcement, $this->validatedContent($request, true), $request->user());
        $message = $announcement->status === 'published'
            ? 'A new private revision is ready for publication review. The currently published version remains visible.'
            : 'Draft revision saved. The previous version is preserved in publication history.';

        return redirect()->route('modules.aasms.announcements')->with('status', $message)->with('created_revision', $revision->id);
    }

    public function publish(Request $request, AgriculturalAnnouncement $announcement, AgriculturalPublicationService $publication): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view')
            && $request->user()->canModuleAction('aasms', 'publish'), 403);
        $revision = $announcement->currentRevision;
        abort_unless($revision, 404);

        $publication->publishRevision($announcement, $revision, $request->user());

        return back()->with('status', 'The reviewed publication revision is now visible to the public.');
    }

    public function archive(Request $request, AgriculturalAnnouncement $announcement, AgriculturalPublicationService $publication): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view')
            && $request->user()->canModuleAction('aasms', 'publish'), 403);
        $publication->archive($announcement, $request->user());

        return back()->with('status', 'Announcement archived. Its publication history is retained.');
    }

    public function preview(Request $request, AgriculturalAnnouncement $announcement, AgriculturalAnnouncementRevision $revision, AgriculturalAnnouncementPresenter $presenter): View
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view'), 403);
        abort_unless((int) $revision->announcement_id === (int) $announcement->id, 404);

        return view('public.agricultural-services.announcement', [
            'announcement' => $announcement,
            'publication' => $presenter->present($announcement, false, $revision->load('media')),
            'preview' => true,
        ]);
    }

    public function staffMedia(Request $request, AgriculturalAnnouncement $announcement, AgriculturalAnnouncementRevision $revision, AgriculturalAnnouncementMedia $media): StreamedResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view'), 403);
        abort_unless((int) $revision->announcement_id === (int) $announcement->id
            && (int) $media->revision_id === (int) $revision->id, 404);

        return $this->imageResponse($media);
    }

    public function farmerIndex(AgriculturalAnnouncementPresenter $presenter): View
    {
        abort_unless(request()->user()->hasRole('farmer'), 403);
        $announcements = AgriculturalAnnouncement::query()->visibleToFarmers()
            ->with(['program', 'publishedRevision.media'])->latest('published_at')->paginate(12)->withQueryString();

        return view('farmer.announcements', [
            'announcements' => $announcements,
            'presentations' => $announcements->getCollection()->mapWithKeys(fn (AgriculturalAnnouncement $notice) => [$notice->id => $presenter->present($notice, true)]),
        ]);
    }

    public function farmerShow(AgriculturalAnnouncement $announcement, AgriculturalAnnouncementPresenter $presenter): View
    {
        abort_unless(request()->user()->hasRole('farmer'), 403);
        abort_unless(AgriculturalAnnouncement::query()->visibleToFarmers()->whereKey($announcement->id)->exists(), 404);
        $announcement->load(['program', 'publishedRevision.media']);

        return view('farmer.announcement', [
            'announcement' => $announcement,
            'publication' => $presenter->present($announcement, true),
        ]);
    }

    private function validatedContent(Request $request, bool $updating = false): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'assistance_program_id' => ['nullable', 'integer', Rule::exists('assistance_programs', 'id')],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'office' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:100'],
            'service_area' => ['nullable', 'string', 'max:180'],
            'requirements_summary' => ['nullable', 'string', 'max:4000'],
            'contact_details' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:4000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_alt' => ['required_with:cover_image', 'nullable', 'string', 'max:180'],
            'gallery_images' => ['nullable', 'array', 'max:6'],
            'gallery_images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery_alt' => ['nullable', 'array'],
            'gallery_alt.*' => ['nullable', 'string', 'max:180'],
            'keep_media_ids' => ['nullable', 'array', 'max:8'],
            'keep_media_ids.*' => ['integer'],
            'existing_cover_id' => ['nullable', 'integer'],
            'media_order' => ['nullable', 'array', 'max:8'],
            'media_order.*' => ['integer'],
            'media_alt' => ['nullable', 'array'],
            'media_alt.*' => ['string', 'max:180'],
        ]);

        $validator->after(function ($validator) use ($request, $updating): void {
            $images = $request->file('gallery_images', []);
            $alts = $request->input('gallery_alt', []);
            if (count($images) !== count($alts)) {
                $validator->errors()->add('gallery_alt', 'Provide alternative text for every gallery photograph.');
            }
            foreach ($alts as $index => $alt) {
                if (isset($images[$index]) && trim((string) $alt) === '') {
                    $validator->errors()->add('gallery_alt.'.$index, 'Describe this photograph for people using assistive technology.');
                }
            }
            foreach ($request->input('media_alt', []) as $mediaId => $alt) {
                if (trim((string) $alt) === '') {
                    $validator->errors()->add('media_alt.'.$mediaId, 'Existing photographs must retain descriptive alternative text.');
                }
            }
            if ($request->hasFile('cover_image') && trim((string) $request->input('cover_alt')) === '') {
                $validator->errors()->add('cover_alt', 'Describe the cover photograph for people using assistive technology.');
            }
            if (! $updating && ($request->filled('keep_media_ids') || $request->filled('media_order'))) {
                $validator->errors()->add('keep_media_ids', 'Existing image controls are not valid when creating a new announcement.');
            }
        });

        return $validator->validate();
    }

    private function imageResponse(AgriculturalAnnouncementMedia $media): StreamedResponse
    {
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
            'Content-Disposition' => 'inline; filename="announcement-image"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
