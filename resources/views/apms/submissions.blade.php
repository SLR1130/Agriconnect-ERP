<x-layouts.app>
    <x-slot name="header">Farmer production review</x-slot>
    <x-slot name="description">Review farmer-submitted agricultural information before it becomes an official APMS record.</x-slot>

    <main class="apms-review-page">
        <a class="erp-button erp-button-secondary apms-review-back" href="{{ route('modules.apms.dashboard') }}"><x-ui.icon name="back" size="sm" /> APMS workspace</a>

        <header class="apms-review-page-heading">
            <div><p class="apms-kicker">Farmer submissions</p><h2>Production review queue</h2><p>Review the farmer and linked farm details, then record a decision with clear feedback.</p></div>
            <span class="erp-pill">{{ number_format($submissions->total()) }} matching submissions</span>
        </header>

        <nav class="apms-review-status-nav" aria-label="Filter production submissions by status">
            @foreach(['' => 'All', 'pending' => 'Pending', 'returned' => 'Returned', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                @php($count = $key === '' ? $statusCounts->sum() : (int) $statusCounts->get($key, 0))
                <a class="apms-review-status-link {{ $status === $key ? 'is-active' : '' }}" href="{{ route('modules.apms.submissions', array_filter(['status' => $key, 'search' => $search], fn ($value) => $value !== '')) }}" @if($status === $key) aria-current="page" @endif>
                    <span>{{ $label }}</span><span class="apms-review-status-count">{{ number_format($count) }}</span>
                </a>
            @endforeach
        </nav>

        <section class="erp-surface apms-submission-list" aria-labelledby="apms-submission-list-title">
            <div class="apms-submission-list-heading"><div><p class="apms-kicker">Review records</p><h3 id="apms-submission-list-title">Submitted production information</h3></div></div>
            <form class="apms-submission-search" method="GET" action="{{ route('modules.apms.submissions') }}" role="search" aria-label="Search production submissions">
                @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <label for="apms-submission-search">Find submissions</label>
                <div class="apms-submission-search-controls"><input id="apms-submission-search" class="erp-input" type="search" name="search" value="{{ $search }}" maxlength="100" placeholder="Farmer, email, farm, barangay, or type"><button class="erp-button erp-button-primary" type="submit"><x-ui.icon name="search" size="sm" /> Search</button>@if($search)<a class="erp-button erp-button-secondary" href="{{ route('modules.apms.submissions', $status ? ['status' => $status] : []) }}">Clear</a>@endif</div>
            </form>

            <div class="apms-submission-table-wrap">
                <table class="apms-submission-table">
                    <thead><tr><th scope="col">Farmer and farm</th><th scope="col">Production type and details</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
                    <tbody>
                        @forelse($submissions as $submission)
                            <tr>
                                <td><strong>{{ $submission->farmer->user->name }}</strong><span>{{ $submission->farm->name }} · {{ $submission->farm->barangay ?: 'Barangay not recorded' }}</span></td>
                                <td><div class="apms-submission-identification"><span class="apms-submission-type"><x-ui.icon name="{{ $submission->category === 'crop' ? 'crop' : ($submission->category === 'fishery' ? 'fish' : 'farms') }}" size="sm" />{{ str($submission->category)->headline() }}</span>@if($submission->summary())<strong>{{ str($submission->summary())->replace('_', ' ') }}</strong>@endif<small>Submission #{{ $submission->id }}</small></div></td>
                                <td><time datetime="{{ $submission->created_at?->toIso8601String() }}">{{ $submission->created_at?->format('M j, Y') }}<span>{{ $submission->created_at?->format('g:i A') }}</span></time></td>
                                <td><span class="erp-pill {{ $submission->status === 'approved' ? 'erp-pill-success' : (in_array($submission->status, ['returned', 'rejected'], true) ? 'erp-pill-warning' : 'erp-pill-neutral') }}">{{ str($submission->status)->replace('_', ' ')->headline() }}</span></td>
                                <td><a class="erp-button erp-button-secondary apms-submission-view" href="{{ route('modules.apms.submissions.show', array_filter(['submission' => $submission, 'status' => $status, 'search' => $search], fn ($value) => $value !== '')) }}">{{ $canReview && $submission->status === 'pending' ? 'Review' : 'View' }}<x-ui.icon name="next" size="xs" /></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="apms-submission-empty"><span class="apms-review-callout-icon"><x-ui.icon name="action" size="md" /></span><strong>No submissions found</strong><p>{{ $search || $status ? 'Try a different search or status filter.' : 'Farmer production submissions will appear here for authorized review.' }}</p>@if($search || $status)<a class="erp-button erp-button-secondary" href="{{ route('modules.apms.submissions') }}">Clear filters</a>@endif</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="apms-submission-cards">
                @forelse($submissions as $submission)
                    <article class="apms-submission-card">
                        <div class="apms-submission-card-top"><span class="apms-submission-type"><x-ui.icon name="{{ $submission->category === 'crop' ? 'crop' : ($submission->category === 'fishery' ? 'fish' : 'farms') }}" size="sm" />{{ str($submission->category)->headline() }}</span><span class="erp-pill {{ $submission->status === 'approved' ? 'erp-pill-success' : (in_array($submission->status, ['returned', 'rejected'], true) ? 'erp-pill-warning' : 'erp-pill-neutral') }}">{{ str($submission->status)->replace('_', ' ')->headline() }}</span></div>
                        @if($submission->summary())<p class="apms-submission-card-summary">{{ str($submission->summary())->replace('_', ' ') }}</p>@endif
                        <small class="apms-submission-card-reference">Submission #{{ $submission->id }}</small>
                        <h3>{{ $submission->farmer->user->name }}</h3><p class="apms-submission-card-farm">{{ $submission->farm->name }} · {{ $submission->farm->barangay ?: 'Barangay not recorded' }}</p>
                        <time datetime="{{ $submission->created_at?->toIso8601String() }}">Submitted {{ $submission->created_at?->format('M j, Y g:i A') }}</time>
                        <a class="erp-button erp-button-secondary" href="{{ route('modules.apms.submissions.show', array_filter(['submission' => $submission, 'status' => $status, 'search' => $search], fn ($value) => $value !== '')) }}">{{ $canReview && $submission->status === 'pending' ? 'Review submission' : 'View submission' }}<x-ui.icon name="next" size="xs" /></a>
                    </article>
                @empty
                    <div class="apms-submission-empty"><span class="apms-review-callout-icon"><x-ui.icon name="action" size="md" /></span><strong>No submissions found</strong><p>{{ $search || $status ? 'Try a different search or status filter.' : 'Farmer production submissions will appear here for authorized review.' }}</p>@if($search || $status)<a class="erp-button erp-button-secondary" href="{{ route('modules.apms.submissions') }}">Clear filters</a>@endif</div>
                @endforelse
            </div>

            <nav class="apms-submission-pagination" aria-label="Production submission pages">{{ $submissions->links() }}</nav>
        </section>
    </main>
</x-layouts.app>
