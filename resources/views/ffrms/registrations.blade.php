<x-layouts.app>
    <x-slot name="header">Farmer & Farm Registration</x-slot>
    <x-slot name="description">Manage farmer identities, review registration decisions, and maintain the farm records used across AgriConnect.</x-slot>

    @if(session('status'))
        <div class="erp-alert erp-alert-success mb-5" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="erp-alert erp-alert-danger mb-5" role="alert" tabindex="-1">
            <strong>There are issues to correct.</strong>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($selectedFarmer)
        @include('ffrms.partials.registration-detail', ['farmer' => $selectedFarmer])
    @else
        <div class="ffrms-overview">
            <div class="ffrms-overview-heading">
                <div>
                    <p class="erp-page-eyebrow">FFRMS workspace</p>
                    <h2 class="erp-section-title">Farmer registry</h2>
                    <p>Identity, farm ownership, and registration decisions remain connected to the same farmer record.</p>
                </div>
                @if($canExport)
                    <a class="erp-button erp-button-secondary" href="{{ route('modules.ffrms.export', request()->only(['search', 'status'])) }}"><x-ui.icon name="reports" size="sm" /> Export filtered CSV</a>
                @endif
            </div>

            @php($statusCards = [['key' => '', 'label' => 'All farmers', 'value' => $registrationCounts->sum()], ['key' => 'pending', 'label' => 'Pending review', 'value' => (int) $registrationCounts->get('pending', 0)], ['key' => 'verified', 'label' => 'Verified', 'value' => (int) $registrationCounts->get('verified', 0)], ['key' => 'returned', 'label' => 'Returned / rejected', 'value' => (int) $registrationCounts->get('returned', 0) + (int) $registrationCounts->get('rejected', 0)]])
            <nav class="ffrms-status-cards" aria-label="Filter registry by registration status">
                @foreach($statusCards as $card)
                    @php($cardQuery = array_filter(['workspace' => 'farmers', 'search' => request('search'), 'status' => $card['key'], 'page' => null], fn ($value) => $value !== null && $value !== ''))
                    <a class="ffrms-status-card {{ request('status', '') === $card['key'] ? 'is-current' : '' }}" href="{{ route('modules.ffrms.dashboard', $cardQuery) }}" @if(request('status', '') === $card['key']) aria-current="page" @endif>
                        <span>{{ $card['label'] }}</span><strong>{{ number_format($card['value']) }}</strong>
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="erp-workspace" data-erp-workspace data-workspace-param="workspace" data-workspace-default="farmers" data-workspace-initial="{{ old('_erp_workspace', old('workspace', request('workspace', 'farmers'))) }}">
            <nav class="erp-workspace-tabs" role="tablist" aria-label="Farmer registration workspaces">
                <button id="ffrms-tab-farmers" class="erp-workspace-tab" type="button" role="tab" aria-selected="true" aria-controls="ffrms-panel-farmers" data-erp-workspace-tab="farmers">Farmer registry</button>
                @if($canManageAssociations)
                    <button id="ffrms-tab-associations" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="ffrms-panel-associations" data-erp-workspace-tab="associations">Associations</button>
                @endif
            </nav>

            <section id="ffrms-panel-farmers" class="erp-workspace-panel" role="tabpanel" aria-labelledby="ffrms-tab-farmers" tabindex="0" data-erp-workspace-panel="farmers">
                <section class="erp-surface ffrms-registry-panel">
                    <div class="erp-section-header">
                        <div><h2 class="erp-section-title">Registrations</h2><p class="mt-1 text-sm text-gray-500">Search and filter the registry, then open an individual record to review its history or make an authorized correction.</p></div>
                        <span class="erp-pill">{{ number_format($farmers->total()) }} matching records</span>
                    </div>

                    <form class="ffrms-filter-toolbar" method="GET" action="{{ route('modules.ffrms.dashboard') }}" role="search" aria-label="Filter farmer registrations">
                        <input type="hidden" name="workspace" value="farmers">
                        <div class="ffrms-search-field"><label class="erp-field-label" for="ffrms-search">Find a farmer</label><input id="ffrms-search" class="erp-input" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Name, email, farmer code, or barangay"></div>
                        <div class="ffrms-status-field"><label class="erp-field-label" for="ffrms-status">Registration status</label><select id="ffrms-status" class="erp-input" name="status"><option value="">All statuses</option>@foreach(['pending','verified','returned','rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                        <div class="ffrms-filter-actions"><button class="erp-button erp-button-primary" type="submit">Apply filters</button><a class="erp-button erp-button-secondary" href="{{ route('modules.ffrms.dashboard', ['workspace' => 'farmers']) }}">Clear</a></div>
                    </form>

                    <div class="ffrms-table-wrap">
                        <table class="ffrms-table">
                            <thead><tr><th scope="col">Farmer</th><th scope="col">Barangay</th><th scope="col">Farms</th><th scope="col">Registration</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                            <tbody>
                                @forelse($farmers as $farmer)
                                    <tr>
                                        <td data-label="Farmer"><strong>{{ $farmer->user->name }}</strong><span class="ffrms-code">{{ $farmer->farmer_code }}</span><span class="ffrms-email">{{ $farmer->user->email }}</span><span class="ffrms-mobile-meta">{{ $farmer->barangay ?: 'Barangay not recorded' }} · {{ number_format($farmer->farms_count) }} {{ \Illuminate\Support\Str::plural('farm', $farmer->farms_count) }}</span></td>
                                        <td data-label="Barangay">{{ $farmer->barangay ?: 'Not recorded' }}</td>
                                        <td data-label="Farms">{{ number_format($farmer->farms_count) }}</td>
                                        <td data-label="Registration"><span class="erp-pill {{ $farmer->registration_status === 'verified' ? 'erp-pill-success' : (in_array($farmer->registration_status, ['rejected', 'returned'], true) ? 'erp-pill-warning' : 'erp-pill-neutral') }}">{{ ucfirst($farmer->registration_status) }}</span></td>
                                        <td data-label="Record"><a class="erp-button erp-button-secondary ffrms-view-record" href="{{ route('modules.ffrms.dashboard', array_filter(['workspace' => 'farmers', 'record' => $farmer->id, 'search' => request('search'), 'status' => request('status'), 'page' => request('page')], fn ($value) => $value !== null && $value !== '')) }}#ffrms-record-detail"><x-ui.icon name="users" size="sm" /> View details</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="erp-empty-cell">No farmer records match these filters. Adjust the search or clear the status filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="ffrms-pagination">{{ $farmers->links() }}</div>
                </section>
            </section>

            @if($canManageAssociations)
                <section id="ffrms-panel-associations" class="erp-workspace-panel" role="tabpanel" aria-labelledby="ffrms-tab-associations" tabindex="0" data-erp-workspace-panel="associations" hidden>
                    <div class="ffrms-association-workspace">
                        <section class="erp-surface ffrms-association-form-surface p-5">
                            <p class="erp-page-eyebrow">Farmer groups</p><h2 class="erp-section-title">Register an association</h2><p class="mt-1 text-sm text-gray-600">Associations and membership are maintained separately from registration verification.</p>
                            <form method="POST" action="{{ route('modules.ffrms.associations.store') }}" class="mt-4 grid gap-3" data-ffrms-association-form>
                                @csrf<input type="hidden" name="workspace" value="associations">
                                <div class="ffrms-field"><label class="erp-field-label" for="association-name">Association name</label><input id="association-name" name="name" required maxlength="150" class="erp-input"></div>
                                <div class="ffrms-field"><label class="erp-field-label" for="association-reference">Registration reference</label><input id="association-reference" name="registration_reference" maxlength="100" class="erp-input"></div>
                                <div class="ffrms-field"><label class="erp-field-label" for="association-barangay">Barangay</label><input id="association-barangay" name="barangay" maxlength="100" class="erp-input"></div>
                                <button class="erp-button erp-button-primary" type="submit">Register association</button>
                            </form>
                        </section>
                        <section class="erp-surface p-5">
                            <div class="erp-section-header"><div><h2 class="erp-section-title">Existing associations</h2><p class="mt-1 text-sm text-gray-500">Add members using their existing farmer record.</p></div><span class="erp-pill">{{ number_format($associations->count()) }} groups</span></div>
                            <div class="mt-4">
                                @forelse($associations as $association)
                                    <article class="ffrms-association-row"><div><strong>{{ $association->name }}</strong><p>{{ $association->registration_reference ?: 'No registration reference' }} · {{ $association->barangay ?: 'All barangays' }} · {{ number_format($association->members_count) }} members</p></div>
                                        <form method="POST" action="{{ route('modules.ffrms.associations.members.store', $association) }}" class="ffrms-association-member-form">
                                            @csrf<input type="hidden" name="workspace" value="associations">
                                            <div class="ffrms-field"><label class="erp-field-label" for="association-farmer-{{ $association->id }}">Farmer record</label><select id="association-farmer-{{ $association->id }}" name="farmer_id" required class="erp-input"><option value="">Select a farmer</option>@foreach($farmers as $farmer)<option value="{{ $farmer->id }}">{{ $farmer->user->name }} · {{ $farmer->farmer_code }}</option>@endforeach</select></div>
                                            <button class="erp-button erp-button-secondary" type="submit">Add member</button>
                                        </form>
                                    </article>
                                @empty
                                    <div class="erp-empty-state ffrms-association-empty">
                                        <span class="ffrms-association-empty-icon" aria-hidden="true"><x-ui.icon name="users" size="md" /></span>
                                        <strong>No associations registered yet</strong>
                                        <p>Register a farmer group to begin organizing existing farmer records and memberships.</p>
                                        <a class="erp-button erp-button-primary" href="#association-name">Register the first association</a>
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </section>
            @endif
        </div>
    @endif
</x-layouts.app>
