<x-layouts.app>
    <x-slot name="header">Administrator Command Center</x-slot>
    <x-slot name="description">Govern the shared ERP, monitor live operations, and manage the six agricultural domains.</x-slot>

    <section class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-[#102f23] via-[#163d2a] to-[#26865c] p-6 text-white shadow-lg md:p-8">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#d5ad55]">System administration</p>
                <h2 class="mt-3 max-w-2xl text-2xl font-extrabold tracking-tight md:text-3xl">One connected view of municipal agriculture.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-[#e7f0e8]/80">Identity, permissions, operational records, and activity history are governed from the same AgriConnect workspace.</p>
            </div>
            <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm backdrop-blur-sm">
                <p class="text-[#e7f0e8]/70">Database status</p>
                <p class="mt-1 flex items-center gap-2 font-bold"><span class="h-2 w-2 rounded-full {{ $health['connected'] ? 'bg-[#d5ad55]' : 'bg-red-300' }}"></span>{{ $health['connected'] ? 'Connected' : 'Unavailable' }}</p>
            </div>
        </div>
    </section>

    <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['key' => 'total_users', 'title' => 'Users', 'tone' => 'text-emerald-green'],
            ['key' => 'active_roles', 'title' => 'Active roles', 'tone' => 'text-forest-green'],
            ['key' => 'enabled_modules', 'title' => 'Enabled modules', 'tone' => 'text-[#a27b23]'],
            ['key' => 'activity_logs', 'title' => 'Activity events', 'tone' => 'text-dark-slate'],
            ['key' => 'pending_items', 'title' => 'Pending requests', 'tone' => 'text-emerald-green'],
        ] as $stat)
            <div class="erp-surface erp-stat-card">
                <p class="erp-stat-label">{{ $stat['title'] }}</p>
                <p class="erp-stat-value {{ $stat['tone'] }}">{{ $stats[$stat['key']]['value'] }}</p>
                <p class="erp-stat-note">{{ $stats[$stat['key']]['description'] }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
        <section class="erp-surface p-5 md:p-6">
            <div class="erp-section-header"><div><p class="erp-page-eyebrow">Control plane</p><h2 class="erp-section-title text-xl">Administration shortcuts</h2></div><span class="erp-pill">Super Admin only</span></div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach([
                    ['route' => 'erp.users.index', 'code' => 'US', 'title' => 'User management', 'copy' => 'Review active accounts and their assigned roles.'],
                    ['route' => 'erp.roles.index', 'code' => 'RL', 'title' => 'Roles & permissions', 'copy' => 'Inspect the permission model used by every domain.'],
                    ['route' => 'erp.modules.index', 'code' => 'MD', 'title' => 'Module management', 'copy' => 'Review the shared agricultural module registry.'],
                    ['route' => 'erp.reports.index', 'code' => 'RP', 'title' => 'Consolidated reports', 'copy' => 'Open the central reporting workspace.'],
                ] as $shortcut)
                    <a class="erp-surface erp-card-link p-5" href="{{ route($shortcut['route']) }}"><span class="erp-nav-icon bg-light-sage text-emerald-green">{{ $shortcut['code'] }}</span><h3 class="mt-4 text-sm font-extrabold text-dark-slate">{{ $shortcut['title'] }}</h3><p class="mt-2 text-sm leading-6 text-gray-500">{{ $shortcut['copy'] }}</p></a>
                @endforeach
            </div>
        </section>

        <section class="erp-surface p-5 md:p-6">
            <div class="erp-section-header"><h2 class="erp-section-title">System health</h2><span class="erp-pill">Live check</span></div>
            <div class="space-y-4 text-sm">
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-4"><span class="text-gray-500">Database connection</span><span class="font-bold {{ $health['connected'] ? 'text-emerald-green' : 'text-red-600' }}">{{ $health['connected'] ? 'Connected' : 'Unavailable' }}</span></div>
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-4"><span class="text-gray-500">Database engine</span><span class="text-right font-bold text-dark-slate">{{ $health['database'] ?: 'Unavailable' }}</span></div>
                <div class="flex items-center justify-between gap-4"><span class="text-gray-500">Last checked</span><span class="text-right font-bold text-dark-slate">{{ $health['checked_at']->format('M j, Y g:i A') }}</span></div>
            </div>
        </section>
    </div>

    <section class="erp-surface mt-6 p-5 md:p-6">
        <div class="erp-section-header"><div><p class="erp-page-eyebrow">Audit trail</p><h2 class="erp-section-title text-xl">Recent operational activity</h2></div><a class="erp-section-link" href="{{ route('erp.activity-logs.index') }}">View activity logs →</a></div>
        <div class="divide-y divide-gray-100">
            @forelse($activities as $activity)
                <div class="flex items-start gap-3 py-4"><span class="mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-light-sage text-xs font-black text-emerald-green">•</span><div class="min-w-0"><p class="text-sm font-semibold text-dark-slate">{{ $activity['description'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $activity['user'] }} · {{ $activity['time'] }}</p></div></div>
            @empty
                <div class="erp-empty">No activity has been recorded yet.</div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
