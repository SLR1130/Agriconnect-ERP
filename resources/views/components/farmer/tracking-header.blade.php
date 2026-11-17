@props(['service', 'title', 'status', 'submittedAt', 'backUrl', 'backLabel', 'icon' => 'activity', 'reference' => null, 'dateLabel' => 'Submitted'])
<a class="farmer-back-link" href="{{ $backUrl }}"><x-ui.icon name="back" size="sm" />{{ $backLabel }}</a>
<header class="farmer-tracking-header farmer-card">
    <span class="farmer-tracking-icon" aria-hidden="true"><x-ui.icon :name="$icon" size="lg" /></span>
    <div class="farmer-tracking-heading">
        <p class="farmer-eyebrow">{{ $service }}</p>
        <h2>{{ $title }}</h2>
        <p class="farmer-tracking-meta">
            @if($reference)<span>Reference {{ $reference }}</span>@endif
            @if($submittedAt)<span>{{ $reference ? '·' : '' }} {{ $dateLabel }} {{ $submittedAt->format('M d, Y') }}</span>@endif
        </p>
    </div>
    <x-ui.status-badge :status="$status" />
</header>
