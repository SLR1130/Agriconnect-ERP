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
                        <h4 class="mt-4 font-semibold">Document requirements at submission</h4>
                        @if(collect(data_get($ackSnapshot, 'requirements_version.documents', []))->isNotEmpty())
                            <ul>@foreach(data_get($ackSnapshot, 'requirements_version.documents', []) as $requirement)<li>{{ $requirement['name'] }} · {{ $requirement['required'] ? 'Required' : 'Optional' }}</li>@endforeach</ul>
                        @else
                            <p>No digitized document checklist was recorded for this application version.</p>
                        @endif
                        <p class="mt-2 text-sm">Online document upload was not active when this application was submitted; the acknowledgment does not claim files were received.</p>
                    @endif
                </section>

                @if($application->status === 'returned')
                    <form id="application-resubmit" class="farmer-tracking-form" method="POST" action="{{ route('farmer.assistance.resubmit', $application) }}">
                        @csrf
                        <div class="farmer-field"><label for="resubmit-purpose">Updated application purpose</label><textarea id="resubmit-purpose" name="purpose" maxlength="2000" rows="4">{{ old('purpose', $application->purpose) }}</textarea></div>
                        @error('purpose')<p class="farmer-field-error">{{ $message }}</p>@enderror
                        <button class="farmer-button" type="submit">Resubmit for MAO review</button>
                    </form>
                @endif
            </section>

            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Distributions received</h2><p>Physical distributions recorded for this application.</p></div></div>
                @forelse($application->distributions as $distribution)
                    <article class="farmer-distribution-row"><div><h3>{{ $distribution->batch->item->name }}</h3><p>{{ $distribution->quantity }} distributed {{ $distribution->distributed_at?->format('M d, Y · h:i A') }}</p></div><p>Reference {{ $distribution->reference }}</p></article>
                @empty
                    <p class="farmer-timeline-empty">No assistance supplies have been distributed for this application yet.</p>
                @endforelse
            </section>
        </div>

        <aside><x-farmer.status-timeline :events="$applicationEvents" empty-message="No application status-history events have been recorded." /></aside>
    </div>
</x-layouts.farmer>
