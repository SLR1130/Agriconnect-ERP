<x-layouts.farmer>
    <x-slot name="title">{{ $program->name }}</x-slot>
    <x-slot name="description">{{ $program->category ?: 'Agricultural assistance' }} · A program from the Municipal Agriculture Office.</x-slot>

    <a class="farmer-back-link" href="{{ route('farmer.services', ['availability' => 'all']) }}"><x-ui.icon name="back" size="sm" />Back to Explore services</a>

    <div class="farmer-program-detail-layout">
        <div class="farmer-program-detail-main">
            <section class="farmer-card" aria-labelledby="program-overview-title">
                @if($publication)
                    @php($cover = $publication['media']->first(fn ($media) => $media->is_cover) ?? $publication['media']->first())
                    @if($cover)<figure class="farmer-announcement-cover farmer-program-published-cover"><img src="{{ route('public.announcements.media', [$announcement, $cover]) }}" alt="{{ $cover->alt_text }}"><figcaption>{{ $cover->alt_text }}</figcaption></figure>@endif
                    @if($publication['content']['office'] || $publication['content']['service_area'])<p class="farmer-notice-byline">@if($publication['content']['office'])<x-ui.icon name="office" size="sm"/>{{ $publication['content']['office'] }}@endif @if($publication['content']['service_area'])<span>· {{ $publication['content']['service_area'] }}</span>@endif</p>@endif
                    @if($publication['content']['requirements_summary'])<div class="farmer-requirements-panel"><h2>Program-specific requirements summary</h2><p>{{ $publication['content']['requirements_summary'] }}</p></div>@endif
                @endif
                <div class="farmer-program-detail-badges">
                    <span class="farmer-program-category">{{ $program->category ?: 'Agricultural assistance' }}</span>
                    @php($availabilityState = $program->availabilityStateOn($businessDate))
                    <span class="farmer-pill {{ $availabilityState === 'available' ? '' : 'farmer-pill-warn' }}">
                        {{ ['available' => 'Accepting applications', 'upcoming' => 'Opens soon', 'ended' => 'Application period ended', 'unpublished' => 'Not published'][$availabilityState] }}
                    </span>
                </div>
                <h2 id="program-overview-title">Program overview</h2>
                <p class="farmer-program-long-description">{{ $program->description ?: 'The MAO has not added a program description. Contact the office for details.' }}</p>

                <dl class="farmer-program-detail-facts">
                    <div><dt>Applications open</dt><dd>{{ $program->opens_on?->format('M d, Y') ?: 'No opening date specified' }}</dd></div>
                    <div><dt>Applications close</dt><dd>{{ $program->closes_on?->format('M d, Y') ?: 'No closing date specified' }}</dd></div>
                    <div><dt>Assistance item</dt><dd>{{ $program->supplyItem?->name ?: 'Determined by the MAO during review' }}</dd></div>
                    <div><dt>Allocation limit</dt><dd>{{ $program->allocation_limit ?: 'Set during application review' }}</dd></div>
                </dl>
                <p class="farmer-date-guidance">Application dates follow the calendar dates published by the MAO.</p>

                <div class="farmer-requirements-panel">
                    <h2>Eligibility and requirements</h2>
                    <p>These checks are based on your current farmer and farm records. The MAO confirms eligibility during review.</p>
                    <ul>
                        <li>Verified farmer registration</li>
                        @if(data_get($program->eligibility_rules, 'requires_farm'))<li>At least one registered farm</li>@endif
                        @if(data_get($program->eligibility_rules, 'barangays'))<li>Eligible barangays: {{ implode(', ', data_get($program->eligibility_rules, 'barangays')) }}</li>@endif
                        @if(data_get($program->eligibility_rules, 'activities'))<li>Farm activities: {{ implode(', ', data_get($program->eligibility_rules, 'activities')) }}</li>@endif
                        @if(data_get($program->eligibility_rules, 'farm_categories'))<li>Farm categories: {{ implode(', ', data_get($program->eligibility_rules, 'farm_categories')) }}</li>@endif
                        @if(data_get($program->eligibility_rules, 'previous_beneficiary_restricted'))<li>Farmers who previously completed this program cannot apply again.</li>@endif
                    </ul>
                </div>
                <div class="farmer-requirements-panel mt-4" aria-labelledby="program-documents-title">
                    <h2 id="program-documents-title">Program document checklist</h2>
                    @if($program->currentRequirementVersion?->documentRequirements?->isNotEmpty())
                        <ul>
                            @foreach($program->currentRequirementVersion->documentRequirements as $requirement)
                                <li><strong>{{ $requirement->display_name }}</strong> — {{ $requirement->is_required ? 'Required' : 'Optional' }}
                                    @if($requirement->description)<span>: {{ $requirement->description }}</span>@endif
                                    @if($requirement->instructions)<span> {{ $requirement->instructions }}</span>@endif
                                    <small class="block">Accepted types: {{ collect($requirement->accepted_mime_types)->map(fn ($mime) => match ($mime) { 'application/pdf' => 'PDF', 'image/jpeg' => 'JPEG', 'image/png' => 'PNG', default => $mime })->join(', ') }} · Maximum {{ number_format(min($requirement->max_size_bytes, $maximumDocumentUploadBytes) / 1048576, 1) }} MiB</small>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>No digitized supporting-document checklist is on file for this program. Follow the published MAO notice or contact the office for current instructions.</p>
                    @endif
                    <p class="mt-3 text-sm">Online document upload is not available yet. Do not submit files through this page; the MAO may provide instructions for physical document submission.</p>
                    @if($program->currentRequirementVersion)<p class="mt-2 text-xs text-slate-500">Requirements version {{ $program->currentRequirementVersion->version_number }}@if($program->currentRequirementVersion->published_at) · published {{ $program->currentRequirementVersion->published_at->timezone(config('agriculture.business_timezone'))->format('M d, Y') }}@endif</p>@endif
                </div>
            </section>

            <div class="farmer-journey-strip" aria-label="Assistance application steps">
                <span class="is-current"><b>1</b> Review</span><span><b>2</b> Apply when eligible</span><span><b>3</b> Track MAO decision</span><span><b>4</b> View distribution</span>
            </div>
        </div>

        <aside class="farmer-card farmer-program-next-step" aria-labelledby="program-next-step-title">
            <span class="farmer-service-kicker">Your next step</span>
            <h2 id="program-next-step-title">Application status</h2>
            @if($application)
                <p>You already applied to this program. Your recorded status is <strong>{{ ucfirst($application->status) }}</strong>.</p>
                <a class="farmer-button farmer-button-gold" href="{{ route('farmer.applications.show', $application) }}">Track application <x-ui.icon name="next" size="sm" /></a>
            @elseif(!$program->isAvailableOn($businessDate))
                <p class="farmer-program-state-note">
                    @if($availabilityState === 'upcoming')
                        Applications have not opened yet. You can review the requirements now and return after {{ $program->opens_on?->format('M d, Y') }}.
                    @else
                        The application period ended {{ $program->closes_on?->format('M d, Y') }}. You can still review the program requirements.
                    @endif
                </p>
                <a class="farmer-button farmer-button-light" href="{{ route('farmer.services', ['availability' => $availabilityState]) }}">See other {{ $availabilityState === 'upcoming' ? 'upcoming' : 'published' }} programs <x-ui.icon name="next" size="sm" /></a>
            @elseif(!$eligibility['passed'])
                <p class="farmer-program-state-note">{{ $eligibility['notes'] }}</p>
                @if(!$farmer->isVerified())
                    <a class="farmer-button farmer-button-light" href="{{ route('farmer.farms') }}">View verification progress <x-ui.icon name="next" size="sm" /></a>
                @elseif(data_get($program->eligibility_rules, 'requires_farm') && $farmer->farms->isEmpty())
                    <a class="farmer-button farmer-button-light" href="{{ route('farmer.farms') }}">Register a farm <x-ui.icon name="next" size="sm" /></a>
                @else
                    <a class="farmer-button farmer-button-light" href="{{ route('farmer.assistance') }}">Back to assistance programs <x-ui.icon name="next" size="sm" /></a>
                @endif
            @else
                @if($applicationSubmissionReady)
                    <p>Review the details above. You can send one application while the program is open and your profile meets the listed checks.</p>
                    <div class="farmer-requirements-panel my-4"><strong>Applying as:</strong> {{ $farmer->user->name }} · {{ $farmer->farmer_code }} · {{ $farmer->barangay }}. These details come from your FFRMS record.</div>
                    <form method="POST" action="{{ route('farmer.assistance.apply', $program) }}" class="farmer-program-apply-form">
                        @csrf<input type="hidden" name="submission_key" value="{{ $submissionKey }}">
                        <div class="farmer-field">
                            <label for="application-purpose">How will this support your farm? <span class="font-normal">(optional)</span></label>
                            <textarea id="application-purpose" name="purpose" maxlength="2000">{{ old('purpose') }}</textarea>
                            <small>Do not include sensitive personal information. The MAO will review this note with your application.</small>
                        </div>
                        <p class="mb-3 text-xs text-slate-600">Submitting records receipt of your application. It does not guarantee approval or entitlement to assistance.</p>
                        <button class="farmer-button farmer-button-gold" type="submit">Submit application <x-ui.icon name="next" size="sm" /></button>
                    </form>
                @else
                    <p class="farmer-program-state-note" role="status">Online applications are temporarily paused while the assistance database update is pending. Your farmer and program records remain unchanged. Please try again later or contact the MAO.</p>
                @endif
            @endif
        </aside>
    </div>
</x-layouts.farmer>
