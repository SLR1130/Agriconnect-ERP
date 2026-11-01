<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AgriculturalAnnouncement;
use App\Models\AgriculturalAnnouncementRevision;
use App\Models\AssistanceProgram;
use App\Models\User;
use App\Notifications\AgriculturalAnnouncementPublished;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgriculturalPublicationService
{
    private const CONTENT_FIELDS = [
        'title', 'body', 'assistance_program_id', 'expires_at', 'office', 'category',
        'service_area', 'requirements_summary', 'contact_details', 'instructions',
    ];

    public function createDraft(array $data, User $actor): AgriculturalAnnouncement
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $actor, &$storedPaths): AgriculturalAnnouncement {
                $content = $this->content($data);
                $announcement = AgriculturalAnnouncement::query()->create($content + [
                    'status' => 'draft',
                    'created_by' => $actor->id,
                ]);
                $revision = $announcement->revisions()->create([
                    'revision_number' => 1,
                    'state' => 'draft',
                    'content' => $content,
                    'created_by' => $actor->id,
                ]);

                $this->appendUploads($revision, $data, $actor, $storedPaths);
                $announcement->forceFill(['current_revision_id' => $revision->id])->save();
                ActivityLog::log('created', 'Agricultural announcement draft created.', 'announcement:'.$announcement->id, $actor->id, 'aasms');

                return $announcement->load(['currentRevision.media', 'revisions.creator', 'revisions.publisher']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    public function saveRevision(AgriculturalAnnouncement $announcement, array $data, User $actor): AgriculturalAnnouncementRevision
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($announcement, $data, $actor, &$storedPaths): AgriculturalAnnouncementRevision {
                $locked = AgriculturalAnnouncement::query()->lockForUpdate()->findOrFail($announcement->id);
                $sourceRevision = $locked->currentRevision()->with('media')->first()
                    ?? $locked->publishedRevision()->with('media')->first();
                $keepIds = collect($data['keep_media_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
                $sourceMedia = $sourceRevision?->media ?? collect();

                if ($keepIds->diff($sourceMedia->modelKeys())->isNotEmpty()) {
                    throw ValidationException::withMessages(['keep_media_ids' => 'One or more selected images do not belong to this announcement revision.']);
                }

                $existingCoverId = isset($data['existing_cover_id']) ? (int) $data['existing_cover_id'] : null;
                if ($existingCoverId !== null && ! $keepIds->contains($existingCoverId)) {
                    throw ValidationException::withMessages(['existing_cover_id' => 'The selected cover image must also be retained.']);
                }

                $revision = $locked->revisions()->create([
                    'revision_number' => ((int) $locked->revisions()->max('revision_number')) + 1,
                    'state' => 'draft',
                    'content' => $this->content($data),
                    'created_by' => $actor->id,
                ]);

                if ($sourceRevision) {
                    $orderedIds = collect($data['media_order'] ?? [])->map(fn ($id) => (int) $id);
                    $keep = $sourceMedia->whereIn('id', $keepIds)->keyBy('id');
                    $ordered = $orderedIds->filter(fn (int $id) => $keep->has($id))->unique()->concat($keepIds->diff($orderedIds))->values();
                    $altOverrides = $data['media_alt'] ?? [];

                    foreach ($ordered as $position => $mediaId) {
                        $source = $keep->get($mediaId);
                        \App\Models\AgriculturalAnnouncementMedia::query()->create([
                            'revision_id' => $revision->id,
                            'storage_path' => $source->storage_path,
                            'mime_type' => $source->mime_type,
                            'file_size' => $source->file_size,
                            'alt_text' => trim((string) ($altOverrides[$mediaId] ?? $source->alt_text)),
                            'sort_order' => $position,
                            'is_cover' => ! isset($data['cover_image']) && $existingCoverId === $mediaId,
                            'uploaded_by' => $source->uploaded_by,
                        ]);
                    }
                }

                $this->appendUploads($revision, $data, $actor, $storedPaths);
                if ($revision->media()->count() > 8) {
                    throw ValidationException::withMessages(['gallery_images' => 'An announcement can contain no more than eight images in one publication revision.']);
                }

                if ($sourceRevision?->state === 'draft') {
                    $sourceRevision->update(['state' => 'superseded']);
                }

                $locked->forceFill(['current_revision_id' => $revision->id])->save();
                ActivityLog::log('updated', 'Agricultural announcement revision saved for review.', 'announcement:'.$locked->id, $actor->id, 'aasms');

                return $revision->load(['media', 'creator', 'publisher']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    public function publishRevision(AgriculturalAnnouncement $announcement, AgriculturalAnnouncementRevision $revision, User $actor): AgriculturalAnnouncement
    {
        $published = DB::transaction(function () use ($announcement, $revision, $actor): AgriculturalAnnouncement {
            $locked = AgriculturalAnnouncement::query()->lockForUpdate()->findOrFail($announcement->id);
            $target = $locked->revisions()->lockForUpdate()->findOrFail($revision->id);

            if ($target->state !== 'draft' || (int) $locked->current_revision_id !== (int) $target->id) {
                throw ValidationException::withMessages(['announcement' => 'Only the current draft revision can be published.']);
            }

            $content = $target->content;
            $expiresAt = $content['expires_at'] ? CarbonImmutable::parse($content['expires_at']) : null;
            if ($expiresAt && $expiresAt->lte(now())) {
                throw ValidationException::withMessages(['expires_at' => 'An announcement must expire after publication.']);
            }

            if ($content['assistance_program_id']) {
                $programIsPublished = AssistanceProgram::query()
                    ->whereKey($content['assistance_program_id'])
                    ->where('status', 'open')
                    ->exists();

                if (! $programIsPublished) {
                    throw ValidationException::withMessages(['assistance_program_id' => 'Publish the linked AASMS program before publishing this announcement.']);
                }
            }

            $locked->publishedRevision()->where('id', '!=', $target->id)->update(['state' => 'superseded']);
            $target->update(['state' => 'published', 'published_at' => now(), 'published_by' => $actor->id]);
            $locked->forceFill($this->parentFields($content) + [
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $actor->id,
                'published_revision_id' => $target->id,
            ])->save();
            ActivityLog::log('published', 'Agricultural announcement revision published.', 'announcement:'.$locked->id, $actor->id, 'aasms');

            return $locked->refresh()->load(['program', 'publishedRevision.media']);
        });

        $this->notifyFarmers($published);

        return $published;
    }

    public function archive(AgriculturalAnnouncement $announcement, User $actor): void
    {
        DB::transaction(function () use ($announcement, $actor): void {
            $locked = AgriculturalAnnouncement::query()->lockForUpdate()->findOrFail($announcement->id);
            if ($locked->status !== 'published') {
                throw ValidationException::withMessages(['announcement' => 'Only a published announcement can be archived.']);
            }

            if ($locked->currentRevision?->state === 'draft') {
                $locked->currentRevision->update(['state' => 'superseded']);
            }

            $snapshot = $locked->publicContent();
            $revision = $locked->revisions()->create([
                'revision_number' => ((int) $locked->revisions()->max('revision_number')) + 1,
                'state' => 'archived',
                'content' => $snapshot,
                'created_by' => $actor->id,
                'published_by' => $actor->id,
                'published_at' => now(),
            ]);
            $locked->forceFill(['status' => 'archived', 'current_revision_id' => $revision->id])->save();
            ActivityLog::log('archived', 'Agricultural announcement archived.', 'announcement:'.$locked->id, $actor->id, 'aasms');
        });
    }

    private function content(array $data): array
    {
        $content = array_intersect_key($data, array_flip(self::CONTENT_FIELDS));
        $content['assistance_program_id'] = ($content['assistance_program_id'] ?? null) ?: null;
        $content['expires_at'] = ! empty($content['expires_at'])
            ? CarbonImmutable::parse($content['expires_at'])->toDateTimeString()
            : null;

        foreach (['office', 'category', 'service_area', 'requirements_summary', 'contact_details', 'instructions'] as $field) {
            $content[$field] = isset($content[$field]) && trim((string) $content[$field]) !== '' ? trim((string) $content[$field]) : null;
        }

        return $content;
    }

    private function parentFields(array $content): array
    {
        return array_intersect_key($content, array_flip(self::CONTENT_FIELDS));
    }

    /** @param array<int, string> $storedPaths */
    private function appendUploads(AgriculturalAnnouncementRevision $revision, array $data, User $actor, array &$storedPaths): void
    {
        $position = $revision->media()->count();
        $cover = $data['cover_image'] ?? null;
        if ($cover instanceof UploadedFile) {
            $this->storeMedia($revision, $cover, (string) $data['cover_alt'], $actor, $position++, true, $storedPaths);
        }

        foreach (($data['gallery_images'] ?? []) as $index => $image) {
            if ($image instanceof UploadedFile) {
                $this->storeMedia($revision, $image, (string) $data['gallery_alt'][$index], $actor, $position++, false, $storedPaths);
            }
        }
    }

    /** @param array<int, string> $storedPaths */
    private function storeMedia(AgriculturalAnnouncementRevision $revision, UploadedFile $file, string $alt, User $actor, int $position, bool $cover, array &$storedPaths): void
    {
        $path = $file->store('agricultural-announcements/'.$revision->announcement_id, 'local');
        if (! $path) {
            throw ValidationException::withMessages(['gallery_images' => 'An image could not be stored. Please try again.']);
        }

        $storedPaths[] = $path;
        $revision->media()->create([
            'storage_path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'file_size' => (int) $file->getSize(),
            'alt_text' => trim($alt),
            'sort_order' => $position,
            'is_cover' => $cover,
            'uploaded_by' => $actor->id,
        ]);
    }

    private function notifyFarmers(AgriculturalAnnouncement $announcement): void
    {
        User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('slug', 'farmer')->where('is_active', true))
            ->chunkById(100, function ($farmers) use ($announcement): void {
                foreach ($farmers as $farmerUser) {
                    $farmerUser->notify(new AgriculturalAnnouncementPublished($announcement));
                }
            });
    }
}
