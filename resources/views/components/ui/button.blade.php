@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'icon' => null, 'size' => 'md'])
@php
    $variants = [
        'primary' => 'ui-button-primary', 'secondary' => 'ui-button-secondary', 'quiet' => 'ui-button-quiet',
        'danger' => 'ui-button-danger', 'gold' => 'ui-button-gold',
    ];
    $classes = 'ui-button '.($variants[$variant] ?? $variants['primary']).' '.($size === 'sm' ? 'ui-button-sm' : '');
@endphp
@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if($icon)<x-ui.icon :name="$icon" size="sm"/>@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>@if($icon)<x-ui.icon :name="$icon" size="sm"/>@endif{{ $slot }}</button>
@endif
