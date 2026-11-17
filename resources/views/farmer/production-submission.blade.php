@php
    $subject = data_get($submission->payload, 'crop_type') ?: data_get($submission->payload, 'animal_type') ?: data_get($submission->payload, 'fishery_type') ?: str($submission->category)->headline()->toString().' information';
    $section = match ($submission->category) { 'fishery' => 'fisheries', 'crop' => 'crops', default => 'livestock' };
    $submissionEvents = $submission->history->sortBy('created_at')->map(function ($entry) {
        $title = match (true) {
            is_null($entry->from_status) => 'Information submitted for MAO review',
            $entry->to_status === 'pending' => 'Corrected information resubmitted',
            $entry->to_status === 'returned' => 'MAO returned the information for correction',
            $entry->to_status === 'approved' => 'MAO approved the information',
            $entry->to_status === 'rejected' => 'MAO rejected the information',
            default => 'Submission status recorded',
        };

        return ['title' => $title, 'status' => $entry->to_status, 'date' => $entry->created_at, 'notes' => $entry->notes];
    })->values();
    [$guidance, $tone] = match ($submission->status) {
        'pending' => ['Your information is waiting for MAO review. Any decision and feedback will appear in this tracker.', 'info'],
        'returned' => ['The MAO requested a correction. Read the feedback and use the correction form below to resubmit.', 'warning'],
        'approved' => ['The MAO approved this submission. The reviewed information is now part of the agricultural records.', 'success'],
        'rejected' => ['The MAO did not approve this submission. Review the feedback and contact the office if you need help.', 'danger'],
        default => ['The status above reflects the latest submission record. Review the recorded history below.', 'info'],
    };
@endphp

<x-layouts.farmer>
    <x-slot name="title">Agricultural submission</x-slot>
    <x-slot name="description">Track information submitted for your farm and review MAO feedback.</x-slot>

    <x-farmer.tracking-header
        service="Agricultural production"
        :title="$subject"
        :status="$submission->status"
        :submitted-at="$submission->created_at"
        :reference="'Submission '.$submission->id"
        :back-url="route('farmer.production', ['section' => $section])"
        back-label="Back to agricultural records"
        icon="crop"
    />

    <x-farmer.next-action :message="$guidance" :tone="$tone">
        @if($submission->status === 'returned' && $farmer->isVerified())<a class="farmer-button" href="#production-correction">Correct submission</a>@endif
    </x-farmer.next-action>

    <div class="farmer-tracking-layout">
        <div class="farmer-tracking-main">
            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Submitted information</h2><p>{{ str($submission->category)->headline() }} information for {{ $submission->farm->name }}.</p></div></div>
                @if($submission->review_notes)<div class="farmer-message {{ $submission->status === 'returned' ? 'farmer-message-warning' : ($submission->status === 'rejected' ? 'farmer-message-error' : 'farmer-message-success') }}"><strong>MAO feedback:</strong> {{ $submission->review_notes }}</div>@endif
                <dl class="farmer-submission-details">
                    @foreach($submission->payload as $key => $value)
                        @if($value !== null && $value !== '')
                            <div><dt>{{ str($key)->replace('_', ' ')->headline() }}</dt><dd>{{ is_array($value) ? collect($value)->join(', ') : $value }}</dd></div>
                        @endif
                    @endforeach
                </dl>
                @if($submission->status === 'returned' && $farmer->isVerified())
                    <details id="production-correction" class="farmer-disclosure farmer-production-correction" open>
                        <summary>Correct and resubmit</summary>
                        @include('farmer.partials.production-form', ['category' => $submission->category, 'submission' => $submission])
                    </details>
                @endif
            </section>
        </div>
        <aside><x-farmer.status-timeline :events="$submissionEvents" empty-message="No production submission history has been recorded." /></aside>
    </div>
</x-layouts.farmer>
