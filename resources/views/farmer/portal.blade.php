<x-layouts.app>
    <x-slot name="header">Farmer Portal</x-slot>
    <x-slot name="description">Keep your farmer identity, farm records, and agricultural services connected in one place.</x-slot>
    <x-slot name="actions">
        <a href="{{ route('farmer.assistance') }}" class="erp-button erp-button-primary">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10.01 10.01 0 0 0 12 2Zm1 15h-2v-2h2Zm2.07-7.25-.9.92A3.49 3.49 0 0 0 13 13.2V14h-2v-.9a3.5 3.5 0 0 1 1.03-2.47l1.24-1.26A1.49 1.49 0 0 0 13.7 8.3a1.7 1.7 0 0 0-3.4 0H8.3a3.7 3.7 0 0 1 7.4 0 3.47 3.47 0 0 1-.63 1.45Z"/></svg>
            Browse assistance
        </a>
    </x-slot>

    @if(session('status'))
        <div class="erp-alert erp-alert-success mb-6" role="status">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5 shrink-0"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10.01 10.01 0 0 0 12 2Zm-1 15-4-4 1.4-1.4L11 14.17l4.6-4.6L17 11Z"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="erp-alert erp-alert-danger mb-6" role="alert">
            <div>
                <p class="font-semibold">Please review the highlighted information.</p>
                <ul class="mt-1 list-disc pl-5 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-3">
        <article class="erp-stat-card">
            <div class="erp-stat-icon bg-emerald-50 text-emerald-700"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5"><path fill="currentColor" d="M12 21a9 9 0 1 1 9-9 9 9 0 0 1-9 9Zm0-16a7 7 0 1 0 7 7 7 7 0 0 0-7-7Zm-1 3h2v5h-2Zm0 7h2v2h-2Z"/></svg></div>
            <div><p class="erp-stat-label">Verification status</p><p class="erp-stat-value text-2xl">{{ ucfirst($farmer->registration_status) }}</p></div>
        </article>
        <article class="erp-stat-card">
            <div class="erp-stat-icon bg-amber-50 text-amber-700"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5"><path fill="currentColor" d="M19 19H5V5h14Zm-2-2V7H7v10Zm-8-1h2v-5H9Zm3 0h2V8h-2Z"/></svg></div>
            <div><p class="erp-stat-label">Registered farms</p><p class="erp-stat-value text-2xl">{{ $farmer->farms->count() }}</p></div>
        </article>
        <article class="erp-stat-card">
            <div class="erp-stat-icon bg-sage text-forest-green"><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10.01 10.01 0 0 0 12 2Zm1 15h-2v-2h2Zm1.8-6.2-.9.9A3.5 3.5 0 0 0 13 14h-2a5.2 5.2 0 0 1 1.5-3.7l1-1a1.7 1.7 0 1 0-2.9-1.2H8.5a3.7 3.7 0 1 1 6.3 2.7Z"/></svg></div>
            <div><p class="erp-stat-label">Farmer ID</p><p class="erp-stat-value text-xl">{{ $farmer->farmer_code }}</p></div>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
        <article class="erp-surface">
            <div class="erp-section-header">
                <div>
                    <p class="erp-page-eyebrow">Your connected identity</p>
                    <h2 class="erp-section-title">Welcome, {{ $farmer->user->name }}</h2>
                </div>
                <span class="erp-pill {{ $farmer->isVerified() ? 'erp-pill-success' : 'erp-pill-warning' }}">{{ ucfirst($farmer->registration_status) }}</span>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="erp-detail-item"><span>Farmer code</span><strong>{{ $farmer->farmer_code }}</strong></div>
                <div class="erp-detail-item"><span>Email</span><strong class="break-all">{{ $farmer->user->email }}</strong></div>
                <div class="erp-detail-item"><span>Barangay</span><strong>{{ $farmer->barangay }}</strong></div>
                <div class="erp-detail-item"><span>Contact number</span><strong>{{ $farmer->phone ?: 'Not provided' }}</strong></div>
            </div>
            @if($farmer->review_notes)
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Latest MAO note</p>
                    <p class="mt-1">{{ $farmer->review_notes }}</p>
                </div>
            @endif
        </article>

        <article class="erp-surface">
            <div class="erp-section-header">
                <div>
                    <p class="erp-page-eyebrow">Keep information current</p>
                    <h2 class="erp-section-title">My profile</h2>
                </div>
            </div>
            <form method="POST" action="{{ route('farmer.profile.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <div class="erp-field"><label for="phone">Contact number</label><input id="phone" name="phone" value="{{ old('phone', $farmer->phone) }}" placeholder="09xx xxx xxxx"></div>
                <div class="erp-field"><label for="address">Address</label><textarea id="address" name="address" rows="2">{{ old('address', $farmer->address) }}</textarea></div>
                <div class="erp-field"><label for="barangay">Barangay</label><input id="barangay" name="barangay" value="{{ old('barangay', $farmer->barangay) }}" required></div>
                <button class="erp-button erp-button-primary" type="submit">Save profile</button>
            </form>
        </article>
    </section>

    <section class="erp-surface mt-6">
        <div class="erp-section-header">
            <div>
                <p class="erp-page-eyebrow">FFRMS records</p>
                <h2 class="erp-section-title">My farms</h2>
                <p class="erp-section-description">Maintain the farm information used for agricultural programs and assistance eligibility.</p>
            </div>
            <span class="erp-pill">{{ $farmer->farms->count() }} registered</span>
        </div>

        <div class="mt-5 space-y-5">
            @forelse($farmer->farms as $farm)
                <div class="rounded-2xl border border-sage bg-off-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-forest-green">{{ $farm->name }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $farm->area ?: 'Area not provided' }} {{ $farm->area_unit }} · {{ $farm->barangay }}</p>
                        </div>
                        <span class="erp-pill erp-pill-neutral">{{ ucfirst($farm->ownership_type) }}</span>
                    </div>
                    <form method="POST" action="{{ route('farmer.farms.update', $farm) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        @csrf
                        @method('PATCH')
                        <div class="erp-field md:col-span-2"><label for="farm-name-{{ $farm->id }}">Farm name</label><input id="farm-name-{{ $farm->id }}" name="name" value="{{ $farm->name }}" required></div>
                        <div class="erp-field"><label for="farm-barangay-{{ $farm->id }}">Barangay</label><input id="farm-barangay-{{ $farm->id }}" name="barangay" value="{{ $farm->barangay }}" required></div>
                        <div class="erp-field"><label for="farm-area-{{ $farm->id }}">Area</label><input id="farm-area-{{ $farm->id }}" name="area" type="number" min="0.01" step="0.01" value="{{ $farm->area }}"></div>
                        <div class="erp-field md:col-span-2"><label for="farm-location-{{ $farm->id }}">Location details</label><input id="farm-location-{{ $farm->id }}" name="location_details" value="{{ $farm->location_details }}"></div>
                        <div class="erp-field"><label for="farm-unit-{{ $farm->id }}">Unit</label><select id="farm-unit-{{ $farm->id }}" name="area_unit"><option value="hectare" @selected($farm->area_unit === 'hectare')>Hectare</option><option value="square_meter" @selected($farm->area_unit === 'square_meter')>Square meter</option></select></div>
                        <div class="erp-field"><label for="farm-ownership-{{ $farm->id }}">Ownership / tenancy</label><select id="farm-ownership-{{ $farm->id }}" name="ownership_type"><option value="owner" @selected($farm->ownership_type === 'owner')>Owner</option><option value="tenant" @selected($farm->ownership_type === 'tenant')>Tenant</option><option value="leaseholder" @selected($farm->ownership_type === 'leaseholder')>Leaseholder</option><option value="other" @selected($farm->ownership_type === 'other')>Other</option></select></div>
                        <div class="erp-field md:col-span-2"><label for="farm-activities-{{ $farm->id }}">Agricultural activities</label><input id="farm-activities-{{ $farm->id }}" name="agricultural_activities" value="{{ implode(', ', $farm->agricultural_activities ?? []) }}" placeholder="Planting, livestock"></div>
                        <div class="erp-field md:col-span-2"><label for="farm-categories-{{ $farm->id }}">Crops or livestock categories</label><input id="farm-categories-{{ $farm->id }}" name="categories" value="{{ implode(', ', $farm->categories ?? []) }}" placeholder="Rice, poultry"></div>
                        <div class="md:col-span-2 xl:col-span-4"><button class="erp-button erp-button-secondary" type="submit">Update farm record</button></div>
                    </form>
                </div>
            @empty
                <div class="erp-empty-state"><p class="font-semibold text-forest-green">No farm records yet</p><p class="mt-1 text-sm">Add your first farm below to make your profile ready for agricultural services.</p></div>
            @endforelse
        </div>

        <div class="mt-6 border-t border-slate-200 pt-6">
            <div class="mb-4"><p class="erp-page-eyebrow">Add a record</p><h3 class="text-lg font-semibold text-forest-green">Register another farm</h3></div>
            <form method="POST" action="{{ route('farmer.farms.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @csrf
                <div class="erp-field md:col-span-2"><label for="new-farm-name">Farm name</label><input id="new-farm-name" name="name" required></div>
                <div class="erp-field"><label for="new-farm-barangay">Barangay</label><input id="new-farm-barangay" name="barangay" value="{{ $farmer->barangay }}" required></div>
                <div class="erp-field"><label for="new-farm-area">Area</label><input id="new-farm-area" name="area" type="number" min="0.01" step="0.01"></div>
                <div class="erp-field md:col-span-2"><label for="new-farm-location">Location details</label><input id="new-farm-location" name="location_details"></div>
                <div class="erp-field"><label for="new-farm-unit">Unit</label><select id="new-farm-unit" name="area_unit"><option value="hectare">Hectare</option><option value="square_meter">Square meter</option></select></div>
                <div class="erp-field"><label for="new-farm-ownership">Ownership / tenancy</label><select id="new-farm-ownership" name="ownership_type"><option value="owner">Owner</option><option value="tenant">Tenant</option><option value="leaseholder">Leaseholder</option><option value="other">Other</option></select></div>
                <div class="erp-field md:col-span-2"><label for="new-farm-activities">Agricultural activities</label><input id="new-farm-activities" name="agricultural_activities" placeholder="Planting, livestock"></div>
                <div class="erp-field md:col-span-2"><label for="new-farm-categories">Crops or livestock categories</label><input id="new-farm-categories" name="categories" placeholder="Rice, poultry"></div>
                <div class="md:col-span-2 xl:col-span-4"><button class="erp-button erp-button-primary" type="submit">Add farm</button></div>
            </form>
        </div>
    </section>

    @if($farmer->registrationHistory->isNotEmpty())
        <section class="erp-surface mt-6">
            <div class="erp-section-header"><div><p class="erp-page-eyebrow">Verification trail</p><h2 class="erp-section-title">Registration history</h2></div></div>
            <div class="mt-4 divide-y divide-slate-200">
                @foreach($farmer->registrationHistory as $history)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <div><span class="erp-pill {{ $history->status === 'verified' ? 'erp-pill-success' : 'erp-pill-neutral' }}">{{ ucfirst($history->status) }}</span><span class="ml-2 text-slate-600">{{ $history->notes ?: 'No note provided' }}</span></div>
                        <time class="text-slate-500">{{ optional($history->created_at)->format('M d, Y h:i A') }}</time>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
