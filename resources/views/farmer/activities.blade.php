<x-layouts.farmer>
    <x-slot name="title">My activities</x-slot>
    <x-slot name="description">Track assistance applications, equipment requests, farm records, and MAO updates in one place.</x-slot>

    <nav class="farmer-subnav" aria-label="Filter activities by status">
        @foreach(['' => 'All activities', 'pending' => 'Pending', 'approved' => 'Approved', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('farmer.activities', array_filter(['status' => $key, 'type' => $type, 'search' => $search])) }}" @if($filter === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('farmer.activities') }}" class="farmer-card farmer-section !mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Search and filter activities">
        @if($filter)<input type="hidden" name="status" value="{{ $filter }}">@endif
        <div class="farmer-field">
            <label for="activity-search">Search your activity</label>
            <input id="activity-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Program, farm, crop, equipment">
        </div>
        <div class="farmer-field">
            <label for="activity-type">Service</label>
            <select id="activity-type" name="type">
                <option value="">All services</option>
                @foreach(['Assistance application', 'Equipment request', 'Registration update', 'Farm record', 'Assistance received', 'Crop record', 'Livestock record', 'Fisheries record', 'Pest or disease report', 'Agricultural submission', 'Notification'] as $option)
                    <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-2">
            <button class="farmer-button" type="submit">Apply filters</button>
            <a class="farmer-button farmer-button-light" href="{{ route('farmer.activities') }}">Clear</a>
        </div>
    </form>

    <section class="farmer-section !mt-4">
        <p class="mb-3 text-sm text-slate-600">{{ $activities->total() }} matching record{{ $activities->total() === 1 ? '' : 's' }}</p>
        <div class="grid gap-3 md:grid-cols-2">
            @forelse($activities as $activity)
                <a class="farmer-card block no-underline hover:border-emerald-300" href="{{ $activity['url'] }}">
                    <div class="flex flex-wrap justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $activity['type'] }}</span>
                        <span class="farmer-pill {{ $activity['group'] === 'rejected' ? 'farmer-pill-bad' : '' }}">{{ ucfirst(str_replace('_', ' ', $activity['status'])) }}</span>
                    </div>
                    <h2 class="mt-2 text-lg font-bold">{{ $activity['title'] }}</h2>
                    @if($activity['detail'])<p class="mt-1 text-sm text-slate-600">{{ $activity['detail'] }}</p>@endif
                    <p class="mt-2 text-xs text-slate-600">Updated {{ $activity['date']?->format('M d, Y h:i A') }}</p>
                    <span class="mt-3 inline-block text-sm font-bold text-emerald-700">View details</span>
                </a>
            @empty
                <div class="farmer-empty md:col-span-2">No activities match these filters. Your submitted requests and MAO decisions will appear here.</div>
            @endforelse
        </div>
        <div class="mt-5">{{ $activities->links() }}</div>
    </section>
</x-layouts.farmer>
