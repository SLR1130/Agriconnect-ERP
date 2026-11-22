@php
    $currentUser = $currentUser ?? auth()->user();
    $currentRoleName = $currentRoleName ?? ($currentUser?->roles?->first()?->name ?: 'Authorized account');
    $currentInitials = $currentInitials ?? collect(preg_split('/\s+/', trim((string) ($currentUser?->name ?: 'User'))))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('');
    $authorization = app(\App\Services\ErpAuthorizationService::class);
    $canAccessModule = fn (string $slug): bool => $authorization->canModule($currentUser, $slug);
    $isSuperAdmin = $authorization->isSuperAdmin($currentUser);
    $isFarmer = $currentUser?->hasRole('farmer');
    $canReports = $currentUser?->canErp('reports.view') || $currentUser?->canErp('reports.generate') || $authorization->visibleModules($currentUser) !== [];
    $canActivity = $currentUser?->canErp('activity_logs.view');
    $canFarmerServices = $isFarmer && $currentUser?->farmer?->isVerified();
    $canAasmsIntake = !$isFarmer && $currentUser?->canModuleAction('aasms', 'intake_assist')
        && app(\App\Services\StaffAssistedIntakePolicy::class)->enabledForIsolatedQa();
    $dashboardRoute = $isFarmer
        ? 'farmer.portal'
        : ($isSuperAdmin ? 'admin.dashboard' : ($currentUser?->hasRole('mao_admin') || $currentUser?->hasRole('municipal_agriculturist') || $currentUser?->hasRole('module_personnel') ? 'mao.dashboard' : 'dashboard'));

    $moduleSections = [
        'Agricultural operations' => [
            ['slug' => 'ffrms', 'label' => 'Farmer & Farm Registration', 'icon' => 'users'],
            ['slug' => 'apms', 'label' => 'Production & Field Operations', 'icon' => 'chart'],
        ],
        'Programs & resources' => [
            ['slug' => 'aisms', 'label' => 'Inventory & Supply', 'icon' => 'inventory'],
            ['slug' => 'aasms', 'label' => 'Assistance & Subsidies', 'icon' => 'assistance'],
            ['slug' => 'aeams', 'label' => 'Equipment & Assets', 'icon' => 'equipment'],
        ],
        'Finance & procurement' => [
            ['slug' => 'abpms', 'label' => 'Budget & Procurement', 'icon' => 'finance'],
        ],
    ];
@endphp

