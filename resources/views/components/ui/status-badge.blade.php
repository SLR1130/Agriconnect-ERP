@props(['status', 'label' => null])
@php
    $key = strtolower(str_replace([' ', '-'], '_', (string) $status));
    $tone = in_array($key, ['verified', 'approved', 'completed', 'received', 'active', 'distributed', 'open'], true) ? 'success'
        : (in_array($key, ['rejected', 'not_eligible', 'inactive', 'failed', 'expired'], true) ? 'danger'
        : (in_array($key, ['pending', 'returned', 'under_review', 'scheduled', 'submitted', 'needs_information'], true) ? 'warning' : 'neutral'));
@endphp
<span {{ $attributes->class(['ui-status-badge', 'ui-status-'.$tone]) }}>{{ $label ?? str($status)->replace('_', ' ')->headline() }}</span>
