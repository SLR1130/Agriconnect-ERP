<x-layouts.app>
    <x-slot name="header">Farmer &amp; Farm Registration</x-slot>
    <x-slot name="description">Search verified farmer records, review registrations, and maintain the farm details used across AgriConnect.</x-slot>

    @if(session('status'))
        <div class="erp-alert erp-alert-success mb-5" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="erp-alert erp-alert-danger mb-5" role="alert" tabindex="-1">
            <strong>Registration update needs attention.</strong>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="erp-workspace" data-erp-workspace data-workspace-param="workspace" data-workspace-default="farmers" data-workspace-initial="{{ old('_erp_workspace', old('workspace', request('workspace', 'farmers'))) }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="erp-page-eyebrow">FFRMS workspace</p>
                <p class="text-sm text-gray-600">Farmer identity and farm ownership remain linked to APMS, AASMS, and AEAMS records.</p>
            </div>
            <span class="erp-pill">{{ $farmers->total() }} farmer records</span>
        </div>

        <div class="erp-workspace-tabs" role="tablist" aria-label="Farmer registration workspaces">
            <button id="ffrms-tab-farmers" class="erp-workspace-tab" type="button" role="tab" aria-selected="true" aria-controls="ffrms-panel-farmers" data-erp-workspace-tab="farmers">Farmers &amp; verification</button>
            @if($canManageAssociations)
                <button id="ffrms-tab-associations" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="ffrms-panel-associations" data-erp-workspace-tab="associations">Farmer associations</button>
            @endif
        </div>

        <section id="ffrms-panel-farmers" class="erp-workspace-panel" role="tabpanel" aria-labelledby="ffrms-tab-farmers" tabindex="0" data-erp-workspace-panel="farmers">
            <div class="erp-surface p-4 sm:p-5">
                <div class="erp-section-header">
                    <div><h2 class="erp-section-title">Farmer registrations</h2><p class="mt-1 text-sm text-gray-500">Search, filter, and open a record when you need to review or update its details.</p></div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="erp-pill">{{ $farmers->count() }} on this page</span>
                        @if($canExport)<a class="erp-button erp-button-secondary" href="{{ route('modules.ffrms.export', request()->except('workspace')) }}">Export filtered CSV</a>@endif
                    </div>
                </div>

                <form class="mt-4 grid gap-3 sm:grid-cols-[minmax(14rem,1fr)_12rem_auto]" method="GET" role="search" aria-label="Filter farmer registrations">
                    <input type="hidden" name="workspace" value="farmers">
                    <div><label class="erp-field-label" for="ffrms-search">Search farmers</label><input id="ffrms-search" class="erp-input w-full" name="search" value="{{ request('search') }}" placeholder="Name, farmer code, or barangay"></div>
                    <div><label class="erp-field-label" for="ffrms-status">Registration status</label><select id="ffrms-status" class="erp-input w-full" name="status"><option value="">All statuses</option>@foreach(['pending','verified','returned','rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                    <div class="flex items-end gap-2"><button class="erp-button erp-button-primary" type="submit">Apply filters</button><a class="erp-button erp-button-secondary" href="{{ route('modules.ffrms.dashboard', ['workspace' => 'farmers']) }}">Clear</a></div>
                </form>

                <div class="erp-table-wrap mt-5">
                    <table class="erp-table w-full">
                        <thead><tr><th scope="col">Farmer</th><th scope="col">Barangay</th><th class="ffrms-table-secondary" scope="col">Farms</th><th scope="col">Registration</th><th scope="col"><span class="sr-only">Record actions</span></th></tr></thead>
                        <tbody>
                            @forelse($farmers as $farmer)
                                @php($farmerDetailsOpen = (string) old('_ffrms_farmer_id') === (string) $farmer->id)
                                <tr>
                                    <td><strong>{{ $farmer->user->name }}</strong><span class="ffrms-code block">{{ $farmer->farmer_code }}</span><span class="ffrms-email block">{{ $farmer->user->email }}</span></td>
                                    <td>{{ $farmer->barangay ?: 'Not recorded' }}</td>
                                    <td class="ffrms-table-secondary">{{ $farmer->farms_count }}</td>
                                    <td><span class="erp-pill {{ $farmer->registration_status === 'verified' ? 'erp-pill-success' : ($farmer->registration_status === 'rejected' ? 'erp-pill-warning' : 'erp-pill-neutral') }}">{{ ucfirst($farmer->registration_status) }}</span></td>
                                    <td class="ffrms-record-action">
                                        <details class="ffrms-record-details" @if($farmerDetailsOpen) open @endif>
                                            <summary class="erp-button erp-button-secondary">{{ $farmerDetailsOpen ? 'Record open' : 'Open record' }}</summary>
                                            <div class="ffrms-record-content">
                                                <div class="ffrms-record-heading"><div><span class="erp-page-eyebrow">{{ $farmer->farmer_code }}</span><h3>{{ $farmer->user->name }}</h3><p>{{ $farmer->user->email }} · {{ $farmer->phone ?: 'No phone recorded' }}</p></div><span class="erp-pill {{ $farmer->registration_status === 'verified' ? 'erp-pill-success' : ($farmer->registration_status === 'rejected' ? 'erp-pill-warning' : 'erp-pill-neutral') }}">{{ ucfirst($farmer->registration_status) }}</span></div>

                                                @if($canReview)
                                                    <form method="POST" action="{{ route('modules.ffrms.review', $farmer) }}" class="ffrms-review-form" data-confirm-submit="Save this farmer registration review?">
                                                        @csrf<input type="hidden" name="_ffrms_farmer_id" value="{{ $farmer->id }}"><input type="hidden" name="workspace" value="farmers">
                                                        <div class="ffrms-field"><label class="erp-field-label" for="ffrms-review-status-{{ $farmer->id }}">Review decision</label><select id="ffrms-review-status-{{ $farmer->id }}" name="status" class="erp-input"><option value="verified">Verify registration</option><option value="returned">Return for correction</option><option value="rejected">Reject registration</option></select></div>
                                                        <div class="ffrms-field"><label class="erp-field-label" for="ffrms-review-reason-{{ $farmer->id }}">Review note or reason</label><input id="ffrms-review-reason-{{ $farmer->id }}" name="reason" value="{{ old('_ffrms_farmer_id') == $farmer->id ? old('reason') : '' }}" class="erp-input" placeholder="Explain the decision when needed"></div>
                                                        <button class="erp-button erp-button-primary" type="submit">Save review</button>
                                                    </form>
                                                @endif

                                                @if($canUpdate)
                                                    <details class="ffrms-subdisclosure" @if($farmerDetailsOpen && old('_ffrms_form') === 'farmer') open @endif>
                                                        <summary>Correct farmer information</summary>
                                                        <form method="POST" action="{{ route('modules.ffrms.registrations.update', $farmer) }}" class="ffrms-field-grid">
                                                            @csrf @method('PATCH')<input type="hidden" name="_ffrms_form" value="farmer"><input type="hidden" name="_ffrms_farmer_id" value="{{ $farmer->id }}"><input type="hidden" name="workspace" value="farmers">
                                                            <div class="ffrms-field"><label class="erp-field-label" for="ffrms-name-{{ $farmer->id }}">Full name</label><input id="ffrms-name-{{ $farmer->id }}" class="erp-input" name="name" value="{{ old('_ffrms_farmer_id') == $farmer->id ? old('name', $farmer->user->name) : $farmer->user->name }}" required></div>
                                                            <div class="ffrms-field"><label class="erp-field-label" for="ffrms-email-{{ $farmer->id }}">Email</label><input id="ffrms-email-{{ $farmer->id }}" class="erp-input" type="email" name="email" value="{{ old('_ffrms_farmer_id') == $farmer->id ? old('email', $farmer->user->email) : $farmer->user->email }}" required></div>
                                                            <div class="ffrms-field"><label class="erp-field-label" for="ffrms-phone-{{ $farmer->id }}">Phone</label><input id="ffrms-phone-{{ $farmer->id }}" class="erp-input" name="phone" value="{{ old('_ffrms_farmer_id') == $farmer->id ? old('phone', $farmer->phone) : $farmer->phone }}"></div>
                                                            <div class="ffrms-field"><label class="erp-field-label" for="ffrms-barangay-{{ $farmer->id }}">Barangay</label><input id="ffrms-barangay-{{ $farmer->id }}" class="erp-input" name="barangay" value="{{ old('_ffrms_farmer_id') == $farmer->id ? old('barangay', $farmer->barangay) : $farmer->barangay }}" required></div>
                                                            <div class="ffrms-field ffrms-field-wide"><label class="erp-field-label" for="ffrms-address-{{ $farmer->id }}">Address</label><input id="ffrms-address-{{ $farmer->id }}" class="erp-input" name="address" value="{{ old('_ffrms_farmer_id') == $farmer->id ? old('address', $farmer->address) : $farmer->address }}"></div>
                                                            <button class="erp-button erp-button-secondary ffrms-field-wide" type="submit">Save audited correction</button>
                                                        </form>
                                                    </details>
                                                @endif

                                                <div class="ffrms-record-grid">
                                                    <section><h4>Registered farms</h4>
                                                        @forelse($farmer->farms as $farm)
                                                            <article class="ffrms-farm-card"><div class="ffrms-farm-summary"><div><strong>{{ $farm->name }}</strong><p>{{ $farm->barangay }} · {{ $farm->area ?: '—' }} {{ $farm->area_unit }}</p></div><span class="erp-pill erp-pill-neutral">{{ ucfirst($farm->status) }}</span></div>
                                                                @if($canUpdate)
                                                                    <details class="ffrms-subdisclosure" @if((string) old('_ffrms_farm_id') === (string) $farm->id) open @endif><summary>Edit farm record</summary>
                                                                        <form method="POST" action="{{ route('modules.ffrms.farms.update', $farm) }}" class="ffrms-field-grid">
                                                                            @csrf @method('PATCH')<input type="hidden" name="_ffrms_farmer_id" value="{{ $farmer->id }}"><input type="hidden" name="_ffrms_farm_id" value="{{ $farm->id }}"><input type="hidden" name="workspace" value="farmers">
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-name-{{ $farm->id }}">Farm name</label><input id="farm-name-{{ $farm->id }}" class="erp-input" name="name" value="{{ old('_ffrms_farm_id') == $farm->id ? old('name', $farm->name) : $farm->name }}" required></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-barangay-{{ $farm->id }}">Barangay</label><input id="farm-barangay-{{ $farm->id }}" class="erp-input" name="barangay" value="{{ old('_ffrms_farm_id') == $farm->id ? old('barangay', $farm->barangay) : $farm->barangay }}" required></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-location-{{ $farm->id }}">Location details</label><input id="farm-location-{{ $farm->id }}" class="erp-input" name="location_details" value="{{ old('_ffrms_farm_id') == $farm->id ? old('location_details', $farm->location_details) : $farm->location_details }}"></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-area-{{ $farm->id }}">Area</label><input id="farm-area-{{ $farm->id }}" class="erp-input" name="area" type="number" min=".01" step=".01" value="{{ old('_ffrms_farm_id') == $farm->id ? old('area', $farm->area) : $farm->area }}"></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-area-unit-{{ $farm->id }}">Area unit</label><select id="farm-area-unit-{{ $farm->id }}" class="erp-input" name="area_unit"><option value="hectare" @selected($farm->area_unit === 'hectare')>Hectare</option><option value="square_meter" @selected($farm->area_unit === 'square_meter')>Square meter</option></select></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-ownership-{{ $farm->id }}">Ownership</label><select id="farm-ownership-{{ $farm->id }}" class="erp-input" name="ownership_type">@foreach(['owner','tenant','leaseholder','other'] as $type)<option value="{{ $type }}" @selected($farm->ownership_type === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-classification-{{ $farm->id }}">Classification</label><input id="farm-classification-{{ $farm->id }}" class="erp-input" name="classification" value="{{ old('_ffrms_farm_id') == $farm->id ? old('classification', $farm->classification) : $farm->classification }}"></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-tenancy-{{ $farm->id }}">Tenancy details</label><input id="farm-tenancy-{{ $farm->id }}" class="erp-input" name="tenancy_details" value="{{ old('_ffrms_farm_id') == $farm->id ? old('tenancy_details', $farm->tenancy_details) : $farm->tenancy_details }}"></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-soil-{{ $farm->id }}">Soil type</label><input id="farm-soil-{{ $farm->id }}" class="erp-input" name="soil_type" value="{{ old('_ffrms_farm_id') == $farm->id ? old('soil_type', $farm->soil_type) : $farm->soil_type }}"></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-irrigation-{{ $farm->id }}">Irrigation type</label><input id="farm-irrigation-{{ $farm->id }}" class="erp-input" name="irrigation_type" value="{{ old('_ffrms_farm_id') == $farm->id ? old('irrigation_type', $farm->irrigation_type) : $farm->irrigation_type }}"></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-activities-{{ $farm->id }}">Agricultural activities</label><input id="farm-activities-{{ $farm->id }}" class="erp-input" name="agricultural_activities" value="{{ old('_ffrms_farm_id') == $farm->id ? old('agricultural_activities', implode(', ', $farm->agricultural_activities ?: [])) : implode(', ', $farm->agricultural_activities ?: []) }}"></div>
                                                                            <div class="ffrms-field"><label class="erp-field-label" for="farm-categories-{{ $farm->id }}">Categories</label><input id="farm-categories-{{ $farm->id }}" class="erp-input" name="categories" value="{{ old('_ffrms_farm_id') == $farm->id ? old('categories', implode(', ', $farm->categories ?: [])) : implode(', ', $farm->categories ?: []) }}"></div>
                                                                            <button class="erp-button erp-button-secondary ffrms-field-wide" type="submit">Save farm record</button>
                                                                        </form>
                                                                    </details>
                                                                @endif
                                                            </article>
                                                        @empty
                                                            <div class="erp-empty">No farms are linked to this farmer.</div>
                                                        @endforelse
                                                    </section>
                                                    <section><h4>Verification history</h4>
                                                        @forelse($farmer->registrationHistory as $history)
                                                            <article class="ffrms-history-entry"><div><strong>{{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}</strong><time>{{ $history->created_at?->format('M d, Y H:i') }}</time></div><p>{{ $history->reason ?: 'No review note recorded' }} · {{ $history->processor?->name ?: 'Farmer portal' }}</p></article>
                                                        @empty
                                                            <div class="erp-empty">No verification history is recorded.</div>
                                                        @endforelse
                                                    </section>
                                                </div>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="erp-empty-cell">No farmer records match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $farmers->links() }}</div>
            </div>
        </section>

        @if($canManageAssociations)
            <section id="ffrms-panel-associations" class="erp-workspace-panel" role="tabpanel" aria-labelledby="ffrms-tab-associations" tabindex="0" data-erp-workspace-panel="associations" hidden>
                <div class="grid gap-5 xl:grid-cols-[minmax(18rem,.8fr)_minmax(0,1.4fr)]">
                    <section class="erp-surface p-5">
                        <p class="erp-page-eyebrow">Shared farmer groups</p><h2 class="erp-section-title">Register an association</h2><p class="mt-1 text-sm text-gray-500">Association membership references existing FFRMS farmer records.</p>
                        <form method="POST" action="{{ route('modules.ffrms.associations.store') }}" class="mt-4 grid gap-3" data-ffrms-association-form>
                            @csrf<input type="hidden" name="workspace" value="associations">
                            <div class="ffrms-field"><label class="erp-field-label" for="association-name">Association name</label><input id="association-name" name="name" required class="erp-input"></div>
                            <div class="ffrms-field"><label class="erp-field-label" for="association-reference">Registration reference</label><input id="association-reference" name="registration_reference" class="erp-input"></div>
                            <div class="ffrms-field"><label class="erp-field-label" for="association-barangay">Barangay</label><input id="association-barangay" name="barangay" class="erp-input"></div>
                            <button class="erp-button erp-button-primary" type="submit">Register association</button>
                        </form>
                    </section>
                    <section class="erp-surface p-5">
                        <div class="erp-section-header"><div><h2 class="erp-section-title">Existing associations</h2><p class="mt-1 text-sm text-gray-500">Add members using their existing farmer record.</p></div><span class="erp-pill">{{ $associations->count() }} groups</span></div>
                        <div class="mt-4 space-y-3">
                            @forelse($associations as $association)
                                <article class="ffrms-association-row"><div><strong>{{ $association->name }}</strong><p>{{ $association->registration_reference ?: 'No registration reference' }} · {{ $association->barangay ?: 'All barangays' }} · {{ $association->members_count }} member(s)</p></div>
                                    <form method="POST" action="{{ route('modules.ffrms.associations.members.store', $association) }}" class="ffrms-association-member-form">
                                        @csrf<input type="hidden" name="workspace" value="associations">
                                        <div class="ffrms-field"><label class="erp-field-label" for="association-farmer-{{ $association->id }}">Farmer record</label><select id="association-farmer-{{ $association->id }}" name="farmer_id" required class="erp-input"><option value="">Select farmer</option>@foreach($farmers as $farmer)<option value="{{ $farmer->id }}">{{ $farmer->user->name }} · {{ $farmer->farmer_code }}</option>@endforeach</select></div>
                                        <button class="erp-button erp-button-secondary" type="submit">Add member</button>
                                    </form>
                                </article>
                            @empty
                                <div class="erp-empty">No farmer associations have been registered.</div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
