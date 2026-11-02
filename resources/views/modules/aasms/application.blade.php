<x-layouts.app>
    <x-slot name="title">AASMS Application</x-slot>
    <x-slot name="header">Assistance Application Details</x-slot>
    <x-slot name="description">Traceable application record and the requirements snapshot used at submission.</x-slot>

    @if(session('status'))<div class="mx-auto mb-4 max-w-5xl rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mx-auto mb-4 max-w-5xl rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($documentUploadsEnabled && !$documentScannerAvailable)<div class="mx-auto mb-4 max-w-5xl rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status"><strong>Document activation is paused.</strong> The malware scanner is unavailable; quarantined files cannot be opened or reviewed.</div>@endif
    <style>.aas-status{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .55rem;font-size:.7rem;font-weight:800;white-space:nowrap}.aas-pending{background:#fff5d8;color:#8a691e}.aas-approved,.aas-completed{background:#e7f0e8;color:#1c6a46}.aas-rejected{background:#fee7e5;color:#a33e36}.aas-returned{background:#e7f0fb;color:#28618e}</style>
    <a class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-emerald-700 underline" href="{{ route('modules.aasms.dashboard', ['workspace' => 'applications']) }}"><x-ui.icon name="back" size="sm"/>Back to application queue</a>
    <div class="mx-auto max-w-5xl space-y-5">
        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs font-extrabold uppercase tracking-widest text-emerald-700">AASMS application</p><h1 class="mt-1 text-2xl font-extrabold text-[#153d2b]">{{ $application->application_reference ?: 'Legacy application #'.$application->id }}</h1><p class="mt-2 text-sm text-gray-600">{{ $application->program->name }} · {{ $application->farmer->user->name }} · {{ $application->farmer->farmer_code }}</p></div>
                <span class="aas-status {{ match($application->status) { 'submitted' => 'aas-pending', 'rejected' => 'aas-rejected', 'returned' => 'aas-returned', 'completed' => 'aas-completed', default => 'aas-approved' } }}">{{ ucfirst($application->status) }}</span>
            </div>
            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl bg-[#f8faf7] p-3"><dt class="text-xs font-bold uppercase text-gray-500">Submitted</dt><dd class="mt-1 text-sm font-semibold">@if($application->submitted_at){{ $application->submitted_at->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Manila time @else Historical timestamp not separately recorded @endif</dd></div>
                <div class="rounded-xl bg-[#f8faf7] p-3"><dt class="text-xs font-bold uppercase text-gray-500">Eligibility at submission</dt><dd class="mt-1 text-sm font-semibold">{{ is_null($application->eligibility_passed) ? 'Not recorded' : ($application->eligibility_passed ? 'Checks passed' : 'Needs review') }}</dd></div>
                <div class="rounded-xl bg-[#f8faf7] p-3"><dt class="text-xs font-bold uppercase text-gray-500">Requirements version</dt><dd class="mt-1 text-sm font-semibold">{{ $application->requirementVersion ? 'Version '.$application->requirementVersion->version_number : 'Legacy evidence unavailable' }}</dd></div>
            </dl>
            @if($application->acknowledgment)<a class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-[#153d2b] px-4 py-2 text-sm font-bold text-white" href="{{ route('modules.aasms.applications.acknowledgment', $application) }}" target="_blank" rel="noopener">Open acknowledgment</a>@else<p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Legacy application: no historical acknowledgment or document-receipt evidence is available.</p>@endif
        </section>

        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <h2 class="text-lg font-bold text-[#153d2b]">Applicant and submission</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2"><div><dt class="text-xs font-bold uppercase text-gray-500">Farmer reference</dt><dd>{{ $application->farmer->farmer_code }}</dd></div><div><dt class="text-xs font-bold uppercase text-gray-500">Barangay</dt><dd>{{ $application->farmer->barangay ?: 'Not recorded' }}</dd></div><div><dt class="text-xs font-bold uppercase text-gray-500">Submission channel</dt><dd>{{ $application->submission_channel ? str($application->submission_channel)->replace('_', ' ')->headline() : 'Historical channel unknown' }}</dd></div><div><dt class="text-xs font-bold uppercase text-gray-500">Purpose</dt><dd>{{ $application->purpose ?: 'No purpose note provided' }}</dd></div></dl>
            @if($application->review_notes)<p class="mt-4 rounded-lg bg-[#f8faf7] p-3 text-sm"><strong>Current review feedback:</strong> {{ $application->review_notes }}</p>@endif
        </section>

        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <h2 class="text-lg font-bold text-[#153d2b]">Requirements and eligibility snapshot</h2>
            @if($application->requirementVersion)
                <p class="mt-1 text-sm text-gray-600">Version {{ $application->requirementVersion->version_number }} · {{ $application->requirementVersion->source === 'legacy_snapshot' ? 'Legacy baseline; publisher and original publication date unknown' : 'Published by '.($application->requirementVersion->publisher?->name ?: 'staff account unavailable') }}</p>
                @if($application->requirementVersion->documentRequirements->isNotEmpty())
                <ul class="mt-4 divide-y divide-gray-100">
                    @foreach($application->requirementVersion->documentRequirements as $requirement)
                        @php($versions = $application->documents->where('requirement_id', $requirement->id)->sortByDesc('version_number'))
                        <li class="py-4">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div><strong>{{ $requirement->display_name }}</strong> <span class="text-xs text-gray-500">{{ $requirement->is_required ? 'Required' : 'Optional' }}</span>
                                @if($requirement->description)<p class="text-sm text-gray-600">{{ $requirement->description }}</p>@endif
                                @if($requirement->instructions)<p class="text-sm text-gray-600">{{ $requirement->instructions }}</p>@endif
                            </div></div>
                            @if(!$documentWorkflowReady)
                                <p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Private document workflow schema is not active on this database.</p>
                            @elseif(!$canViewPrivateDocuments)
                                <p class="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">Uploaded-file details are restricted. Viewing them requires the separate AASMS private-document permission.</p>
                            @elseif($versions->isEmpty())
                                <p class="mt-2 text-sm text-gray-600">No file has been uploaded for this requirement.</p>
                            @else
                                <div class="mt-3 space-y-3">
                                    @foreach($versions as $document)
                                        @php($isCurrentVersion = !$versions->contains(fn ($candidate) => (int)$candidate->supersedes_document_id === (int)$document->id))
                                        <article class="rounded-xl border border-gray-200 bg-[#f8faf7] p-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div><strong>{{ $document->original_filename }}</strong><span class="ml-2 text-xs text-gray-500">Version {{ $document->version_number }} · {{ number_format($document->file_size / 1024, 0) }} KB · {{ $document->mime_type }}</span></div>
                                                <span class="aas-status {{ $document->scan_status === 'clean' ? 'aas-approved' : 'aas-pending' }}">{{ match($document->scan_status) { 'clean' => 'Security scan passed', 'infected' => 'Rejected by scan', 'unavailable' => 'Quarantined · scanner unavailable', 'failed' => 'Quarantined · scan failed', default => 'Quarantined · scan pending' } }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-600">Uploaded by {{ $document->uploader?->name ?? 'Staff account unavailable' }} · {{ $document->uploaded_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }} Manila time · review: {{ str($document->review_status)->replace('_', ' ')->headline() }}@if($document->reviewer) · reviewed by {{ $document->reviewer->name }} {{ $document->reviewed_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }}@endif</p>
                                            @if($document->scan_status === 'clean')<a class="mt-2 inline-flex text-sm font-bold text-emerald-800 underline" href="{{ route('modules.aasms.applications.documents.file', [$application, $document]) }}">Download privately <x-ui.icon name="download" size="sm" /></a>@else<p class="mt-2 text-sm text-amber-900">This file cannot be opened or reviewed until malware scanning succeeds.</p>@endif
                                            @if($document->farmer_correction_notes)<p class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900"><strong>Farmer-visible feedback:</strong> {{ $document->farmer_correction_notes }}</p>@endif
                                            <details class="mt-3"><summary class="cursor-pointer text-xs font-bold text-forest-green">Document review history ({{ $document->events->where('event_type', 'review_decision')->count() }})</summary><ol class="mt-2 space-y-2">@foreach($document->events as $event)<li class="border-l-2 border-emerald-600 pl-3 text-xs"><strong>{{ str($event->event_type)->replace('_', ' ')->headline() }}</strong> · {{ $event->created_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }} · {{ $event->actor?->name ?? 'Automated security service' }}@if($event->note)<p class="mt-1">{{ $event->note }}</p>@endif @if($event->internal_note)<p class="mt-1 text-slate-500">Internal note: {{ $event->internal_note }}</p>@endif</li>@endforeach</ol></details>
                                            @if($canReviewDocuments && $isCurrentVersion && in_array($application->status, ['submitted', 'returned'], true) && $document->scan_status === 'clean')
                                                <form method="POST" action="{{ route('modules.aasms.applications.documents.review', [$application, $document]) }}" class="mt-3 grid gap-2 rounded-lg border border-emerald-100 bg-white p-3">@csrf @method('PATCH')
                                                    <label class="text-xs font-bold">Document decision<select class="aas-input mt-1" name="review_status" required><option value="verified">Verify document</option><option value="correction_requested">Request correction</option><option value="not_accepted">Do not accept</option></select></label>
                                                    <label class="text-xs font-bold">Feedback to farmer<textarea class="aas-input mt-1" name="farmer_correction_notes" maxlength="2000" rows="2" placeholder="Required for correction or non-acceptance"></textarea></label>
                                                    <label class="text-xs font-bold">Internal review note (staff only)<textarea class="aas-input mt-1" name="internal_review_notes" maxlength="2000" rows="2"></textarea></label>
                                                    <button class="aas-btn aas-primary justify-self-start" type="submit">Record document review</button>
                                                </form>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if(!$canViewPrivateDocuments && $documentWorkflowReady)<p class="mt-3 text-sm text-amber-900">This account can review the application but does not have permission to inspect private supporting files.</p>@endif
                @if(!$documentUploadsEnabled)<p class="mt-3 text-sm text-gray-600">New private document uploads are disabled until the MAO-approved security configuration is active.</p>@endif
            @else
                <p class="mt-3 text-sm text-gray-600">No digitized supporting-document requirements were recorded in this version.</p>
            @endif
            @else
                <p class="mt-3 text-sm text-gray-600">No versioned requirements evidence is available for this historical application.</p>
            @endif
            @if($application->eligibility_notes)<p class="mt-4 text-sm"><strong>Eligibility result:</strong> {{ $application->eligibility_notes }}</p>@endif
        </section>

        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <h2 class="text-lg font-bold text-[#153d2b]">Status history</h2>
            <ol class="mt-3 space-y-3">@forelse($application->history->sortBy('created_at') as $entry)<li class="border-l-2 border-emerald-600 pl-4"><p class="font-semibold">{{ $entry->from_status ? ucfirst($entry->from_status).' → ' : '' }}{{ ucfirst($entry->to_status) }}</p><time class="text-xs text-gray-500">{{ $entry->created_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Manila time</time>@if($entry->notes)<p class="mt-1 text-sm text-gray-600">{{ $entry->notes }}</p>@endif</li>@empty<li class="text-sm text-gray-600">No status history is recorded.</li>@endforelse</ol>
        </section>
    </div>
</x-layouts.app>
