<x-layouts.farmer>
    <x-slot name="title">{{ $publication['content']['title'] }}</x-slot>
    <x-slot name="description">Official MAO notice · Published {{ $announcement->published_at->timezone('Asia/Manila')->format('M d, Y') }}</x-slot>
    <a class="farmer-back-link" href="{{ route('farmer.announcements') }}"><x-ui.icon name="back" size="sm"/>All notices</a>
    <article class="farmer-card farmer-announcement-detail">
        @php($cover = $publication['media']->first(fn ($media) => $media->is_cover) ?? $publication['media']->first())
        @if($cover)<figure class="farmer-announcement-cover"><img src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text }}"><figcaption>{{ $cover->alt_text }}</figcaption></figure>@endif
        <div class="farmer-announcement-heading"><span class="farmer-pill">{{ $publication['content']['category'] ?: 'Official announcement' }}</span><h2>{{ $publication['content']['title'] }}</h2><p><x-ui.icon name="office" size="sm"/>{{ $publication['content']['office'] ?: 'Bongabong Municipal Agriculture Office' }} · {{ $announcement->published_at->timezone('Asia/Manila')->format('F j, Y') }}</p></div>
        @if($publication['media']->count() > 1)<div class="farmer-announcement-gallery" aria-label="Announcement photographs">@foreach($publication['media']->reject(fn ($media) => $cover && $media->id === $cover->id) as $media)<figure><img src="{{ route('public.announcements.media', [$announcement, $media]) }}" alt="{{ $media->alt_text }}" loading="lazy"><figcaption>{{ $media->alt_text }}</figcaption></figure>@endforeach</div>@endif
        <div class="farmer-announcement-content">{{ $publication['bodyHtml'] }}</div>
        <div class="farmer-announcement-facts">
            @if($publication['content']['service_area'])<section><h3>Service area</h3><p>{{ $publication['content']['service_area'] }}</p></section>@endif
            @if($publication['content']['requirements_summary'])<section><h3>Requirements summary</h3><p>{{ $publication['content']['requirements_summary'] }}</p></section>@endif
            @if($publication['content']['instructions'])<section><h3>Official instructions</h3><p>{{ $publication['content']['instructions'] }}</p></section>@endif
            @if($publication['content']['contact_details'])<section><h3>Contact</h3><p class="whitespace-pre-line">{{ $publication['content']['contact_details'] }}</p></section>@endif
        </div>
        @if($publication['program'] && $publication['program']->status === 'open')<a class="farmer-button farmer-button-gold mt-5" href="{{ route('farmer.programs.show', $publication['program']) }}">View program and check eligibility <x-ui.icon name="next" size="sm"/></a>@endif
        @if($announcement->expires_at)<p class="farmer-date-guidance">This notice is available until {{ $announcement->expires_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} Philippine time.</p>@endif
    </article>
</x-layouts.farmer>
