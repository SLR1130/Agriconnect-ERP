@php
    $authorization = app(\App\Services\ErpAuthorizationService::class);
    $canAccess = fn (string $slug): bool => $authorization->canModule(auth()->user(), $slug);
@endphp

<x-layouts.app>
    <x-slot name="header">MAO Operations Dashboard</x-slot>
    <x-slot name="description">A role-aware municipal agriculture workspace for coordinated field, inventory, assistance, asset, and procurement operations.</x-slot>

    <section class="mb-6 overflow-hidden rounded-2xl border border-[#dfe9e1] bg-white p-6 shadow-sm md:p-8">
        <div class="flex flex-col justify-between gap-5 md:flex-row md:items-center">
            <div>
                <p class="text-xs font-black uppercase tracking-[.18em] text-emerald-green">{{ $roleContext['eyebrow'] }}</p>
                <h2 class="mt-2 max-w-3xl text-2xl font-extrabold tracking-tight text-dark-slate md:text-3xl">{{ $roleContext['headline'] }}</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-gray-500">{{ $roleContext['description'] }}</p>
            </div>
            <div class="rounded-xl bg-light-sage px-4 py-3 text-sm"><p class="text-xs font-bold uppercase tracking-wider text-gray-500">System status</p><p class="mt-1 flex items-center gap-2 font-extrabold text-emerald-green"><span class="h-2 w-2 rounded-full bg-emerald-green"></span>{{ $health['connected'] ? 'Operational' : 'Database unavailable' }}</p></div>
        </div>
    </section>

    <section class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($roleContext['cards'] as $card)
            <div class="erp-surface erp-stat-card"><p class="erp-stat-label">{{ $card['label'] }}</p><p class="erp-stat-value">{{ $card['value'] }}</p><p class="erp-stat-note">{{ $card['description'] }}</p></div>
        @endforeach
    </section>

    <section class="mb-6">
        <div class="erp-section-header"><div><p class="erp-page-eyebrow">Authorized workspaces</p><h2 class="erp-section-title text-xl">Agricultural modules</h2></div><span class="erp-pill">Permission-aware</span></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($modules as $key => $module)
                @if($canAccess($key))
                    <a class="erp-surface erp-card-link p-5" href="{{ route('modules.'.$key.'.dashboard') }}"><div class="flex items-start justify-between gap-3"><span class="erp-pill">{{ strtoupper($key) }}</span><span class="text-xs font-bold text-gray-400">{{ $module['enabled'] ? 'Available' : 'Disabled' }}</span></div><h3 class="mt-4 text-base font-extrabold text-dark-slate">{{ $module['name'] }}</h3><p class="mt-2 text-sm leading-6 text-gray-500">{{ $module['description'] }}</p><span class="mt-4 inline-flex text-xs font-extrabold text-emerald-green">Open workspace &rarr;</span></a>
                @endif
            @empty
                <div class="erp-surface erp-empty md:col-span-2 xl:col-span-3">No agricultural modules are registered yet.</div>
            @endforelse
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[1.35fr_.65fr]">
        <section class="erp-surface p-5 md:p-6"><div class="erp-section-header"><div><p class="erp-page-eyebrow">Live feed</p><h2 class="erp-section-title text-xl">Recent operations</h2></div><span class="erp-pill">Database-backed</span></div><div class="divide-y divide-gray-100">@forelse($activities as $activity)<div class="flex items-start gap-3 py-4"><span class="mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-light-sage text-xs font-black text-emerald-green">&bull;</span><div><p class="text-sm font-semibold text-dark-slate">{{ $activity['description'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $activity['user'] }} &middot; {{ $activity['time'] }}</p></div></div>@empty<div class="erp-empty">No recent operational activity.</div>@endforelse</div></section>
        <section class="erp-surface p-5 md:p-6"><div class="erp-section-header"><h2 class="erp-section-title">Upcoming deadlines</h2><span class="erp-pill">Workflow</span></div>@forelse($deadlines as $deadline)<div class="border-b border-gray-100 py-3 last:border-0"><p class="text-sm font-semibold text-dark-slate">{{ $deadline['title'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $deadline['deadline']->format('M j, Y') }} &middot; {{ ucfirst($deadline['priority']) }} priority</p></div>@empty<div class="erp-empty px-0 py-8">No workflow deadlines are scheduled.</div>@endforelse</section>
    </div>
</x-layouts.app>
