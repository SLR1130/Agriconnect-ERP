@php
    $ackSnapshot = $application->acknowledgment?->snapshot ?? [];
    $snapshotRules = data_get($ackSnapshot, 'eligibility.rules', $application->requirementVersion?->eligibility_rules ?? $application->program->eligibility_rules ?? []);
    $snapshotSubmittedAt = $application->submitted_at?->timezone(config('agriculture.business_timezone'));
    $applicationEvents = $application->history->sortBy('created_at')->map(function ($entry) {
        $title = match (true) {
            is_null($entry->from_status) => 'Application submitted',
            $entry->to_status === 'submitted' => 'Application resubmitted',
            $entry->to_status === 'returned' => 'MAO returned the application for correction',
            $entry->to_status === 'approved' => 'MAO approved the application',
            $entry->to_status === 'rejected' => 'MAO rejected the application',
            $entry->to_status === 'completed' => 'Assistance distribution completed',
            default => 'Application status recorded',
        };

        return ['title' => $title, 'status' => $entry->to_status, 'date' => $entry->created_at, 'notes' => $entry->notes];
    })->values();
    [$guidance, $tone] = match ($application->status) {
        'submitted' => ['Your application is awaiting MAO review. We will show the decision and any feedback here.', 'info'],
        'returned' => ['The MAO requested a correction. Review the recorded feedback, update your response, and resubmit below.', 'warning'],
        'approved' => ['Your application is approved. The MAO will record a distribution after supplies are physically released.', 'success'],
        'completed' => ['The assistance distribution is recorded below with its quantity and reference.', 'success'],
        'rejected' => ['The MAO did not approve this application. Review the recorded reason and contact the office if you need help.', 'danger'],
        default => ['The status above reflects the latest application record. Review the service history for recorded updates.', 'info'],
    };
@endphp

