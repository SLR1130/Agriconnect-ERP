@props(['events', 'emptyMessage' => 'No status-history events have been recorded.'])
<section class="farmer-card farmer-tracking-timeline" aria-labelledby="tracking-history-title">
    <div class="farmer-section-heading"><div><h2 id="tracking-history-title">Service history</h2><p>Updates recorded by you or the Municipal Agriculture Office.</p></div></div>
    @if(count($events))
        <ol class="farmer-timeline-list">
            @foreach($events as $event)
                <li class="farmer-timeline-event">
                    <span class="farmer-timeline-marker" aria-hidden="true"><x-ui.icon name="activity" size="xs" /></span>
                    <div class="farmer-timeline-content">
                        <div class="farmer-timeline-heading"><h3>{{ $event['title'] }}</h3>@if(!empty($event['status']))<x-ui.status-badge :status="$event['status']" />@endif</div>
                        <time datetime="{{ $event['date']?->toIso8601String() }}">{{ $event['date']?->format('M d, Y · h:i A') ?: 'Date unavailable' }}</time>
                        @if(!empty($event['notes']))<p>{{ $event['notes'] }}</p>@endif
                        @if(!empty($event['condition']))<p>Recorded condition: {{ str($event['condition'])->replace('_', ' ')->headline() }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    @else
        <p class="farmer-timeline-empty">{{ $emptyMessage }}</p>
    @endif
</section>
