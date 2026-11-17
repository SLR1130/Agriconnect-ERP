@props(['heading' => 'What happens next', 'message', 'tone' => 'info'])
@php($toneClass = match($tone) { 'warning' => 'farmer-next-action-warning', 'danger' => 'farmer-next-action-danger', 'success' => 'farmer-next-action-success', default => 'farmer-next-action-info' })
<section class="farmer-next-action {{ $toneClass }}" role="status">
    <div><h2>{{ $heading }}</h2><p>{{ $message }}</p></div>
    @if(trim((string) $slot) !== '')<div class="farmer-next-action-controls">{{ $slot }}</div>@endif
</section>
