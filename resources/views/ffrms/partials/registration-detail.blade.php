@php
    $registryQuery = array_filter(['workspace' => 'farmers', 'search' => request('search'), 'status' => request('status'), 'page' => request('page')], fn ($value) => $value !== null && $value !== '');
    $registryUrl = route('modules.ffrms.dashboard', $registryQuery);
    $detailQuery = array_merge(['workspace' => 'farmers', 'record' => $farmer->id], request()->only(['search', 'status', 'page']));
    $decisions = [];
    if ($canVerify && $farmer->registration_status !== 'verified') $decisions['verified'] = 'Verify registration';
    if ($canReturn && $farmer->registration_status !== 'returned') $decisions['returned'] = 'Return for correction';
    if ($canReject && $farmer->registration_status !== 'rejected') $decisions['rejected'] = 'Reject registration';
@endphp

<section id="ffrms-record-detail" class="ffrms-detail-page" aria-labelledby="ffrms-detail-title">
    <div class="ffrms-detail-topbar">
        <a class="erp-button erp-button-secondary" href="{{ $registryUrl }}"><x-ui.icon name="back" size="sm" /> Back to filtered registry</a>
        <span class="ffrms-record-code">{{ $farmer->farmer_code }}</span>
    </div>

    <header class="ffrms-detail-header erp-surface">
        <div class="ffrms-detail-identity">
            <span class="ffrms-avatar" aria-hidden="true">{{ collect(preg_split('/\s+/', trim($farmer->user->name)))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->implode('') }}</span>
            <div><p class="erp-page-eyebrow">Farmer record</p><h2 id="ffrms-detail-title">{{ $farmer->user->name }}</h2><p>{{ $farmer->farmer_code }} · {{ $farmer->barangay ?: 'Barangay not recorded' }}</p></div>
        </div>
        <span class="erp-pill {{ $farmer->registration_status === 'verified' ? 'erp-pill-success' : (in_array($farmer->registration_status, ['returned', 'rejected'], true) ? 'erp-pill-warning' : 'erp-pill-neutral') }}">{{ ucfirst($farmer->registration_status) }}</span>
    </header>

    <div class="ffrms-detail-grid">
        <div class="ffrms-detail-main">
            <section class="erp-surface ffrms-detail-section" aria-labelledby="ffrms-identity-heading">
                <div class="erp-section-header"><div><p class="erp-page-eyebrow">Registration record</p><h3 id="ffrms-identity-heading" class="erp-section-title">Farmer details</h3></div>@if($canUpdate)<span class="erp-pill">Authorized correction available</span>@endif</div>
                <dl class="ffrms-identity-grid">
                    <div><dt>Email</dt><dd>{{ $farmer->user->email }}</dd></div>
                    <div><dt>Phone</dt><dd>{{ $farmer->phone ?: 'Not recorded' }}</dd></div>
                    <div><dt>Barangay</dt><dd>{{ $farmer->barangay ?: 'Not recorded' }}</dd></div>
                    <div><dt>Address</dt><dd>{{ $farmer->address ?: 'Not recorded' }}</dd></div>
                    <div><dt>Verified on</dt><dd>{{ $farmer->verified_at?->format('M j, Y g:i A') ?: 'Not verified' }}</dd></div>
                    <div><dt>Verified by</dt><dd>{{ $farmer->verifier?->name ?: 'Not applicable' }}</dd></div>
                </dl>
                @if($farmer->review_notes)
                    <div class="ffrms-current-feedback"><strong>Latest MAO note</strong><p>{{ $farmer->review_notes }}</p></div>
                @endif
                @if($canUpdate)
                    <details class="ffrms-edit-disclosure" @if(old('registry_form') === 'farmer') open @endif>
                        <summary><x-ui.icon name="action" size="sm" /> Correct farmer information</summary>
                        <form method="POST" action="{{ route('modules.ffrms.registrations.update', $farmer) }}" class="ffrms-edit-grid">
                            @csrf @method('PATCH')
                            <input type="hidden" name="registry_form" value="farmer">
                            <input type="hidden" name="registry_search" value="{{ request('search') }}">
                            <input type="hidden" name="registry_status" value="{{ request('status') }}">
                            <input type="hidden" name="registry_page" value="{{ request('page') }}">
                            <div class="erp-field"><label for="ffrms-edit-name">Full name</label><input id="ffrms-edit-name" class="erp-input" name="name" value="{{ old('registry_form') === 'farmer' ? old('name', $farmer->user->name) : $farmer->user->name }}" maxlength="255" required></div>
                            <div class="erp-field"><label for="ffrms-edit-email">Email address</label><input id="ffrms-edit-email" class="erp-input" type="email" name="email" value="{{ old('registry_form') === 'farmer' ? old('email', $farmer->user->email) : $farmer->user->email }}" maxlength="255" required></div>
                            <div class="erp-field"><label for="ffrms-edit-phone">Phone</label><input id="ffrms-edit-phone" class="erp-input" name="phone" value="{{ old('registry_form') === 'farmer' ? old('phone', $farmer->phone) : $farmer->phone }}" maxlength="30"></div>
                            <div class="erp-field"><label for="ffrms-edit-barangay">Barangay</label><input id="ffrms-edit-barangay" class="erp-input" name="barangay" value="{{ old('registry_form') === 'farmer' ? old('barangay', $farmer->barangay) : $farmer->barangay }}" maxlength="100" required></div>
                            <div class="erp-field ffrms-edit-wide"><label for="ffrms-edit-address">Address</label><input id="ffrms-edit-address" class="erp-input" name="address" value="{{ old('registry_form') === 'farmer' ? old('address', $farmer->address) : $farmer->address }}" maxlength="255"></div>
                            <div class="ffrms-form-actions ffrms-edit-wide"><button class="erp-button erp-button-primary" type="submit">Save audited correction</button><button class="erp-button erp-button-secondary" type="button" data-ffrms-close-edit>Cancel</button></div>
                        </form>
                    </details>
                @endif
            </section>

            <section class="erp-surface ffrms-detail-section" aria-labelledby="ffrms-farms-heading">
                <div class="erp-section-header"><div><p class="erp-page-eyebrow">Linked land records</p><h3 id="ffrms-farms-heading" class="erp-section-title">Registered farms</h3></div><span class="erp-pill">{{ number_format($farmer->farms_count) }} {{ $farmer->farms_count === 1 ? 'farm' : 'farms' }}</span></div>
                @forelse($farmer->farms as $farm)
                    <article class="ffrms-farm-detail">
                        <div class="ffrms-farm-detail-heading"><div><h4>{{ $farm->name }}</h4><p>{{ $farm->barangay }} @if($farm->location_details) · {{ $farm->location_details }} @endif</p></div><span class="erp-pill erp-pill-neutral">{{ ucfirst(str_replace('_', ' ', $farm->status)) }}</span></div>
                        <dl class="ffrms-farm-facts">
                            <div><dt>Area</dt><dd>{{ $farm->area ? number_format((float) $farm->area, 2).' '.str_replace('_', ' ', $farm->area_unit) : 'Not recorded' }}</dd></div>
                            <div><dt>Tenure</dt><dd>{{ ucfirst($farm->ownership_type) }}@if($farm->tenancy_details) · {{ $farm->tenancy_details }} @endif</dd></div>
                            <div><dt>Classification</dt><dd>{{ $farm->classification ?: 'Not recorded' }}</dd></div>
                            <div><dt>Soil / irrigation</dt><dd>{{ $farm->soil_type ?: 'Not recorded' }} · {{ $farm->irrigation_type ?: 'Not recorded' }}</dd></div>
                            <div class="ffrms-farm-fact-wide"><dt>Agricultural activities</dt><dd>{{ count($farm->agricultural_activities ?: []) ? implode(', ', $farm->agricultural_activities) : 'Not recorded' }}</dd></div>
                            <div class="ffrms-farm-fact-wide"><dt>Farm categories</dt><dd>{{ count($farm->categories ?: []) ? implode(', ', $farm->categories) : 'Not recorded' }}</dd></div>
                            <div class="ffrms-farm-fact-wide"><dt>Farm map pin</dt><dd>@if(! $farmLocationsReady)Waiting for database activation @elseif($farm->latitude !== null && $farm->longitude !== null){{ $farm->latitude }}, {{ $farm->longitude }} <span class="ffrms-muted-note">· farmer-provided, not MAO-verified</span> <a href="https://www.openstreetmap.org/?mlat={{ $farm->latitude }}&amp;mlon={{ $farm->longitude }}#map=17/{{ $farm->latitude }}/{{ $farm->longitude }}" target="_blank" rel="noopener noreferrer">Open map</a>@else Not recorded @endif</dd></div>
                        </dl>
                        @if($farmLocationsReady)
                            @php($farmMapPoint = $farm->latitude !== null && $farm->longitude !== null ? [[
                                'latitude' => (float) $farm->latitude,
                                'longitude' => (float) $farm->longitude,
                                'farmer' => $farmer->user->name,
                                'farmerCode' => $farmer->farmer_code,
                                'farm' => $farm->name,
                                'barangay' => $farm->barangay,
                            ]] : [])
                            <div class="ffrms-farm-map-panel">
                                <div class="ffrms-farm-map" data-ffrms-map data-ffrms-map-points="{{ json_encode($farmMapPoint) }}" tabindex="0" role="region" aria-label="Map for {{ $farm->name }}"></div>
                                @if($farmMapPoint === [])
                                    <p class="ffrms-map-note" data-ffrms-map-empty role="status">No location pin is recorded for this farm.</p>
                                @endif
                                <p class="ffrms-map-attribution">Map data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>.</p>
                            </div>
                        @endif
                        @if($canUpdate)
                            <details class="ffrms-edit-disclosure" @if(old('registry_form') === 'farm-'.$farm->id) open @endif>
                                <summary><x-ui.icon name="action" size="sm" /> Edit farm record</summary>
                                <form method="POST" action="{{ route('modules.ffrms.farms.update', $farm) }}" class="ffrms-edit-grid">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="registry_form" value="farm-{{ $farm->id }}">
                                    <input type="hidden" name="registry_search" value="{{ request('search') }}">
                                    <input type="hidden" name="registry_status" value="{{ request('status') }}">
                                    <input type="hidden" name="registry_page" value="{{ request('page') }}">
                                    <div class="erp-field"><label for="farm-name-{{ $farm->id }}">Farm name</label><input id="farm-name-{{ $farm->id }}" class="erp-input" name="name" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('name', $farm->name) : $farm->name }}" maxlength="255" required></div>
                                    <div class="erp-field"><label for="farm-barangay-{{ $farm->id }}">Barangay</label><input id="farm-barangay-{{ $farm->id }}" class="erp-input" name="barangay" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('barangay', $farm->barangay) : $farm->barangay }}" maxlength="100" required></div>
                                    <div class="erp-field"><label for="farm-location-{{ $farm->id }}">Location details</label><input id="farm-location-{{ $farm->id }}" class="erp-input" name="location_details" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('location_details', $farm->location_details) : $farm->location_details }}" maxlength="255"></div>
                                    @if($farmLocationsReady)
                                        <div class="erp-field"><label for="farm-latitude-{{ $farm->id }}">Latitude</label><input id="farm-latitude-{{ $farm->id }}" class="erp-input" name="latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('latitude', $farm->latitude) : $farm->latitude }}"></div>
                                        <div class="erp-field"><label for="farm-longitude-{{ $farm->id }}">Longitude</label><input id="farm-longitude-{{ $farm->id }}" class="erp-input" name="longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('longitude', $farm->longitude) : $farm->longitude }}"></div>
                                        <input type="hidden" name="location_source" value="manual"><input type="hidden" name="location_accuracy_meters" value="">
                                    @else
                                        <p class="ffrms-muted-note ffrms-edit-wide">Exact map-pin editing will be available after the database update is activated. Other farm details can still be edited.</p>
                                    @endif
                                    <div class="erp-field"><label for="farm-area-{{ $farm->id }}">Area</label><input id="farm-area-{{ $farm->id }}" class="erp-input" name="area" type="number" min=".01" step=".01" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('area', $farm->area) : $farm->area }}"></div>
                                    <div class="erp-field"><label for="farm-area-unit-{{ $farm->id }}">Area unit</label><select id="farm-area-unit-{{ $farm->id }}" class="erp-input" name="area_unit">@foreach(['hectare' => 'Hectare', 'square_meter' => 'Square meter'] as $value => $label)<option value="{{ $value }}" @selected((old('registry_form') === 'farm-'.$farm->id ? old('area_unit', $farm->area_unit) : $farm->area_unit) === $value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="erp-field"><label for="farm-ownership-{{ $farm->id }}">Ownership / tenure</label><select id="farm-ownership-{{ $farm->id }}" class="erp-input" name="ownership_type">@foreach(['owner', 'tenant', 'leaseholder', 'other'] as $value)<option value="{{ $value }}" @selected((old('registry_form') === 'farm-'.$farm->id ? old('ownership_type', $farm->ownership_type) : $farm->ownership_type) === $value)>{{ ucfirst($value) }}</option>@endforeach</select></div>
                                    <div class="erp-field"><label for="farm-classification-{{ $farm->id }}">Classification</label><input id="farm-classification-{{ $farm->id }}" class="erp-input" name="classification" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('classification', $farm->classification) : $farm->classification }}" maxlength="100"></div>
                                    <div class="erp-field"><label for="farm-tenancy-{{ $farm->id }}">Tenancy details</label><input id="farm-tenancy-{{ $farm->id }}" class="erp-input" name="tenancy_details" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('tenancy_details', $farm->tenancy_details) : $farm->tenancy_details }}" maxlength="255"></div>
                                    <div class="erp-field"><label for="farm-soil-{{ $farm->id }}">Soil type</label><input id="farm-soil-{{ $farm->id }}" class="erp-input" name="soil_type" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('soil_type', $farm->soil_type) : $farm->soil_type }}" maxlength="100"></div>
                                    <div class="erp-field"><label for="farm-irrigation-{{ $farm->id }}">Irrigation</label><input id="farm-irrigation-{{ $farm->id }}" class="erp-input" name="irrigation_type" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('irrigation_type', $farm->irrigation_type) : $farm->irrigation_type }}" maxlength="100"></div>
                                    <div class="erp-field ffrms-edit-wide"><label for="farm-activities-{{ $farm->id }}">Agricultural activities (comma separated)</label><input id="farm-activities-{{ $farm->id }}" class="erp-input" name="agricultural_activities" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('agricultural_activities', implode(', ', $farm->agricultural_activities ?: [])) : implode(', ', $farm->agricultural_activities ?: []) }}" maxlength="1000"></div>
                                    <div class="erp-field ffrms-edit-wide"><label for="farm-categories-{{ $farm->id }}">Categories (comma separated)</label><input id="farm-categories-{{ $farm->id }}" class="erp-input" name="categories" value="{{ old('registry_form') === 'farm-'.$farm->id ? old('categories', implode(', ', $farm->categories ?: [])) : implode(', ', $farm->categories ?: []) }}" maxlength="1000"></div>
                                    <div class="ffrms-form-actions ffrms-edit-wide"><button class="erp-button erp-button-primary" type="submit">Save farm record</button><button class="erp-button erp-button-secondary" type="button" data-ffrms-close-edit>Cancel</button></div>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="erp-empty">No farms are linked to this farmer record.</div>
                @endforelse
            </section>

            <section class="erp-surface ffrms-detail-section" aria-labelledby="ffrms-associations-heading">
                <div class="erp-section-header"><div><p class="erp-page-eyebrow">Membership records</p><h3 id="ffrms-associations-heading" class="erp-section-title">Farmer associations</h3></div><span class="erp-pill">{{ number_format($farmer->associations->count()) }} memberships</span></div>
                @forelse($farmer->associations as $association)
                    <article class="ffrms-association-membership"><strong>{{ $association->name }}</strong><span>{{ ucfirst(str_replace('_', ' ', $association->pivot->membership_status)) }}</span><time>{{ $association->pivot->joined_at ? \Illuminate\Support\Carbon::parse($association->pivot->joined_at)->format('M j, Y') : 'Join date not recorded' }}</time></article>
                @empty
                    <p class="ffrms-muted-note">No association memberships are recorded for this farmer.</p>
                @endforelse
            </section>
        </div>

        <aside class="ffrms-detail-aside">
            @if($decisions)
                <section class="erp-surface ffrms-review-panel" aria-labelledby="ffrms-review-title">
                    <p class="erp-page-eyebrow">Registration review</p><h3 id="ffrms-review-title" class="erp-section-title">Available decision</h3>
                    <p class="ffrms-muted-note">Choose an action permitted for your account. Add a note to explain the decision to the farmer.</p>
                    <form method="POST" action="{{ route('modules.ffrms.review', $farmer) }}" class="ffrms-review-form" data-confirm-submit="Save this farmer registration decision?">
                        @csrf
                        <input type="hidden" name="registry_search" value="{{ request('search') }}"><input type="hidden" name="registry_status" value="{{ request('status') }}"><input type="hidden" name="registry_page" value="{{ request('page') }}">
                        <div class="erp-field"><label for="ffrms-review-status">Review decision</label><select id="ffrms-review-status" name="status" class="erp-input" required>@foreach($decisions as $value => $label)<option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="erp-field"><label for="ffrms-review-reason">Review note</label><textarea id="ffrms-review-reason" name="reason" class="erp-input" rows="4" maxlength="2000" aria-describedby="ffrms-review-help">{{ old('reason') }}</textarea><p id="ffrms-review-help" class="ffrms-field-help">Optional. A clear note helps the farmer understand the outcome.</p></div>
                        <button class="erp-button erp-button-primary w-full" type="submit">Save review decision</button>
                    </form>
                </section>
            @elseif($canVerify || $canReturn || $canReject)
                <section class="erp-surface ffrms-review-panel"><p class="erp-page-eyebrow">Registration review</p><h3 class="erp-section-title">No decision available</h3><p class="ffrms-muted-note">This account has no permitted status change for the farmer’s current state.</p></section>
            @endif

            <section class="erp-surface ffrms-history-panel" aria-labelledby="ffrms-history-heading">
                <p class="erp-page-eyebrow">Audit trail</p><h3 id="ffrms-history-heading" class="erp-section-title">Verification history</h3>
                @forelse($farmer->registrationHistory as $history)
                    <article class="ffrms-history-entry"><span class="ffrms-history-marker" aria-hidden="true"></span><div><div class="ffrms-history-heading"><strong>{{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}</strong><time datetime="{{ $history->created_at?->toIso8601String() }}">{{ $history->created_at?->format('M j, Y g:i A') }}</time></div><p>{{ $history->reason ?: 'No review note recorded.' }}</p><span class="ffrms-history-actor">{{ $history->processor?->name ?: 'Farmer portal' }}</span></div></article>
                @empty
                    <div class="erp-empty">No verification history is recorded.</div>
                @endforelse
            </section>
        </aside>
    </div>
</section>
