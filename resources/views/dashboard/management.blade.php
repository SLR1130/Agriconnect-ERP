@php
    $sections = $analytics['sections'];
    $overviewCards = collect($sections)->flatMap(fn (array $section) => $section['cards'])->take(6);
    $hasAgriculture = isset($sections['ffrms']) || isset($sections['apms']);
    $hasResources = isset($sections['aisms']) || isset($sections['aasms']) || isset($sections['aeams']);
    $hasFinance = isset($sections['abpms']);
    $canSeeActivity = count($activities) > 0;
    $dashboardIcons = ['ffrms' => 'users', 'apms' => 'chart', 'aisms' => 'inventory', 'aasms' => 'assistance', 'aeams' => 'equipment', 'abpms' => 'finance'];
@endphp

<x-layouts.app>
    <x-slot name="header">{{ $roleContext['dashboard_title'] }}</x-slot>
    <x-slot name="description">{{ $roleContext['description'] }}</x-slot>

    <x-ui.workflow-strip
        label="Connected MAO workflow"
        description="Farmer records are the starting point. Production information guides planning, while approved assistance and procurement movements remain traceable in the same ERP."
        :steps="['Verify farmer records', 'Monitor production', 'Approve services', 'Release resources']"
    />

    <form method="GET" action="{{ route($dashboardRoute) }}" class="erp-dashboard-filter erp-surface mb-5" aria-label="Filter dashboard reporting period">
        <div class="erp-dashboard-filter-intro"><p class="erp-page-eyebrow">Reporting period</p><p>Choose a date range to refresh the operational summaries.</p></div>
        <div class="erp-dashboard-filter-fields">
            <div class="erp-field"><label for="dashboard-from">From</label><input id="dashboard-from" type="date" name="from" value="{{ $analytics['period']['from'] }}" required></div>
            <div class="erp-field"><label for="dashboard-to">Through</label><input id="dashboard-to" type="date" name="to" value="{{ $analytics['period']['to'] }}" min="{{ $analytics['period']['from'] }}" required></div>
            @if(count($analytics['seasons']))
                <div class="erp-field"><label for="dashboard-season">Season</label><select id="dashboard-season" name="season"><option value="">All seasons</option>@foreach($analytics['seasons'] as $season)<option value="{{ $season->id }}" @selected((int) $analytics['selected_season'] === (int) $season->id)>{{ $season->name }}</option>@endforeach</select></div>
            @endif
            <div class="erp-dashboard-filter-actions"><button class="erp-button erp-button-primary" type="submit">Apply filters</button><a class="erp-button erp-button-secondary" href="{{ route($dashboardRoute) }}">Reset</a></div>
        </div>
    </form>

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-bold">The dashboard filters need attention.</p>
            <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(count($pendingActions))
        <section class="erp-action-center erp-surface mb-5" aria-labelledby="pending-action-center-title">
            <div class="erp-section-header"><div><p class="erp-page-eyebrow">Authorized work queues</p><h2 id="pending-action-center-title" class="erp-section-title">Action center</h2><p class="mt-1 text-sm text-gray-500">Review the pending records available to your role.</p></div><span class="erp-pill">{{ collect($pendingActions)->sum('count') }} awaiting attention</span></div>
            <div class="erp-action-grid">
                @foreach($pendingActions as $queue)
                    <article class="erp-action-card">
                        <div class="erp-action-card-heading"><span class="erp-action-icon"><x-ui.icon :name="$dashboardIcons[strtolower($queue['module'])] ?? 'action'" size="sm" /></span><div class="min-w-0"><p class="erp-action-module">{{ $queue['module'] }}</p><h3>{{ $queue['title'] }}</h3></div><span class="erp-action-count {{ $queue['count'] > 0 ? 'is-pending' : 'is-clear' }}">{{ $queue['count'] }}</span></div>
                        @if(count($queue['items']))
                            <ul class="erp-action-list">
                                @foreach($queue['items'] as $item)
                                    <li><a href="{{ $item['href'] }}"><span class="erp-action-item-title">{{ $item['title'] }}</span><span class="erp-action-item-detail">{{ $item['detail'] }}</span></a><time datetime="{{ $item['date']->toIso8601String() }}">{{ $item['date']->format('M j') }}</time></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="erp-action-empty">No records are waiting in this queue.</p>
                        @endif
                        <a class="erp-action-link" href="{{ $queue['href'] }}">Open {{ strtolower($queue['module']) }} queue <x-ui.icon name="next" size="xs" /></a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($health)
        <section class="erp-admin-summary erp-surface mb-5" aria-label="System administration indicators">
            <div class="erp-admin-summary-heading"><div><p class="erp-page-eyebrow">System administration</p><h2 class="erp-section-title">Account and platform summary</h2></div><span class="erp-pill">Current status</span></div>
            <dl class="erp-admin-summary-grid">
                @foreach([
                    ['label' => 'Accounts', 'key' => 'total_users'],
                    ['label' => 'Active accounts', 'key' => 'active_users'],
                    ['label' => 'Active roles', 'key' => 'active_roles'],
                    ['label' => 'Enabled modules', 'key' => 'enabled_modules'],
                    ['label' => 'Audit events', 'key' => 'activity_logs'],
                ] as $stat)
                    <div><dt>{{ $stat['label'] }}</dt><dd>{{ number_format($systemStats[$stat['key']]['value']) }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif

    <section class="erp-operational-snapshot mb-6" aria-label="Operational indicators for selected period">
        <div class="erp-operational-heading">
            <div><p class="erp-page-eyebrow">Selected period</p><h2 class="erp-section-title text-lg">Operational snapshot</h2></div>
            <p>{{ \Illuminate\Support\Carbon::parse($analytics['period']['from'])->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($analytics['period']['to'])->format('M j, Y') }} <span>Current stock and asset availability are as of today.</span></p>
        </div>
        @if($overviewCards->isNotEmpty())
            <div class="erp-operational-grid">
                @foreach($overviewCards as $card)
                    <article class="erp-dashboard-stat">
                        <p>{{ $card['label'] }}</p>
                        <strong>{{ is_numeric($card['value']) ? number_format((float) $card['value'], str_contains((string) $card['value'], '.') ? 2 : 0) : $card['value'] }}</strong>
                        <span>{{ $card['note'] }}</span>
                    </article>
                @endforeach
            </div>
        @else
            <div class="erp-surface erp-empty">No agricultural module access is assigned to this account. Contact an administrator if this is unexpected.</div>
        @endif
    </section>
    <div class="erp-workspace" data-erp-workspace data-workspace-param="workspace" data-workspace-default="{{ request('workspace', 'overview') }}">
        <nav class="erp-workspace-tabs" role="tablist" aria-label="Management dashboard sections">
            <button class="erp-workspace-tab" id="dashboard-tab-overview" role="tab" type="button" aria-selected="true" aria-controls="dashboard-panel-overview" data-erp-workspace-tab="overview">Overview</button>
            @if($hasAgriculture)<button class="erp-workspace-tab" id="dashboard-tab-agriculture" role="tab" type="button" aria-selected="false" aria-controls="dashboard-panel-agriculture" data-erp-workspace-tab="agriculture">Agricultural operations</button>@endif
            @if($hasResources)<button class="erp-workspace-tab" id="dashboard-tab-resources" role="tab" type="button" aria-selected="false" aria-controls="dashboard-panel-resources" data-erp-workspace-tab="resources">Resources &amp; assistance</button>@endif
            @if($hasFinance)<button class="erp-workspace-tab" id="dashboard-tab-finance" role="tab" type="button" aria-selected="false" aria-controls="dashboard-panel-finance" data-erp-workspace-tab="finance">Finance &amp; procurement</button>@endif
            @if($canViewReports)<button class="erp-workspace-tab" id="dashboard-tab-reports" role="tab" type="button" aria-selected="false" aria-controls="dashboard-panel-reports" data-erp-workspace-tab="reports">Reports</button>@endif
        </nav>

        <section class="erp-workspace-panel" id="dashboard-panel-overview" role="tabpanel" aria-labelledby="dashboard-tab-overview" tabindex="0" data-erp-workspace-panel="overview">
            <div class="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
                <div class="erp-surface p-5 md:p-6">
                    <div class="erp-section-header"><div><p class="erp-page-eyebrow">Authorized shortcuts</p><h2 class="erp-section-title text-lg">Your workspaces</h2></div><span class="erp-pill">{{ count($modules) }} available</span></div>
                    @if(count($modules))
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach($modules as $slug => $module)
                                <a href="{{ route('modules.'.$slug.'.dashboard') }}" class="erp-dashboard-workspace group rounded-xl border border-[#e4ebe5] bg-white p-4 transition hover:border-[#26865c] hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[#26865c]">
    <div class="flex items-center justify-between gap-2"><span class="rounded-md bg-[#e7f0e8] px-2 py-1 text-[.65rem] font-extrabold tracking-wide text-[#153d2b]">{{ strtoupper($slug) }}</span><span class="text-[.68rem] font-semibold {{ $module['enabled'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $module['enabled'] ? 'Enabled' : 'Registry disabled' }}</span></div>
    <div class="erp-dashboard-shortcut mt-4"><span class="erp-dashboard-shortcut-icon"><x-ui.icon :name="$dashboardIcons[$slug] ?? 'modules'" size="md" /></span><span class="erp-dashboard-shortcut-copy"><h3 class="text-sm font-extrabold text-[#243324] group-hover:text-[#26865c]">{{ $module['name'] }}</h3><p class="mt-1 text-xs leading-5 text-gray-500">{{ $module['description'] }}</p></span></div>
    <span class="mt-4 inline-flex items-center gap-1 text-xs font-extrabold text-[#26865c]">Open workspace <x-ui.icon name="next" size="xs" /></span>
</a>
                            @endforeach
                        </div>
                    @else
                        <p class="erp-empty">No workspaces are available for this role.</p>
                    @endif
                </div>

                <div class="space-y-5">
                    @if($health)
                        <section class="erp-surface p-5" aria-labelledby="system-status-title">
                            <div class="erp-section-header"><h2 id="system-status-title" class="erp-section-title">System health</h2><span class="erp-pill">Live check</span></div>
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-gray-500">Database connection</dt><dd class="font-bold {{ $health['connected'] ? 'text-emerald-700' : 'text-red-700' }}">{{ $health['connected'] ? 'Connected' : 'Unavailable' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-gray-500">Database engine</dt><dd class="font-bold text-[#243324]">{{ $health['database'] ?: 'Unavailable' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-gray-500">Checked</dt><dd class="text-right text-gray-700">{{ $health['checked_at']->format('M j, Y g:i A') }}</dd></div>
                            </dl>
                        </section>
                    @endif
                    @if($canSeeActivity)
                        <section class="erp-surface p-5" aria-labelledby="activity-title">
                            <div class="erp-section-header"><div><p class="erp-page-eyebrow">Audit history</p><h2 id="activity-title" class="erp-section-title">Recent activity</h2></div><a class="erp-section-link" href="{{ route('erp.activity-logs.index') }}">View all <span aria-hidden="true">→</span></a></div>
                            <div class="divide-y divide-gray-100">
                                @foreach($activities as $activity)
                                    <div class="py-3"><p class="text-sm font-semibold text-[#243324]">{{ $activity['description'] }}</p><p class="mt-1 text-xs text-gray-500">{{ $activity['module'] }} · {{ $activity['user'] }} · {{ $activity['time'] }}</p></div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    @if(count($deadlines))
                        <section class="erp-surface p-5" aria-labelledby="deadlines-title">
                            <div class="erp-section-header"><h2 id="deadlines-title" class="erp-section-title">Upcoming workflow deadlines</h2><span class="erp-pill">Authorized scope</span></div>
                            @foreach($deadlines as $deadline)
                                <div class="flex items-center justify-between gap-3 border-t border-gray-100 py-3 first:border-0"><span class="text-sm font-semibold text-[#243324]">{{ $deadline['title'] }}</span><span class="shrink-0 text-xs {{ $deadline['priority'] === 'high' ? 'font-bold text-red-700' : 'text-gray-500' }}">{{ $deadline['deadline']->format('M j') }}</span></div>
                            @endforeach
                        </section>
                    @endif
                </div>
            </div>
        </section>

        @if($hasAgriculture)
            <section class="erp-workspace-panel" id="dashboard-panel-agriculture" role="tabpanel" aria-labelledby="dashboard-tab-agriculture" tabindex="0" data-erp-workspace-panel="agriculture" hidden>
                <div class="space-y-5">
                    @foreach(['ffrms' => 'Farmer & farm registration', 'apms' => 'Production & monitoring'] as $slug => $title)
                        @if(isset($sections[$slug]))
                            <section class="erp-surface p-5 md:p-6">
                                <div class="erp-section-header"><div><p class="erp-page-eyebrow">{{ strtoupper($slug) }}</p><h2 class="erp-section-title">{{ $title }}</h2></div><a class="erp-section-link" href="{{ route('modules.'.$slug.'.dashboard') }}">Open module <span aria-hidden="true">→</span></a></div>
                                <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                    @foreach($sections[$slug]['cards'] as $card)<article class="rounded-xl bg-[#f8faf7] p-4"><p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p><p class="mt-1 text-2xl font-extrabold text-[#153d2b]">{{ is_numeric($card['value']) ? number_format((float) $card['value'], str_contains((string) $card['value'], '.') ? 2 : 0) : $card['value'] }}</p><p class="mt-1 text-xs leading-5 text-gray-500">{{ $card['note'] }}</p></article>@endforeach
                                </div>
                                <div class="grid gap-5 lg:grid-cols-2">
                                    @foreach($sections[$slug]['breakdowns'] as $breakdown)
                                        <div class="min-w-0 rounded-xl border border-[#e8eee8] p-4">
                                            <h3 class="text-sm font-extrabold text-[#243324]">{{ $breakdown['title'] }}</h3>
                                            @if(count($breakdown['rows']))
                                                @php($maximum = max(1, (float) collect($breakdown['rows'])->max('value')))
                                                <div class="mt-4 space-y-3" role="list" aria-label="{{ $breakdown['title'] }}">
                                                    @foreach($breakdown['rows'] as $row)
                                                        <div role="listitem"><div class="flex items-baseline justify-between gap-3 text-xs"><span class="min-w-0 truncate font-semibold text-gray-700">{{ $row['label'] }}</span><span class="shrink-0 font-extrabold text-[#153d2b]">{{ is_numeric($row['value']) ? number_format((float) $row['value'], str_contains((string) $row['value'], '.') ? 2 : 0) : $row['value'] }} {{ $row['unit'] }}</span></div><div class="mt-1.5 h-2 overflow-hidden rounded-full bg-[#e7f0e8]" aria-hidden="true"><div class="h-full rounded-full bg-[#26865c]" style="width: {{ min(100, max(2, ((float) $row['value'] / $maximum) * 100)) }}%"></div></div>@if(!empty($row['detail']))<p class="mt-1 text-[.68rem] text-gray-500">{{ $row['detail'] }}</p>@endif</div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="erp-empty mt-3">No records match the selected period.</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if($hasResources)
            <section class="erp-workspace-panel" id="dashboard-panel-resources" role="tabpanel" aria-labelledby="dashboard-tab-resources" tabindex="0" data-erp-workspace-panel="resources" hidden>
                <div class="space-y-5">
                    @foreach(['aisms' => 'Inventory and supply', 'aasms' => 'Assistance and subsidies', 'aeams' => 'Equipment and assets'] as $slug => $title)
                        @if(isset($sections[$slug]))
                            <section class="erp-surface p-5 md:p-6">
                                <div class="erp-section-header"><div><p class="erp-page-eyebrow">{{ strtoupper($slug) }}</p><h2 class="erp-section-title">{{ $title }}</h2></div><a class="erp-section-link" href="{{ route('modules.'.$slug.'.dashboard') }}">Open module <span aria-hidden="true">→</span></a></div>
                                <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                                    @foreach($sections[$slug]['cards'] as $card)<article class="rounded-xl bg-[#f8faf7] p-4"><p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p><p class="mt-1 break-words text-2xl font-extrabold text-[#153d2b]">{{ is_numeric($card['value']) ? number_format((float) $card['value'], str_contains((string) $card['value'], '.') ? 2 : 0) : $card['value'] }}</p><p class="mt-1 text-xs leading-5 text-gray-500">{{ $card['note'] }}</p></article>@endforeach
                                </div>
                                @if(isset($sections[$slug]['stock_items']))
                                    <div class="mt-5 overflow-hidden rounded-xl border border-[#e8eee8]"><div class="flex items-center justify-between gap-3 px-4 py-3"><h3 class="text-sm font-extrabold text-[#243324]">Usable stock by supply item</h3><span class="text-xs text-gray-500">First {{ count($sections[$slug]['stock_items']) }} active catalog items</span></div><div class="overflow-x-auto"><table class="min-w-[38rem] w-full text-left text-sm"><thead class="bg-[#f8faf7] text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Supply</th><th class="px-4 py-3">Usable stock</th><th class="px-4 py-3">Minimum</th><th class="px-4 py-3">Status</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($sections[$slug]['stock_items'] as $item)<tr><td class="px-4 py-3"><span class="font-bold text-[#243324]">{{ $item['name'] }}</span><span class="ml-2 text-xs text-gray-500">{{ $item['code'] }}</span></td><td class="px-4 py-3 font-semibold">{{ number_format($item['usable'], 2) }} {{ $item['unit'] }}</td><td class="px-4 py-3">{{ number_format($item['minimum'], 2) }} {{ $item['unit'] }}</td><td class="px-4 py-3"><span class="erp-pill {{ $item['low'] ? 'erp-pill-warning' : 'erp-pill-success' }}">{{ $item['low'] ? 'Low stock' : 'In range' }}</span></td></tr>@empty<tr><td colspan="4" class="erp-empty-cell">No active supply items are registered.</td></tr>@endforelse</tbody></table></div></div>
                                @else
                                    <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                        @foreach($sections[$slug]['breakdowns'] as $breakdown)
                                            <div class="rounded-xl border border-[#e8eee8] p-4"><h3 class="text-sm font-extrabold text-[#243324]">{{ $breakdown['title'] }}</h3>@if(count($breakdown['rows']))<div class="mt-3 divide-y divide-gray-100">@foreach($breakdown['rows'] as $row)<div class="flex items-center justify-between gap-3 py-2 text-sm"><span class="text-gray-600">{{ $row['label'] }}</span><span class="shrink-0 font-bold text-[#153d2b]">{{ is_numeric($row['value']) ? number_format((float) $row['value'], str_contains((string) $row['value'], '.') ? 2 : 0) : $row['value'] }} {{ $row['unit'] }}</span></div>@endforeach</div>@else<p class="erp-empty mt-2">No records match the selected period.</p>@endif</div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if($hasFinance)
            <section class="erp-workspace-panel" id="dashboard-panel-finance" role="tabpanel" aria-labelledby="dashboard-tab-finance" tabindex="0" data-erp-workspace-panel="finance" hidden>
                @php($finance = $sections['abpms'])
                <section class="erp-surface p-5 md:p-6">
                    <div class="erp-section-header"><div><p class="erp-page-eyebrow">ABPMS</p><h2 class="erp-section-title">Budget and procurement</h2></div><a class="erp-section-link" href="{{ route('modules.abpms.dashboard') }}">Open ABPMS <span aria-hidden="true">→</span></a></div>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">@foreach($finance['cards'] as $card)<article class="rounded-xl bg-[#f8faf7] p-4"><p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p><p class="mt-1 break-words text-2xl font-extrabold text-[#153d2b]">{{ is_numeric($card['value']) ? number_format((float) $card['value'], str_contains((string) $card['value'], '.') ? 2 : 0) : $card['value'] }}</p><p class="mt-1 text-xs leading-5 text-gray-500">{{ $card['note'] }}</p></article>@endforeach</div>
                    <div class="mt-5 grid gap-5 lg:grid-cols-2">@foreach($finance['breakdowns'] as $breakdown)<div class="rounded-xl border border-[#e8eee8] p-4"><h3 class="text-sm font-extrabold text-[#243324]">{{ $breakdown['title'] }}</h3>@if(count($breakdown['rows']))<div class="mt-3 divide-y divide-gray-100">@foreach($breakdown['rows'] as $row)<div class="flex items-center justify-between gap-3 py-2 text-sm"><span class="text-gray-600">{{ $row['label'] }}</span><span class="shrink-0 font-bold text-[#153d2b]">{{ is_numeric($row['value']) ? number_format((float) $row['value'], str_contains((string) $row['value'], '.') ? 2 : 0) : $row['value'] }} {{ $row['unit'] }}</span></div>@endforeach</div>@else<p class="erp-empty mt-2">No records match the selected period.</p>@endif</div>@endforeach</div>
                    <div class="mt-5 overflow-hidden rounded-xl border border-[#e8eee8]"><div class="px-4 py-3"><h3 class="text-sm font-extrabold text-[#243324]">Active allocations and ledger balance</h3><p class="mt-1 text-xs text-gray-500">Available, committed, and actual amounts use the shared BudgetService definitions.</p></div><div class="overflow-x-auto"><table class="min-w-[48rem] w-full text-left text-sm"><thead class="bg-[#f8faf7] text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3">Allocation</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">Authorized</th><th class="px-4 py-3">Committed</th><th class="px-4 py-3">Expenditure</th><th class="px-4 py-3">Available</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($finance['allocations'] as $allocation)<tr><td class="px-4 py-3"><span class="font-bold">{{ $allocation['program'] }}</span><span class="ml-2 text-xs text-gray-500">{{ $allocation['reference'] }}</span></td><td class="px-4 py-3">{{ $allocation['period'] }}</td><td class="px-4 py-3">₱{{ $allocation['authorized'] }}</td><td class="px-4 py-3">₱{{ $allocation['committed'] }}</td><td class="px-4 py-3">₱{{ $allocation['actual'] }}</td><td class="px-4 py-3 font-bold">₱{{ $allocation['available'] }}</td></tr>@empty<tr><td colspan="6" class="erp-empty-cell">No active budget allocation overlaps this period.</td></tr>@endforelse</tbody></table></div></div>
                </section>
            </section>
        @endif

        @if($canViewReports)
            <section class="erp-workspace-panel" id="dashboard-panel-reports" role="tabpanel" aria-labelledby="dashboard-tab-reports" tabindex="0" data-erp-workspace-panel="reports" hidden>
                <section class="erp-surface p-6 md:p-8"><span class="erp-pill">{{ $reportCount }} authorized report types</span><h2 class="mt-4 text-xl font-extrabold text-[#153d2b]">Consolidated agricultural reporting</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">Open the reporting workspace to select a source domain, set reporting dates and supported filters, inspect aggregate rows, and export only the modules your role can view and export.</p><a class="mt-5 inline-flex rounded-lg bg-[#153d2b] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#26865c] focus:outline-none focus:ring-2 focus:ring-[#26865c] focus:ring-offset-2" href="{{ route('erp.reports.index') }}">Open reports <span class="ml-2" aria-hidden="true">→</span></a></section>
            </section>
        @endif
    </div>
</x-layouts.app>
