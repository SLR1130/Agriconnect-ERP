@props(['title', 'description' => null, 'eyebrow' => null])
<div {{ $attributes->class('ui-section-heading') }}>
    <div>@if($eyebrow)<p class="ui-eyebrow">{{ $eyebrow }}</p>@endif<h2>{{ $title }}</h2>@if($description)<p class="ui-section-description">{{ $description }}</p>@endif</div>
    @if(isset($actions) && $actions->isNotEmpty())<div class="ui-section-action">{{ $actions }}</div>@endif
</div>
