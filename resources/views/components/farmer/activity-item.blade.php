@props(['activity', 'compact' => false])
@php
    $icon = match ($activity['type']) {
        'Assistance application', 'Assistance received' => 'assistance',
        'Equipment request' => 'equipment',
        'Registration update', 'Farm record' => 'farms',
        'Crop record', 'Livestock record', 'Agricultural submission' => 'crop',
        'Fisheries record' => 'fish',
        'Pest or disease report' => 'monitoring',
        default => 'activity',
    };
@endphp

<article class="farmer-activity-item {{ $compact ? 'farmer-activity-item-compact' : '' }}">
    <span class="farmer-activity-icon" aria-hidden="true"><x-ui.icon :name="$icon" size="md" /></span>
    <div class="farmer-activity-body">
        <div class="farmer-activity-topline">
            <p class="farmer-activity-service">{{ $activity['type'] }}</p>
            <x-ui.status-badge :status="$activity['status']" />
        </div>
        <h3 class="farmer-activity-title">{{ $activity['title'] }}</h3>
        @if($activity['detail'])<p class="farmer-activity-detail">{{ $activity['detail'] }}</p>@endif
        <p class="farmer-activity-event">{{ $activity['event_description'] }}</p>
        @if($activity['event_notes'] && in_array($activity['status'], ['returned', 'rejected'], true))
            <p class="farmer-activity-note"><strong>MAO feedback:</strong> {{ $activity['event_notes'] }}</p>
        @endif
        @if($activity['next_step'])<p class="farmer-activity-next"><strong>Next step:</strong> {{ $activity['next_step'] }}</p>@endif
        <div class="farmer-activity-footer">
            <p><span>{{ $activity['date_label'] }}</span> · <time datetime="{{ $activity['date']?->toIso8601String() }}">{{ $activity['date']?->format('M d, Y · h:i A') ?: 'Date unavailable' }}</time></p>
            <a href="{{ $activity['url'] }}">View details <x-ui.icon name="next" size="xs" /></a>
        </div>
    </div>
</article>
