@php
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
        :submitted-at="$application->created_at"
        :reference="'Application '.$application->id"
        :back-url="route('farmer.assistance').'#applications'"
        back-label="Back to my applications"
        icon="assistance"
    />

    <x-farmer.next-action :message="$guidance" :tone="$tone">
        @if($application->status === 'returned')
            <a class="farmer-button" href="#application-resubmit">Correct application</a>
        @endif
    </x-farmer.next-action>

    <div class="farmer-tracking-layout">
        <div class="farmer-tracking-main">
            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Application details</h2><p>Information submitted for this assistance program.</p></div></div>
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
                        @if(data_get($application->program->eligibility_rules, 'requires_farm'))<li>Registered farm</li>@endif
                        @if(data_get($application->program->eligibility_rules, 'barangays'))<li>Eligible barangays: {{ implode(', ', data_get($application->program->eligibility_rules, 'barangays')) }}</li>@endif
                        @if(data_get($application->program->eligibility_rules, 'activities'))<li>Eligible activities: {{ implode(', ', data_get($application->program->eligibility_rules, 'activities')) }}</li>@endif
                        @if(data_get($application->program->eligibility_rules, 'farm_categories'))<li>Eligible farm categories: {{ implode(', ', data_get($application->program->eligibility_rules, 'farm_categories')) }}</li>@endif
                    </ul>
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
