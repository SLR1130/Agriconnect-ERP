<x-layouts.public-agricultural title="Agricultural programs and announcements" description="Browse official agricultural programs, assistance opportunities, and service notices from Bongabong Municipal Agriculture Office.">
    <section class="public-agri-intro" aria-labelledby="public-feed-title">
        <p class="public-agri-eyebrow"><x-ui.icon name="office" size="sm" />Official municipal agriculture services</p>
        <h1 id="public-feed-title">Agricultural programs and updates</h1>
        <p>Find current assistance, official announcements, and service advisories from the Bongabong Municipal Agriculture Office.</p>
    </section>

    <form class="public-agri-filters" method="GET" action="{{ route('public.agricultural-services.index') }}" aria-label="Search agricultural programs and notices">
        <div class="public-agri-filter-search">
            <label for="public-search">Search programs and notices</label>
            <div><x-ui.icon name="search"/><input id="public-search" name="search" value="{{ $search }}" placeholder="Search by program or topic" autocomplete="off"></div>
        </div>
        <div>
            <label for="public-category">Category</label>
            <select id="public-category" name="category"><option value="">All categories</option>@foreach($categories as $option)<option value="{{ $option }}" @selected($category === $option)>{{ $option }}</option>@endforeach</select>
        </div>
        <div>
            <label for="public-availability">Program application period</label>
            <select id="public-availability" name="availability">
                <option value="all" @selected($availability === 'all')>All published programs</option>
                <option value="available" @selected($availability === 'available')>Accepting applications</option>
                <option value="upcoming" @selected($availability === 'upcoming')>Upcoming</option>
                <option value="ended" @selected($availability === 'ended')>Closed to applications / ended</option>
            </select>
        </div>
        <button class="farmer-button" type="submit">Apply filters</button>
        @if($search !== '' || $category !== '' || $availability !== 'all')<a class="public-agri-clear" href="{{ route('public.agricultural-services.index') }}">Clear filters</a>@endif
    </form>
    <p class="public-agri-filter-help">Search and category filters apply to programs and notices. The application-period filter narrows programs; published notices remain listed.</p>

    <section class="public-agri-section public-feed-section" aria-labelledby="public-feed-heading">
        <div class="public-agri-section-heading">
            <div><p class="public-agri-eyebrow">Official publications and AASMS</p><h2 id="public-feed-heading">Programs and notices</h2><p>Browse published opportunities, announcements, and agricultural service advisories.</p></div>
            <span class="farmer-pill">{{ $programs->total() + $announcements->total() }} public items</span>
        </div>

        @if($feedItems->isNotEmpty())
            <div class="public-feed-grid">
                @foreach($feedItems as $feedItem)
                    @if($feedItem['type'] === 'program')
                        @php($program = $feedItem['record'])
                        @php($notice = $program->announcements->first())
                        @php($cover = $notice?->publishedRevision?->media?->first(fn ($media) => $media->is_cover) ?? $notice?->publishedRevision?->media?->first())
                        @php($state = $program->availabilityStateOn($businessDate))
                        <article class="public-feed-card">
                            @if($cover)
                                <a class="public-feed-card-media" href="{{ route('public.agricultural-programs.show', $program) }}"><img src="{{ route('public.announcements.media', [$notice, $cover]) }}" alt="{{ $cover->alt_text ?: 'Official photograph for '.$program->name }}" loading="lazy"></a>
                            @else
                                <div class="public-feed-card-fallback" aria-hidden="true"><x-ui.icon name="crop" size="xl"/><span>{{ $program->name }}</span><small>Official photograph not provided</small></div>
                            @endif
                            <div class="public-feed-card-content">
                                <div class="public-feed-card-meta">
                                    <span class="public-feed-type"><x-ui.icon name="crop" size="sm"/>Agricultural assistance program</span>
                                    @php($publicationDate = $notice?->published_at ?? $program->created_at)
                                    @if($publicationDate)<time class="public-feed-published-date" datetime="{{ $publicationDate->toIso8601String() }}">Published {{ $publicationDate->timezone('Asia/Manila')->format('M j, Y') }}</time>@endif
                                    <span class="farmer-pill {{ $state === 'available' ? '' : 'farmer-pill-warn' }}">{{ ['available' => 'Accepting applications', 'upcoming' => 'Upcoming', 'ended' => 'Applications closed', 'unpublished' => 'Not published'][$state] }}</span>
                                </div>
                                <p class="public-feed-category">{{ $program->category ?: 'Agricultural assistance' }}</p>
                                <h3><a href="{{ route('public.agricultural-programs.show', $program) }}">{{ $program->name }}</a></h3>
                                <p class="public-feed-excerpt">{{ \Illuminate\Support\Str::limit($program->description ?: 'Open the program page for published details and eligibility requirements.', 180) }}</p>
                                @if($program->supplyItem)<p class="public-feed-benefit"><strong>Assistance item</strong><span>{{ $program->supplyItem->name }}</span></p>@endif
                                <p class="public-feed-schedule"><x-ui.icon name="calendar" size="sm"/><span>{{ $program->opens_on ? 'Opens '.$program->opens_on->format('M j, Y') : 'No opening date specified' }} &middot; {{ $program->closes_on ? 'Closes '.$program->closes_on->format('M j, Y') : 'No closing date announced' }}</span></p>
                                @if($notice && $notice->publicContent()['office'])<p class="public-feed-office"><x-ui.icon name="office" size="sm"/>{{ $notice->publicContent()['office'] }}</p>@endif
                                <div class="public-feed-actions">
                                    @if($state === 'available')
                                        @if(auth()->check() && auth()->user()->hasRole('farmer'))<a class="farmer-button farmer-button-gold" href="{{ route('farmer.programs.show', $program) }}">Check eligibility <x-ui.icon name="next" size="sm"/></a>
                                        @else<a class="farmer-button farmer-button-gold" href="{{ route('login') }}">Sign in to check eligibility <x-ui.icon name="next" size="sm"/></a>@endif
                                        <a class="farmer-text-link" href="{{ route('public.agricultural-programs.show', $program) }}">Program details</a>
                                    @else
                                        <a class="farmer-button farmer-button-light" href="{{ route('public.agricultural-programs.show', $program) }}">View program details <x-ui.icon name="next" size="sm"/></a>
                                    @endif
                                    @if($notice)<a class="farmer-text-link" href="{{ route('public.announcements.show', $notice) }}">Read linked notice</a>@endif
                                </div>
                            </div>
                        </article>
                    @else
                        @php($announcement = $feedItem['record'])
                        @php($content = $announcement->publicContent())
                        @php($cover = $announcement->publishedRevision?->media?->first(fn ($media) => $media->is_cover) ?? $announcement->publishedRevision?->media?->first())
                        @php($isAdvisory = \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower((string) ($content['category'] ?? '')), 'advisory'))
                        @php($linkedProgram = $announcement->program)
                        @php($programState = $linkedProgram?->availabilityStateOn($businessDate))
                        <article class="public-feed-card">
                            @if($cover)
                                <a class="public-feed-card-media" href="{{ route('public.announcements.show', $announcement) }}"><img src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text ?: 'Official photograph for '.$content['title'] }}" loading="lazy"></a>
                            @else
                                <div class="public-feed-card-fallback public-feed-card-fallback-notice" aria-hidden="true"><x-ui.icon name="office" size="xl"/><span>{{ $content['category'] ?: 'Agricultural service update' }}</span><small>Official photograph not provided</small></div>
                            @endif
                            <div class="public-feed-card-content">
                                <div class="public-feed-card-meta"><span class="public-feed-type"><x-ui.icon name="office" size="sm"/>{{ $isAdvisory ? 'Service advisory' : 'Official announcement' }}</span><time datetime="{{ $announcement->published_at?->toIso8601String() }}">{{ $announcement->published_at?->timezone('Asia/Manila')->format('M j, Y') }}</time></div>
                                <p class="public-feed-category">{{ $content['category'] ?: 'Municipal agriculture update' }}</p>
                                <h3><a href="{{ route('public.announcements.show', $announcement) }}">{{ $content['title'] }}</a></h3>
                                <p class="public-feed-office"><x-ui.icon name="office" size="sm"/>{{ $content['office'] ?: 'Bongabong Municipal Agriculture Office' }}</p>
                                <p class="public-feed-excerpt">{{ \Illuminate\Support\Str::limit(strip_tags((string) \Illuminate\Support\Str::markdown($content['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false])), 190) }}</p>
                                @if($linkedProgram)
                                    <div class="public-feed-linked-program"><strong>Related program</strong><a href="{{ route('public.agricultural-programs.show', $linkedProgram) }}">{{ $linkedProgram->name }}</a><span class="farmer-pill {{ $programState === 'available' ? '' : 'farmer-pill-warn' }}">{{ ['available' => 'Accepting applications', 'upcoming' => 'Upcoming', 'ended' => 'Applications closed', 'unpublished' => 'Not published'][$programState] }}</span></div>
                                @endif
                                <div class="public-feed-actions"><a class="farmer-button farmer-button-light" href="{{ route('public.announcements.show', $announcement) }}">Read official notice <x-ui.icon name="next" size="sm"/></a>@if($linkedProgram)<a class="farmer-text-link" href="{{ route('public.agricultural-programs.show', $linkedProgram) }}">Program requirements</a>@endif</div>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>

            @if($programs->hasPages() || $announcements->hasPages())
                <nav class="public-feed-pagination" aria-label="More public programs and notices">
                    @if($programs->hasPages())<div><strong>More assistance programs</strong>{{ $programs->links() }}</div>@endif
                    @if($announcements->hasPages())<div><strong>More announcements and advisories</strong>{{ $announcements->links() }}</div>@endif
                </nav>
            @endif
        @else
            <div class="farmer-empty public-agri-empty"><span class="ui-service-icon"><x-ui.icon name="search" size="lg"/></span><strong>No published programs or notices match these filters.</strong><p>Try another category or application period. Only official published AASMS programs and announcements appear here.</p><a class="farmer-button farmer-button-light" href="{{ route('public.agricultural-services.index') }}">View all programs and notices</a></div>
        @endif
    </section>
</x-layouts.public-agricultural>
