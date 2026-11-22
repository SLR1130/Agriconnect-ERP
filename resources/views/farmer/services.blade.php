<x-layouts.farmer>
    <x-slot name="title">Explore services</x-slot>
    <x-slot name="description">Find the right agricultural service, check what you need, and follow the next step.</x-slot>

    @php($verified = $farmer->isVerified())
    <section class="farmer-section farmer-service-directory" aria-labelledby="service-directory-title">
        <div class="farmer-section-heading">
            <div>
                <h2 id="service-directory-title">Services for your farm</h2>
                <p>Choose a service to view your records, send information, or request MAO assistance.</p>
            </div>
            <span class="farmer-pill {{ $verified ? '' : 'farmer-pill-warn' }}">
                {{ $verified ? 'Registration verified' : 'Verification '.$farmer->registration_status }}
            </span>
        </div>

        <div class="farmer-service-grid">
            @foreach([
                ['Borrow MAO equipment', 'Choose an available municipal asset, request the dates you need, and follow the MAO decision through return.', route('farmer.equipment'), 'equipment', 'Borrow equipment', $verified ? 'Available assets are subject to schedule review.' : 'Equipment requests open after farmer verification.'],
                ['Assistance and subsidies', 'Find published programs, review requirements, apply when eligible, and track MAO decisions.', route('farmer.assistance'), 'assistance', 'Browse programs', 'Program dates and eligibility determine when you can apply.'],
                ['Farmer and farm records', 'Keep your contact details and farm locations current and follow registration verification.', route('farmer.farms'), 'farms', 'Manage my farms', $verified ? 'Your farmer registration is verified.' : 'Applications for some services open after verification.'],
                ['Crop and livestock', 'Send crop or livestock information to the MAO and review your submitted records.', route('farmer.production', ['section' => 'crops']), 'crop', 'Open production services', $verified ? 'Submit information for MAO review.' : 'Submission is available after farmer verification.'],
                ['Fisheries', 'Submit fishery production details for your registered farm and track the review.', route('farmer.production', ['section' => 'fisheries']), 'fish', 'Open fisheries services', $verified ? 'Submit information for MAO review.' : 'Submission is available after farmer verification.'],
                ['Farm monitoring and incident reports', 'Review monitoring records or report a pest, disease, or other farm incident.', route('farmer.production', ['section' => 'incidents']), 'monitoring', 'Open monitoring services', $verified ? 'Choose a registered farm when sending a report.' : 'Reporting forms require a verified farmer account.'],
            ] as [$name, $copy, $url, $icon, $action, $serviceNote])
                <article class="farmer-card farmer-service-card">
                    <div class="farmer-service-card-top">
                        <span class="ui-service-icon" aria-hidden="true"><x-ui.icon :name="$icon" size="lg" /></span>
                        <span class="farmer-service-kicker">Agricultural service</span>
                    </div>
                    <h3>{{ $name }}</h3>
                    <p class="farmer-service-copy">{{ $copy }}</p>
                    <p class="farmer-service-availability"><x-ui.icon name="success" size="sm" />{{ $serviceNote }}</p>
                    <a class="farmer-button farmer-button-light farmer-service-action" href="{{ $url }}">{{ $action }} <x-ui.icon name="next" size="sm" /></a>
                </article>
            @endforeach
        </div>
    </section>

    @include('farmer.partials.assistance-catalog', [
        'catalogHeading' => 'Published assistance programs',
        'catalogDescription' => 'Search current and upcoming MAO programs. Applications are offered only when dates and eligibility allow.',
    ])

    @if($announcementsAvailable)
        <section class="farmer-section farmer-card" aria-labelledby="service-notices-title">
            <div class="farmer-section-heading">
                <div><h2 id="service-notices-title">Official MAO notices</h2><p>Published agricultural announcements and service advisories.</p></div>
                <a class="farmer-text-link" href="{{ route('public.agricultural-services.index') }}">Public programs and notices <x-ui.icon name="next" size="sm" /></a>
            </div>
            <div class="farmer-notice-grid">
                @forelse($announcements as $announcement)
                    @php($publication = $announcementPresentations[$announcement->id])
                    @php($cover = $publication['media']->first(fn ($media) => $media->is_cover) ?? $publication['media']->first())
                    <a class="farmer-notice-card" href="{{ route('farmer.announcements.show', $announcement) }}">
                        @if($cover)<img class="farmer-notice-image" src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text }}" loading="lazy">@endif
                        <span class="farmer-service-kicker">MAO notice</span>
                        <strong>{{ $publication['content']['title'] }}</strong>
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) \Illuminate\Support\Str::markdown($publication['content']['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false])), 180) }}</p>
                    </a>
                @empty
                    <p class="farmer-empty">No official MAO notices are currently published.</p>
                @endforelse
            </div>
        </section>
    @endif
</x-layouts.farmer>
