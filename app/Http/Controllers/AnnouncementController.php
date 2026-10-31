<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AgriculturalAnnouncement;
use App\Models\AssistanceProgram;
use App\Models\User;
use App\Notifications\AgriculturalAnnouncementPublished;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function staffIndex(Request $request): View
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view'), 403);

        return view('modules.aasms.announcements', [
            'announcements' => AgriculturalAnnouncement::query()->with('program')->latest()->paginate(20),
            'programs' => AssistanceProgram::query()->where('status', 'open')->orderBy('name')->get(),
            'canCreate' => $request->user()->canModuleAction('aasms', 'create'),
            'canPublish' => $request->user()->canModuleAction('aasms', 'update'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'create'), 403);
        $announcement = AgriculturalAnnouncement::create($this->data($request) + ['status' => 'draft', 'created_by' => $request->user()->id]);
        ActivityLog::log('created', 'Agricultural announcement draft created.', 'announcement:'.$announcement->id, $request->user()->id, 'aasms');

        return redirect()->route('modules.aasms.announcements')->with('status', 'Announcement saved as a private draft.');
    }

    public function update(Request $request, AgriculturalAnnouncement $announcement): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'update'), 403);
        if ($announcement->status !== 'draft') {
            throw ValidationException::withMessages(['announcement' => 'Only draft announcements can be edited.']);
        }
        $announcement->update($this->data($request));
        ActivityLog::log('updated', 'Agricultural announcement draft updated.', 'announcement:'.$announcement->id, $request->user()->id, 'aasms');

        return back()->with('status', 'Draft updated.');
    }

    public function publish(Request $request, AgriculturalAnnouncement $announcement): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'update'), 403);
        DB::transaction(function () use ($request, $announcement): void {
            $locked = AgriculturalAnnouncement::query()->lockForUpdate()->findOrFail($announcement->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['announcement' => 'Only a draft can be published.']);
            }
            if ($locked->expires_at && $locked->expires_at->lte(now())) {
                throw ValidationException::withMessages(['expires_at' => 'An announcement must expire after publication.']);
            }
            if ($locked->program && $locked->program->status !== 'open') {
                throw ValidationException::withMessages(['assistance_program_id' => 'Publish the linked assistance program before this announcement.']);
            }
            $locked->update(['status' => 'published', 'published_at' => now(), 'published_by' => $request->user()->id]);
            ActivityLog::log('published', 'Agricultural announcement published.', 'announcement:'.$locked->id, $request->user()->id, 'aasms');
            User::query()->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'farmer')->where('is_active', true))
                ->chunkById(100, function ($farmers) use ($locked): void {
                    foreach ($farmers as $farmerUser) {
                        $farmerUser->notify(new AgriculturalAnnouncementPublished($locked));
                    }
                });
        });

        return back()->with('status', 'Announcement published for farmers.');
    }

    public function archive(Request $request, AgriculturalAnnouncement $announcement): RedirectResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'update'), 403);
        if ($announcement->status !== 'published') {
            throw ValidationException::withMessages(['announcement' => 'Only a published announcement can be archived.']);
        }
        $announcement->update(['status' => 'archived']);
        ActivityLog::log('archived', 'Agricultural announcement archived.', 'announcement:'.$announcement->id, $request->user()->id, 'aasms');

        return back()->with('status', 'Announcement archived.');
    }

    public function farmerIndex(Request $request): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);

        return view('farmer.announcements', ['announcements' => AgriculturalAnnouncement::query()->with('program')->visibleToFarmers()->latest('published_at')->paginate(12)]);
    }

    public function farmerShow(Request $request, AgriculturalAnnouncement $announcement): View
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        abort_unless(AgriculturalAnnouncement::query()->visibleToFarmers()->whereKey($announcement->id)->exists(), 404);

        return view('farmer.announcement', ['announcement' => $announcement->load('program')]);
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:4000'],
            'assistance_program_id' => ['nullable', 'integer', 'exists:assistance_programs,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
    }
}
