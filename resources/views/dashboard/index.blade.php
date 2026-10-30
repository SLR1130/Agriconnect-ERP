@php
    $authorization = app(\App\Services\ErpAuthorizationService::class);
    $canAccess = fn (string $slug): bool => $authorization->canModule(auth()->user(), $slug);
    $isSuperAdmin = $authorization->isSuperAdmin(auth()->user());
@endphp

<x-layouts.app>
    <x-slot name="header">Dashboard</x-slot>
    <x-slot name="description">{{ $roleContext['description'] }}</x-slot>

    <section class="mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-green to-forest-green p-8 text-white">
        <p class="text-xs font-black uppercase tracking-[.18em] text-agricultural-gold">{{ $roleContext['eyebrow'] }}</p>
        <h1 class="mt-3 text-2xl font-bold">{{ $roleContext['headline'] }}</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-emerald-50">Live records from {{ $health['database'] ?: 'the connected ERP database' }} &middot; Checked {{ $health['checked_at']->format('M j, Y g:i A') }}</p>
    </section>

    <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($roleContext['cards'] as $card)
            <div class="erp-surface erp-stat-card"><p class="erp-stat-label">{{ $card['label'] }}</p><p class="erp-stat-value">{{ $card['value'] }}</p><p class="erp-stat-note">{{ $card['description'] }}</p></div>
        @endforeach
    </section>

    <section class="mb-8">
        <div class="erp-section-header"><div><p class="erp-page-eyebrow">Authorized workspaces</p><h2 class="erp-section-title text-xl">Agricultural modules</h2></div>@if($isSuperAdmin)<a href="{{ route('erp.modules.index') }}" class="erp-section-link">Manage modules &rarr;</a>@endif</div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($modules as $key => $module)
                @if($canAccess($key))
                    <a href="{{ route('modules.'.$key.'.dashboard') }}" class="erp-surface erp-card-link p-5"><div class="flex items-start justify-between gap-3"><div><h3 class="text-base font-semibold text-dark-slate">{{ $module['name'] }}</h3><p class="mt-1 text-sm text-gray-600">{{ $module['description'] }}</p></div><span class="erp-pill">{{ $module['enabled'] ? 'Available' : 'Disabled' }}</span></div><div class="mt-5 text-sm text-gray-600">Open the shared operational workspace for authorized records and actions.</div></a>
                @endif
            @empty
                <div class="erp-surface erp-empty md:col-span-3">No modules are registered yet.</div>
            @endforelse
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="erp-surface p-6 lg:col-span-2"><div class="erp-section-header"><h3 class="erp-section-title text-lg">Recent activity</h3>@if($authorization->allows(auth()->user(), 'activity_logs.view'))<a href="{{ route('erp.activity-logs.index') }}" class="erp-section-link">View all &rarr;</a>@endif</div>@forelse($activities as $activity)<div class="flex items-start gap-3 border-b border-gray-100 px-1 py-3 last:border-0"><div class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-light-sage text-emerald-green">&bull;</div><div class="min-w-0"><p class="text-sm text-gray-900">{{ $activity['description'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $activity['user'] }} &middot; {{ $activity['time'] }}</p></div></div>@empty<div class="erp-empty">No activity has been recorded yet.</div>@endforelse</section>
        <section class="erp-surface p-6"><h3 class="erp-section-title text-lg">System status</h3><div class="mt-5 flex items-center justify-between"><span class="text-sm text-gray-700">MySQL connection</span><span class="erp-pill {{ $health['connected'] ? 'erp-pill-success' : 'erp-pill-warning' }}">{{ $health['connected'] ? 'Connected' : 'Unavailable' }}</span></div><div class="mt-6 border-t border-gray-200 pt-4"><h4 class="text-sm font-medium text-gray-700">Upcoming deadlines</h4>@forelse($deadlines as $deadline)<div class="flex items-center justify-between gap-2 py-2"><span class="text-sm text-gray-600">{{ $deadline['title'] }}</span><span class="text-xs text-gray-500">{{ $deadline['deadline']->format('M j') }}</span></div>@empty<p class="mt-3 text-sm text-gray-500">No workflow deadlines are scheduled.</p>@endforelse</div></section>
    </div>
</x-layouts.app>
