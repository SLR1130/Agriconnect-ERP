@php
    $moduleKey = $moduleKey ?? null;
    $domainStatus = $domainStatus ?? 'This domain is connected to the shared ERP workspace.';
    $moduleLinks = [
        ['key' => 'ffrms', 'label' => 'Farmer records', 'route' => 'modules.ffrms.dashboard', 'description' => 'Authoritative farmer and farm registration records.'],
        ['key' => 'apms', 'label' => 'Production monitoring', 'route' => 'modules.apms.dashboard', 'description' => 'Crop, livestock, fisheries, incident, and farm monitoring records.'],
        ['key' => 'aisms', 'label' => 'Inventory and supply', 'route' => 'modules.aisms.dashboard', 'description' => 'Stock receiving, release, adjustment, and batch controls.'],
        ['key' => 'aasms', 'label' => 'Assistance programs', 'route' => 'modules.aasms.dashboard', 'description' => 'Program applications, review, allocation, and distribution.'],
        ['key' => 'aeams', 'label' => 'Equipment and assets', 'route' => 'modules.aeams.dashboard', 'description' => 'Equipment and asset management workspace.'],
        ['key' => 'abpms', 'label' => 'Budget and procurement', 'route' => 'modules.abpms.dashboard', 'description' => 'Budget and procurement management workspace.'],
    ];
@endphp

<x-layouts.app>
    <x-slot name="header">{{ $header ?? 'AgriConnect ERP workspace' }}</x-slot>
    <x-slot name="description">{{ $description ?? 'Connected agricultural operations in one system.' }}</x-slot>

    <div class="space-y-6">
        <section class="rounded-2xl border border-emerald-100 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Unified ERP workspace
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-forest-green sm:text-3xl">
                        {{ $header ?? 'AgriConnect ERP workspace' }}
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base">
                        {{ $description ?? 'Connected agricultural operations in one system.' }}
                        This domain uses the same authentication, permissions, farmer records, navigation, and audit boundaries as the rest of AgriConnect ERP.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                        <span class="rounded-full bg-sage px-3 py-1.5">Shared authentication</span>
                        <span class="rounded-full bg-sage px-3 py-1.5">Shared authoritative records</span>
                        <span class="rounded-full bg-sage px-3 py-1.5">Cross-domain navigation</span>
                    </div>
                </div>
                <a href="{{ route('dashboard') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-emerald-200 px-4 py-2.5 text-sm font-bold text-forest-green transition hover:bg-emerald-50">
                    Return to ERP overview
                </a>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.35fr_.65fr]">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Current capability boundary</p>
                <h2 class="mt-2 text-xl font-bold text-forest-green">Connected without invented transactions</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $domainStatus }}</p>
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    Transaction counts and activity are intentionally not fabricated here. When a service and persistence model are registered for this domain, they can be added to this same workspace without creating a competing application.
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-sage p-6">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">ERP contract</p>
                <h2 class="mt-2 text-xl font-bold text-forest-green">One operational system</h2>
                <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                    <li class="flex gap-2"><span class="font-bold text-emerald-700">✓</span> One login and role policy</li>
                    <li class="flex gap-2"><span class="font-bold text-emerald-700">✓</span> One shared sidebar and visual language</li>
                    <li class="flex gap-2"><span class="font-bold text-emerald-700">✓</span> Existing module routes remain discoverable</li>
                    <li class="flex gap-2"><span class="font-bold text-emerald-700">✓</span> No duplicate farmer or inventory records</li>
                </ul>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Connected domains</p>
                    <h2 class="mt-2 text-xl font-bold text-forest-green">Continue work in the same ERP</h2>
                </div>
                <p class="text-sm text-slate-500">Use the shared navigation to move between operational areas.</p>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($moduleLinks as $module)
                    <a href="{{ route($module['route']) }}" class="group rounded-xl border border-slate-200 p-4 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-bold text-slate-800 group-hover:text-emerald-800">{{ $module['label'] }}</h3>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ strtoupper($module['key']) }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-5 text-slate-600">{{ $module['description'] }}</p>
                        <span class="mt-3 inline-flex text-xs font-bold text-emerald-700">Open workspace →</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
