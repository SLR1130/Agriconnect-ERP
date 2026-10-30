@php
    $currentUser = $currentUser ?? auth()->user();
    $currentRoleName = $currentRoleName ?? ($currentUser?->roles?->first()?->name ?: 'Authorized account');
    $currentInitials = $currentInitials ?? 'AC';
    $notifications = $currentUser?->notifications()->latest()->take(5)->get() ?? collect();
    $unreadNotifications = $currentUser?->unreadNotifications()->count() ?? 0;
    $profileUrl = $currentUser?->hasRole('farmer') ? route('farmer.portal') : route('dashboard');
    $canSettings = $currentUser?->isSuperAdministrator();
@endphp

<header class="erp-topbar">
    <div class="erp-topbar-left">
        <button id="desktop-sidebar-toggle" class="erp-desktop-toggle" type="button" aria-controls="app-sidebar" aria-expanded="true" aria-label="Collapse navigation" title="Collapse navigation"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16M14 9l-3 3 3 3"/></svg></button>
        <button id="mobile-sidebar-toggle" class="erp-menu-toggle lg:hidden" type="button" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
        <div class="erp-context"><div class="erp-context-title" aria-live="polite">{{ $header ?? 'ERP workspace' }}</div><div class="erp-context-path"><a href="{{ route('dashboard') }}">AgriConnect</a><span aria-hidden="true">/</span><span>{{ $currentRoleName }}</span></div></div>
    </div>

    <div class="erp-topbar-right">
        <div class="erp-notification-wrap">
            <button id="notification-menu-toggle" class="erp-icon-button" type="button" data-menu-toggle aria-controls="notification-menu" aria-expanded="false" aria-haspopup="true" aria-label="Notifications{{ $unreadNotifications ? ', '.$unreadNotifications.' unread' : '' }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>@if($unreadNotifications > 0)<span class="erp-unread-badge" aria-hidden="true">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>@endif</button>
            <div id="notification-menu" class="erp-menu" data-topbar-menu hidden aria-label="Notifications"><div class="erp-menu-header"><p class="erp-menu-title">Notifications</p><p class="erp-menu-meta">{{ $unreadNotifications }} unread notification(s)</p></div>@forelse($notifications as $notification)<div class="erp-notification-item"><p>{{ data_get($notification->data, 'message', 'New ERP activity requires your attention.') }}</p><time>{{ $notification->created_at?->diffForHumans() }}</time></div>@empty<div class="erp-notification-empty">No notifications yet.</div>@endforelse</div>
        </div>

        <div class="erp-user-wrap">
            <button id="profile-menu-toggle" class="erp-user-button" type="button" data-menu-toggle aria-controls="profile-menu" aria-expanded="false" aria-haspopup="true" aria-label="Account menu for {{ $currentUser?->name ?: 'Authenticated user' }}"><span class="erp-avatar erp-avatar--small" aria-hidden="true">{{ $currentInitials }}</span><span class="erp-user-copy"><span class="erp-user-name">{{ $currentUser?->name ?: 'Authenticated user' }}</span><span class="erp-user-role">{{ $currentRoleName }}</span></span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
            <div id="profile-menu" class="erp-menu" data-topbar-menu hidden><div class="erp-menu-header"><p class="erp-menu-title">{{ $currentUser?->name ?: 'Authenticated user' }}</p><p class="erp-menu-meta">{{ $currentUser?->email }} · {{ $currentRoleName }}</p></div><a class="erp-menu-link" href="{{ $profileUrl }}"><span aria-hidden="true">◉</span>My profile</a>@if($canSettings)<a class="erp-menu-link" href="{{ route('erp.settings.index') }}"><span aria-hidden="true">⚙</span>Settings</a>@endif<form method="POST" action="{{ route('logout') }}">@csrf<button class="erp-menu-link erp-menu-link--danger" type="submit"><span aria-hidden="true">↪</span>Sign out</button></form></div>
        </div>
    </div>
</header>
