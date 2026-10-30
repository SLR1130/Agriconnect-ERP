<x-layouts.app>
    <x-slot name="header">User management</x-slot>
    <x-slot name="description">Review every account, its role identity, assigned agricultural areas, and the effective access Laravel enforces.</x-slot>
    <x-slot name="actions"><a href="{{ route('erp.users.create') }}" class="erp-button erp-button-primary">Create staff account</a></x-slot>

    @php
        $activeCount = collect($rows)->where('status', 'Active')->count();
        $roleCount = collect($rows)->sum(fn ($row) => count($row['roles']));
        $moduleCount = collect($rows)->sum(fn ($row) => count($row['effective_modules']));
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="erp-stat-card"><p class="erp-stat-label">Accounts</p><p class="erp-stat-value">{{ count($rows) }}</p><p class="erp-stat-note">Database-backed user records</p></div>
        <div class="erp-stat-card"><p class="erp-stat-label">Active accounts</p><p class="erp-stat-value">{{ $activeCount }}</p><p class="erp-stat-note">Inactive or suspended accounts are denied by policy</p></div>
        <div class="erp-stat-card"><p class="erp-stat-label">Effective module access</p><p class="erp-stat-value">{{ $moduleCount }}</p><p class="erp-stat-note">Role assignments projected through the policy service</p></div>
    </div>

    <div class="erp-alert erp-alert-success mt-6">
        <span class="font-black">Policy view</span>
        <p>Effective access below is calculated by <code>ErpAuthorizationService</code>. Stored role JSON and module assignments are shown separately as legacy/reference data; they are not treated as automatic permission grants.</p>
    </div>

    <form method="GET" action="{{ route('erp.users.index') }}" class="erp-surface mt-6 grid grid-cols-1 gap-3 md:grid-cols-[1fr_12rem_auto] md:items-end">
        <div class="erp-field"><label for="search">Search accounts</label><input id="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"></div>
        <div class="erp-field"><label for="status">Status filter</label><select id="status" name="status"><option value="">All statuses</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option></select></div>
        <div class="flex gap-2"><button class="erp-button erp-button-secondary" type="submit">Filter</button><a class="erp-button erp-button-secondary" href="{{ route('erp.users.index') }}">Clear</a></div>
    </form>

    <div class="mt-6 space-y-5">
        @forelse($rows as $account)
            <article class="erp-surface">
                <div class="flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="erp-section-title text-lg">{{ $account['name'] }}</h2>
                            <span class="erp-pill {{ $account['status_tone'] === 'success' ? 'erp-pill-success' : 'erp-pill-warning' }}">{{ $account['status'] }}</span>
                        </div>
                        <p class="mt-1 break-all text-sm text-gray-500">{{ $account['email'] }} · Account #{{ $account['id'] }} · Created {{ $account['created_at'] }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse($account['roles'] as $role)
                                <span class="erp-pill {{ $role['active'] ? '' : 'erp-pill-warning' }}">{{ $role['name'] }} <span class="font-normal opacity-70">({{ $role['slug'] }})</span>{{ $role['active'] ? '' : ' · inactive' }}</span>
                            @empty
                                <span class="erp-pill erp-pill-warning">No role assigned</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="shrink-0 rounded-xl bg-light-sage px-4 py-3 text-xs text-emerald-green">
                        <p class="font-black uppercase tracking-wider">Role identity</p>
                        <p class="mt-1">{{ count($account['roles']) }} assigned role{{ count($account['roles']) === 1 ? '' : 's' }}</p>
                        <div class="mt-3 flex flex-wrap gap-2"><a class="erp-button erp-button-secondary !min-h-0 !px-2 !py-1 !text-[.68rem]" href="{{ route('erp.users.show', $account['id']) }}">Details</a><a class="erp-button erp-button-secondary !min-h-0 !px-2 !py-1 !text-[.68rem]" href="{{ route('erp.users.edit', $account['id']) }}">Edit</a></div>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <section class="min-w-0 xl:col-span-2">
                        <div class="erp-section-header">
                            <div><p class="erp-page-eyebrow">Enforced access</p><h3 class="erp-section-title">Effective agricultural permissions</h3></div>
                            <span class="erp-pill erp-pill-success">Authoritative</span>
                        </div>

                        <div class="space-y-3">
                            @forelse($account['effective_modules'] as $module)
                                <div data-effective-account-id="{{ $account['id'] }}" data-effective-module="{{ $module['slug'] }}" class="rounded-xl border border-gray-100 bg-off-white p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div><h4 class="text-sm font-extrabold text-dark-slate">{{ $module['name'] }}</h4><p class="text-xs uppercase tracking-wider text-gray-400">{{ $module['slug'] }}</p></div>
                                        <span class="text-xs font-bold text-emerald-green">{{ count($module['actions']) }} effective action{{ count($module['actions']) === 1 ? '' : 's' }}</span>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($module['actions'] as $action)
                                            <span class="erp-pill">{{ $action['label'] }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="erp-empty-state">No agricultural module access is effective for this account.</div>
                            @endforelse
                        </div>

                        <div class="mt-5 rounded-xl border border-gray-100 bg-white p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><h4 class="text-sm font-extrabold text-dark-slate">Global and owner-scoped permissions</h4><span class="text-xs text-gray-500">{{ count($account['effective_global']) }} granted</span></div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse($account['effective_global'] as $permission)
                                    <span class="erp-pill">{{ $permission['label'] }} <span class="font-normal opacity-70">({{ $permission['permission'] }})</span></span>
                                @empty
                                    <span class="text-sm text-gray-500">None granted.</span>
                                @endforelse
                            </div>
                        </div>
                    </section>

                    <aside class="min-w-0">
                        <div class="erp-section-header"><div><p class="erp-page-eyebrow">Control boundary</p><h3 class="erp-section-title">Administrative capabilities</h3></div></div>
                        <div class="space-y-2">
                            @foreach($account['effective_admin'] as $capability)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 px-3 py-3">
                                    <span class="text-sm text-gray-700">{{ $capability['label'] }}</span>
                                    <span class="erp-pill {{ $capability['granted'] ? 'erp-pill-success' : 'erp-pill-neutral' }}">{{ $capability['granted'] ? 'Granted' : 'Restricted' }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="mt-4 text-xs leading-5 text-gray-500">System administration routes also require the active Super Administrator role. A stored wildcard or module assignment cannot bypass that boundary.</p>
                    </aside>
                </div>

                @if($account['can_delegate'])
                    <section class="mt-6 border-t border-gray-100 pt-5">
                        <div class="erp-section-header"><div><p class="erp-page-eyebrow">Controlled delegation</p><h3 class="erp-section-title">Assign agricultural responsibilities to this Module Personnel account</h3></div><span class="erp-pill erp-pill-warning">Read/write assignment</span></div>
                        <p class="mb-4 text-xs leading-5 text-gray-500">These account-specific assignments are enforced by Laravel and cannot grant system administration or workflow approval authority. Unchecked actions are revoked when saved.</p>
                        <form method="POST" action="{{ route('erp.users.module-access.update', $account['id']) }}" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            @csrf
                            @method('PUT')
                            @foreach($account['delegated_modules'] as $delegatedModule)
                                <fieldset class="rounded-xl border border-gray-100 bg-off-white p-4">
                                    <legend class="px-1 text-sm font-extrabold text-dark-slate">{{ $delegatedModule['name'] }}</legend>
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach($delegatedModule['actions'] as $action)
                                            <label class="flex items-center gap-2 rounded-lg bg-white px-2 py-2 text-xs text-gray-700"><input type="checkbox" name="assignments[{{ $delegatedModule['slug'] }}][]" value="{{ $action }}" @checked(in_array($action, $delegatedModule['selected'], true))> <span>{{ ucfirst($action) }}</span></label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                            <div class="lg:col-span-2"><button class="erp-button erp-button-primary" type="submit">Save delegated responsibilities</button></div>
                        </form>
                    </section>
                @endif

                <details class="mt-6 border-t border-gray-100 pt-4">
                    <summary class="cursor-pointer text-sm font-extrabold text-emerald-green">View stored role and module assignments (reference only)</summary>
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @forelse($account['stored_roles'] as $storedRole)
                            <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="text-sm font-extrabold text-dark-slate">{{ $storedRole['name'] }}</h4><span class="text-xs text-gray-500">{{ $storedRole['active'] ? 'Active row' : 'Inactive row' }}</span></div>
                                <p class="mt-1 text-xs text-gray-500">{{ $storedRole['slug'] }}</p>
                                <p class="mt-3 text-xs font-bold uppercase tracking-wider text-gray-500">Stored role grants</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @forelse($storedRole['permissions'] as $permission)
                                        <code class="rounded bg-white px-2 py-1 text-[.68rem] text-gray-600">{{ $permission }}</code>
                                    @empty
                                        <span class="text-xs text-gray-500">None</span>
                                    @endforelse
                                </div>
                                <p class="mt-4 text-xs font-bold uppercase tracking-wider text-gray-500">Stored module assignments</p>
                                <div class="mt-2 space-y-2">
                                    @forelse($storedRole['module_access'] as $assignment)
                                        <div class="rounded-lg bg-white px-3 py-2"><p class="text-xs font-bold text-dark-slate">{{ $assignment['name'] }}</p><p class="mt-1 break-words text-[.68rem] text-gray-500">{{ implode(', ', $assignment['permissions']) ?: 'No stored actions' }}</p></div>
                                    @empty
                                        <span class="text-xs text-gray-500">None</span>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <div class="erp-empty-state lg:col-span-2">No stored role assignments.</div>
                        @endforelse
                    </div>
                    <p class="mt-4 text-xs leading-5 text-amber-800">These values are preserved for audit context. They are not displayed as effective access and cannot be edited from this read-only inspector.</p>
                </details>
            </article>
        @empty
            <div class="erp-empty-state">No accounts are available.</div>
        @endforelse
    </div>
</x-layouts.app>