<aside id="app-sidebar" class="erp-sidebar" aria-label="AgriConnect primary navigation">
    <div class="erp-brand">
        <a class="erp-brand-home" href="{{ route($dashboardRoute) }}" aria-label="AgriConnect home">
            <span class="erp-brand-mark" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21V10"/><path d="M12 13c-4.5 0-7.5-2.5-7.5-7.5C9.5 5.5 12 8 12 13Z"/><path d="M12 16c4.5 0 7.5-2 7.5-7.5C14.5 8.5 12 11 12 16Z"/></svg></span>
            <span class="erp-brand-copy"><span class="erp-brand-name">AGRICONNECT</span><span class="erp-brand-subtitle">Municipal agriculture services</span></span>
        </a>
        <button id="mobile-sidebar-close" class="erp-sidebar-close" type="button" aria-label="Close navigation"><x-ui.icon name="close" size="md" /></button>
    </div>

    <nav class="erp-sidebar-scroll" aria-label="Main menu">
        <section class="erp-nav-section">
            <p class="erp-nav-label">Workspace</p>
            <ul class="erp-nav-list">
                <li>
                    <a class="erp-nav-link {{ request()->routeIs('dashboard', 'admin.dashboard', 'mao.dashboard', 'farmer.portal') ? 'is-active' : '' }}" href="{{ route($dashboardRoute) }}" title="Dashboard" @if(request()->routeIs('dashboard', 'admin.dashboard', 'mao.dashboard', 'farmer.portal')) aria-current="page" @endif>
                        <span class="erp-nav-icon"><x-ui.icon name="dashboard" size="sm" /></span><span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </section>

        @if($isFarmer)
            @if($canFarmerServices)
                <section class="erp-nav-section">
                    <p class="erp-nav-label">Farmer services</p>
                    <ul class="erp-nav-list">
                        <li><a class="erp-nav-link {{ request()->routeIs('farmer.portal') ? 'is-active' : '' }}" href="{{ route('farmer.portal') }}" title="My profile" @if(request()->routeIs('farmer.portal')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="profile" size="sm" /></span><span>My profile</span></a></li>
                        <li><a class="erp-nav-link {{ request()->routeIs('farmer.services') ? 'is-active' : '' }}" href="{{ route('farmer.services') }}" title="Explore services" @if(request()->routeIs('farmer.services')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="services" size="sm" /></span><span>Explore services</span></a></li>
                        <li><a class="erp-nav-link {{ request()->routeIs('farmer.assistance') ? 'is-active' : '' }}" href="{{ route('farmer.assistance') }}" title="Assistance services" @if(request()->routeIs('farmer.assistance')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="assistance" size="sm" /></span><span>Assistance services</span></a></li>
                        <li><a class="erp-nav-link {{ request()->routeIs('modules.aeams.*') ? 'is-active' : '' }}" href="{{ route('modules.aeams.dashboard') }}" title="Equipment requests" @if(request()->routeIs('modules.aeams.*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="equipment" size="sm" /></span><span>Equipment requests</span></a></li>
                    </ul>
                </section>
            @endif
        @else
            @foreach($moduleSections as $sectionLabel => $links)
                @php($visibleLinks = collect($links)->filter(fn ($link) => $canAccessModule($link['slug']))->values())
                @if($visibleLinks->isNotEmpty())
                    <section class="erp-nav-section">
                        <p class="erp-nav-label">{{ $sectionLabel }}</p>
                        <ul class="erp-nav-list">
                            @foreach($visibleLinks as $link)
                                <li><a class="erp-nav-link {{ request()->routeIs('modules.'.$link['slug'].'.*') ? 'is-active' : '' }}" href="{{ route('modules.'.$link['slug'].'.dashboard') }}" title="{{ $link['label'] }}" @if(request()->routeIs('modules.'.$link['slug'].'.*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon :name="$link['icon']" size="sm" /></span><span>{{ $link['label'] }}</span></a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach

            @if($canAasmsIntake)
                <section class="erp-nav-section">
                    <p class="erp-nav-label">AASMS intake</p>
                    <ul class="erp-nav-list">
                        <li><a class="erp-nav-link {{ request()->routeIs('modules.aasms.intake*') ? 'is-active' : '' }}" href="{{ route('modules.aasms.intake') }}" title="Staff-assisted farmer intake" @if(request()->routeIs('modules.aasms.intake*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="users" size="sm" /></span><span>Staff-assisted intake</span></a></li>
                    </ul>
                </section>
            @endif

            @if($canReports || $canActivity)
                <section class="erp-nav-section">
                    <p class="erp-nav-label">Reporting &amp; oversight</p>
                    <ul class="erp-nav-list">
                        @if($canReports)<li><a class="erp-nav-link {{ request()->routeIs('erp.reports.*') ? 'is-active' : '' }}" href="{{ route('erp.reports.index') }}" title="Consolidated reports" @if(request()->routeIs('erp.reports.*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="reports" size="sm" /></span><span>Consolidated reports</span></a></li>@endif
                        @if($canActivity)<li><a class="erp-nav-link {{ request()->routeIs('erp.activity-logs.*') ? 'is-active' : '' }}" href="{{ route('erp.activity-logs.index') }}" title="Activity logs" @if(request()->routeIs('erp.activity-logs.*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="activity" size="sm" /></span><span>Activity logs</span></a></li>@endif
                    </ul>
                </section>
            @endif

            @if($isSuperAdmin)
                <section class="erp-nav-section">
                    <p class="erp-nav-label">System administration</p>
                    <ul class="erp-nav-list">
                        <li><a class="erp-nav-link {{ request()->routeIs('erp.users.*') ? 'is-active' : '' }}" href="{{ route('erp.users.index') }}" title="User management" @if(request()->routeIs('erp.users.*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="users" size="sm" /></span><span>User management</span></a></li>
                        <li><a class="erp-nav-link {{ request()->routeIs('erp.roles.*') ? 'is-active' : '' }}" href="{{ route('erp.roles.index') }}" title="Roles &amp; permissions" @if(request()->routeIs('erp.roles.*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="security" size="sm" /></span><span>Roles &amp; permissions</span></a></li>
                        <li><a class="erp-nav-link {{ request()->routeIs('erp.modules.*') ? 'is-active' : '' }}" href="{{ route('erp.modules.index') }}" title="Module management" @if(request()->routeIs('erp.modules.*')) aria-current="page" @endif><span class="erp-nav-icon"><x-ui.icon name="modules" size="sm" /></span><span>Module management</span></a></li>
                    </ul>
                </section>
            @endif
        @endif
    </nav>

    <div class="erp-account-strip">
        <div class="erp-account"><span class="erp-avatar" aria-hidden="true">{{ $currentInitials }}</span><div class="erp-account-copy"><p class="erp-account-name">{{ $currentUser?->name ?: 'Authenticated user' }}</p><p class="erp-account-role">{{ $currentRoleName }}</p></div></div>
    </div>
</aside>
