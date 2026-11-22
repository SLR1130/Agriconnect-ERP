<x-layouts.farmer>
    <x-slot name="title">My farms</x-slot>
    <x-slot name="description">Keep your farm information current so the Municipal Agriculture Office can connect you with the right services.</x-slot>

    <x-ui.card class="farmer-registration-card" aria-labelledby="registration-status-title">
        <div class="farmer-registration-summary">
            <div class="farmer-registration-mark"><x-ui.icon name="office" size="lg"/></div>
            <div class="farmer-registration-copy">
                <p class="ui-eyebrow">Farmer registration · {{ $farmer->farmer_code }}</p>
                <h2 id="registration-status-title">Your registration</h2>
                <p>{{ $farmer->user->name }} <span aria-hidden="true">·</span> {{ $farmer->barangay }}</p>
            </div>
            <x-ui.status-badge :status="$farmer->registration_status" />
        </div>
        @if($farmer->review_notes)
            <div class="farmer-message {{ $farmer->registration_status === 'rejected' || $farmer->registration_status === 'returned' ? 'farmer-message-warning' : 'farmer-message-success' }} mt-4" role="status"><strong>MAO feedback:</strong> {{ $farmer->review_notes }}</div>
        @endif
        @if(!$farmer->isVerified())
            <p class="farmer-registration-guidance"><x-ui.icon name="success" size="sm"/><span class="ui-guidance-copy">Farm records can be added while your registration is under review. Assistance applications and equipment requests require MAO verification.</span></p>
        @else
            <p class="farmer-registration-guidance"><x-ui.icon name="success" size="sm"/><span class="ui-guidance-copy">Your farmer registration is verified. You can use the services for which you meet the program requirements.</span></p>
        @endif
    </x-ui.card>

    @php
        $initialStep = $errors->hasAny(['name', 'barangay']) ? 1 : ($errors->hasAny(['location', 'location_details', 'latitude', 'longitude', 'location_source', 'location_accuracy_meters', 'boundary_coordinates']) ? 2 : 3);
        $createHasErrors = $errors->any() && !old('_farm_id');
    @endphp

    <details id="farm-registration" class="farmer-section ui-card ui-farm-disclosure" @if($createHasErrors) open @endif>
        <summary class="ui-farm-summary">
            <span class="ui-farm-summary-mark"><x-ui.icon name="add"/></span>
            <span class="ui-farm-summary-copy"><strong>{{ $farmer->farms->isEmpty() ? 'Add your first farm' : 'Add another farm' }}</strong><small>About two minutes · your information is saved to your farmer account</small></span>
            <span class="ui-farm-summary-action">{{ $farmer->farms->isEmpty() ? 'Get started' : 'Add farm' }} <x-ui.icon name="chevron" size="sm"/></span>
        </summary>
        <div id="farm-registration-form" class="ui-farm-wizard" data-farm-wizard data-initial-step="{{ $createHasErrors ? $initialStep : 1 }}">
            <div class="ui-wizard-header"><div><p class="ui-eyebrow">Farm details</p><h2>Register a farm</h2><p>Your farm details stay linked to your farmer record.</p></div><p class="ui-wizard-step-status" data-farm-step-status aria-live="polite">Step 1 of 4</p></div>
            @if($createHasErrors)<div class="farmer-message farmer-message-error" role="alert"><strong>Some details need attention.</strong> Review the highlighted fields before continuing.</div>@endif
            <ol class="ui-wizard-progress" aria-label="Farm registration steps">
                <li data-farm-step-indicator="1"><span>1</span><small>Basics</small></li><li data-farm-step-indicator="2"><span>2</span><small>Location</small></li><li data-farm-step-indicator="3"><span>3</span><small>Farm details</small></li><li data-farm-step-indicator="4"><span>4</span><small>Review</small></li>
            </ol>
            <form method="POST" action="{{ route('farmer.farms.store') }}" class="ui-farm-form" data-farm-wizard-form>
                @csrf
                @include('farmer.partials.farm-fields', ['wizard' => true, 'prefix' => 'new-farm'])
                <section class="ui-wizard-review" data-farm-step-panel="4" aria-labelledby="new-farm-review-title">
                    <h3 class="wizard-step-title" id="new-farm-review-title" tabindex="-1" data-farm-step-heading>Review your farm details</h3><p class="wizard-step-copy">Check the information before sending it to your farm records.</p>
                    <dl class="ui-review-list"><div><dt>Farm</dt><dd data-review-name>Not entered</dd></div><div><dt>Barangay</dt><dd data-review-barangay>Not entered</dd></div><div><dt>Map pin</dt><dd data-review-coordinates>Not added</dd></div><div><dt>Area</dt><dd data-review-area>Not entered</dd></div><div><dt>Land arrangement</dt><dd data-review-ownership>Not entered</dd></div><div><dt>Activities</dt><dd data-review-activities>Not entered</dd></div><div><dt>Usual farm products</dt><dd data-review-categories>Not entered</dd></div></dl>
                    <p class="ui-review-note">After saving, you can review or edit this farm from My farms.</p>
                </section>
                <div class="ui-wizard-controls"><button type="button" class="farmer-button farmer-button-light" data-farm-back hidden><x-ui.icon name="back" size="sm"/> Back</button><span class="ui-wizard-control-spacer"></span><button type="button" class="farmer-button" data-farm-next>Continue <x-ui.icon name="next" size="sm"/></button><button type="submit" class="farmer-button farmer-button-gold" data-farm-submit hidden><x-ui.icon name="success" size="sm"/> Save farm</button></div>
                <p class="ui-submit-status" data-farm-submit-status role="status" aria-live="polite"></p>
            </form>
        </div>
    </details>

    <section class="farmer-section" aria-labelledby="registered-farms-title">
        <x-ui.section-heading id="registered-farms-title" title="Your registered farms" description="Only farms linked to your account are shown here.">
            <x-slot:actions><x-ui.status-badge status="neutral" :label="$farmer->farms->count().' '.str('farm')->plural($farmer->farms->count())"/></x-slot:actions>
        </x-ui.section-heading>
        @if($farmer->farms->isEmpty())
            <x-ui.empty-state icon="farms" title="No farms added yet" description="Add your first farm above to keep your land and agricultural activities together in one place."><a class="farmer-button farmer-button-light" href="#farm-registration" data-farm-wizard-open><x-ui.icon name="add" size="sm"/> Add your first farm</a></x-ui.empty-state>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach($farmer->farms as $farm)
                    <article id="farm-{{ $farm->id }}" class="farmer-card ui-farm-card">
                        <div class="ui-farm-card-heading"><span class="ui-farm-card-icon"><x-ui.icon name="farms"/></span><div class="min-w-0"><h3>{{ $farm->name }}</h3><p>{{ $farm->barangay }}@if($farm->area) <span aria-hidden="true">·</span> {{ number_format((float)$farm->area, 2) }} {{ $farm->area_unit === 'square_meter' ? 'sq m' : 'ha' }}@endif</p></div><x-ui.status-badge :status="$farm->ownership_type" /></div>
                        @if($farm->location_details || ($farm->latitude !== null && $farm->longitude !== null))<div class="ui-farm-location"><x-ui.icon name="location" size="sm"/><div><span>{{ $farm->location_details ?: $farm->barangay }}</span>@if($farm->latitude !== null && $farm->longitude !== null)<small>Farmer-provided pin{{ $farm->location_source === 'gps' && $farm->location_accuracy_meters ? ' · device accuracy about '.number_format((float)$farm->location_accuracy_meters).' m' : '' }} · not yet verified by the MAO.</small><a href="https://www.google.com/maps/search/?api=1&amp;query={{ $farm->latitude }},{{ $farm->longitude }}" target="_blank" rel="noopener noreferrer">Open farm location in Google Maps</a>@endif</div></div>@endif
                        <div class="ui-farm-tags"><div><span>Farm activities</span><strong>{{ count($farm->agricultural_activities ?? []) ? implode(', ', $farm->agricultural_activities) : 'Not added' }}</strong></div><div><span>Usual farm products</span><strong>{{ count($farm->categories ?? []) ? collect($farm->categories)->map(fn ($value) => str($value)->replace('_', ' ')->title())->implode(', ') : 'Not added' }}</strong></div></div>
                        <section class="mt-4 border-t border-sage pt-4" aria-label="Seasonal production for {{ $farm->name }}">
                            <h4 class="font-bold text-forest-green">Report this season's production</h4>
                            <p class="mt-1 text-sm leading-6 text-slate-600">Your farm profile lists what you usually grow or raise. These reports tell the MAO what is happening this season on this specific farm.</p>
                            @if(!$productionSubmissionsAvailable)
                                <p class="mt-3 text-sm text-slate-600">Production reporting is unavailable in this database. Please contact the MAO.</p>
                            @elseif(!$farmer->isVerified())
                                <p class="mt-3 text-sm text-slate-600">You can send a seasonal report after the MAO verifies your farmer registration.</p>
                            @else
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a class="farmer-button farmer-button-light" aria-label="Report crops for {{ $farm->name }}" href="{{ route('farmer.production', ['section' => 'crops', 'farm_id' => $farm->id, 'report' => 1]) }}#production-report-form">Report crops</a>
                                    <a class="farmer-button farmer-button-light" aria-label="Report livestock for {{ $farm->name }}" href="{{ route('farmer.production', ['section' => 'livestock', 'farm_id' => $farm->id, 'report' => 1]) }}#production-report-form">Report livestock</a>
                                    <a class="farmer-button farmer-button-light" aria-label="Report fisheries for {{ $farm->name }}" href="{{ route('farmer.production', ['section' => 'fisheries', 'farm_id' => $farm->id, 'report' => 1]) }}#production-report-form">Report fish</a>
                                </div>
                            @endif
                            <p class="mt-2 text-xs leading-5 text-slate-500">Submitted reports go to the MAO for review. Approved reports become official Agricultural Production and Monitoring System (APMS) records. They are not offers to sell.</p>
                        </section>
                        <details class="ui-farm-edit" @if((string) old('_farm_id') === (string) $farm->id) open @endif><summary>Edit farm information <x-ui.icon name="chevron" size="sm"/></summary><form method="POST" action="{{ route('farmer.farms.update', $farm) }}" class="grid gap-3 sm:grid-cols-2">@csrf @method('PATCH')@include('farmer.partials.farm-fields', ['farm' => $farm, 'prefix' => 'farm-'.$farm->id])<button class="farmer-button sm:col-span-2" type="submit">Save changes</button></form></details>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="farmer-section" aria-labelledby="verification-history-title">
        <x-ui.card>
            <x-ui.section-heading id="verification-history-title" title="Verification history" description="Updates recorded by the Municipal Agriculture Office."/>
            <div class="ui-history-list">@forelse($farmer->registrationHistory->sortByDesc('created_at') as $history)<article class="ui-history-row"><span class="ui-history-mark"><x-ui.icon name="success" size="sm"/></span><div><x-ui.status-badge :status="$history->to_status"/><p>{{ $history->reason ?: 'No additional MAO note.' }}</p>@if($history->processor)<small>Reviewed by {{ $history->processor->name }}</small>@endif</div><time datetime="{{ $history->created_at?->toAtomString() }}">{{ $history->created_at?->format('M d, Y · h:i A') }}</time></article>@empty<x-ui.empty-state icon="activity" title="No verification updates yet" description="When the MAO reviews your registration, the update will appear here."/>@endforelse</div>
        </x-ui.card>
    </section>
</x-layouts.farmer>
