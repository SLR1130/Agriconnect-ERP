<?php

namespace App\Services;

use App\Contracts\PrivateApplicationDocumentScanner;
use App\Models\AssistanceApplication;
use App\Models\AssistanceApplicationDocument;
use App\Models\AssistanceApplicationDocumentEvent;
use App\Models\AssistanceApplicationSubmissionSnapshot;
use App\Models\AssistanceProgramDocumentRequirement;
use App\Models\User;
use App\Notifications\AASMSApplicationDocumentReviewNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AssistanceApplicationDocumentService
{
    public function __construct(
        private readonly AgriculturalSchemaCapabilities $schema,
        private readonly PrivateApplicationDocumentScanner $scanner,
        private readonly ApplicationDocumentUploadPolicy $uploadPolicy,
    ) {}

    public function uploadEnabled(): bool
    {
        return (bool) config('agricultural_documents.enabled') && $this->schema->documentWorkflowReady();
    }

    public function scannerAvailable(): bool
    {
        try {
            return $this->scanner->available();
        } catch (Throwable) {
            return false;
        }
    }

    public function maximumUploadBytes(?int $requirementMaximum = null): int
    {
        return $this->uploadPolicy->maximumBytes($requirementMaximum);
    }

    public function maximumUploadKilobytes(?int $requirementMaximum = null): int
    {
        return $this->uploadPolicy->maximumKilobytes($requirementMaximum);
    }

    public function assertUploadEnabled(): void
    {
        abort_unless($this->uploadEnabled(), 503, 'Private document upload is not enabled. The MAO must approve the checklist and activate the reviewed document schema first.');
    }

    public function upload(AssistanceApplication $application, AssistanceProgramDocumentRequirement $requirement, UploadedFile $file, User $actor): AssistanceApplicationDocument
    {
        $this->assertUploadEnabled();
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['document' => 'The uploaded file did not arrive intact. Please select it again.']);
        }
        if (! in_array($application->status, ['submitted', 'returned'], true)) {
            throw ValidationException::withMessages(['document' => 'Documents can only be added while an application is submitted or returned for correction.']);
        }
        if (! $application->requirement_version_id || (int) $requirement->requirement_version_id !== (int) $application->requirement_version_id) {
            throw ValidationException::withMessages(['document' => 'This requirement is not part of the version recorded for this application.']);
        }

        $mime = (string) $file->getMimeType();
        $supported = ['application/pdf', 'image/jpeg', 'image/png'];
        $accepted = array_values(array_intersect($requirement->accepted_mime_types ?? [], $supported));
        if (! in_array($mime, $accepted, true)) {
            throw ValidationException::withMessages(['document' => 'The detected file type is not accepted for this requirement. Upload a valid PDF, JPEG, or PNG allowed by the program.']);
        }

        $size = (int) $file->getSize();
        $limit = $this->maximumUploadBytes((int) $requirement->max_size_bytes);
        if ($size < 1 || $size > $limit) {
            throw ValidationException::withMessages(['document' => 'The file is empty or exceeds the maximum upload size for this requirement.']);
        }

        $extension = match ($mime) {
            'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
        };
        $clientName = str_replace(['\\', '/'], '/', (string) $file->getClientOriginalName());
        $safeName = basename($clientName);
        $safeName = preg_replace('/[\x00-\x1F\x7F]/u', '', $safeName) ?: 'supporting-document';
        $safeName = trim(substr($safeName, 0, 180), " .\t\n\r\0\x0B");
        if ($safeName === '' || $safeName === '.' || $safeName === '..') {
            $safeName = 'supporting-document';
        }

        $disk = 'application-documents';
        $path = null;
        try {
            $document = DB::transaction(function () use ($application, $requirement, $actor, $file, $mime, $size, $limit, $extension, $safeName, $disk, &$path): AssistanceApplicationDocument {
                $lockedApplication = AssistanceApplication::query()->lockForUpdate()->findOrFail($application->id);
                if (! in_array($lockedApplication->status, ['submitted', 'returned'], true)
                    || (int) $lockedApplication->requirement_version_id !== (int) $requirement->requirement_version_id) {
                    throw ValidationException::withMessages(['document' => 'This application no longer accepts a file for that requirement.']);
                }
                $lockedRequirement = AssistanceProgramDocumentRequirement::query()->findOrFail($requirement->id);
                if ((int) $lockedRequirement->requirement_version_id !== (int) $lockedApplication->requirement_version_id) {
                    throw ValidationException::withMessages(['document' => 'The selected requirement does not belong to this application.']);
                }

                $previous = AssistanceApplicationDocument::query()
                    ->where('application_id', $lockedApplication->id)
                    ->where('requirement_id', $lockedRequirement->id)
                    ->orderByDesc('version_number')->lockForUpdate()->first();
                if ($previous && ! in_array($previous->review_status, ['correction_requested', 'not_accepted'], true)) {
                    throw ValidationException::withMessages(['document' => 'A replacement is allowed after the MAO requests a correction. Contact the MAO if the prior upload has not been reviewed.']);
                }

                $version = (int) ($previous?->version_number ?? 0) + 1;
                $opaqueName = Str::uuid()->toString().'.'.$extension;
                $directory = 'applications/'.$lockedApplication->id.'/requirements/'.$lockedRequirement->id;
                $path = $file->storeAs($directory, $opaqueName, $disk);
                if (! $path) {
                    throw new HttpException(503, 'The private document store is unavailable. No document was registered.');
                }

                $document = AssistanceApplicationDocument::create([
                    'application_id' => $lockedApplication->id,
                    'requirement_version_id' => $lockedApplication->requirement_version_id,
                    'requirement_id' => $lockedRequirement->id,
                    'version_number' => $version,
                    'supersedes_document_id' => $previous?->id,
                    'uploaded_by' => $actor->id,
                    'original_filename' => $safeName,
                    'mime_type' => $mime,
                    'file_size' => $size,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'scan_status' => 'pending',
                    'review_status' => 'pending',
                    'uploaded_at' => now()->utc(),
                ]);
                AssistanceApplicationDocumentEvent::create([
                    'document_id' => $document->id,
                    'event_type' => $previous ? 'replacement_uploaded' : 'uploaded',
                    'from_review_status' => $previous?->review_status,
                    'to_review_status' => 'pending',
                    'actor_id' => $actor->id,
                    'visibility' => 'farmer',
                    'note' => 'File received in private quarantine. It is not available for review or download until the security scan succeeds.',
                    'metadata' => ['version_number' => $version, 'detected_mime' => $mime, 'file_size' => $size, 'requirement_key' => $lockedRequirement->requirement_key],
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }
            throw $exception;
        }

        try {
            if (! $this->scanner->available()) {
                $scan = ['status' => 'unavailable', 'engine' => null];
            } else {
                $result = $this->scanner->scan(Storage::disk($disk)->path($document->storage_path));
                $status = $result['status'] ?? null;
                $scan = in_array($status, ['clean', 'infected', 'unavailable', 'failed', 'pending'], true)
                    ? ['status' => $status, 'engine' => is_string($result['engine'] ?? null) ? $result['engine'] : null]
                    : ['status' => 'failed', 'engine' => null];
            }
        } catch (Throwable) {
            // Scanner errors and timeouts are quarantine outcomes, never clean results.
            $scan = ['status' => 'failed', 'engine' => null];
        }
        $from = $document->scan_status;
        $document->forceFill([
            'scan_status' => $scan['status'],
            'scan_engine' => $scan['engine'],
            'scan_completed_at' => $scan['status'] === 'pending' ? null : now()->utc(),
        ])->save();
        AssistanceApplicationDocumentEvent::create([
            'document_id' => $document->id,
            'event_type' => 'security_scan_result',
            'from_scan_status' => $from,
            'to_scan_status' => $scan['status'],
            'actor_id' => null,
            'visibility' => 'farmer',
            'note' => match ($scan['status']) {
                'clean' => 'Security scan completed. The document is awaiting authorized MAO review.',
                'infected' => 'The security scan rejected this file. It remains private and cannot be opened.',
                'unavailable' => 'No approved malware scanner is available. This file remains quarantined and cannot be opened.',
                default => 'The malware scan could not complete. This file remains quarantined and cannot be opened.',
            },
            'metadata' => ['scanner' => $scan['engine']],
        ]);

        return $document->fresh(['events', 'requirement']);
    }

    public function review(AssistanceApplicationDocument $document, User $actor, string $decision, ?string $farmerNotes, ?string $internalNotes): AssistanceApplicationDocument
    {
        if ($document->scan_status !== 'clean') {
            throw ValidationException::withMessages(['document' => 'Only a file that passed the configured malware scan can be reviewed.']);
        }
        if (! in_array($decision, ['verified', 'correction_requested', 'not_accepted'], true)) {
            throw ValidationException::withMessages(['review_status' => 'Choose a valid document review decision.']);
        }
        if ($decision !== 'verified' && blank($farmerNotes)) {
            throw ValidationException::withMessages(['farmer_correction_notes' => 'Explain the document decision to the farmer.']);
        }

        return DB::transaction(function () use ($document, $actor, $decision, $farmerNotes, $internalNotes): AssistanceApplicationDocument {
            $locked = AssistanceApplicationDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->scan_status !== 'clean') {
                throw ValidationException::withMessages(['document' => 'The document is no longer eligible for review.']);
            }
            if (AssistanceApplicationDocument::query()->where('supersedes_document_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['document' => 'This version was replaced and cannot receive a new review decision.']);
            }
            $previous = $locked->review_status;
            $locked->forceFill([
                'review_status' => $decision,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now()->utc(),
                'farmer_correction_notes' => $decision === 'verified' ? null : trim((string) $farmerNotes),
                'internal_review_notes' => filled($internalNotes) ? trim((string) $internalNotes) : null,
            ])->save();
            AssistanceApplicationDocumentEvent::create([
                'document_id' => $locked->id,
                'event_type' => 'review_decision',
                'from_review_status' => $previous,
                'to_review_status' => $decision,
                'actor_id' => $actor->id,
                'visibility' => 'farmer',
                'note' => $decision === 'verified' ? ($farmerNotes ?: 'The MAO verified this document.') : trim((string) $farmerNotes),
                'internal_note' => filled($internalNotes) ? trim((string) $internalNotes) : null,
                'metadata' => ['internal_note_present' => filled($internalNotes)],
            ]);

            $application = $locked->application()->with('farmer.user')->firstOrFail();
            $application->farmer?->user?->notify(new AASMSApplicationDocumentReviewNotification($locked->load('application', 'requirement')));

            return $locked->fresh(['events', 'reviewer', 'requirement']);
        });
    }

    /** @return array<int,string> Missing or not-yet-verified required document names. */
    public function outstandingRequiredDocuments(AssistanceApplication $application): array
    {
        if (! $application->requirement_version_id) {
            return [];
        }

        $requirements = AssistanceProgramDocumentRequirement::query()
            ->where('requirement_version_id', $application->requirement_version_id)
            ->where('is_required', true)->orderBy('display_order')->get();

        if (! $this->schema->documentWorkflowReady()) {
            return $requirements->pluck('display_name')->all();
        }

        return $requirements->filter(function (AssistanceProgramDocumentRequirement $requirement) use ($application): bool {
            $latest = AssistanceApplicationDocument::query()
                ->where('application_id', $application->id)->where('requirement_id', $requirement->id)
                ->orderByDesc('version_number')->first();

            return ! $latest || $latest->scan_status !== 'clean' || $latest->review_status !== 'verified';
        })->pluck('display_name')->all();
    }

    public function assertRequiredDocumentsReady(AssistanceApplication $application, string $action): void
    {
        $outstanding = $this->outstandingRequiredDocuments($application);
        if ($outstanding !== []) {
            throw ValidationException::withMessages([
                'documents' => 'The application cannot be '.$action.' until all required documents are securely scanned and verified: '.implode(', ', $outstanding).'.',
            ]);
        }
    }

    public function recordInitialSubmissionSnapshot(AssistanceApplication $application, User $actor, \DateTimeInterface $submittedAt): ?AssistanceApplicationSubmissionSnapshot
    {
        if (! $this->schema->documentWorkflowReady()) {
            return null;
        }
        $snapshot = $application->acknowledgment()->firstOrFail()->snapshot;

        return $this->saveSubmissionSnapshot($application, $actor, 'initial', $snapshot, $submittedAt);
    }

    public function recordResubmissionSnapshot(AssistanceApplication $application, User $actor, \DateTimeInterface $submittedAt): ?AssistanceApplicationSubmissionSnapshot
    {
        if (! $this->schema->documentWorkflowReady()) {
            return null;
        }
        $documents = AssistanceApplicationDocument::query()->with('requirement')
            ->where('application_id', $application->id)->orderBy('requirement_id')->orderByDesc('version_number')->get()
            ->unique('requirement_id')->values()->map(fn (AssistanceApplicationDocument $document): array => [
                'requirement_key' => $document->requirement?->requirement_key,
                'requirement_name' => $document->requirement?->display_name,
                'document_version' => $document->version_number,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'sha256' => $document->sha256,
                'scan_status' => $document->scan_status,
                'review_status' => $document->review_status,
                'farmer_feedback' => $document->farmer_correction_notes,
            ])->all();
        $snapshot = [
            'schema_version' => 1,
            'submission_type' => 'resubmission',
            'application' => ['id' => $application->id, 'reference' => $application->application_reference, 'status_at_submission' => 'submitted'],
            'program' => ['id' => $application->program?->id, 'name' => $application->program?->name],
            'requirements_version_id' => $application->requirement_version_id,
            'submitted_at_utc' => \Carbon\CarbonImmutable::instance($submittedAt)->utc()->toIso8601String(),
            'purpose' => $application->purpose,
            'documents' => $documents,
            'original_acknowledgment_unchanged' => true,
        ];

        return $this->saveSubmissionSnapshot($application, $actor, 'resubmission', $snapshot, $submittedAt);
    }

    private function saveSubmissionSnapshot(AssistanceApplication $application, User $actor, string $type, array $snapshot, \DateTimeInterface $submittedAt): AssistanceApplicationSubmissionSnapshot
    {
        $attempt = (int) AssistanceApplicationSubmissionSnapshot::query()->where('application_id', $application->id)->max('attempt_number') + 1;
        $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return AssistanceApplicationSubmissionSnapshot::create([
            'application_id' => $application->id,
            'attempt_number' => $attempt,
            'submission_type' => $type,
            'snapshot' => $snapshot,
            'snapshot_sha256' => hash('sha256', $encoded),
            'created_by' => $actor->id,
            'submitted_at' => $submittedAt,
        ]);
    }
}
