<x-layouts.farmer>
    <x-slot name="title">Borrow equipment from MAO</x-slot>
    <x-slot name="description">Request municipal agricultural equipment for a future schedule, then track MAO approval, release, and return.</x-slot>

    <section class="farmer-task-hero" aria-labelledby="equipment-task-title">
        <div class="farmer-task-hero-copy">
            <p class="farmer-eyebrow"><x-ui.icon name="equipment" size="sm" />Equipment borrowing</p>
            <h2 id="equipment-task-title">Request the equipment your farm needs.</h2>
            <p>Choose an available municipal asset, send the dates you need, and follow the decision here. Equipment is physically released only after the MAO approves the schedule.</p>
            @if($farmer->isVerified() && $assets->count())
                <a class="farmer-button farmer-button-gold" href="#equipment-request-form" data-open-disclosure="equipment-request-form"><x-ui.icon name="calendar" size="sm" />Choose equipment and request dates</a>
            @elseif(!$farmer->isVerified())
                <a class="farmer-button farmer-button-light" href="{{ route('farmer.farms') }}"><x-ui.icon name="farms" size="sm" />Complete farmer verification</a>
            @endif
        </div>
        <ol class="farmer-task-flow" aria-label="Equipment borrowing process">
            <li><span>1</span><div><strong>Choose equipment</strong><small>See what the MAO has made available.</small></div></li>
            <li><span>2</span><div><strong>MAO confirms the schedule</strong><small>Conflicts and condition are checked before approval.</small></div></li>
            <li><span>3</span><div><strong>Collect and return it</strong><small>Staff records physical release and return.</small></div></li>
        </ol>
    </section>

    @if(!$farmer->isVerified())
        <div class="farmer-message farmer-message-warning" role="status"><strong>Farmer verification is required before borrowing equipment.</strong> Your registration is {{ str($farmer->registration_status)->replace('_', ' ') }}. Keep your farm record current so the MAO can complete its review. <a class="font-bold underline" href="{{ route('farmer.farms') }}">Open My farms</a>.</div>
    @endif

    <section class="farmer-section" aria-labelledby="available-equipment-title">
        <div class="farmer-section-heading">
            <div><p class="farmer-eyebrow">Available to request</p><h2 id="available-equipment-title">Municipal equipment catalog</h2><p>Only equipment currently available for a new schedule is shown. A request does not reserve an asset until the MAO approves it.</p></div>
        </div>
        <form method="GET" action="{{ route('farmer.equipment') }}" class="farmer-filter-bar" aria-label="Search available equipment">
            <div class="farmer-field"><label for="equipment-search">Find equipment</label><input id="equipment-search" name="search" type="search" value="{{ request('search') }}" placeholder="For example: tractor, pump, thresher"></div>
            <div class="farmer-filter-actions"><button class="farmer-button" type="submit"><x-ui.icon name="search" size="sm" />Search catalog</button><a class="farmer-button farmer-button-light" href="{{ route('farmer.equipment') }}">Clear search</a></div>
        </form>

        @if($farmer->isVerified() && $assets->count())
            <details id="equipment-request-form" class="farmer-card farmer-request-form" @if(request('request_asset') || $errors->hasAny(['equipment_asset_id', 'schedule_start', 'schedule_end', 'intended_use'])) open @endif>
                <summary><span><x-ui.icon name="equipment" size="md" />Request a borrowing schedule</span><small>Choose the equipment, dates, and intended farm use.</small></summary>
                <div class="farmer-form-intro"><strong>Before you send</strong><span>All dates use Philippine time. The MAO checks schedules before confirming physical release.</span></div>
                <form method="POST" action="{{ route('modules.aeams.requests.store') }}" class="farmer-request-fields" data-submit-feedback>@csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="farmer-field farmer-field-wide"><label for="equipment-asset">Equipment to borrow</label><select id="equipment-asset" name="equipment_asset_id" required><option value="">Choose available equipment</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected((string) old('equipment_asset_id', request('request_asset')) === (string) $asset->id)>{{ $asset->name }} &middot; {{ $asset->category?->name }} &middot; {{ str($asset->condition)->headline() }} condition</option>@endforeach</select>@error('equipment_asset_id')<small class="ui-field-error">{{ $message }}</small>@enderror</div>
                    <div class="farmer-field"><label for="equipment-start">When do you need it?</label><input id="equipment-start" name="schedule_start" type="datetime-local" min="{{ now(config('agriculture.business_timezone'))->format('Y-m-d\TH:i') }}" value="{{ old('schedule_start') }}" required>@error('schedule_start')<small class="ui-field-error">{{ $message }}</small>@enderror</div>
                    <div class="farmer-field"><label for="equipment-end">When will you return it?</label><input id="equipment-end" name="schedule_end" type="datetime-local" min="{{ now(config('agriculture.business_timezone'))->format('Y-m-d\TH:i') }}" value="{{ old('schedule_end') }}" required>@error('schedule_end')<small class="ui-field-error">{{ $message }}</small>@enderror</div>
                    <div class="farmer-field farmer-field-wide"><label for="equipment-use">What farm work will it be used for?</label><textarea id="equipment-use" name="intended_use" maxlength="2000" required aria-describedby="equipment-use-help">{{ old('intended_use') }}</textarea><small id="equipment-use-help">Describe the farm work so the MAO can review the request.</small>@error('intended_use')<small class="ui-field-error">{{ $message }}</small>@enderror</div>
                    <button class="farmer-button farmer-field-wide" type="submit"><x-ui.icon name="success" size="sm" /><span data-submit-label>Send borrowing request to MAO</span></button>
                </form>
            </details>
        @endif

        <div class="farmer-equipment-grid">@forelse($assets as $asset)<article class="farmer-equipment-card"><div class="farmer-equipment-card-head"><span class="ui-service-icon" aria-hidden="true"><x-ui.icon name="equipment" size="md" /></span><span class="farmer-pill">Available to request</span></div><h3>{{ $asset->name }}</h3><dl><div><dt>Category</dt><dd>{{ $asset->category?->name ?? 'Municipal equipment' }}</dd></div><div><dt>Condition</dt><dd>{{ str($asset->condition)->headline() }}</dd></div></dl><div class="farmer-equipment-card-actions"><a class="farmer-text-link" href="{{ route('farmer.equipment.show', $asset) }}">View equipment details</a>@if($farmer->isVerified())<a class="farmer-button farmer-button-light" href="{{ route('farmer.equipment', ['request_asset' => $asset->id]) }}#equipment-request-form">Request this equipment</a>@endif</div></article>@empty
            @if($equipmentTotal === 0)
                <div class="farmer-empty"><strong>The MAO equipment catalog is not set up yet.</strong> No equipment asset has been registered by authorized MAO personnel, so there is nothing to borrow yet. Once the MAO registers an asset and marks it available, it will appear here automatically.</div>
            @else
                <div class="farmer-empty"><strong>No equipment is available right now.</strong> The registered assets may be reserved, issued, archived, or under maintenance. Check again later or contact the MAO.</div>
            @endif
        @endforelse</div>
        <div class="mt-4">{{ $assets->links() }}</div>
    </section>

    <section class="farmer-section" aria-labelledby="my-equipment-requests-title">
        <div class="farmer-section-heading">
            <div>
                <p class="farmer-eyebrow">My borrowing history</p>
                <h2 id="my-equipment-requests-title">My equipment requests</h2>
                <p>Every request stays visible here, including MAO decisions, issue, and return.</p>
            </div>
        </div>
        <div class="farmer-request-list">
            @forelse($requests as $requestRecord)
                @php($requestGuidance = match($requestRecord->status) {
                    'pending' => 'The MAO will review asset availability and your requested dates.',
                    'approved' => 'Your schedule is approved. Wait for MAO instructions before collection.',
                    'issued' => 'The equipment is currently issued to you. Return it on the agreed schedule.',
                    'returned' => 'The MAO has recorded this equipment as returned.',
                    'rejected' => 'Read the MAO feedback before making another request.',
                    'cancelled' => 'This request was cancelled.',
                    default => 'Open this request to view its full history.',
                })
                <article class="farmer-request-card">
                    <div class="farmer-request-card-status">
                        <span class="farmer-pill {{ $requestRecord->status === 'rejected' ? 'farmer-pill-bad' : ($requestRecord->status === 'pending' ? 'farmer-pill-warn' : '') }}">{{ str($requestRecord->status)->replace('_', ' ')->headline() }}</span>
                        <span>{{ $requestRecord->reference }}</span>
                    </div>
                    <div>
                        <h3>{{ $requestRecord->asset->name }}</h3>
                        <p class="farmer-request-schedule"><x-ui.icon name="calendar" size="sm" />{{ $requestRecord->schedule_start?->timezone(config('agriculture.business_timezone'))->format('M d, Y h:i A') }} to {{ $requestRecord->schedule_end?->timezone(config('agriculture.business_timezone'))->format('M d, Y h:i A') }} Philippine time</p>
                        <p class="farmer-request-purpose"><strong>Planned use:</strong> {{ \Illuminate\Support\Str::limit($requestRecord->intended_use, 180) }}</p>
                        <p>{{ $requestGuidance }}</p>
                    </div>
                    <div class="farmer-request-card-actions"><a class="farmer-button farmer-button-light" href="{{ route('farmer.equipment-requests.show', $requestRecord) }}">View request history</a></div>
                </article>
            @empty
                <div class="farmer-empty">You have not requested municipal equipment yet. Choose an available item above when you are ready to request a schedule.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $requests->links() }}</div>
    </section>
</x-layouts.farmer>
