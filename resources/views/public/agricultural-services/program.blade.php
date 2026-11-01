<x-layouts.public-agricultural :title="$program->name" description="Published agricultural assistance program and application information.">
    <a class="farmer-back-link" href="{{ route('public.agricultural-services.index', ['availability' => 'all']) }}"><x-ui.icon name="back" size="sm"/>All programs and notices</a>
    <article class="public-program-detail">
        @if($publication)
            @php($cover = $publication['media']->first(fn ($media) => $media->is_cover) ?? $publication['media']->first())
            @if($cover)<figure class="public-article-cover"><img src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text }}"><figcaption>{{ $cover->alt_text }}</figcaption></figure>@endif
        @endif
        <div class="public-program-detail-heading"><span class="public-program-category">{{ $program->category ?: 'Agricultural assistance' }}</span>
            @php($availabilityState = $program->availabilityStateOn($businessDate))
            <span class="farmer-pill {{ $availabilityState === 'available' ? '' : 'farmer-pill-warn' }}">{{ ['available' => 'Accepting applications', 'upcoming' => 'Upcoming', 'ended' => 'Application period ended', 'unpublished' => 'Not published'][$availabilityState] }}</span>
            <h1>{{ $program->name }}</h1>
            <p>{{ $program->description ?: 'The MAO has not added a program description.' }}</p>
        </div>
        <div class="public-program-detail-grid">
            <section class="public-program-detail-main"><h2>Application schedule</h2><dl class="public-schedule-facts"><div><dt>Opens</dt><dd>{{ $program->opens_on?->format('F j, Y') ?: 'No opening date specified' }}</dd></div><div><dt>Closes</dt><dd>{{ $program->closes_on?->format('F j, Y') ?: 'No closing date specified' }}</dd></div></dl>
                <p class="public-date-note">Dates use the Philippine business calendar published by the MAO. The start and end dates are included.</p>
                <h2>Published eligibility requirements</h2><ul class="public-requirement-list">@foreach($requirements as $requirement)<li><x-ui.icon name="success" size="sm"/>{{ $requirement }}</li>@endforeach</ul>
                @if($publication && $publication['content']['requirements_summary'])<div class="public-requirement-summary"><strong>Program-specific instructions</strong><p>{{ $publication['content']['requirements_summary'] }}</p></div>@endif
                @if($publication && $publication['content']['instructions'])<div class="public-requirement-summary"><strong>How to proceed</strong><p>{{ $publication['content']['instructions'] }}</p></div>@endif
                @if($publication && $publication['content']['service_area'])<p class="public-detail-office"><x-ui.icon name="location" size="sm"/>Service area: {{ $publication['content']['service_area'] }}</p>@endif
                @if($publication && $publication['content']['office'])<p class="public-detail-office"><x-ui.icon name="office" size="sm"/>Published by {{ $publication['content']['office'] }}</p>@endif
            </section>
            <aside class="public-apply-panel"><p class="public-agri-eyebrow">Next step</p><h2>Check your application access</h2>
                @if($availabilityState === 'upcoming')<p>Applications are not open yet. Review your farmer registration and return when the opening date arrives.</p>
                @elseif($availabilityState === 'ended')<p>The application period has ended. This published page remains available for information.</p>
                @elseif(auth()->check() && auth()->user()->hasRole('farmer'))<p>Sign in to the Farmer Portal to check your current records and apply through the existing assistance workflow.</p><a class="farmer-button farmer-button-gold" href="{{ route('farmer.programs.show', $program) }}">Check eligibility and apply <x-ui.icon name="next" size="sm"/></a>
                @else<p>Application eligibility is checked privately using your verified farmer and farm records.</p><a class="farmer-button farmer-button-gold" href="{{ route('login') }}">Sign in to continue <x-ui.icon name="next" size="sm"/></a><a class="farmer-text-link mt-3" href="{{ route('farmer.register') }}">Register as a farmer</a>@endif
                @if($publication && $publication['content']['contact_details'])<div class="public-apply-contact"><strong>Questions?</strong><p class="whitespace-pre-line">{{ $publication['content']['contact_details'] }}</p></div>@endif
            </aside>
        </div>
        @if($publication)<a class="farmer-text-link public-program-source" href="{{ route('public.announcements.show', $announcement) }}">Read the official program announcement <x-ui.icon name="next" size="sm"/></a>@endif
    </article>
</x-layouts.public-agricultural>
