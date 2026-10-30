@php
    $currentUser = $currentUser ?? auth()->user();
    $currentRoleName = $currentRoleName ?? ($currentUser?->roles?->first()?->name ?: 'Authorized account');
    $currentInitials = $currentInitials ?? collect(preg_split('/\s+/', trim((string) ($currentUser?->name ?: 'User'))))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
    $authorization = app(\App\Services\ErpAuthorizationService::class);
    $canAccessModule = fn (string $slug): bool => $authorization->canModule($currentUser, $slug);
    $isSuperAdmin = $authorization->isSuperAdmin($currentUser);
    $canReports = $currentUser?->canErp('reports.view') || $currentUser?->canErp('reports.generate');
    $canActivity = $currentUser?->canErp('activity_logs.view');
    $canOverview = $currentUser?->canErp('dashboard.view');
    $canFarmerServices = $currentUser?->hasRole('farmer') && $currentUser?->farmer?->isVerified();
    $dashboardRoute = $currentUser?->hasRole('farmer')
        ? 'farmer.portal'
        : ($isSuperAdmin ? 'admin.dashboard' : ($currentUser?->hasRole('mao_admin') || $currentUser?->hasRole('municipal_agriculturist') || $currentUser?->hasRole('module_personnel') ? 'mao.dashboard' : 'dashboard'));
    $moduleLinks = [
        ['slug' => 'ffrms', 'label' => 'Farmer & Farm Registration', 'code' => 'FF'],
        ['slug' => 'apms', 'label' => 'Production & Monitoring', 'code' => 'PR'],
        ['slug' => 'aisms', 'label' => 'Inventory & Supply', 'code' => 'IN'],
        ['slug' => 'aasms', 'label' => 'Assistance & Subsidies', 'code' => 'AS'],
        ['slug' => 'aeams', 'label' => 'Equipment & Assets', 'code' => 'EA'],
        ['slug' => 'abpms', 'label' => 'Budget & Procurement', 'code' => 'BP'],
    ];
@endphp

<aside id="app-sidebar" class="erp-sidebar" aria-label="AgriConnect primary navigation">
    <div class="erp-brand">
        <div class="erp-brand-mark" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21V10"/><path d="M12 13c-4.5 0-7.5-2.5-7.5-7.5C9.5 5.5 12 8 12 13Z"/><path d="M12 16c4.5 0 7.5-2 7.5-7.5C14.5 8.5 12 11 12 16Z"/></svg></div>
        <div><p class="erp-brand-name">AGRICONNECT</p><p class="erp-brand-subtitle">Unified agricultural ERP</p></div>
    </div>

    <nav class="erp-sidebar-scroll">
        <section class="erp-nav-section">
            <p class="erp-nav-label">Workspace</p>
            <ul class="erp-nav-list">
                <li><a class="erp-nav-link {{ request()->routeIs('dashboard', 'admin.dashboard', 'mao.dashboard', 'farmer.portal') ? 'is-active' : '' }}" href="{{ route($dashboardRoute) }}" title="Dashboard" @if(request()->routeIs('dashboard', 'admin.dashboard', 'mao.dashboard', 'farmer.portal')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">DS</span><span>Dashboard</span></a></li>
                @if($canOverview && ! $currentUser?->hasRole('farmer'))
                    <li><a class="erp-nav-link {{ request()->routeIs('overview') ? 'is-active' : '' }}" href="{{ route('overview') }}" title="Overview" @if(request()->routeIs('overview')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">OV</span><span>Overview</span></a></li>
                @endif
            </ul>
        </section>

        @php($visibleModules = collect($moduleLinks)->filter(fn ($module) => $canAccessModule($module['slug']))->values())
        @if($visibleModules->isNotEmpty())
            <section class="erp-nav-section">
                <p class="erp-nav-label">Agricultural modules</p>
                <ul class="erp-nav-list">
                    @foreach($visibleModules as $module)
                        <li><a class="erp-nav-link {{ request()->routeIs('modules.'.$module['slug'].'.*') ? 'is-active' : '' }}" href="{{ route('modules.'.$module['slug'].'.dashboard') }}" title="{{ $module['label'] }}" @if(request()->routeIs('modules.'.$module['slug'].'.*')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">{{ $module['code'] }}</span><span>{{ $module['label'] }}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if($canFarmerServices)
            <section class="erp-nav-section">
                <p class="erp-nav-label">Farmer services</p>
                <ul class="erp-nav-list">
                    <li><a class="erp-nav-link {{ request()->routeIs('farmer.portal') ? 'is-active' : '' }}" href="{{ route('farmer.portal') }}" title="My farmer profile" @if(request()->routeIs('farmer.portal')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">ME</span><span>My farmer profile</span></a></li>
                    <li><a class="erp-nav-link {{ request()->routeIs('farmer.assistance') ? 'is-active' : '' }}" href="{{ route('farmer.assistance') }}" title="Assistance services" @if(request()->routeIs('farmer.assistance')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">AS</span><span>Assistance services</span></a></li>
                    <li><a class="erp-nav-link {{ request()->routeIs('modules.aeams.*') ? 'is-active' : '' }}" href="{{ route('modules.aeams.dashboard') }}" title="Equipment requests" @if(request()->routeIs('modules.aeams.*')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">EA</span><span>Equipment requests</span></a></li>
                </ul>
            </section>
        @endif

        @if($isSuperAdmin || $canReports || $canActivity)
            <section class="erp-nav-section">
                <p class="erp-nav-label">Administration</p>
                <ul class="erp-nav-list">
                    @if($isSuperAdmin)
                        <li><a class="erp-nav-link {{ request()->routeIs('erp.users.*') ? 'is-active' : '' }}" href="{{ route('erp.users.index') }}" title="User management" @if(request()->routeIs('erp.users.*')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">US</span><span>User management</span></a></li>
                        <li><a class="erp-nav-link {{ request()->routeIs('erp.roles.*') ? 'is-active' : '' }}" href="{{ route('erp.roles.index') }}" title="Roles &amp; permissions" @if(request()->routeIs('erp.roles.*')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">RL</span><span>Roles &amp; permissions</span></a></li>
                        <li><a class="erp-nav-link {{ request()->routeIs('erp.modules.*') ? 'is-active' : '' }}" href="{{ route('erp.modules.index') }}" title="Module management" @if(request()->routeIs('erp.modules.*')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">MD</span><span>Module management</span></a></li>
                    @endif
                    @if($canReports)<li><a class="erp-nav-link {{ request()->routeIs('erp.reports.*') ? 'is-active' : '' }}" href="{{ route('erp.reports.index') }}" title="Consolidated reports" @if(request()->routeIs('erp.reports.*')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">RP</span><span>Consolidated reports</span></a></li>@endif
                    @if($canActivity)<li><a class="erp-nav-link {{ request()->routeIs('erp.activity-logs.*') ? 'is-active' : '' }}" href="{{ route('erp.activity-logs.index') }}" title="Activity logs" @if(request()->routeIs('erp.activity-logs.*')) aria-current="page" @endif><span class="erp-nav-icon" aria-hidden="true">AL</span><span>Activity logs</span></a></li>@endif
                </ul>
            </section>
        @endif
    </nav>

    <div class="erp-account-strip"><div class="erp-account"><span class="erp-avatar" aria-hidden="true">{{ $currentInitials }}</span><div class="min-w-0"><p class="erp-account-name">{{ $currentUser?->name ?: 'Authenticated user' }}</p><p class="erp-account-role">{{ $currentRoleName }}</p></div></div></div>
</aside>
