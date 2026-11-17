@props(['title', 'description' => null, 'icon' => 'farms'])
<div {{ $attributes->class('ui-empty-state') }}><span class="ui-empty-icon"><x-ui.icon :name="$icon" size="lg"/></span><h3>{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif{{ $slot }}</div>
