@props(['name', 'size' => 'md', 'label' => null])
@php
    $icons = [
        'home' => 'home', 'dashboard' => 'squares-2x2', 'services' => 'squares-2x2', 'farms' => 'map', 'activity' => 'clipboard-document-list',
        'livestock' => 'heart', 'warning' => 'exclamation-triangle',
        'notifications' => 'bell', 'profile' => 'user-circle', 'assistance' => 'sparkles', 'equipment' => 'wrench-screwdriver',
        'crop' => 'beaker', 'fish' => 'circle-stack', 'monitoring' => 'bug-ant', 'location' => 'map-pin', 'add' => 'plus',
        'next' => 'arrow-right', 'back' => 'arrow-left', 'chevron' => 'chevron-down', 'success' => 'check-circle',
        'close' => 'x-mark', 'office' => 'building-office-2', 'search' => 'magnifying-glass', 'calendar' => 'calendar-days',
        'users' => 'user-group', 'chart' => 'chart-bar', 'inventory' => 'archive-box', 'finance' => 'banknotes',
        'reports' => 'document-chart-bar', 'security' => 'shield-check', 'modules' => 'squares-plus',
        'settings' => 'cog-6-tooth', 'sign-out' => 'arrow-left-start-on-rectangle', 'menu' => 'bars-3',
        'collapse' => 'rectangle-group', 'action' => 'inbox-stack',
    ];
    $component = 'heroicon-o-'.($icons[$name] ?? 'question-mark-circle');
    $dimensions = ['xs' => 'h-3.5 w-3.5', 'sm' => 'h-4 w-4', 'md' => 'h-5 w-5', 'lg' => 'h-6 w-6', 'xl' => 'h-8 w-8'];
@endphp
@php($iconAttributes = $attributes->class([$dimensions[$size] ?? $dimensions['md'], 'shrink-0']))
{!! svg($component, $iconAttributes->get('class'), array_merge($iconAttributes->except('class')->getAttributes(), $label ? ['role' => 'img', 'aria-label' => $label] : ['aria-hidden' => 'true']))->toHtml() !!}
