<x-layouts.app>
    <x-slot name="header">Agricultural Production &amp; Monitoring</x-slot>
    <x-slot name="description">Manage agricultural production, field assessments and monitoring records.</x-slot>

    @php
        $tabs = [
            'overview' => 'Overview',
            'production' => 'Production records',
            'monitoring' => 'Field monitoring',
            'seasons' => 'Seasons & reports',
        ];
        $recordTypes = ['crops' => 'Crops', 'livestock' => 'Livestock', 'fisheries' => 'Fisheries'];
        $monitoringTypes = ['incidents' => 'Pest & disease', 'visits' => 'Farm visits'];
    @endphp

    <div class="apms-workspace" data-apms-workspace
        data-initial-tab="{{ $selectedTab }}"
        data-initial-record-type="{{ $selectedRecordType }}"
        data-initial-monitoring-type="{{ $selectedMonitoringType }}"
        data-error-form="{{ old('_apms_form') }}"
        data-error-record="{{ old('_apms_record_id') }}">

        @if(session('status'))
            <div class="erp-alert erp-alert-success mb-5" role="status"><span aria-hidden="true">✓</span><span>{{ session('status') }}</span></div>
        @endif
        @if($errors->any())
            <div class="erp-alert erp-alert-danger mb-5" role="alert" aria-labelledby="apms-error-heading">
                <span aria-hidden="true">!</span>
                <div><strong id="apms-error-heading">Review the highlighted fields.</strong><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif

        <section class="apms-stat-grid" aria-label="Production summary">
            @foreach([
                ['label' => 'Crop records', 'value' => $stats['crops'], 'mark' => 'CR'],
                ['label' => 'Livestock records', 'value' => $stats['livestock'], 'mark' => 'LS'],
                ['label' => 'Fisheries records', 'value' => $stats['fisheries'], 'mark' => 'FS'],
                ['label' => 'Open incidents', 'value' => $stats['incidents'], 'mark' => '!', 'alert' => true],
            ] as $stat)
                <article class="apms-stat">
                    <span class="apms-stat-mark {{ ($stat['alert'] ?? false) ? 'apms-stat-mark--alert' : '' }}" aria-hidden="true">{{ $stat['mark'] }}</span>
                    <div><p>{{ $stat['label'] }}</p><strong>{{ number_format($stat['value']) }}</strong></div>
                </article>
            @endforeach
        </section>

        <nav class="apms-tabs" role="tablist" aria-label="APMS workspaces">
            @foreach($tabs as $key => $label)
                <a id="apms-tab-{{ $key }}" class="apms-tab {{ $selectedTab === $key ? 'is-active' : '' }}"
                    role="tab" aria-controls="apms-panel-{{ $key }}" aria-selected="{{ $selectedTab === $key ? 'true' : 'false' }}"
                    tabindex="{{ $selectedTab === $key ? '0' : '-1' }}"
                    href="{{ route('modules.apms.dashboard', array_merge(request()->query(), ['tab' => $key])) }}"
                    data-apms-primary-tab="{{ $key }}">{{ $label }}</a>
            @endforeach
        </nav>

        <section id="apms-panel-overview" class="apms-panel" role="tabpanel" aria-labelledby="apms-tab-overview" tabindex="0" @if($selectedTab !== 'overview') hidden @endif>
            <div class="apms-overview-grid">
                <section class="erp-surface apms-surface">
                    <div class="apms-section-heading"><div><p class="apms-kicker">At a glance</p><h2>Production overview</h2><p>Recent records linked to verified farmers and farms.</p></div></div>
                    <div class="apms-overview-metrics">
                        <div><span>Production records</span><strong>{{ number_format($stats['crops'] + $stats['livestock'] + $stats['fisheries']) }}</strong></div>
                        <div><span>Monitoring records</span><strong>{{ number_format($stats['monitoring']) }}</strong></div>
                        <div><span>Open incidents</span><strong>{{ number_format($stats['incidents']) }}</strong></div>
                    </div>
                    <div class="apms-activity-heading"><h3>Recent activity</h3><a href="{{ route('modules.apms.dashboard', ['tab' => 'monitoring']) }}" data-apms-primary-tab="monitoring">View monitoring</a></div>
                    @forelse($recentActivity as $activity)
                        <article class="apms-activity-row">
                            <span class="apms-activity-dot" aria-hidden="true"></span>
                            <div class="min-w-0"><strong>{{ $activity['title'] }}</strong><p>{{ $activity['type'] }} <span aria-hidden="true">·</span> {{ $activity['farm'] ?: 'Farm not recorded' }}</p></div>
                            <time datetime="{{ $activity['date']?->toDateString() }}">{{ $activity['date']?->format('M j, Y') ?: 'Date not recorded' }}</time>
                        </article>
                    @empty
                        <div class="erp-empty-state apms-empty-state"><strong>No production activity yet</strong><p>Production and monitoring records appear here as staff enter verified field information.</p>@if($canCreate)<button class="erp-button erp-button-primary" type="button" data-apms-open-editor="crop" data-apms-title="Add crop record">Add a crop record</button>@endif</div>
                    @endforelse
                </section>

                <aside class="apms-overview-aside">
                    <section class="erp-surface apms-surface">
                        <div class="apms-section-heading"><div><p class="apms-kicker">Shortcuts</p><h2>Common tasks</h2><p>Open only the editor you need.</p></div></div>
                        <div class="apms-quick-actions">
                            @if($canCreate)
                                <button type="button" class="apms-quick-action" data-apms-open-editor="crop" data-apms-title="Add crop record"><span aria-hidden="true">CR</span><span><strong>Record crop production</strong><small>Crop, area, planting and yield</small></span><b aria-hidden="true">→</b></button>
                                <button type="button" class="apms-quick-action" data-apms-open-editor="incident" data-apms-title="Report pest or disease incident"><span aria-hidden="true">!</span><span><strong>Report an incident</strong><small>Capture severity and affected crop</small></span><b aria-hidden="true">→</b></button>
                                <button type="button" class="apms-quick-action" data-apms-open-editor="monitoring" data-apms-title="Add farm monitoring visit"><span aria-hidden="true">FV</span><span><strong>Log a farm visit</strong><small>Record findings and recommendations</small></span><b aria-hidden="true">→</b></button>
                            @else
                                <p class="apms-readonly-note">This account can review APMS records but cannot create operational entries.</p>
                            @endif
                        </div>
                    </section>
                    <section class="apms-guidance">
                        <span class="apms-guidance-icon" aria-hidden="true">i</span>
                        <div><strong>Verified records</strong><p>New production entries must reference a verified farmer and one of that farmer’s registered farms.</p></div>
                    </section>
                </aside>
            </div>
        </section>

        <section id="apms-panel-production" class="apms-panel" role="tabpanel" aria-labelledby="apms-tab-production" tabindex="0" @if($selectedTab !== 'production') hidden @endif>
            <div class="apms-workspace-heading"><div><p class="apms-kicker">Production data</p><h2>Production records</h2><p>Choose a record type to review, search, edit or add entries.</p></div>
                @if($canExport)<a class="erp-button erp-button-secondary" href="{{ route('modules.apms.dashboard', ['tab' => 'seasons']) }}" data-apms-primary-tab="seasons">Reports &amp; export</a>@endif
            </div>
            <nav class="apms-subtabs" role="tablist" aria-label="Production record type">
                @foreach($recordTypes as $key => $label)
                    <a id="apms-record-tab-{{ $key }}" class="apms-subtab {{ $selectedRecordType === $key ? 'is-active' : '' }}" role="tab"
                        aria-controls="apms-record-panel-{{ $key }}" aria-selected="{{ $selectedRecordType === $key ? 'true' : 'false' }}"
                        tabindex="{{ $selectedRecordType === $key ? '0' : '-1' }}"
                        href="{{ route('modules.apms.dashboard', array_merge(request()->query(), ['tab' => 'production', 'record_type' => $key])) }}"
                        data-apms-record-tab="{{ $key }}">{{ $label }}</a>
                @endforeach
            </nav>

            @foreach($recordTypes as $key => $label)
                <section id="apms-record-panel-{{ $key }}" class="apms-record-panel" role="tabpanel" aria-labelledby="apms-record-tab-{{ $key }}" tabindex="0" @if($selectedRecordType !== $key) hidden @endif>
                    <div class="apms-record-toolbar">
                        <form method="GET" action="{{ route('modules.apms.dashboard') }}" class="apms-filter-form">
                            <input type="hidden" name="tab" value="production"><input type="hidden" name="record_type" value="{{ $key }}">
                            <div class="apms-search-field"><label class="sr-only" for="record-search-{{ $key }}">Search {{ strtolower($label) }}</label><input id="record-search-{{ $key }}" class="erp-input" type="search" name="record_search" value="{{ $recordSearch }}" placeholder="Search {{ strtolower($label) }}, farms or farmers"></div>
                            @if($key === 'crops')
                                <label class="sr-only" for="crop-status-filter">Crop status</label><select id="crop-status-filter" class="erp-input" name="crop_status"><option value="">All statuses</option>@foreach(['planned','growing','harvested','cancelled'] as $status)<option value="{{ $status }}" @selected(request('crop_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                            @elseif($key === 'livestock')
                                <label class="sr-only" for="livestock-status-filter">Livestock status</label><select id="livestock-status-filter" class="erp-input" name="livestock_status"><option value="">All statuses</option>@foreach(['active','sold','deceased'] as $status)<option value="{{ $status }}" @selected(request('livestock_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                            @endif
                            <button class="erp-button erp-button-secondary" type="submit">Search</button>
                        </form>
                        @if($canCreate)<button type="button" class="erp-button erp-button-primary" data-apms-open-editor="{{ $key === 'crops' ? 'crop' : ($key === 'livestock' ? 'livestock' : 'fishery') }}" data-apms-title="Add {{ $key === 'crops' ? 'crop' : ($key === 'livestock' ? 'livestock' : 'fisheries') }} record">Add record</button>@endif
                    </div>

                    @if($key === 'crops')
                        <div class="apms-table-wrap" role="region" aria-label="Crop production records" tabindex="0"><table class="apms-table apms-table--crops"><thead><tr><th scope="col">Crop</th><th scope="col">Farmer &amp; farm</th><th scope="col">Season</th><th scope="col">Area / yield</th><th scope="col">Status</th>@if($canUpdate)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead><tbody>
                            @forelse($crops as $crop)
                                <tr><td><strong>{{ $crop->crop_type }}</strong><span class="apms-table-meta">{{ $crop->variety ?: 'Variety not specified' }}</span></td><td>{{ $crop->farm?->farmer?->user?->name ?: 'Farmer' }}<span class="apms-table-meta">{{ $crop->farm?->name ?: 'Farm not recorded' }}</span></td><td>{{ $crop->season?->name ?: 'Unassigned' }}<span class="apms-table-meta">{{ $crop->planted_on?->format('M j, Y') ?: 'Planting date not recorded' }}</span></td><td>{{ $crop->area_planted ? number_format((float) $crop->area_planted, 2).' '.str_replace('_', ' ', $crop->area_unit) : '—' }}<span class="apms-table-meta">{{ $crop->yield_quantity !== null ? number_format((float) $crop->yield_quantity, 2).' '.$crop->yield_unit : 'Yield not recorded' }}</span></td><td><span class="erp-pill {{ $crop->status === 'harvested' ? 'erp-pill-success' : ($crop->status === 'cancelled' ? 'erp-pill-neutral' : 'erp-pill-warning') }}">{{ ucfirst($crop->status) }}</span></td>
                                    @if($canUpdate)<td class="apms-actions-cell"><button type="button" class="apms-row-action" data-apms-open-editor="crop" data-apms-mode="edit" data-apms-title="Edit crop record" data-apms-record-id="{{ $crop->id }}" data-apms-action="{{ route('modules.apms.crops.update', ['record' => $crop->id, 'tab' => 'production', 'record_type' => 'crops']) }}" data-apms-record="{{ json_encode(['farmer_id' => $crop->farmer_id, 'farm_id' => $crop->farm_id, 'season_id' => $crop->season_id, 'crop_type' => $crop->crop_type, 'variety' => $crop->variety, 'planted_on' => $crop->planted_on?->format('Y-m-d'), 'harvested_on' => $crop->harvested_on?->format('Y-m-d'), 'area_planted' => $crop->area_planted, 'area_unit' => $crop->area_unit, 'yield_quantity' => $crop->yield_quantity, 'yield_unit' => $crop->yield_unit, 'status' => $crop->status, 'notes' => $crop->notes]) }}" aria-haspopup="dialog">Edit</button></td>@endif
                                </tr>
                            @empty<tr><td colspan="{{ $canUpdate ? 6 : 5 }}"><div class="apms-table-empty"><strong>No crop records match this view.</strong><span>Try changing the search or status filter, or add a crop production record.</span></div></td></tr>@endforelse
                        </tbody></table></div><div class="apms-pagination">{{ $crops->links() }}</div>
                    @elseif($key === 'livestock')
                        <div class="apms-table-wrap" role="region" aria-label="Livestock records" tabindex="0"><table class="apms-table apms-table--livestock"><thead><tr><th scope="col">Animal</th><th scope="col">Farmer &amp; farm</th><th scope="col">Quantity</th><th scope="col">Recorded</th><th scope="col">Status</th>@if($canUpdate)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead><tbody>
                            @forelse($livestock as $record)
                                <tr><td><strong>{{ $record->animal_type }}</strong><span class="apms-table-meta">{{ $record->breed ?: 'Breed not specified' }}</span></td><td>{{ $record->farm?->farmer?->user?->name ?: 'Farmer' }}<span class="apms-table-meta">{{ $record->farm?->name ?: 'Farm not recorded' }}</span></td><td>{{ number_format($record->quantity) }} head</td><td>{{ $record->recorded_on?->format('M j, Y') ?: '—' }}</td><td><span class="erp-pill {{ $record->status === 'active' ? 'erp-pill-success' : 'erp-pill-neutral' }}">{{ ucfirst($record->status) }}</span></td>
                                    @if($canUpdate)<td class="apms-actions-cell"><button type="button" class="apms-row-action" data-apms-open-editor="livestock" data-apms-mode="edit" data-apms-title="Edit livestock record" data-apms-record-id="{{ $record->id }}" data-apms-action="{{ route('modules.apms.livestock.update', ['record' => $record->id, 'tab' => 'production', 'record_type' => 'livestock']) }}" data-apms-record="{{ json_encode(['farmer_id' => $record->farmer_id, 'farm_id' => $record->farm_id, 'animal_type' => $record->animal_type, 'breed' => $record->breed, 'quantity' => $record->quantity, 'recorded_on' => $record->recorded_on?->format('Y-m-d'), 'status' => $record->status, 'notes' => $record->notes]) }}" aria-haspopup="dialog">Edit</button></td>@endif
                                </tr>
                            @empty<tr><td colspan="{{ $canUpdate ? 6 : 5 }}"><div class="apms-table-empty"><strong>No livestock records match this view.</strong><span>Try another search or add a livestock record.</span></div></td></tr>@endforelse
                        </tbody></table></div><div class="apms-pagination">{{ $livestock->links() }}</div>
                    @else
                        <div class="apms-table-wrap" role="region" aria-label="Fisheries records" tabindex="0"><table class="apms-table apms-table--fisheries"><thead><tr><th scope="col">Fishery</th><th scope="col">Farmer &amp; farm</th><th scope="col">Production</th><th scope="col">Recorded</th>@if($canUpdate)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead><tbody>
                            @forelse($fisheries as $record)
                                <tr><td><strong>{{ $record->fishery_type }}</strong><span class="apms-table-meta">{{ $record->species ?: 'Species not specified' }}</span></td><td>{{ $record->farm?->farmer?->user?->name ?: 'Farmer' }}<span class="apms-table-meta">{{ $record->farm?->name ?: 'Farm not recorded' }}</span></td><td>{{ $record->production_quantity !== null ? number_format((float) $record->production_quantity, 2).' '.$record->production_unit : 'Not recorded' }}</td><td>{{ $record->recorded_on?->format('M j, Y') ?: '—' }}</td>
                                    @if($canUpdate)<td class="apms-actions-cell"><button type="button" class="apms-row-action" data-apms-open-editor="fishery" data-apms-mode="edit" data-apms-title="Edit fisheries record" data-apms-record-id="{{ $record->id }}" data-apms-action="{{ route('modules.apms.fisheries.update', ['record' => $record->id, 'tab' => 'production', 'record_type' => 'fisheries']) }}" data-apms-record="{{ json_encode(['farmer_id' => $record->farmer_id, 'farm_id' => $record->farm_id, 'fishery_type' => $record->fishery_type, 'species' => $record->species, 'production_quantity' => $record->production_quantity, 'production_unit' => $record->production_unit, 'recorded_on' => $record->recorded_on?->format('Y-m-d'), 'notes' => $record->notes]) }}" aria-haspopup="dialog">Edit</button></td>@endif
                                </tr>
                            @empty<tr><td colspan="{{ $canUpdate ? 5 : 4 }}"><div class="apms-table-empty"><strong>No fisheries records match this view.</strong><span>Try another search or add a fisheries record.</span></div></td></tr>@endforelse
                        </tbody></table></div><div class="apms-pagination">{{ $fisheries->links() }}</div>
                    @endif
                </section>
            @endforeach
        </section>

        <section id="apms-panel-monitoring" class="apms-panel" role="tabpanel" aria-labelledby="apms-tab-monitoring" tabindex="0" @if($selectedTab !== 'monitoring') hidden @endif>
            <div class="apms-workspace-heading"><div><p class="apms-kicker">Field operations</p><h2>Field monitoring</h2><p>Review pest and disease events alongside farm visit records.</p></div></div>
            <nav class="apms-subtabs" role="tablist" aria-label="Field monitoring record type">
                @foreach($monitoringTypes as $key => $label)
                    <a id="apms-monitoring-tab-{{ $key }}" class="apms-subtab {{ $selectedMonitoringType === $key ? 'is-active' : '' }}" role="tab" aria-controls="apms-monitoring-panel-{{ $key }}" aria-selected="{{ $selectedMonitoringType === $key ? 'true' : 'false' }}" tabindex="{{ $selectedMonitoringType === $key ? '0' : '-1' }}" href="{{ route('modules.apms.dashboard', array_merge(request()->query(), ['tab' => 'monitoring', 'monitoring_type' => $key])) }}" data-apms-monitoring-tab="{{ $key }}">{{ $label }}</a>
                @endforeach
            </nav>

            @foreach($monitoringTypes as $key => $label)
                <section id="apms-monitoring-panel-{{ $key }}" class="apms-record-panel" role="tabpanel" aria-labelledby="apms-monitoring-tab-{{ $key }}" tabindex="0" @if($selectedMonitoringType !== $key) hidden @endif>
                    <div class="apms-record-toolbar"><form method="GET" action="{{ route('modules.apms.dashboard') }}" class="apms-filter-form"><input type="hidden" name="tab" value="monitoring"><input type="hidden" name="monitoring_type" value="{{ $key }}"><div class="apms-search-field"><label class="sr-only" for="monitoring-search-{{ $key }}">Search {{ strtolower($label) }}</label><input id="monitoring-search-{{ $key }}" class="erp-input" type="search" name="record_search" value="{{ $recordSearch }}" placeholder="Search farm or {{ strtolower($label) }}"></div>
                                @if($key === 'incidents')<label class="sr-only" for="incident-status-filter">Incident status</label><select id="incident-status-filter" class="erp-input" name="incident_status"><option value="">All statuses</option>@foreach(['reported','investigating','resolved','closed'] as $status)<option value="{{ $status }}" @selected(request('incident_status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select><label class="sr-only" for="incident-severity-filter">Incident severity</label><select id="incident-severity-filter" class="erp-input" name="severity"><option value="">All severity</option>@foreach(['low','medium','high','critical'] as $severity)<option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ ucfirst($severity) }}</option>@endforeach</select>@else<label class="sr-only" for="monitoring-status-filter">Visit status</label><select id="monitoring-status-filter" class="erp-input" name="monitoring_status"><option value="">All statuses</option>@foreach(['recorded','follow_up','closed'] as $status)<option value="{{ $status }}" @selected(request('monitoring_status') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>@endif<button class="erp-button erp-button-secondary" type="submit">Search</button></form>
                        @if($canCreate)<button type="button" class="erp-button erp-button-primary" data-apms-open-editor="{{ $key === 'incidents' ? 'incident' : 'monitoring' }}" data-apms-title="{{ $key === 'incidents' ? 'Report pest or disease incident' : 'Add farm monitoring visit' }}">{{ $key === 'incidents' ? 'Report incident' : 'Add farm visit' }}</button>@endif
                    </div>

                    @if($key === 'incidents')
                        <div class="apms-table-wrap" role="region" aria-label="Pest and disease incidents" tabindex="0"><table class="apms-table apms-table--incidents"><thead><tr><th scope="col">Incident</th><th scope="col">Farm</th><th scope="col">Severity</th><th scope="col">Reported</th><th scope="col">Status</th>@if($canUpdate)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead><tbody>
                            @forelse($incidents as $incident)
                                <tr><td><strong>{{ $incident->incident_type }}</strong><span class="apms-table-meta">{{ $incident->affected_crop_or_animal }}</span></td><td>{{ $incident->farm?->name ?: 'Farm not recorded' }}</td><td><span class="erp-pill {{ in_array($incident->severity, ['high','critical'], true) ? 'erp-pill-danger' : ($incident->severity === 'medium' ? 'erp-pill-warning' : 'erp-pill-neutral') }}">{{ ucfirst($incident->severity) }}</span></td><td>{{ $incident->reported_on?->format('M j, Y') ?: '—' }}</td><td><span class="erp-pill {{ in_array($incident->status, ['reported','investigating'], true) ? 'erp-pill-warning' : 'erp-pill-success' }}">{{ ucfirst($incident->status) }}</span></td>
                                    @if($canUpdate)<td class="apms-actions-cell"><button type="button" class="apms-row-action" data-apms-open-editor="incident" data-apms-mode="edit" data-apms-title="Edit incident" data-apms-record-id="{{ $incident->id }}" data-apms-action="{{ route('modules.apms.incidents.update', ['record' => $incident->id, 'tab' => 'monitoring', 'monitoring_type' => 'incidents']) }}" data-apms-record="{{ json_encode(['farmer_id' => $incident->farmer_id, 'farm_id' => $incident->farm_id, 'incident_type' => $incident->incident_type, 'affected_crop_or_animal' => $incident->affected_crop_or_animal, 'reported_on' => $incident->reported_on?->format('Y-m-d'), 'severity' => $incident->severity, 'status' => $incident->status, 'description' => $incident->description, 'action_taken' => $incident->action_taken]) }}" aria-haspopup="dialog">Edit</button></td>@endif
                                </tr>
                            @empty<tr><td colspan="{{ $canUpdate ? 6 : 5 }}"><div class="apms-table-empty"><strong>No incidents match this view.</strong><span>Change your filters or report a new incident.</span></div></td></tr>@endforelse
                        </tbody></table></div><div class="apms-pagination">{{ $incidents->links() }}</div>
                    @else
                        <div class="apms-table-wrap" role="region" aria-label="Farm monitoring visits" tabindex="0"><table class="apms-table apms-table--visits"><thead><tr><th scope="col">Visit type</th><th scope="col">Farm</th><th scope="col">Findings</th><th scope="col">Visit date</th><th scope="col">Status</th>@if($canUpdate)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead><tbody>
                            @forelse($monitoring as $record)
                                <tr><td><strong>{{ str_replace('_', ' ', ucfirst($record->monitoring_type)) }}</strong></td><td>{{ $record->farm?->name ?: 'Farm not recorded' }}</td><td><span class="apms-truncate">{{ $record->findings }}</span><span class="apms-table-meta">{{ $record->recommendations ?: 'No recommendation recorded' }}</span></td><td>{{ $record->visited_on?->format('M j, Y') ?: '—' }}</td><td><span class="erp-pill {{ $record->status === 'closed' ? 'erp-pill-success' : 'erp-pill-warning' }}">{{ str_replace('_', ' ', ucfirst($record->status)) }}</span></td>
                                    @if($canUpdate)<td class="apms-actions-cell"><button type="button" class="apms-row-action" data-apms-open-editor="monitoring" data-apms-mode="edit" data-apms-title="Edit farm monitoring visit" data-apms-record-id="{{ $record->id }}" data-apms-action="{{ route('modules.apms.monitoring.update', ['record' => $record->id, 'tab' => 'monitoring', 'monitoring_type' => 'visits']) }}" data-apms-record="{{ json_encode(['farmer_id' => $record->farmer_id, 'farm_id' => $record->farm_id, 'visited_on' => $record->visited_on?->format('Y-m-d'), 'monitoring_type' => $record->monitoring_type, 'findings' => $record->findings, 'recommendations' => $record->recommendations, 'status' => $record->status]) }}" aria-haspopup="dialog">Edit</button></td>@endif
                                </tr>
                            @empty<tr><td colspan="{{ $canUpdate ? 6 : 5 }}"><div class="apms-table-empty"><strong>No farm visits match this view.</strong><span>Add a monitoring visit after an authorized field assessment.</span></div></td></tr>@endforelse
                        </tbody></table></div><div class="apms-pagination">{{ $monitoring->links() }}</div>
                    @endif
                </section>
            @endforeach
        </section>

        <section id="apms-panel-seasons" class="apms-panel" role="tabpanel" aria-labelledby="apms-tab-seasons" tabindex="0" @if($selectedTab !== 'seasons') hidden @endif>
            <div class="apms-workspace-heading"><div><p class="apms-kicker">Planning &amp; reporting</p><h2>Seasons &amp; reports</h2><p>Maintain production seasons and export records for a selected date range.</p></div>
                @if($canCreate)<button class="erp-button erp-button-primary" type="button" data-apms-open-editor="season" data-apms-title="Add agricultural season">Add season</button>@endif
            </div>
            <div class="apms-season-grid">
                <section class="erp-surface apms-surface">
                    <div class="apms-section-heading"><div><h3>Agricultural seasons</h3><p>Season records remain available for historical production reporting.</p></div></div>
                    <form method="GET" action="{{ route('modules.apms.dashboard') }}" class="apms-season-search"><input type="hidden" name="tab" value="seasons"><label class="sr-only" for="season-search">Search seasons</label><input id="season-search" class="erp-input" type="search" name="season_search" value="{{ $seasonSearch }}" placeholder="Search seasons by name"><button class="erp-button erp-button-secondary" type="submit">Search</button></form>
                    <div class="apms-table-wrap" role="region" aria-label="Agricultural seasons" tabindex="0"><table class="apms-table apms-table--seasons"><thead><tr><th scope="col">Season</th><th scope="col">Dates</th><th scope="col">Status</th>@if($canUpdate)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr></thead><tbody>
                        @forelse($seasonRecords as $season)
                            <tr><td><strong>{{ $season->name }}</strong></td><td>{{ $season->starts_on?->format('M j, Y') }} – {{ $season->ends_on?->format('M j, Y') }}</td><td><span class="erp-pill {{ $season->status === 'open' ? 'erp-pill-success' : 'erp-pill-neutral' }}">{{ ucfirst($season->status) }}</span></td>
                                @if($canUpdate)<td class="apms-actions-cell"><button type="button" class="apms-row-action" data-apms-open-editor="season" data-apms-mode="edit" data-apms-title="Edit agricultural season" data-apms-record-id="{{ $season->id }}" data-apms-action="{{ route('modules.apms.seasons.update', ['season' => $season->id, 'tab' => 'seasons']) }}" data-apms-record="{{ json_encode(['name' => $season->name, 'starts_on' => $season->starts_on?->format('Y-m-d'), 'ends_on' => $season->ends_on?->format('Y-m-d'), 'status' => $season->status]) }}" aria-haspopup="dialog">Edit</button></td>@endif
                            </tr>
                        @empty<tr><td colspan="{{ $canUpdate ? 4 : 3 }}"><div class="apms-table-empty"><strong>No seasons configured.</strong><span>Add a season to organize new production records and reports.</span></div></td></tr>@endforelse
                    </tbody></table></div><div class="apms-pagination">{{ $seasonRecords->links() }}</div>
                </section>
                <section class="erp-surface apms-surface apms-report-card">
                    <p class="apms-kicker">Export</p><h3>Production report</h3><p>Download the existing APMS CSV report. Choose a date range to limit all included record types.</p>
                    @if($canExport)
                        <form method="GET" action="{{ route('modules.apms.export') }}" class="apms-report-form"><div class="apms-field-grid apms-field-grid--two"><div class="erp-field"><label for="report-from">From date</label><input id="report-from" class="erp-input" name="from" type="date" value="{{ request('from') }}"></div><div class="erp-field"><label for="report-to">To date</label><input id="report-to" class="erp-input" name="to" type="date" value="{{ request('to') }}"></div></div><p class="apms-report-hint">The selected range uses each record type’s own event date.</p><button class="erp-button erp-button-primary" type="submit">Export production CSV</button></form>
                    @else
                        <div class="apms-readonly-note">CSV export is not enabled for this account.</div>
                    @endif
                </section>
            </div>
        </section>
    </div>

    <dialog id="apms-editor-dialog" class="apms-dialog" aria-labelledby="apms-editor-title">
        <div class="apms-dialog-shell">
            <header class="apms-dialog-header"><div><p class="apms-kicker">APMS record editor</p><h2 id="apms-editor-title">Add record</h2><p id="apms-editor-description">Enter the details for this operational record.</p></div><button type="button" class="apms-dialog-close" data-apms-close aria-label="Close editor">×</button></header>
            <div id="apms-editor-content" class="apms-dialog-content"></div>
        </div>
    </dialog>

    <template id="apms-form-crop">
        <form method="POST" action="{{ route('modules.apms.crops.store', ['tab' => 'production', 'record_type' => 'crops']) }}" data-apms-editor-form data-store-action="{{ route('modules.apms.crops.store', ['tab' => 'production', 'record_type' => 'crops']) }}">
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_apms_form" value="crop"><input type="hidden" name="_apms_record_id" value="">
            <section class="apms-form-section"><h3>Farmer and farm</h3><p>Choose a verified farmer and one of their registered farms.</p><div class="apms-field-grid">@include('apms.farm-fields', ['formKey' => 'crop-editor'])
                <div class="erp-field"><label for="crop-season_id">Agricultural season</label><select id="crop-season_id" class="erp-input" name="season_id"><option value="">No season selected</option>@foreach($seasons as $season)<option value="{{ $season->id }}" @selected((string) old('season_id') === (string) $season->id)>{{ $season->name }}</option>@endforeach</select>@error('season_id')<span class="apms-field-error">{{ $message }}</span>@enderror</div></div></section>
            <section class="apms-form-section"><h3>Production details</h3><div class="apms-field-grid"><div class="erp-field"><label for="crop-crop_type">Crop type <span aria-hidden="true">*</span></label><input id="crop-crop_type" class="erp-input" name="crop_type" value="{{ old('crop_type') }}" maxlength="120" required>@error('crop_type')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="crop-variety">Variety</label><input id="crop-variety" class="erp-input" name="variety" value="{{ old('variety') }}" maxlength="120">@error('variety')<span class="apms-field-error">{{ $message }}</span>@enderror</div>
                <div class="erp-field"><label for="crop-area_planted">Area planted</label><input id="crop-area_planted" class="erp-input" name="area_planted" type="number" min="0.01" step="0.01" value="{{ old('area_planted') }}">@error('area_planted')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="crop-area_unit">Area unit <span aria-hidden="true">*</span></label><select id="crop-area_unit" class="erp-input" name="area_unit" required><option value="hectare" @selected(old('area_unit', 'hectare') === 'hectare')>Hectare</option><option value="square_meter" @selected(old('area_unit') === 'square_meter')>Square meter</option></select>@error('area_unit')<span class="apms-field-error">{{ $message }}</span>@enderror</div>
                <div class="erp-field"><label for="crop-planted_on">Planting date</label><input id="crop-planted_on" class="erp-input" name="planted_on" type="date" value="{{ old('planted_on') }}">@error('planted_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="crop-harvested_on">Harvest date</label><input id="crop-harvested_on" class="erp-input" name="harvested_on" type="date" value="{{ old('harvested_on') }}">@error('harvested_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div>
                <div class="erp-field"><label for="crop-yield_quantity">Yield quantity</label><input id="crop-yield_quantity" class="erp-input" name="yield_quantity" type="number" min="0" step="0.01" value="{{ old('yield_quantity') }}">@error('yield_quantity')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="crop-yield_unit">Yield unit <span aria-hidden="true">*</span></label><input id="crop-yield_unit" class="erp-input" name="yield_unit" value="{{ old('yield_unit', 'kilogram') }}" maxlength="30" required>@error('yield_unit')<span class="apms-field-error">{{ $message }}</span>@enderror</div>
                <div class="erp-field"><label for="crop-status">Production status <span aria-hidden="true">*</span></label><select id="crop-status" class="erp-input" name="status" required>@foreach(['planned','growing','harvested','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status', 'planned') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>@error('status')<span class="apms-field-error">{{ $message }}</span>@enderror</div></div></section>
            <section class="apms-form-section"><h3>Additional information</h3><div class="erp-field"><label for="crop-notes">Notes</label><textarea id="crop-notes" class="erp-input" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>@error('notes')<span class="apms-field-error">{{ $message }}</span>@enderror</div></section>
            <footer class="apms-form-footer"><button type="button" class="erp-button erp-button-secondary" data-apms-close>Cancel</button><button type="submit" class="erp-button erp-button-primary" data-apms-submit>Save crop record</button></footer>
        </form>
    </template>

    <template id="apms-form-livestock">
        <form method="POST" action="{{ route('modules.apms.livestock.store', ['tab' => 'production', 'record_type' => 'livestock']) }}" data-apms-editor-form data-store-action="{{ route('modules.apms.livestock.store', ['tab' => 'production', 'record_type' => 'livestock']) }}">
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_apms_form" value="livestock"><input type="hidden" name="_apms_record_id" value="">
            <section class="apms-form-section"><h3>Farmer and farm</h3><p>Choose a verified farmer and one of their registered farms.</p><div class="apms-field-grid">@include('apms.farm-fields', ['formKey' => 'livestock-editor'])</div></section>
            <section class="apms-form-section"><h3>Livestock details</h3><div class="apms-field-grid"><div class="erp-field"><label for="livestock-animal_type">Animal type <span aria-hidden="true">*</span></label><input id="livestock-animal_type" class="erp-input" name="animal_type" value="{{ old('animal_type') }}" maxlength="120" required>@error('animal_type')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="livestock-breed">Breed</label><input id="livestock-breed" class="erp-input" name="breed" value="{{ old('breed') }}" maxlength="120">@error('breed')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="livestock-quantity">Quantity <span aria-hidden="true">*</span></label><input id="livestock-quantity" class="erp-input" name="quantity" type="number" min="1" value="{{ old('quantity') }}" required>@error('quantity')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="livestock-recorded_on">Record date <span aria-hidden="true">*</span></label><input id="livestock-recorded_on" class="erp-input" name="recorded_on" type="date" value="{{ old('recorded_on') }}" required>@error('recorded_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="livestock-status">Status</label><select id="livestock-status" class="erp-input" name="status">@foreach(['active','sold','deceased'] as $status)<option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>@error('status')<span class="apms-field-error">{{ $message }}</span>@enderror</div></div><div class="erp-field mt-4"><label for="livestock-notes">Notes</label><textarea id="livestock-notes" class="erp-input" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>@error('notes')<span class="apms-field-error">{{ $message }}</span>@enderror</div></section>
            <footer class="apms-form-footer"><button type="button" class="erp-button erp-button-secondary" data-apms-close>Cancel</button><button type="submit" class="erp-button erp-button-primary" data-apms-submit>Save livestock record</button></footer>
        </form>
    </template>

    <template id="apms-form-fishery">
        <form method="POST" action="{{ route('modules.apms.fisheries.store', ['tab' => 'production', 'record_type' => 'fisheries']) }}" data-apms-editor-form data-store-action="{{ route('modules.apms.fisheries.store', ['tab' => 'production', 'record_type' => 'fisheries']) }}">
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_apms_form" value="fishery"><input type="hidden" name="_apms_record_id" value="">
            <section class="apms-form-section"><h3>Farmer and farm</h3><p>Choose a verified farmer and one of their registered farms.</p><div class="apms-field-grid">@include('apms.farm-fields', ['formKey' => 'fishery-editor'])</div></section>
            <section class="apms-form-section"><h3>Fisheries details</h3><div class="apms-field-grid"><div class="erp-field"><label for="fishery-fishery_type">Fishery type <span aria-hidden="true">*</span></label><input id="fishery-fishery_type" class="erp-input" name="fishery_type" value="{{ old('fishery_type') }}" maxlength="120" required>@error('fishery_type')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="fishery-species">Species</label><input id="fishery-species" class="erp-input" name="species" value="{{ old('species') }}" maxlength="120">@error('species')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="fishery-production_quantity">Production quantity</label><input id="fishery-production_quantity" class="erp-input" name="production_quantity" type="number" min="0" step="0.01" value="{{ old('production_quantity') }}">@error('production_quantity')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="fishery-production_unit">Production unit <span aria-hidden="true">*</span></label><input id="fishery-production_unit" class="erp-input" name="production_unit" value="{{ old('production_unit', 'kilogram') }}" maxlength="30" required>@error('production_unit')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="fishery-recorded_on">Record date <span aria-hidden="true">*</span></label><input id="fishery-recorded_on" class="erp-input" name="recorded_on" type="date" value="{{ old('recorded_on') }}" required>@error('recorded_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div></div><div class="erp-field mt-4"><label for="fishery-notes">Notes</label><textarea id="fishery-notes" class="erp-input" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>@error('notes')<span class="apms-field-error">{{ $message }}</span>@enderror</div></section>
            <footer class="apms-form-footer"><button type="button" class="erp-button erp-button-secondary" data-apms-close>Cancel</button><button type="submit" class="erp-button erp-button-primary" data-apms-submit>Save fisheries record</button></footer>
        </form>
    </template>

    <template id="apms-form-incident">
        <form method="POST" action="{{ route('modules.apms.incidents.store', ['tab' => 'monitoring', 'monitoring_type' => 'incidents']) }}" data-apms-editor-form data-store-action="{{ route('modules.apms.incidents.store', ['tab' => 'monitoring', 'monitoring_type' => 'incidents']) }}">
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_apms_form" value="incident"><input type="hidden" name="_apms_record_id" value="">
            <section class="apms-form-section"><h3>Farmer and farm</h3><p>Choose a verified farmer and one of their registered farms.</p><div class="apms-field-grid">@include('apms.farm-fields', ['formKey' => 'incident-editor'])</div></section>
            <section class="apms-form-section"><h3>Incident details</h3><div class="apms-field-grid"><div class="erp-field"><label for="incident-incident_type">Pest or disease <span aria-hidden="true">*</span></label><input id="incident-incident_type" class="erp-input" name="incident_type" value="{{ old('incident_type') }}" maxlength="120" required>@error('incident_type')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="incident-affected_crop_or_animal">Affected crop or animal <span aria-hidden="true">*</span></label><input id="incident-affected_crop_or_animal" class="erp-input" name="affected_crop_or_animal" value="{{ old('affected_crop_or_animal') }}" maxlength="120" required>@error('affected_crop_or_animal')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="incident-reported_on">Report date <span aria-hidden="true">*</span></label><input id="incident-reported_on" class="erp-input" name="reported_on" type="date" value="{{ old('reported_on') }}" required>@error('reported_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="incident-severity">Severity <span aria-hidden="true">*</span></label><select id="incident-severity" class="erp-input" name="severity" required>@foreach(['low','medium','high','critical'] as $severity)<option value="{{ $severity }}" @selected(old('severity', 'medium') === $severity)>{{ ucfirst($severity) }}</option>@endforeach</select>@error('severity')<span class="apms-field-error">{{ $message }}</span>@enderror</div>
                <div class="erp-field"><label for="incident-status">Status</label><select id="incident-status" class="erp-input" name="status">@foreach(['reported','investigating','resolved','closed'] as $status)<option value="{{ $status }}" @selected(old('status', 'reported') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>@error('status')<span class="apms-field-error">{{ $message }}</span>@enderror</div></div><div class="erp-field mt-4"><label for="incident-description">Description</label><textarea id="incident-description" class="erp-input" name="description" rows="3" maxlength="3000">{{ old('description') }}</textarea>@error('description')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field mt-4"><label for="incident-action_taken">Action taken</label><textarea id="incident-action_taken" class="erp-input" name="action_taken" rows="3" maxlength="3000">{{ old('action_taken') }}</textarea>@error('action_taken')<span class="apms-field-error">{{ $message }}</span>@enderror</div></section>
            <footer class="apms-form-footer"><button type="button" class="erp-button erp-button-secondary" data-apms-close>Cancel</button><button type="submit" class="erp-button erp-button-primary" data-apms-submit>Save incident</button></footer>
        </form>
    </template>

    <template id="apms-form-monitoring">
        <form method="POST" action="{{ route('modules.apms.monitoring.store', ['tab' => 'monitoring', 'monitoring_type' => 'visits']) }}" data-apms-editor-form data-store-action="{{ route('modules.apms.monitoring.store', ['tab' => 'monitoring', 'monitoring_type' => 'visits']) }}">
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_apms_form" value="monitoring"><input type="hidden" name="_apms_record_id" value="">
            <section class="apms-form-section"><h3>Farmer and farm</h3><p>Choose a verified farmer and one of their registered farms.</p><div class="apms-field-grid">@include('apms.farm-fields', ['formKey' => 'monitoring-editor'])</div></section>
            <section class="apms-form-section"><h3>Visit details</h3><div class="apms-field-grid"><div class="erp-field"><label for="monitoring-visited_on">Visit date <span aria-hidden="true">*</span></label><input id="monitoring-visited_on" class="erp-input" name="visited_on" type="date" value="{{ old('visited_on') }}" required>@error('visited_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="monitoring-monitoring_type">Monitoring type <span aria-hidden="true">*</span></label><input id="monitoring-monitoring_type" class="erp-input" name="monitoring_type" value="{{ old('monitoring_type', 'field_visit') }}" maxlength="120" required>@error('monitoring_type')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="monitoring-status">Visit status</label><select id="monitoring-status" class="erp-input" name="status">@foreach(['recorded','follow_up','closed'] as $status)<option value="{{ $status }}" @selected(old('status', 'recorded') === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>@error('status')<span class="apms-field-error">{{ $message }}</span>@enderror</div></div><div class="erp-field mt-4"><label for="monitoring-findings">Findings <span aria-hidden="true">*</span></label><textarea id="monitoring-findings" class="erp-input" name="findings" rows="4" maxlength="4000" required>{{ old('findings') }}</textarea>@error('findings')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field mt-4"><label for="monitoring-recommendations">Recommendations</label><textarea id="monitoring-recommendations" class="erp-input" name="recommendations" rows="3" maxlength="4000">{{ old('recommendations') }}</textarea>@error('recommendations')<span class="apms-field-error">{{ $message }}</span>@enderror</div></section>
            <footer class="apms-form-footer"><button type="button" class="erp-button erp-button-secondary" data-apms-close>Cancel</button><button type="submit" class="erp-button erp-button-primary" data-apms-submit>Save monitoring record</button></footer>
        </form>
    </template>

    <template id="apms-form-season">
        <form method="POST" action="{{ route('modules.apms.seasons.store', ['tab' => 'seasons']) }}" data-apms-editor-form data-store-action="{{ route('modules.apms.seasons.store', ['tab' => 'seasons']) }}">
            @csrf<input type="hidden" name="_method" value=""><input type="hidden" name="_apms_form" value="season"><input type="hidden" name="_apms_record_id" value="">
            <section class="apms-form-section"><h3>Season information</h3><div class="apms-field-grid"><div class="erp-field apms-field-grid--full"><label for="season-name">Season name <span aria-hidden="true">*</span></label><input id="season-name" class="erp-input" name="name" value="{{ old('name') }}" maxlength="150" required>@error('name')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="season-starts_on">Starts on <span aria-hidden="true">*</span></label><input id="season-starts_on" class="erp-input" name="starts_on" type="date" value="{{ old('starts_on') }}" required>@error('starts_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="season-ends_on">Ends on <span aria-hidden="true">*</span></label><input id="season-ends_on" class="erp-input" name="ends_on" type="date" value="{{ old('ends_on') }}" required>@error('ends_on')<span class="apms-field-error">{{ $message }}</span>@enderror</div><div class="erp-field"><label for="season-status">Status</label><select id="season-status" class="erp-input" name="status"><option value="open" @selected(old('status', 'open') === 'open')>Open</option><option value="closed" @selected(old('status') === 'closed')>Closed</option></select>@error('status')<span class="apms-field-error">{{ $message }}</span>@enderror</div></div></section>
            <footer class="apms-form-footer"><button type="button" class="erp-button erp-button-secondary" data-apms-close>Cancel</button><button type="submit" class="erp-button erp-button-primary" data-apms-submit>Save season</button></footer>
        </form>
    </template>
</x-layouts.app>