<x-layouts.farmer>
    <x-slot name="title">Assistance application</x-slot>
    <x-slot name="description">Track your application and review decisions recorded by the MAO.</x-slot>

    <x-farmer.tracking-header
        service="Assistance and subsidies"
        :title="$application->program->name"
        :status="$application->status"
        :submitted-at="$snapshotSubmittedAt ?? $application->created_at"
        :date-label="$application->submitted_at ? 'Submitted' : 'Record created'"
        :reference="$application->application_reference ?: 'Legacy record '.$application->id"
        :back-url="route('farmer.assistance').'#applications'"
        back-label="Back to my applications"
        icon="assistance"
    />

    <x-farmer.next-action :message="$guidance" :tone="$tone">
        @if($application->status === 'returned')
            <a class="farmer-button" href="#application-resubmit">Correct application</a>
        @endif
    </x-farmer.next-action>

    @if(session('status'))<div class="farmer-message farmer-message-success" role="status">{{ session('status') }}</div>@endif

    <div class="farmer-tracking-layout">
        <div class="farmer-tracking-main">
            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Application details</h2><p>Information submitted for this assistance program.</p></div></div>
                @if($application->acknowledgment)
                    <div class="farmer-application-ack-actions" aria-label="Application acknowledgment actions">
                        <a class="farmer-button farmer-button-light" href="{{ route('farmer.applications.acknowledgment', $application) }}" target="_blank" rel="noopener">View / print acknowledgment</a>
                        <a class="farmer-button" href="{{ route('farmer.applications.acknowledgment', ['application' => $application, 'download' => 1]) }}">Download acknowledgment</a>
                    </div>
                @else
                    <p class="farmer-message farmer-message-warning">This legacy application predates acknowledgments. Its original reference and submission evidence were not recorded, so no receipt is being reconstructed.</p>
                @endif
                @if($application->submission_channel === 'mao_staff_assisted')
                    <div class="farmer-message farmer-message-info mt-4"><strong>MAO-assisted submission.</strong> This application was entered by {{ data_get($ackSnapshot, 'intake.actor_name', 'an authorized MAO staff member') }} on your FFRMS record. The acknowledgment identifies the staff intake actor; the MAO review decision is recorded separately below.</div>
                @else
                    <p class="mt-3 text-xs text-slate-500">Submission channel: Farmer Portal</p>
                @endif
                @if($application->purpose)<p class="farmer-tracking-purpose">{{ $application->purpose }}</p>@else<p class="text-sm text-slate-600">No additional purpose was provided.</p>@endif
                @if($application->review_notes)<div class="farmer-message {{ $application->status === 'rejected' ? 'farmer-message-error' : 'farmer-message-warning' }} mt-4"><strong>MAO feedback:</strong> {{ $application->review_notes }}</div>@endif
                @if($application->approved_quantity)<p class="mt-4 text-sm"><strong>Approved allocation:</strong> {{ $application->approved_quantity }} {{ $application->approvedSupplyItem?->name }}</p>@endif

                <section class="farmer-application-program-summary mt-5" aria-labelledby="application-program-details">
                    <h3 id="application-program-details">Program details and eligibility</h3>
                    <p>{{ $application->program->category ?: 'Agricultural assistance' }}</p>
                    <dl><div><dt>Opens</dt><dd>{{ $application->program->opens_on?->format('M d, Y') ?: 'No start date' }}</dd></div><div><dt>Closes</dt><dd>{{ $application->program->closes_on?->format('M d, Y') ?: 'No closing date' }}</dd></div></dl>
                    <p class="farmer-application-eligibility"><strong>Eligibility at submission:</strong> {{ is_null($application->eligibility_passed) ? 'Not recorded' : ($application->eligibility_passed ? 'Checks passed' : 'MAO review required') }}</p>
                    @if($application->eligibility_notes)<p class="farmer-application-eligibility-note">{{ $application->eligibility_notes }}</p>@endif
                    <ul>
                        <li>Verified farmer registration</li>
                        @if(data_get($snapshotRules, 'requires_farm'))<li>Registered farm</li>@endif
                        @if(data_get($snapshotRules, 'barangays'))<li>Eligible barangays: {{ implode(', ', data_get($snapshotRules, 'barangays')) }}</li>@endif
                        @if(data_get($snapshotRules, 'activities'))<li>Eligible activities: {{ implode(', ', data_get($snapshotRules, 'activities')) }}</li>@endif
                        @if(data_get($snapshotRules, 'farm_categories'))<li>Eligible farm categories: {{ implode(', ', data_get($snapshotRules, 'farm_categories')) }}</li>@endif
                    </ul>
                    @if($application->requirementVersion)
                        <p class="mt-3 text-xs text-slate-500">Requirements version {{ $application->requirementVersion->version_number }}@if($application->requirementVersion->source === 'legacy_snapshot') · legacy baseline; original publisher and publication date are unknown @endif</p>
                        <h4 class="mt-4 font-semibold">Supporting documents for this application</h4>
                        @if($documentUploadsEnabled && !$scannerAvailable)<p class="farmer-message farmer-message-warning mt-3" role="status">Files can only be placed in private quarantine while malware scanning is unavailable. Uploaded files cannot be opened, reviewed, or marked complete. Please wait until the MAO confirms scanning is active.</p>@endif
                        @if($application->requirementVersion->documentRequirements->isNotEmpty())
                            <div class="mt-3 space-y-3">
                                @foreach($application->requirementVersion->documentRequirements as $requirement)
                                    @php($uploadedDocument = $latestDocuments->get($requirement->id))
                                    <article class="rounded-xl border border-slate-200 bg-white p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <h5 class="font-semibold">{{ $requirement->display_name }} <span class="text-xs font-normal text-slate-500">{{ $requirement->is_required ? 'Required' : 'Optional' }}</span></h5>
                                                @if($requirement->description)<p class="mt-1 text-sm text-slate-600">{{ $requirement->description }}</p>@endif
                                                @if($requirement->instructions)<p class="mt-1 text-sm text-slate-600">{{ $requirement->instructions }}</p>@endif
                                                <p class="mt-2 text-xs text-slate-500">Accepted: {{ collect($requirement->accepted_mime_types)->map(fn ($mime) => match ($mime) { 'application/pdf' => 'PDF', 'image/jpeg' => 'JPEG', 'image/png' => 'PNG', default => $mime })->join(', ') }} · Maximum {{ number_format(min($requirement->max_size_bytes, $maximumDocumentUploadBytes) / 1048576, 1) }} MiB for this upload</p>
                                            </div>
                                            @if($uploadedDocument)
                                                <span class="farmer-pill {{ $uploadedDocument->scan_status === 'clean' ? 'farmer-pill-warn' : 'farmer-pill-bad' }}">{{ match($uploadedDocument->scan_status) { 'clean' => 'Security scan passed', 'infected' => 'Rejected by security scan', 'unavailable' => 'Quarantined · scanner unavailable', 'failed' => 'Quarantined · scan failed', default => 'Quarantined · scan pending' } }}</span>
                                            @else
                                                <span class="farmer-pill">Not uploaded</span>
                                            @endif
                                        </div>
                                        @if($uploadedDocument)
                                            <div class="mt-3 border-t border-slate-100 pt-3 text-sm">
                                                <p><strong>File:</strong> {{ $uploadedDocument->original_filename }} · version {{ $uploadedDocument->version_number }} · {{ number_format($uploadedDocument->file_size / 1024, 0) }} KB</p>
                                                <p class="mt-1"><strong>MAO review:</strong> {{ str($uploadedDocument->review_status)->replace('_', ' ')->headline() }}</p>
                                                @if($uploadedDocument->farmer_correction_notes)<p class="farmer-message farmer-message-warning mt-2"><strong>Correction requested:</strong> {{ $uploadedDocument->farmer_correction_notes }}</p>@endif
                                                @foreach($uploadedDocument->events->where('visibility', 'farmer') as $documentEvent)
                                                    <p class="mt-2 text-xs text-slate-500">{{ $documentEvent->created_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }} · {{ $documentEvent->note }}</p>
                                                @endforeach
                                                @if($uploadedDocument->scan_status === 'clean')<a class="farmer-text-link mt-2 inline-flex" href="{{ route('farmer.applications.documents.file', [$application, $uploadedDocument]) }}">Download private file <x-ui.icon name="download" size="sm" /></a>@endif
                                            </div>
                                        @endif
                                        @if($documentUploadsEnabled && in_array($application->status, ['submitted', 'returned'], true))
                                            <form class="mt-3 border-t border-slate-100 pt-3" method="POST" enctype="multipart/form-data" action="{{ route('farmer.applications.documents.upload', [$application, $requirement]) }}" data-private-document-upload>
                                                @csrf
                                                <label class="farmer-field"><span>{{ $uploadedDocument ? 'Upload a replacement file' : 'Choose a file' }}</span><input type="file" name="document" accept="{{ collect($requirement->accepted_mime_types)->map(fn ($mime) => match ($mime) { 'application/pdf' => '.pdf', 'image/jpeg' => '.jpg,.jpeg', 'image/png' => '.png', default => '' })->filter()->join(',') }}" required></label>
                                                @error('document')<p class="farmer-field-error">{{ $message }}</p>@enderror
                                                <div class="mt-3 flex items-center gap-3 text-sm text-slate-600" data-document-upload-progress hidden aria-live="polite">
                                                    <progress max="100" aria-label="Document upload in progress" class="w-24 accent-emerald-700"></progress>
                                                    <span>Uploading to private quarantine. Keep this page open until the confirmation appears.</span>
                                                </div>
                                                <button class="farmer-button mt-3" type="submit">{{ $uploadedDocument ? 'Submit replacement securely' : 'Upload securely' }}</button>
                                            </form>
                                        @elseif(!$documentUploadsEnabled)
                                            <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-amber-800">Online uploads are not active for this system. Do not attach sensitive records here; follow the MAO instructions.</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p>No digital document checklist was published for this application version.</p>
                        @endif
                        <p class="mt-3 text-xs text-slate-500">The original acknowledgment records the initial submission. Later files and corrections are recorded separately.</p>
                    @endif
                </section>

                @if($application->status === 'approved' || $claimTickets->isNotEmpty())
                    <section class="farmer-card" aria-labelledby="claim-ticket-heading">
                        <div class="farmer-section-heading">
                            <div><h2 id="claim-ticket-heading">Assistance claim ticket</h2><p>A ticket records your approved allocation. It is not proof that supplies were released.</p></div>
                            <x-ui.icon name="ticket" size="lg" />
                        </div>
                        <div class="farmer-message farmer-message-warning mt-4" role="note">Claim tickets in this QA phase cannot be redeemed. They do not reserve supplies or guarantee stock availability. AISMS availability is checked separately at an authorized physical handover.</div>
                        @forelse($claimTickets as $claimTicket)
                            <article class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket reference</p>
                                    <h3 class="mt-1 text-lg font-bold text-forest-green">{{ $claimTicket->ticket_reference }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">{{ str($claimTicket->effectiveStatus())->headline() }} · issued {{ $claimTicket->issued_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2"><a class="farmer-button" href="{{ route('farmer.claim-tickets.show', $claimTicket) }}">View ticket and history <x-ui.icon name="next" size="sm" /></a>@if($claimTicket->handoverAcknowledgment)<a class="farmer-button farmer-button-light" href="{{ route('farmer.handovers.acknowledgment', $claimTicket->handoverAcknowledgment) }}">Distribution acknowledgment</a>@endif</div>
                            </article>
                        @empty
                            <p class="farmer-timeline-empty mt-4">The MAO has not issued a ticket for this approved allocation yet. Your application remains approved; no supply release is recorded here.</p>
                        @endforelse
                    </section>
                @endif

                @if($application->status === 'returned')
                    @if($outstandingRequiredDocuments === [])
                    <form id="application-resubmit" class="farmer-tracking-form" method="POST" action="{{ route('farmer.assistance.resubmit', $application) }}">
                        @csrf
                        <div class="farmer-field"><label for="resubmit-purpose">Updated application purpose</label><textarea id="resubmit-purpose" name="purpose" maxlength="2000" rows="4">{{ old('purpose', $application->purpose) }}</textarea></div>
                        @error('purpose')<p class="farmer-field-error">{{ $message }}</p>@enderror
                        <button class="farmer-button" type="submit">Resubmit for MAO review</button>
                    </form>
                    @else
                        <div class="farmer-message farmer-message-warning" role="status"><strong>Resubmission is paused.</strong> Each required document must pass security scanning and receive MAO verification first. Pending: {{ implode(', ', $outstandingRequiredDocuments) }}.</div>
                    @endif
                @endif
            </section>

            @if($application->relationLoaded('submissionSnapshots') && $application->submissionSnapshots->where('submission_type', 'resubmission')->isNotEmpty())
                <section class="farmer-card">
                    <div class="farmer-section-heading"><div><h2>Resubmission history</h2><p>Later corrections are recorded separately from your original acknowledgment.</p></div></div>
                    <ol class="space-y-3">@foreach($application->submissionSnapshots->where('submission_type', 'resubmission') as $snapshot)<li class="border-l-2 border-emerald-600 pl-4"><strong>Attempt {{ $snapshot->attempt_number }} submitted</strong><time class="ml-2 text-xs text-slate-500">{{ $snapshot->submitted_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }}</time><ul class="mt-1 text-sm">@foreach(data_get($snapshot->snapshot, 'documents', []) as $file)<li>{{ $file['requirement_name'] }} · version {{ $file['document_version'] }} · {{ str($file['review_status'])->replace('_', ' ')->headline() }}</li>@endforeach</ul></li>@endforeach</ol>
                </section>
            @endif

            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Distributions received</h2><p>Physical distributions recorded for this application.</p></div></div>
                @forelse($application->distributions as $distribution)
                    <article class="farmer-distribution-row"><div><h3>{{ $distribution->batch->item->name }}</h3><p>{{ $distribution->quantity }} distributed {{ $distribution->distributed_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }}</p></div><div><p>Reference {{ $distribution->reference }}</p>@if($distribution->acknowledgment)<a class="farmer-text-link mt-2 inline-flex" href="{{ route('farmer.handovers.acknowledgment', $distribution->acknowledgment) }}">View handover acknowledgment</a>@endif</div></article>
                @empty
                    <p class="farmer-timeline-empty">No assistance supplies have been distributed for this application yet.</p>
                @endforelse
            </section>
        </div>

        <aside><x-farmer.status-timeline :events="$applicationEvents" empty-message="No application status-history events have been recorded." /></aside>
    </div>
</x-layouts.farmer>
