<x-layouts.public-agricultural :title="$publication['content']['title']" description="Official agricultural announcement from Bongabong Municipal Agriculture Office.">
    @if($preview)<div class="public-preview-banner" role="status"><x-ui.icon name="warning"/>Private preview · this content is not visible to the public</div>@endif
    <a class="farmer-back-link" href="{{ $preview ? route('modules.aasms.announcements') : route('public.agricultural-services.index') }}"><x-ui.icon name="back" size="sm"/>{{ $preview ? 'Back to publication workspace' : 'Programs and notices' }}</a>
    <article class="public-article">
        @php($cover = $publication['media']->first(fn ($media) => $media->is_cover) ?? $publication['media']->first())
        @if($cover)
            <figure class="public-article-cover"><img src="{{ $preview ? route('modules.aasms.announcements.media', [$announcement, $publication['revision'], $cover]) : route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text }}"><figcaption>{{ $cover->alt_text }}</figcaption></figure>
        @endif
        <div class="public-article-heading">
            <p class="public-agri-eyebrow">{{ $publication['content']['category'] ?: 'Official agricultural announcement' }}</p>
            <h1>{{ $publication['content']['title'] }}</h1>
            <div class="public-article-meta"><span><x-ui.icon name="office" size="sm"/>{{ $publication['content']['office'] ?: 'Bongabong Municipal Agriculture Office' }}</span><time datetime="{{ $announcement->published_at?->toIso8601String() }}">{{ $announcement->published_at?->timezone('Asia/Manila')->format('F j, Y') ?: 'Draft preview' }}</time></div>
        </div>
        @if($publication['media']->count() > 1)
            <section class="public-gallery" aria-label="Announcement photographs">
                @foreach($publication['media']->reject(fn ($media) => $cover && $media->id === $cover->id) as $media)
                    <figure><img src="{{ $preview ? route('modules.aasms.announcements.media', [$announcement, $publication['revision'], $media]) : route('public.announcements.media', [$announcement, $media]) }}" alt="{{ $media->alt_text }}" loading="lazy"><figcaption>{{ $media->alt_text }}</figcaption></figure>
                @endforeach
            </section>
        @endif
        <div class="public-article-grid">
            <div class="public-article-body">{{ $publication['bodyHtml'] }}</div>
            <aside class="public-article-facts">
                @if($publication['content']['service_area'])<div><strong>Service area</strong><p>{{ $publication['content']['service_area'] }}</p></div>@endif
                @if($publication['content']['requirements_summary'])<div><strong>Requirements summary</strong><p>{{ $publication['content']['requirements_summary'] }}</p></div>@endif
                @if($publication['content']['instructions'])<div><strong>How to proceed</strong><p>{{ $publication['content']['instructions'] }}</p></div>@endif
                @if($publication['content']['contact_details'])<div><strong>Official contact</strong><p class="whitespace-pre-line">{{ $publication['content']['contact_details'] }}</p></div>@endif
                @if($publication['program'])<a class="farmer-button public-article-cta" href="{{ route('public.agricultural-programs.show', $publication['program']) }}">View linked assistance program <x-ui.icon name="next" size="sm"/></a>@endif
                @if($publication['program'])
                    <div class="public-article-program-status">
                        <strong>Application availability</strong>
                        <span class="farmer-pill {{ $programAvailability === 'available' ? '' : 'farmer-pill-warn' }}">{{ ['available' => 'Accepting applications', 'upcoming' => 'Upcoming', 'ended' => 'Applications closed', 'unpublished' => 'Not published'][$programAvailability] }}</span>
                        @if($programAvailability === 'available')
                            @if(auth()->check() && auth()->user()->hasRole('farmer'))<a class="farmer-text-link" href="{{ route('farmer.programs.show', $publication['program']) }}">Check eligibility and apply</a>
                            @else<a class="farmer-text-link" href="{{ route('login') }}">Sign in to check eligibility</a>@endif
                        @elseif($programAvailability === 'upcoming')<p>Applications are not open yet. Review the schedule and return when the opening date arrives.</p>
                        @else<p>The application period has ended. The published program information remains available.</p>@endif
                    </div>
                @endif
            </aside>
        </div>
        @if($publication['program'])<p class="public-article-schedule"><strong>Application schedule:</strong> {{ $publication['program']->opens_on ? 'Opens '.$publication['program']->opens_on->format('M j, Y') : 'No opening date specified' }} · {{ $publication['program']->closes_on ? 'Closes '.$publication['program']->closes_on->format('M j, Y') : 'No closing date specified' }}</p>@endif
    </article>
</x-layouts.public-agricultural>
