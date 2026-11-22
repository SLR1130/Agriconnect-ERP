@php
    $currentUser = $currentUser ?? auth()->user();
    $currentRoleName = $currentRoleName ?? ($currentUser?->roles?->first()?->name ?: 'Authorized account');
    $currentInitials = $currentInitials ?? 'AC';
    $notifications = $currentUser?->notifications()->latest()->take(5)->get() ?? collect();
    $unreadNotifications = $currentUser?->unreadNotifications()->count() ?? 0;
    $isSuperAdmin = app(\App\Services\ErpAuthorizationService::class)->isSuperAdmin($currentUser);
    $dashboardRoute = $currentUser?->hasRole('farmer') ? 'farmer.portal' : ($isSuperAdmin ? 'admin.dashboard' : ($currentUser?->hasRole('mao_admin') || $currentUser?->hasRole('municipal_agriculturist') || $currentUser?->hasRole('module_personnel') ? 'mao.dashboard' : 'dashboard'));
    $canSettings = $isSuperAdmin;
    $routeContext = match (true) {
        request()->routeIs('mao.dashboard') => ['group' => 'MAO operations', 'page' => 'Operations dashboard', 'icon' => 'dashboard'],
        request()->routeIs('admin.dashboard') => ['group' => 'System administration', 'page' => 'Administrator dashboard', 'icon' => 'security'],
        request()->routeIs('modules.ffrms.*') => ['group' => 'Agricultural operations', 'page' => 'Farmer & farm registration', 'icon' => 'users'],
        request()->routeIs('modules.apms.*') => ['group' => 'Agricultural operations', 'page' => 'Production & monitoring', 'icon' => 'chart'],
        request()->routeIs('modules.aisms.*') => ['group' => 'Programs & resources', 'page' => 'Inventory & supply', 'icon' => 'inventory'],
        request()->routeIs('modules.aasms.*') => ['group' => 'Programs & resources', 'page' => 'Assistance & subsidies', 'icon' => 'assistance'],
        request()->routeIs('modules.aeams.*') => ['group' => 'Programs & resources', 'page' => 'Equipment & assets', 'icon' => 'equipment'],
        request()->routeIs('modules.abpms.*') => ['group' => 'Finance & procurement', 'page' => 'Budget & procurement', 'icon' => 'finance'],
        request()->routeIs('erp.users.*') => ['group' => 'System administration', 'page' => 'User management', 'icon' => 'users'],
        request()->routeIs('erp.roles.*') => ['group' => 'System administration', 'page' => 'Roles & permissions', 'icon' => 'security'],
        request()->routeIs('erp.modules.*') => ['group' => 'System administration', 'page' => 'Module management', 'icon' => 'modules'],
        request()->routeIs('erp.reports.*') => ['group' => 'Reporting & oversight', 'page' => 'Consolidated reports', 'icon' => 'reports'],
        request()->routeIs('erp.activity-logs.*') => ['group' => 'Reporting & oversight', 'page' => 'Activity logs', 'icon' => 'activity'],
        request()->routeIs('erp.settings.*') => ['group' => 'System administration', 'page' => 'Settings', 'icon' => 'settings'],
        request()->routeIs('farmer.*') => ['group' => 'Farmer services', 'page' => 'Farmer portal', 'icon' => 'profile'],
        default => ['group' => 'Workspace', 'page' => 'Dashboard', 'icon' => 'dashboard'],
    };
    $pageTitle = isset($header) && trim((string) $header) !== '' ? trim((string) $header) : $routeContext['page'];
@endphp

<header class="erp-topbar">
    <div class="erp-topbar-left">
        <button id="desktop-sidebar-toggle" class="erp-desktop-toggle" type="button" aria-controls="app-sidebar" aria-expanded="true" aria-label="Collapse navigation" title="Collapse navigation"><x-ui.icon name="collapse" size="sm" /></button>
        <button id="mobile-sidebar-toggle" class="erp-menu-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation"><x-ui.icon name="menu" size="md" /></button>
        <div class="erp-context">
            <div class="erp-context-title" aria-live="polite"><span class="erp-context-title-icon" aria-hidden="true"><x-ui.icon :name="$routeContext['icon']" size="sm" /></span>{{ $pageTitle }}</div>
            <nav class="erp-context-path" aria-label="Breadcrumb">
                <a href="{{ route($dashboardRoute) }}">AgriConnect</a><span aria-hidden="true">/</span><span>{{ $routeContext['group'] }}</span>
                @if($routeContext['page'] !== 'Dashboard')<span aria-hidden="true">/</span><span aria-current="page">{{ $routeContext['page'] }}</span>@endif
            </nav>
        </div>
    </div>

    <div class="erp-topbar-right">
        <div class="erp-notification-wrap">
            <button id="notification-menu-toggle" class="erp-icon-button" type="button" data-menu-toggle aria-controls="notification-menu" aria-expanded="false" aria-haspopup="true" aria-label="Notifications{{ $unreadNotifications ? ', '.$unreadNotifications.' unread' : '' }}"><x-ui.icon name="notifications" size="sm" />@if($unreadNotifications > 0)<span class="erp-unread-badge" aria-hidden="true">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>@endif</button>
            <div id="notification-menu" class="erp-menu" data-topbar-menu hidden aria-label="Notifications">
                <div class="erp-menu-header"><p class="erp-menu-title">Notifications</p><p class="erp-menu-meta">{{ $unreadNotifications }} unread notification(s)</p></div>
                @forelse($notifications as $notification)
                    <div class="erp-notification-item {{ $notification->read_at ? '' : 'is-unread' }}"><p>{{ data_get($notification->data, 'message', 'New ERP activity requires your attention.') }}</p><time>{{ $notification->created_at?->diffForHumans() }}</time></div>
                @empty
                    <div class="erp-notification-empty">No notifications yet.</div>
                @endforelse
            </div>
        </div>

        <div class="erp-user-wrap">
            <button id="profile-menu-toggle" class="erp-user-button" type="button" data-menu-toggle aria-controls="profile-menu" aria-expanded="false" aria-haspopup="true" aria-label="Account menu for {{ $currentUser?->name ?: 'Authenticated user' }}">
                <span class="erp-avatar erp-avatar--small" aria-hidden="true">{{ $currentInitials }}</span>
                <span class="erp-user-copy"><span class="erp-user-name">{{ $currentUser?->name ?: 'Authenticated user' }}</span><span class="erp-user-role">{{ $currentRoleName }}</span></span>
                <x-ui.icon name="chevron" size="xs" />
            </button>
            <div id="profile-menu" class="erp-menu" data-topbar-menu hidden>
                <div class="erp-menu-header"><p class="erp-menu-title">{{ $currentUser?->name ?: 'Authenticated user' }}</p><p class="erp-menu-meta">{{ $currentUser?->email }} · {{ $currentRoleName }}</p></div>
                <a class="erp-menu-link" href="{{ route($dashboardRoute) }}"><x-ui.icon name="profile" size="sm" />My workspace</a>
                @if($canSettings)<a class="erp-menu-link" href="{{ route('erp.settings.index') }}"><x-ui.icon name="settings" size="sm" />System settings</a>@endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="erp-menu-link erp-menu-link--danger" type="submit"><x-ui.icon name="sign-out" size="sm" />Sign out</button></form>
            </div>
        </div>
    </div>
</header>
