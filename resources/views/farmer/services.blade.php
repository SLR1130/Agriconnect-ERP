<x-layouts.farmer>
    <x-slot name="title">Explore services</x-slot>
    <x-slot name="description">Find agricultural support and see what is available for your farm.</x-slot>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" aria-label="Agricultural services">
        @foreach([
            ['Assistance and subsidies', 'Browse programs and track applications.', route('farmer.assistance')],
            ['Equipment requests', 'Browse available equipment and request dates.', route('farmer.equipment')],
            ['Farm registration', 'Manage your farmer and farm information.', route('farmer.farms')],
            ['Crop and livestock records', 'Send information for MAO review and view official records.', route('farmer.production')],
            ['Fisheries services', 'Send fishery information and review your official records.', route('farmer.production', ['section' => 'fisheries'])],
            ['Farm monitoring and reports', 'See MAO visits and send a pest or disease report.', route('farmer.production', ['section' => 'monitoring'])],
        ] as [$name, $copy, $url])
            <a class="farmer-card block no-underline hover:border-emerald-300" href="{{ $url }}"><h2 class="font-bold">{{ $name }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $copy }}</p><span class="mt-3 inline-block text-sm font-bold text-emerald-700">Explore →</span></a>
        @endforeach
    </section>

    <section class="farmer-section" aria-labelledby="published-programs-title">
        <div class="farmer-section-heading"><div><h2 id="published-programs-title">Published assistance programs</h2><p>Only programs opened for farmers by the MAO appear here.</p></div></div>
        <form method="GET" action="{{ route('farmer.services') }}" class="farmer-card mb-4 grid gap-3 sm:grid-cols-[1fr_12rem_auto]" aria-label="Search published programs">
            <div class="farmer-field"><label for="service-search">Search programs</label><input id="service-search" name="search" type="search" value="{{ $search }}" placeholder="Program name or description"></div>
            <div class="farmer-field"><label for="service-category">Category</label><select id="service-category" name="category"><option value="">All categories</option>@foreach($categories as $name)<option value="{{ $name }}" @selected($category === $name)>{{ $name }}</option>@endforeach</select></div>
            <div class="flex items-end gap-2"><button class="farmer-button" type="submit">Search</button><a class="farmer-button farmer-button-light" href="{{ route('farmer.services') }}">Clear</a></div>
        </form>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">@forelse($programs as $program)@include('farmer.partials.program-card', ['program' => $program])@empty<div class="farmer-empty md:col-span-2 xl:col-span-3">No published programs match this search. Try another category or check back for new MAO programs.</div>@endforelse</div>
        <div class="mt-4">{{ $programs->links() }}</div>
    </section>
    @if($announcementsAvailable)<section class="farmer-section farmer-card"><div class="farmer-section-heading"><div><h2>Official MAO notices</h2><p>Published agricultural announcements and service advisories.</p></div><a class="text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.announcements') }}">All notices</a></div><div class="grid gap-3 md:grid-cols-3">@forelse($announcements as $announcement)<a class="rounded-xl border border-sage p-4 no-underline" href="{{ route('farmer.announcements.show', $announcement) }}"><strong class="text-forest-green">{{ $announcement->title }}</strong><p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $announcement->body }}</p></a>@empty<p class="text-sm text-slate-600 md:col-span-3">No official MAO notices are currently published.</p>@endforelse</div></section>@endif
</x-layouts.farmer>
