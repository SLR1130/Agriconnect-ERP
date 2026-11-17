<section class="farmer-section farmer-assistance-catalog" aria-labelledby="assistance-catalog-title">
    <div class="farmer-section-heading">
        <div>
            <h2 id="assistance-catalog-title">{{ $catalogHeading ?? 'Browse assistance programs' }}</h2>
            <p>{{ $catalogDescription ?? 'Published programs use the same date and eligibility rules throughout the Farmer Portal.' }}</p>
        </div>
        <a class="farmer-text-link" href="{{ route('farmer.assistance') }}#applications">My applications <x-ui.icon name="next" size="sm" /></a>
    </div>

    <form method="GET" action="{{ request()->url() }}" class="farmer-card farmer-catalog-filters" aria-label="Filter assistance programs">
        <div class="farmer-field farmer-catalog-search">
            <label for="assistance-search">Search programs</label>
            <div class="farmer-search-input"><x-ui.icon name="search" size="sm" /><input id="assistance-search" name="search" type="search" value="{{ $search }}" placeholder="Program name or details"></div>
        </div>
        <div class="farmer-field">
            <label for="assistance-category">Program category</label>
            <select id="assistance-category" name="category">
                <option value="">All published categories</option>
                @foreach($categories as $name)<option value="{{ $name }}" @selected($category === $name)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="farmer-field">
            <label for="assistance-availability">Application period</label>
            <select id="assistance-availability" name="availability">
                <option value="available" @selected($availability === 'available')>Accepting applications</option>
                <option value="upcoming" @selected($availability === 'upcoming')>Opening soon</option>
                <option value="ended" @selected($availability === 'ended')>Application period ended</option>
                <option value="all" @selected($availability === 'all')>All published programs</option>
            </select>
        </div>
        <div class="farmer-catalog-filter-actions">
            <button class="farmer-button" type="submit">Search programs</button>
            <a class="farmer-button farmer-button-light" href="{{ request()->url() }}">Clear filters</a>
        </div>
    </form>

    <p class="farmer-catalog-result-count" role="status">
        {{ $programs->total() }} {{ \Illuminate\Support\Str::plural('published program', $programs->total()) }}
        @if($availability !== 'all') · {{ ['available' => 'accepting applications', 'upcoming' => 'opening soon', 'ended' => 'past the application period'][$availability] }} @endif
    </p>

    <div class="farmer-program-grid">
        @forelse($programs as $program)
            @include('farmer.partials.program-card', ['program' => $program, 'eligibility' => $eligibilities[$program->id] ?? null])
        @empty
            <div class="farmer-empty farmer-program-empty">
                <span class="ui-service-icon" aria-hidden="true"><x-ui.icon name="search" size="lg" /></span>
                <strong>No published programs match these filters.</strong>
                <p>Try another category or application period, or check again when the MAO publishes a program.</p>
            </div>
        @endforelse
    </div>
    @if($programs->hasPages())<div class="farmer-pagination">{{ $programs->links() }}</div>@endif
</section>
