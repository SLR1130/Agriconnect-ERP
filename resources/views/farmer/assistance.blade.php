<x-layouts.farmer>
    <x-slot name="title">Assistance</x-slot>
    <x-slot name="description">Discover MAO programs and keep each application, review, and distribution together.</x-slot>

    @if(!$farmer->isVerified())
        <div class="farmer-message farmer-message-warning" role="status">
            Your farmer registration is {{ $farmer->registration_status }}. You may review published program details; application forms appear after verification when you meet the program rules.
            <a class="font-bold underline" href="{{ route('farmer.farms') }}">View verification progress</a>
        </div>
    @endif

    @include('farmer.partials.assistance-catalog', [
        'catalogHeading' => 'Published assistance programs',
        'catalogDescription' => 'Search the same published program catalog shown under Explore services. Availability and eligibility are evaluated consistently.',
    ])

    <section class="farmer-section !mt-0" id="applications" aria-labelledby="my-assistance-title">
        <div class="farmer-section-heading">
            <div><h2 id="my-assistance-title">My applications</h2><p>Review the latest MAO status, notes, and any completed distribution.</p></div>
            <a class="farmer-text-link" href="{{ route('farmer.activities', ['type' => 'Assistance application']) }}">Application activity <x-ui.icon name="next" size="sm" /></a>
        </div>
        <div class="farmer-application-list">
            @forelse($applications as $application)
                <article class="farmer-card farmer-application-card" id="application-{{ $application->id }}">
                    <div class="farmer-application-main">
                        <span class="farmer-service-kicker">{{ $application->program->category ?: 'Agricultural assistance' }}</span>
                        <h3>{{ $application->program->name }}</h3>
                        <p>@if($application->application_reference)<strong>Reference {{ $application->application_reference }}</strong> · @endif{{ $application->submitted_at ? 'Submitted '.$application->submitted_at->timezone(config('agriculture.business_timezone'))->format('M d, Y') : 'Historical submission date not separately recorded' }}@if($application->distributions->isNotEmpty()) · {{ $application->distributions->count() }} {{ \Illuminate\Support\Str::plural('distribution', $application->distributions->count()) }} recorded @endif</p>
                        @if($application->review_notes)<p class="farmer-review-note"><strong>MAO feedback:</strong> {{ $application->review_notes }}</p>@endif
                        @foreach($application->distributions as $distribution)
                            <p class="farmer-distribution-line"><x-ui.icon name="success" size="sm" />{{ $distribution->batch->item->name }} · {{ $distribution->quantity }} · Ref {{ $distribution->reference }}</p>
                        @endforeach
                    </div>
                    <div class="farmer-application-actions">
                        <span class="farmer-pill {{ $application->status === 'rejected' ? 'farmer-pill-bad' : (in_array($application->status, ['returned', 'pending'], true) ? 'farmer-pill-warn' : '') }}">{{ ucfirst($application->status) }}</span>
                        <a class="farmer-button farmer-button-light" href="{{ route('farmer.applications.show', $application) }}">View status and history <x-ui.icon name="next" size="sm" /></a>
                        @if($application->acknowledgment)<a class="farmer-text-link" href="{{ route('farmer.applications.acknowledgment', $application) }}">Open acknowledgment</a>@endif
                    </div>
                </article>
            @empty
                <div class="farmer-empty">
                    <strong>No assistance applications yet.</strong>
                    <p>Use the program search below to check requirements and see whether you can apply.</p>
                </div>
            @endforelse
        </div>
    </section>

</x-layouts.farmer>
