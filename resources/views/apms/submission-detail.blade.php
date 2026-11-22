<x-layouts.app>
    <x-slot name="header">Production submission review</x-slot>
    <x-slot name="description">Verify the submitted information against the farmer and farm records before deciding.</x-slot>

    @php
        $queueUrl = route('modules.apms.submissions', array_filter(request()->only(['status', 'search']), fn ($value) => $value !== ''));
        $statusTone = $submission->status === 'approved' ? 'erp-pill-success' : (in_array($submission->status, ['returned', 'rejected'], true) ? 'erp-pill-warning' : 'erp-pill-neutral');
    @endphp

    <main class="apms-review-detail-page">
        <a class="erp-button erp-button-secondary apms-review-back" href="{{ $queueUrl }}"><x-ui.icon name="back" size="sm" /> Back to review queue</a>

        @if($errors->any())
            <div class="erp-alert erp-alert-danger" role="alert" tabindex="-1"><strong>Review the decision details.</strong><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if(session('status'))<div class="erp-alert erp-alert-success" role="status">{{ session('status') }}</div>@endif

        <header class="apms-submission-detail-header erp-surface">
            <div><p class="apms-kicker">{{ str($submission->category)->headline() }} production submission</p><h2>{{ $submission->farmer->user->name }}</h2><p>{{ $submission->farm->name }} · {{ $submission->farm->barangay ?: 'Barangay not recorded' }}</p></div>
            <div class="apms-submission-detail-status"><span class="erp-pill {{ $statusTone }}">{{ str($submission->status)->replace('_', ' ')->headline() }}</span><span>Submitted {{ $submission->created_at?->format('M j, Y · g:i A') }}</span></div>
        </header>

        <div class="apms-submission-detail-grid">
            <div class="apms-submission-detail-main">
                <section class="erp-surface apms-submission-facts" aria-labelledby="apms-submission-facts-title">
                    <div class="apms-submission-section-heading"><div><p class="apms-kicker">Submitted information</p><h3 id="apms-submission-facts-title">{{ ucfirst($submission->category) }} details</h3></div><span class="apms-review-callout-icon"><x-ui.icon name="{{ $submission->category === 'crop' ? 'crop' : ($submission->category === 'fishery' ? 'fish' : 'farms') }}" size="md" /></span></div>
                    <dl class="apms-submission-payload-grid">
                        @foreach($submission->payload ?? [] as $key => $value)
                            @if($value !== null && $value !== '')
                                @php($displayValue = is_array($value) ? implode(', ', array_map(fn ($item) => is_string($item) && str_contains($item, '_') ? ucfirst(str_replace('_', ' ', $item)) : (is_scalar($item) ? (string) $item : json_encode($item)), $value)) : (is_bool($value) ? ($value ? 'Yes' : 'No') : (is_string($value) && str_contains($value, '_') ? ucfirst(str_replace('_', ' ', $value)) : $value)))
                                <div><dt>{{ \Illuminate\Support\Str::headline((string) $key) }}</dt><dd>{{ $displayValue }}</dd></div>
                            @endif
                        @endforeach
                    </dl>
                    @if($submission->review_notes)
                        <div class="apms-submission-feedback"><strong>Latest MAO feedback</strong><p>{{ $submission->review_notes }}</p>@if($submission->reviewer)<span>Recorded by {{ $submission->reviewer->name }} @if($submission->reviewed_at) · {{ $submission->reviewed_at->format('M j, Y g:i A') }} @endif</span>@endif</div>
                    @endif
                </section>

                <section class="erp-surface apms-submission-history" aria-labelledby="apms-submission-history-title">
                    <div class="apms-submission-section-heading"><div><p class="apms-kicker">Decision record</p><h3 id="apms-submission-history-title">Review history</h3></div></div>
                    @forelse($submission->history as $entry)
                        <article class="apms-submission-history-entry"><span class="apms-submission-history-marker" aria-hidden="true"></span><div><div class="apms-submission-history-heading"><strong>{{ str($entry->to_status)->replace('_', ' ')->headline() }}</strong><time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->format('M j, Y · g:i A') }}</time></div><span class="apms-submission-history-actor">{{ $entry->actor?->name ?: 'System' }}</span><p>{{ $entry->notes ?: 'No review note recorded.' }}</p></div></article>
                    @empty
                        <div class="apms-submission-empty"><strong>No review decisions yet</strong><p>This submission is waiting for its first MAO review.</p></div>
                    @endforelse
                </section>
            </div>

            <aside class="apms-submission-detail-aside">
                <section class="erp-surface apms-submission-farmer-card" aria-labelledby="apms-submission-farmer-title">
                    <p class="apms-kicker">Registered farmer</p><h3 id="apms-submission-farmer-title">{{ $submission->farmer->user->name }}</h3>
                    <dl><div><dt>Farmer code</dt><dd>{{ $submission->farmer->farmer_code ?: 'Not recorded' }}</dd></div><div><dt>Email</dt><dd>{{ $submission->farmer->user->email }}</dd></div><div><dt>Phone</dt><dd>{{ $submission->farmer->phone ?: 'Not recorded' }}</dd></div><div><dt>Farm</dt><dd>{{ $submission->farm->name }}</dd></div><div><dt>Farm barangay</dt><dd>{{ $submission->farm->barangay ?: 'Not recorded' }}</dd></div></dl>
                </section>

                @if($canReview && $submission->status === 'pending')
                    <section class="erp-surface apms-submission-decision" aria-labelledby="apms-submission-decision-title">
                        <p class="apms-kicker">Authorized MAO action</p><h3 id="apms-submission-decision-title">Review submission</h3>
                        <p>Approval creates the corresponding official APMS record once. Return the submission when the farmer needs to correct information.</p>
                        <form method="POST" action="{{ route('modules.apms.submissions.review', $submission) }}" data-apms-review-form>
                            @csrf
                            <input type="hidden" name="queue_status" value="{{ request('status') }}"><input type="hidden" name="queue_search" value="{{ request('search') }}">
                            <div class="erp-field"><label for="apms-review-decision">Decision</label><select id="apms-review-decision" name="decision" class="erp-input" required data-apms-review-decision><option value="">Select a decision</option><option value="approved" @selected(old('decision') === 'approved')>Approve official record</option><option value="returned" @selected(old('decision') === 'returned')>Return for correction</option><option value="rejected" @selected(old('decision') === 'rejected')>Reject submission</option></select>@error('decision')<span class="apms-field-error">{{ $message }}</span>@enderror</div>
                            <div class="erp-field"><label for="apms-review-notes">Review notes</label><textarea id="apms-review-notes" name="review_notes" class="erp-input" rows="4" maxlength="2000" aria-describedby="apms-review-notes-help" data-apms-review-notes>{{ old('review_notes') }}</textarea><p id="apms-review-notes-help" class="apms-review-help">Required when returning or rejecting. Give the farmer a clear next step.</p>@error('review_notes')<span class="apms-field-error">{{ $message }}</span>@enderror</div>
                            <button class="erp-button erp-button-primary" type="submit"><x-ui.icon name="success" size="sm" /> Save MAO decision</button>
                        </form>
                    </section>
                @elseif($submission->status === 'pending')
                    <section class="erp-surface apms-submission-decision apms-submission-readonly"><p class="apms-kicker">Read-only review</p><h3>Decision controls unavailable</h3><p>This account can inspect the submission but does not have permission to change its status.</p></section>
                @else
                    <section class="erp-surface apms-submission-decision apms-submission-readonly"><p class="apms-kicker">Current outcome</p><h3>{{ str($submission->status)->replace('_', ' ')->headline() }}</h3><p>This submission is no longer awaiting a decision. Its review history and feedback remain available above.</p></section>
                @endif
            </aside>
        </div>
    </main>
</x-layouts.app>
