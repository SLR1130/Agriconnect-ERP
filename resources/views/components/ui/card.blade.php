@props(['as' => 'section', 'padding' => 'md'])
<{{ $as }} {{ $attributes->class(['ui-card', 'ui-card-compact' => $padding === 'sm', 'ui-card-spacious' => $padding === 'lg']) }}>{{ $slot }}</{{ $as }}>
