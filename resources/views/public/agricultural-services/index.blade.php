<x-layouts.public-agricultural title="Agricultural programs and announcements" description="Browse official agricultural programs, assistance opportunities, and service notices from Bongabong Municipal Agriculture Office.">
    <section class="public-agri-intro">
        <p class="public-agri-eyebrow"><x-ui.icon name="office" size="sm" />Official municipal agriculture services</p>
        <h1>Programs and updates for local farmers</h1>
        <p>Explore published assistance programs and official notices. Application availability follows the dates published by the MAO.</p>
    </section>

    <form class="public-agri-filters" method="GET" action="{{ route('public.agricultural-services.index') }}" aria-label="Search agricultural services">
        <div class="public-agri-filter-search"><label for="public-search">Search programs and notices</label><div><x-ui.icon name="search"/><input id="public-search" name="search" value="{{ $search }}" placeholder="Search by program or topic"></div></div>
        <div><label for="public-category">Category</label><select id="public-category" name="category"><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>@endforeach</select></div>
        <div><label for="public-availability">Application period</label><select id="public-availability" name="availability"><option value="all" @selected($availability === 'all')>All published programs</option><option value="available" @selected($availability === 'available')>Accepting applications</option><option value="upcoming" @selected($availability === 'upcoming')>Upcoming</option><option value="ended" @selected($availability === 'ended')>Application period ended</option></select></div>
        <button class="farmer-button" type="submit">Apply filters</button>
        @if($search !== '' || $category !== '' || $availability !== 'all')<a class="public-agri-clear" href="{{ route('public.agricultural-services.index') }}">Clear filters</a>@endif
    </form>

    <section class="public-agri-section" aria-labelledby="public-programs-heading">
        <div class="public-agri-section-heading"><div><p class="public-agri-eyebrow">AASMS programs</p><h2 id="public-programs-heading">Agricultural assistance programs</h2><p>Published opportunities and their application schedules.</p></div><span class="farmer-pill">{{ $programs->total() }} published</span></div>
        @if($programs->count())
            <div class="public-program-grid">
                @foreach($programs as $program)
                    @php($notice = $program->announcements->first())
                    @php($cover = $notice?->publishedRevision?->media?->first(fn ($media) => $media->is_cover) ?? $notice?->publishedRevision?->media?->first())
                    @php($state = $program->availabilityStateOn($businessDate))
                    <article class="public-program-card">
                        @if($cover)
                            <a class="public-program-image" href="{{ route('public.agricultural-programs.show', $program) }}"><img src="{{ route('public.announcements.media', [$notice, $cover]) }}" alt="{{ $cover->alt_text }}" loading="lazy"></a>
                        @else
                            <div class="public-program-image public-program-image-empty" aria-hidden="true"><x-ui.icon name="crop" size="xl"/><span>Official program information</span></div>
                        @endif
                        <div class="public-program-card-content">
                            <div class="public-program-meta"><span>{{ $program->category ?: 'Agricultural assistance' }}</span><span class="farmer-pill {{ $state === 'available' ? '' : 'farmer-pill-warn' }}">{{ ['available' => 'Accepting applications', 'upcoming' => 'Upcoming', 'ended' => 'Period ended', 'unpublished' => 'Not published'][$state] }}</span></div>
                            <h3><a href="{{ route('public.agricultural-programs.show', $program) }}">{{ $program->name }}</a></h3>
                            <p>{{ \Illuminate\Support\Str::limit($program->description ?: 'Open the program page for its published details and requirements.', 170) }}</p>
                            <div class="public-program-date"><x-ui.icon name="activity" size="sm"/><span>{{ $program->opens_on ? $program->opens_on->format('M j, Y') : 'No opening date specified' }} <span aria-hidden="true">·</span> {{ $program->closes_on ? 'Closes '.$program->closes_on->format('M j, Y') : 'No closing date announced' }}</span></div>
                            <a class="farmer-button farmer-button-light public-program-action" href="{{ route('public.agricultural-programs.show', $program) }}">Program details <x-ui.icon name="next" size="sm"/></a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($programs->hasPages())<div class="public-agri-pagination">{{ $programs->links() }}</div>@endif
        @else
            <div class="farmer-empty public-agri-empty"><span class="ui-service-icon"><x-ui.icon name="search" size="lg"/></span><strong>No published programs match these filters.</strong><p>Try another category or application period. Only official published AASMS programs appear here.</p><a class="farmer-button farmer-button-light" href="{{ route('public.agricultural-services.index') }}">View all programs and notices</a></div>
        @endif
    </section>

    <section class="public-agri-section" aria-labelledby="public-notices-heading">
        <div class="public-agri-section-heading"><div><p class="public-agri-eyebrow">Official publications</p><h2 id="public-notices-heading">Announcements and service advisories</h2><p>Notices published by authorized MAO personnel.</p></div></div>
        @if($announcements->count())
            <div class="public-notice-grid">
                @foreach($announcements as $announcement)
                    @php($content = $announcement->publicContent())
                    @php($cover = $announcement->publishedRevision?->media?->first(fn ($media) => $media->is_cover) ?? $announcement->publishedRevision?->media?->first())
                    <article class="public-notice-card">
                        @if($cover)<a class="public-notice-image" href="{{ route('public.announcements.show', $announcement) }}"><img src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text }}" loading="lazy"></a>@endif
                        <div class="public-notice-copy"><div class="public-program-meta"><span>{{ $content['category'] ?: 'Official MAO notice' }}</span><time datetime="{{ $announcement->published_at?->toIso8601String() }}">{{ $announcement->published_at?->timezone('Asia/Manila')->format('M j, Y') }}</time></div>
                            <h3><a href="{{ route('public.announcements.show', $announcement) }}">{{ $content['title'] }}</a></h3>
                            @if($content['office'])<p class="public-notice-office"><x-ui.icon name="office" size="sm"/>{{ $content['office'] }}</p>@endif
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) \Illuminate\Support\Str::markdown($content['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false])), 190) }}</p>
                            <a class="farmer-text-link" href="{{ route('public.announcements.show', $announcement) }}">Read official notice <x-ui.icon name="next" size="sm"/></a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($announcements->hasPages())<div class="public-agri-pagination">{{ $announcements->links() }}</div>@endif
        @else
            <div class="farmer-empty public-agri-empty"><span class="ui-service-icon"><x-ui.icon name="office" size="lg"/></span><strong>No official announcements are currently published.</strong><p>The MAO will post service advisories and program updates here when they are ready.</p></div>
        @endif
    </section>
</x-layouts.public-agricultural>
