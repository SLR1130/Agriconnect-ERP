<x-layouts.farmer>
    <x-slot name="title">Official MAO notices</x-slot>
    <x-slot name="description">Official service updates and agricultural announcements relevant to your community.</x-slot>
    <a class="farmer-back-link" href="{{ route('public.agricultural-services.index') }}"><x-ui.icon name="back" size="sm"/>Browse programs and notices</a>
    <div class="farmer-notice-grid farmer-notice-grid-rich">
        @forelse($announcements as $announcement)
            @php($publication = $presentations[$announcement->id])
            @php($cover = $publication['media']->first(fn ($media) => $media->is_cover) ?? $publication['media']->first())
            <article class="farmer-card farmer-notice-feature">
                @if($cover)<a href="{{ route('farmer.announcements.show', $announcement) }}"><img class="farmer-notice-image" src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text }}" loading="lazy"></a>@endif
                <div class="farmer-notice-feature-content"><div class="farmer-section-heading"><span class="farmer-pill">{{ $publication['content']['category'] ?: 'Official notice' }}</span><time datetime="{{ $announcement->published_at?->toIso8601String() }}">{{ $announcement->published_at?->timezone('Asia/Manila')->format('M j, Y') }}</time></div>
                    <h2>{{ $publication['content']['title'] }}</h2>
                    @if($publication['content']['office'])<p class="farmer-notice-byline"><x-ui.icon name="office" size="sm"/>{{ $publication['content']['office'] }}</p>@endif
                    <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) \Illuminate\Support\Str::markdown($publication['content']['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false])), 220) }}</p>
                    <a class="farmer-button farmer-button-light" href="{{ route('farmer.announcements.show', $announcement) }}">Read official notice <x-ui.icon name="next" size="sm"/></a>
                </div>
            </article>
        @empty
            <div class="farmer-empty"><span class="ui-service-icon"><x-ui.icon name="office" size="lg"/></span><strong>No MAO announcements are currently published.</strong><p>You can still explore published agricultural programs and service requirements.</p><a class="farmer-button farmer-button-light" href="{{ route('public.agricultural-services.index') }}">Explore public services</a></div>
        @endforelse
    </div>
    @if($announcements->hasPages())<div class="farmer-pagination">{{ $announcements->links() }}</div>@endif
</x-layouts.farmer>
