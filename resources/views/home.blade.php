<x-layouts.public-agricultural
    title="Agricultural services for Bongabong"
    description="Explore official agricultural programs, announcements, and farmer services from the Bongabong Municipal Agriculture Office."
>
    <section class="public-home-hero" aria-labelledby="public-home-title">
        <div class="public-home-hero-copy">
            <p class="public-home-kicker"><span class="public-home-kicker-mark"><x-ui.icon name="crop" size="sm" /></span> Bongabong Municipal Agriculture Office</p>
            <h1 id="public-home-title" lang="fil">Serbisyong pang-agrikultura, mas malinaw at mas madaling maabot.</h1>
            <p class="public-home-lede">Empowering Agriculture Through Connected Digital Services. AgriConnect brings farmer registration, agricultural programs, and official updates together in one accessible place.</p>
            <div class="public-home-actions">
                <a class="public-button public-button-primary" href="{{ route('public.agricultural-services.index') }}">Explore programs and services <x-ui.icon name="next" size="sm" /></a>
                <a class="public-button public-button-light" href="{{ route('farmer.register') }}">Create a farmer account</a>
            </div>
            <p class="public-home-signin">Already have an account? <a href="{{ route('login') }}">Sign in to AgriConnect</a></p>
        </div>
        <figure class="public-home-hero-photo">
            <img src="{{ asset('images/philippine-rice-field-farmer-960.jpg') }}" srcset="{{ asset('images/philippine-rice-field-farmer-960.jpg') }} 960w, {{ asset('images/philippine-rice-field-farmer.jpg') }} 1280w" sizes="(max-width: 800px) calc(100vw - 2rem), 46vw" width="1280" height="853" alt="A farmer standing among green rice plants in Bohol, Philippines. This is an illustrative photograph, not an MAO event photo." fetchpriority="high">
            <figcaption>Illustrative photo from Bohol, Philippines. <a href="https://commons.wikimedia.org/wiki/File:A_farmer_woman_in_the_middle_of_her_rice_fields_in_Bohol_-_Philippines.jpg" target="_blank" rel="noopener noreferrer">Photo by &Oslash;yvind Holmstad</a>, Wikimedia Commons, CC BY-SA 4.0.</figcaption>
            <span class="public-home-photo-label"><x-ui.icon name="location" size="xs" /> Philippine agriculture</span>
        </figure>
    </section>

    <section class="public-home-about" aria-labelledby="about-title">
        <div class="public-home-about-mark"><x-ui.icon name="office" size="lg" /></div>
        <div><p class="public-agri-eyebrow">About AgriConnect</p><h2 id="about-title">One connected place for Bongabong agricultural services</h2><p>AgriConnect is the digital service portal of the Bongabong Municipal Agriculture Office. It helps farmers find official information, submit service requests, and follow updates through their Farmer Portal account.</p></div>
        <p class="public-home-about-note">Program eligibility, farmer verification, and approval decisions remain subject to MAO review and the published requirements.</p>
    </section>

    <section class="public-home-service-section" id="services" aria-labelledby="services-title">
        <div class="public-home-section-heading"><div><p class="public-agri-eyebrow">Agricultural Services</p><h2 id="services-title">Start with the service you need</h2></div><a class="public-home-section-link" href="{{ route('public.agricultural-services.index') }}">Browse programs and notices <x-ui.icon name="next" size="sm" /></a></div>
        <div class="public-home-service-grid">
            <a class="public-home-service-card" href="{{ route('public.agricultural-services.index') }}"><span class="public-home-service-icon"><x-ui.icon name="assistance" /></span><span class="public-home-service-copy"><strong>Assistance and subsidies</strong><small>Explore published programs, eligibility, and application schedules.</small><span class="public-home-card-action">View programs <x-ui.icon name="next" size="xs" /></span></span></a>
            <a class="public-home-service-card" href="{{ route('farmer.register') }}"><span class="public-home-service-icon"><x-ui.icon name="users" /></span><span class="public-home-service-copy"><strong>Farmer registration</strong><small>Create an account and submit your details for MAO verification.</small><span class="public-home-card-action">Register <x-ui.icon name="next" size="xs" /></span></span></a>
            <a class="public-home-service-card" href="{{ auth()->check() && auth()->user()->hasRole('farmer') ? route('farmer.production') : route('login') }}"><span class="public-home-service-icon"><x-ui.icon name="crop" /></span><span class="public-home-service-copy"><strong>Production services</strong><small>Submit crop, livestock, fisheries, or farm updates through your account.</small><span class="public-home-card-action">{{ auth()->check() && auth()->user()->hasRole('farmer') ? 'Open production services' : 'Sign in to continue' }} <x-ui.icon name="next" size="xs" /></span></span></a>
            <a class="public-home-service-card" href="{{ route('public.agricultural-services.index') }}"><span class="public-home-service-icon"><x-ui.icon name="reports" /></span><span class="public-home-service-copy"><strong>Official announcements</strong><small>Read current agricultural advisories and municipal program updates.</small><span class="public-home-card-action">Read notices <x-ui.icon name="next" size="xs" /></span></span></a>
            <a class="public-home-service-card" href="{{ auth()->check() && auth()->user()->hasRole('farmer') ? route('farmer.equipment') : route('login') }}"><span class="public-home-service-icon"><x-ui.icon name="equipment" /></span><span class="public-home-service-copy"><strong>Equipment services</strong><small>Explore available equipment and manage your own requests.</small><span class="public-home-card-action">{{ auth()->check() && auth()->user()->hasRole('farmer') ? 'View equipment' : 'Sign in to continue' }} <x-ui.icon name="next" size="xs" /></span></span></a>
            <a class="public-home-service-card" href="{{ auth()->check() && auth()->user()->hasRole('farmer') ? route('farmer.farms') : route('login') }}"><span class="public-home-service-icon"><x-ui.icon name="farms" /></span><span class="public-home-service-copy"><strong>Farm records and activities</strong><small>Access your farms, application progress, and submitted service history.</small><span class="public-home-card-action">{{ auth()->check() && auth()->user()->hasRole('farmer') ? 'View my farms' : 'Sign in to continue' }} <x-ui.icon name="next" size="xs" /></span></span></a>
        </div>
    </section>

    <section class="public-home-program-section" aria-labelledby="programs-title">
        <div class="public-home-section-heading"><div><p class="public-agri-eyebrow">Official opportunities</p><h2 id="programs-title">Agricultural programs</h2><p>Published programs from the Municipal Agriculture Office. Availability follows each program's stated schedule.</p></div><a class="public-home-section-link" href="{{ route('public.agricultural-services.index') }}">View all programs <x-ui.icon name="next" size="sm" /></a></div>
        @if($programs->isNotEmpty())
            <div class="public-home-program-grid">
                @foreach($programs as $program)
                    @php
                        $state = $program->availabilityStateOn($businessDate);
                        $announcement = $program->announcements->first();
                        $cover = $announcement?->publishedRevision?->media?->firstWhere('is_cover', true);
                        $content = $announcement?->publicContent() ?? [];
                    @endphp
                    <article class="public-home-program-card">
                        @if($announcement && $cover)
                            <a class="public-home-program-image" href="{{ route('public.agricultural-programs.show', $program) }}" tabindex="-1" aria-hidden="true"><img src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="" loading="lazy" width="1200" height="700"></a>
                        @else
                            <div class="public-home-program-fallback"><x-ui.icon name="assistance" size="lg" /><span>Municipal agricultural program</span></div>
                        @endif
                        <div class="public-home-program-body">
                            <div class="public-home-program-meta"><span class="public-home-category">{{ $program->category ?: 'Agricultural assistance' }}</span><span class="public-home-status public-home-status-{{ $state }}">@if($state === 'available') Applications open @elseif($state === 'upcoming') Upcoming @else Schedule ended @endif</span></div>
                            <h3><a href="{{ route('public.agricultural-programs.show', $program) }}">{{ $program->name }}</a></h3>
                            <p class="public-home-program-summary">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($content['body'] ?? $program->description)))), 150) }}</p>
                            <p class="public-home-program-date"><x-ui.icon name="calendar" size="xs" /> @if($state === 'available') @if($program->closes_on) Applications through {{ $program->closes_on->format('M j, Y') }} @else Application schedule is open-ended @endif @elseif($state === 'upcoming' && $program->opens_on) Opens {{ $program->opens_on->format('M j, Y') }} @elseif($program->closes_on) Closed {{ $program->closes_on->format('M j, Y') }} @else Check the program details for its schedule @endif</p>
                            <a class="public-home-program-link" href="{{ route('public.agricultural-programs.show', $program) }}">Review program details <x-ui.icon name="next" size="xs" /></a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="public-home-empty"><span class="public-home-service-icon"><x-ui.icon name="calendar" /></span><div><h3>No programs to feature right now</h3><p>Check the official announcements or return later for newly published agricultural programs.</p></div><a class="public-button public-button-outline" href="{{ route('public.agricultural-services.index') }}">Browse service updates</a></div>
        @endif
    </section>

    <section class="public-home-announcement-section" id="announcements" aria-labelledby="announcements-title">
        <div class="public-home-section-heading"><div><p class="public-agri-eyebrow">From the MAO</p><h2 id="announcements-title">Latest official updates</h2></div><a class="public-home-section-link" href="{{ route('public.agricultural-services.index') }}">All programs and notices <x-ui.icon name="next" size="sm" /></a></div>
        @if($announcements->isNotEmpty())
            <div class="public-home-announcement-list">
                @foreach($announcements as $announcement)
                    @php($content = $announcement->publicContent())
                    <article class="public-home-announcement"><span class="public-home-announcement-icon"><x-ui.icon name="reports" /></span><div class="public-home-announcement-copy"><p>{{ $content['category'] ?? 'Official update' }} <span aria-hidden="true">|</span> <time datetime="{{ $announcement->published_at?->toIso8601String() }}">{{ $announcement->published_at?->format('M j, Y') }}</time></p><h3><a href="{{ route('public.announcements.show', $announcement) }}">{{ $content['title'] ?? $announcement->title }}</a></h3><span>{{ $content['office'] ?? 'Bongabong Municipal Agriculture Office' }}</span></div><a class="public-home-announcement-action" href="{{ route('public.announcements.show', $announcement) }}" aria-label="Read {{ $content['title'] ?? $announcement->title }}"><x-ui.icon name="next" /></a></article>
                @endforeach
            </div>
        @else
            <div class="public-home-empty public-home-empty-notice"><span class="public-home-service-icon"><x-ui.icon name="reports" /></span><div><h3>No new public notices</h3><p>Official notices will appear here after they are published by authorized MAO personnel.</p></div><a class="public-home-section-link" href="{{ route('public.agricultural-services.index') }}">Explore available services <x-ui.icon name="next" size="sm" /></a></div>
        @endif
    </section>

    <section class="public-home-steps-section" id="how-it-works" aria-labelledby="how-title">
        <div class="public-home-steps-intro"><p class="public-agri-eyebrow">A clear path to service</p><h2 id="how-title">How AgriConnect works</h2><p>Follow the steps that apply to the service you need. Program requirements and decisions are handled by the Municipal Agriculture Office.</p></div>
        <ol class="public-home-steps">
            <li><span>01</span><div><h3>Explore</h3><p>Review official services, program details, and application schedules.</p></div></li>
            <li><span>02</span><div><h3>Register</h3><p>Create your account and provide farmer and farm information when requested.</p></div></li>
            <li><span>03</span><div><h3>Complete requirements</h3><p>Farmer verification or supporting documents may be required for a service.</p></div></li>
            <li><span>04</span><div><h3>Track updates</h3><p>Sign in to review your submission status and official MAO feedback.</p></div></li>
        </ol>
        <p class="public-home-steps-note">Submitting an application does not guarantee approval or assistance. Each program follows its published eligibility rules and review process.</p>
    </section>

    <section class="public-home-final-cta" aria-labelledby="final-cta-title"><div><p class="public-agri-eyebrow">AgriConnect Farmer Portal</p><h2 id="final-cta-title">Ready to access your agricultural services?</h2><p>Start with official program information or create an account to manage your farmer services.</p></div><div class="public-home-actions"><a class="public-button public-button-primary" href="{{ route('farmer.register') }}">Create an account</a><a class="public-button public-button-outline" href="{{ route('public.agricultural-services.index') }}">Explore programs</a></div></section>
</x-layouts.public-agricultural>
