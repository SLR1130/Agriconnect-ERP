<?php

namespace App\Http\Controllers;

use App\Models\AssistanceApplication;
use App\Models\AssistanceApplicationDocument;
use App\Models\AssistanceApplicationDocumentAccessLog;
use App\Models\AssistanceProgramDocumentRequirement;
use App\Services\AgriculturalSchemaCapabilities;
use App\Services\AssistanceApplicationDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;

class AssistanceApplicationDocumentController extends Controller
{
    public function farmerUpload(
        Request $request,
        AssistanceApplication $application,
        int $requirement,
        AssistanceApplicationDocumentService $documents,
    ) {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->first();
        abort_unless($farmer && (int) $application->farmer_id === (int) $farmer->id, 404);
        $documents->assertUploadEnabled();
        $requirement = AssistanceProgramDocumentRequirement::query()->findOrFail($requirement);
        $data = $request->validate([
            'document' => ['required', 'file', 'max:65536', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ]);

        $document = $documents->upload($application, $requirement, $data['document'], $request->user());
        $message = match ($document->scan_status) {
            'clean' => 'The file passed the security scan and is awaiting MAO document review.',
            'infected' => 'The file was received privately but rejected by the security scan. It cannot be opened.',
            'unavailable' => 'The file was received into private quarantine. No malware scanner is available, so it cannot be viewed or reviewed yet.',
            default => 'The file was received into private quarantine. The security scan did not complete, so it cannot be viewed or reviewed yet.',
        };

        return redirect()->route('farmer.applications.show', $application)->with('status', $message);
    }

    public function farmerFile(Request $request, AssistanceApplication $application, int $document, AgriculturalSchemaCapabilities $schema): StreamedResponse
    {
        abort_unless($request->user()->hasRole('farmer'), 403);
        $farmer = $request->user()->farmer()->first();
        abort_unless($farmer && (int) $application->farmer_id === (int) $farmer->id, 404);
        abort_unless($schema->documentWorkflowReady(), 404);
        $document = AssistanceApplicationDocument::query()->findOrFail($document);
        abort_unless((int) $document->application_id === (int) $application->id, 404);

        return $this->streamDocument($request, $document);
    }

    public function staffFile(Request $request, AssistanceApplication $application, int $document, AgriculturalSchemaCapabilities $schema): StreamedResponse
    {
        abort_unless($request->user()->canModuleAction('aasms', 'view')
            && $request->user()->canModuleAction('aasms', 'documents_view_sensitive'), 403);
        abort_unless($schema->documentWorkflowReady(), 404);
        $document = AssistanceApplicationDocument::query()->findOrFail($document);
        abort_unless((int) $document->application_id === (int) $application->id, 404);

        return $this->streamDocument($request, $document);
    }

    public function review(
        Request $request,
        AssistanceApplication $application,
        int $document,
        AgriculturalSchemaCapabilities $schema,
        AssistanceApplicationDocumentService $documents,
    ) {
        abort_unless($request->user()->canModuleAction('aasms', 'view')
            && $request->user()->canModuleAction('aasms', 'documents_view_sensitive')
            && $request->user()->canModuleAction('aasms', 'documents_review'), 403);
        abort_unless($schema->documentWorkflowReady(), 404);
        $document = AssistanceApplicationDocument::query()->findOrFail($document);
        abort_unless((int) $document->application_id === (int) $application->id, 404);
        $data = $request->validate([
            'review_status' => ['required', Rule::in(['verified', 'correction_requested', 'not_accepted'])],
            'farmer_correction_notes' => ['required_unless:review_status,verified', 'nullable', 'string', 'max:2000'],
            'internal_review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $documents->review(
            $document,
            $request->user(),
            $data['review_status'],
            $data['farmer_correction_notes'] ?? null,
            $data['internal_review_notes'] ?? null,
        );

        return redirect()->route('modules.aasms.applications.show', $application)->with('status', 'Document review was recorded in the application history.');
    }

    private function streamDocument(Request $request, AssistanceApplicationDocument $document): StreamedResponse
    {
        abort_unless($document->scan_status === 'clean', 423, 'This file is quarantined or did not pass the malware scan. It cannot be opened.');
        $disk = Storage::disk($document->storage_disk);
        abort_unless($disk->exists($document->storage_path), 404);

        AssistanceApplicationDocumentAccessLog::create([
            'document_id' => $document->id,
            'actor_id' => $request->user()->id,
            'access_action' => $request->boolean('inline') ? 'preview' : 'download',
            'accessed_at' => now()->utc(),
        ]);

        $inline = $request->boolean('inline');
        $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $document->original_filename) ?: 'supporting-document';
        $disposition = HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $safeName, 'supporting-document');
        $path = $document->storage_path;
        $mime = $document->mime_type;

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
