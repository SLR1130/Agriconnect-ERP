<x-layouts.app>
    <x-slot name="header">Roles &amp; permissions</x-slot>
    <x-slot name="description">Inspect the policy boundary applied to each role and compare it with legacy database grants without changing live assignments.</x-slot>

    @php
        $activeRoles = collect($rows)->where('active', true)->count();
        $assignedUsers = collect($rows)->sum('users_count');
    @endphp

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="erp-stat-card"><p class="erp-stat-label">Defined roles</p><p class="erp-stat-value">{{ count($rows) }}</p><p class="erp-stat-note">Shared across the unified ERP</p></div>
        <div class="erp-stat-card"><p class="erp-stat-label">Active roles</p><p class="erp-stat-value">{{ $activeRoles }}</p><p class="erp-stat-note">Inactive roles grant no effective access</p></div>
        <div class="erp-stat-card"><p class="erp-stat-label">Role assignments</p><p class="erp-stat-value">{{ $assignedUsers }}</p><p class="erp-stat-note">Current user-to-role relationships</p></div>
    </div>

    <div class="erp-alert erp-alert-success mt-6">
        <span class="font-black">Effective policy</span>
        <p>Granted actions in this view are evaluated through <code>ErpAuthorizationService::roleAllows</code>. Legacy JSON and module-access rows are retained below for comparison and do not silently expand built-in role capabilities.</p>
    </div>

    <div class="mt-6 space-y-5">
        @forelse($rows as $role)
            <article class="erp-surface">
                <div class="flex flex-col gap-4 border-b border-gray-100 pb-5 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="erp-section-title text-lg">{{ $role['name'] }}</h2>
                            <span class="erp-pill {{ $role['active'] ? 'erp-pill-success' : 'erp-pill-warning' }}">{{ $role['active'] ? 'Active policy' : 'Inactive policy' }}</span>
                            @if($role['default'])<span class="erp-pill erp-pill-neutral">Built-in role</span>@endif
                        </div>
                        <p class="mt-1 text-sm text-gray-500">{{ $role['slug'] }}{{ $role['description'] ? ' · '.$role['description'] : '' }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ $role['users_count'] }} assigned user{{ $role['users_count'] === 1 ? '' : 's' }} · {{ $role['module_count'] }} stored module assignment{{ $role['module_count'] === 1 ? '' : 's' }}</p>
                    </div>
                    <div class="rounded-xl bg-light-sage px-4 py-3 text-xs text-emerald-green">
                        <p class="font-black uppercase tracking-wider">Assigned accounts</p>
                        <p class="mt-1 max-w-xs leading-5">{{ $role['users'] ? implode(', ', $role['users']) : 'No accounts assigned' }}</p>
                        @if($role['slug'] === 'super_admin')
                            <p class="mt-3 text-[.68rem] font-bold text-amber-800">Super Administrator policy is protected.</p>
                        @else
                            <form method="POST" action="{{ route('erp.roles.status.update', $role['id']) }}" class="mt-3 flex items-end gap-2">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="role-status-{{ $role['id'] }}">Role policy status</label>
                                <select id="role-status-{{ $role['id'] }}" name="is_active" class="!min-h-0 !px-2 !py-1 text-[.68rem]"><option value="1" @selected($role['active'])>Active</option><option value="0" @selected(!$role['active'])>Inactive</option></select>
                                <button class="erp-button erp-button-secondary !min-h-0 !px-2 !py-1 !text-[.68rem]" type="submit">Save policy status</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <section class="xl:col-span-2">
                        <div class="erp-section-header"><div><p class="erp-page-eyebrow">Enforced policy</p><h3 class="erp-section-title">Effective grants</h3></div><span class="erp-pill erp-pill-success">Authoritative</span></div>

                        <div class="rounded-xl border border-gray-100 bg-off-white p-4">
                            <h4 class="text-sm font-extrabold text-dark-slate">Global capabilities</h4>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse($role['effective_global'] as $permission)
                                    <span class="erp-pill">{{ $permission['label'] }} <span class="font-normal opacity-70">({{ $permission['permission'] }})</span></span>
                                @empty
                                    <span class="text-sm text-gray-500">No global capabilities are effective.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="mt-3 space-y-3">
                            @forelse($role['effective_modules'] as $module)
                                <div data-effective-role-id="{{ $role['id'] }}" data-effective-module="{{ $module['slug'] }}" class="rounded-xl border border-gray-100 p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h4 class="text-sm font-extrabold text-dark-slate">{{ $module['name'] }}</h4><p class="text-xs uppercase tracking-wider text-gray-400">{{ $module['slug'] }}</p></div><span class="text-xs font-bold text-emerald-green">{{ count($module['actions']) }} effective action{{ count($module['actions']) === 1 ? '' : 's' }}</span></div>
                                    <div class="mt-3 flex flex-wrap gap-2">@foreach($module['actions'] as $action)<span class="erp-pill">{{ $action['label'] }}</span>@endforeach</div>
                                </div>
                            @empty
                                <div class="erp-empty-state">No agricultural module actions are effective for this role.</div>
                            @endforelse
                        </div>
                    </section>

                    <aside>
                        <div class="erp-section-header"><div><p class="erp-page-eyebrow">Governance</p><h3 class="erp-section-title">Assignment policy</h3></div></div>
                        <div class="rounded-xl border border-gray-100 bg-white p-4">
                            <p class="text-sm leading-6 text-gray-600">Role and module assignments are currently read-only in this interface. Controlled delegated access must be changed through the reviewed role policy and authorization service so the displayed grants remain truthful.</p>
                            <div class="mt-4 space-y-2 text-xs text-gray-500">
                                <p><span class="font-bold text-dark-slate">View:</span> read-only operational access.</p>
                                <p><span class="font-bold text-dark-slate">Write:</span> create, update, receive, release, adjust, issue, or return.</p>
                                <p><span class="font-bold text-dark-slate">Workflow:</span> review, approve, reject, or distribute.</p>
                                <p><span class="font-bold text-dark-slate">Restricted:</span> system administration remains Super Administrator-only.</p>
                            </div>
                        </div>
                    </aside>
                </div>

                <details class="mt-6 border-t border-gray-100 pt-4">
                    <summary class="cursor-pointer text-sm font-extrabold text-emerald-green">View stored database grants (legacy/reference only)</summary>
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-4">
                            <h4 class="text-sm font-extrabold text-dark-slate">Stored role permissions</h4>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse($role['stored_permissions'] as $permission)
                                    <code class="rounded bg-white px-2 py-1 text-[.68rem] text-gray-600">{{ $permission }}</code>
                                @empty
                                    <span class="text-xs text-gray-500">None</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-4">
                            <h4 class="text-sm font-extrabold text-dark-slate">Stored module assignments</h4>
                            <div class="mt-3 space-y-2">
                                @forelse($role['stored_module_access'] as $assignment)
                                    <div class="rounded-lg bg-white px-3 py-2"><p class="text-xs font-bold text-dark-slate">{{ $assignment['name'] }}</p><p class="mt-1 break-words text-[.68rem] text-gray-500">{{ implode(', ', $assignment['permissions']) ?: 'No stored actions' }}</p></div>
                                @empty
                                    <span class="text-xs text-gray-500">None</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-amber-800">Stored values can be broader than the effective policy for legacy compatibility. This page intentionally does not provide an editor that could imply an override which Laravel would ignore.</p>
                </details>
            </article>
        @empty
            <div class="erp-empty-state">No roles are available.</div>
        @endforelse
    </div>
</x-layouts.app>
