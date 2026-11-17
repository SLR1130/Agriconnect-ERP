<x-layouts.farmer>
    <x-slot name="title">My activities</x-slot>
    <x-slot name="description">A chronological view of your agricultural service records, decisions and transactions.</x-slot>

    <form method="GET" action="{{ route('farmer.activities') }}" class="farmer-activity-filters" aria-label="Search and filter service history">
        <div class="farmer-field">
            <label for="activity-search">Search service history</label>
            <input id="activity-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Program, farm, crop or reference">
        </div>
        <div class="farmer-field">
            <label for="activity-type">Agricultural service</label>
            <select id="activity-type" name="type">
                <option value="">All services</option>
                @foreach(['Assistance application', 'Equipment request', 'Registration update', 'Farm record', 'Assistance received', 'Crop record', 'Livestock record', 'Fisheries record', 'Pest or disease report', 'Agricultural submission'] as $option)
                    <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="farmer-field">
            <label for="activity-status">Exact current status</label>
            <select id="activity-status" name="status">
                <option value="">All statuses</option>
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="farmer-activity-filter-actions">
            <button class="farmer-button" type="submit"><x-ui.icon name="search" size="sm" />Apply filters</button>
            <a class="farmer-button farmer-button-light" href="{{ route('farmer.activities') }}">Clear</a>
        </div>
    </form>

    <section class="farmer-activity-results" aria-labelledby="activity-results-title">
        <div class="farmer-activity-results-heading">
            <h2 id="activity-results-title">Service history</h2>
            <p>{{ $activities->total() }} matching record{{ $activities->total() === 1 ? '' : 's' }}</p>
        </div>

        @if($activities->count())
            <ol class="farmer-activity-list" aria-label="Chronological agricultural service records">
                @foreach($activities as $activity)
                    <li><x-farmer.activity-item :activity="$activity" /></li>
                @endforeach
            </ol>
            <nav class="farmer-activity-pagination" aria-label="Service history pages">{{ $activities->links() }}</nav>
        @else
            <x-ui.empty-state
                :title="($search || $type || $filter) ? 'No matching service records' : 'No service activity yet'"
                :description="($search || $type || $filter) ? 'Try changing or clearing your search and filters.' : 'Your submitted services, agricultural records and recorded decisions will appear here.'"
                icon="activity"
            >
                @if($search || $type || $filter)
                    <a class="farmer-button farmer-button-light" href="{{ route('farmer.activities') }}">Clear filters</a>
                @else
                    <a class="farmer-button" href="{{ route('farmer.services') }}">Explore services</a>
                @endif
            </x-ui.empty-state>
        @endif
    </section>
</x-layouts.farmer>
