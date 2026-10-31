<x-layouts.farmer>
    <section class="farmer-hero">
        <p class="mb-3 text-xs font-bold uppercase tracking-[.17em] text-yellow-200">Your agriculture, connected</p>
        <h1>Good day, {{ $farmer->user->name }}.</h1>
        <p class="mt-4">Find local support, keep your farm information current, and follow your requests in one place.</p>
        <div class="relative mt-6 flex flex-wrap gap-3"><a class="farmer-button farmer-button-gold" href="{{ route('farmer.services') }}">Explore programs and services</a><a class="farmer-button farmer-button-light" href="{{ route('farmer.farms') }}">Manage my farms</a></div>
    </section>

    @if(!$farmer->isVerified())
        <div class="farmer-message {{ $farmer->registration_status === 'rejected' ? 'farmer-message-error' : 'farmer-message-success' }} mt-5" role="status">
            <strong>Registration {{ str_replace('_', ' ', $farmer->registration_status) }}.</strong>
            @if($farmer->registration_status === 'pending') The MAO is reviewing your registration. You can explore services while you wait; applications and equipment requests require verification.
            @elseif($farmer->registration_status === 'returned') The MAO needs a correction. Review the note on My Farms and contact the office.
            @else Review the MAO feedback on My Farms and contact the office for next steps. @endif
            <a class="font-bold underline" href="{{ route('farmer.farms') }}">View registration progress</a>
        </div>
    @endif

    <section class="farmer-section" aria-labelledby="farmer-services-title">
        <div class="farmer-section-heading"><div><h2 id="farmer-services-title">What can we help you with?</h2><p>Choose a service to see requirements and your next step.</p></div></div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['Assistance & subsidies', 'Discover open support programs and apply when eligible.', route('farmer.assistance'), '✦'],
                ['Equipment services', 'See available equipment and request a schedule.', route('farmer.equipment'), '⚙'],
                ['My farms', 'View your registration and update farm records.', route('farmer.farms'), '♧'],
                ['Crop & livestock records', 'Send information for MAO review and view official records.', route('farmer.production'), '◌'],
                ['Fisheries', 'Send fishery information and review official records.', route('farmer.production', ['section' => 'fisheries']), '≈'],
                ['Farm monitoring', 'Read MAO visits and report pests or disease.', route('farmer.production', ['section' => 'monitoring']), '◎'],
            ] as [$name, $copy, $url, $icon])
                <a href="{{ $url }}" class="farmer-card group block no-underline transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-lg"><span class="mb-4 inline-grid h-10 w-10 place-items-center rounded-xl bg-sage text-xl text-forest-green" aria-hidden="true">{{ $icon }}</span><h3 class="text-base font-bold">{{ $name }}</h3><p class="mt-1 text-sm leading-6 text-slate-600">{{ $copy }}</p><span class="mt-4 inline-block text-sm font-bold text-emerald-700">Open service →</span></a>
            @endforeach
        </div>
    </section>

    <section class="farmer-section" aria-labelledby="featured-programs-title">
        <div class="farmer-section-heading"><div><h2 id="featured-programs-title">Programs accepting applications</h2><p>Published assistance from the Municipal Agriculture Office.</p></div><a class="text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.services') }}">View all</a></div>
        <div class="grid gap-3 md:grid-cols-3">
            @forelse($programs as $program)
                <article class="farmer-card flex flex-col"><span class="farmer-pill self-start">Open for applications</span><h3 class="mt-3 text-lg font-bold">{{ $program->name }}</h3><p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ $program->description ?: 'See the program requirements and application details.' }}</p><p class="mt-3 text-xs text-slate-600">{{ $program->closes_on ? 'Apply by '.$program->closes_on->format('M d, Y') : 'No closing date announced' }} · {{ data_get($program->eligibility_rules, 'requires_farm') ? 'Registered farm required' : 'Verified farmer required' }}</p><a class="farmer-button farmer-button-light mt-4 self-start" href="{{ route('farmer.programs.show', $program) }}">View details</a></article>
            @empty
                <div class="farmer-empty md:col-span-3">No assistance programs are accepting applications right now. You can still review your farm information and check back for MAO updates.</div>
            @endforelse
        </div>
    </section>

    <section class="farmer-section grid gap-5 lg:grid-cols-[1.3fr_.7fr]" aria-label="Your activity and updates">
        <div class="farmer-card"><div class="farmer-section-heading"><div><h2>Recent requests</h2><p>Assistance applications and equipment requests. Open My activities for production and other updates.</p></div><a class="text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.activities') }}">View all activity</a></div>
            <div class="divide-y divide-slate-100">
                @forelse($applications as $application)<a class="flex items-center justify-between gap-3 py-3 text-sm no-underline" href="{{ route('farmer.applications.show', $application) }}"><span><strong class="block text-forest-green">{{ $application->program->name }}</strong><small class="text-slate-600">Assistance application</small></span><span class="farmer-pill {{ $application->status === 'rejected' ? 'farmer-pill-bad' : '' }}">{{ ucfirst($application->status) }}</span></a>@empty<p class="py-3 text-sm text-slate-600">No assistance applications yet.</p>@endforelse
                @foreach($equipmentRequests as $equipmentRequest)<a class="flex items-center justify-between gap-3 py-3 text-sm no-underline" href="{{ route('farmer.equipment-requests.show', $equipmentRequest) }}"><span><strong class="block text-forest-green">{{ $equipmentRequest->asset->name }}</strong><small class="text-slate-600">Equipment request</small></span><span class="farmer-pill">{{ ucfirst($equipmentRequest->status) }}</span></a>@endforeach
            </div>
        </div>
        <div class="space-y-4"><div class="farmer-card"><p class="farmer-eyebrow">Your registration</p><h2 class="mt-2 text-xl font-bold">{{ ucfirst($farmer->registration_status) }}</h2><p class="mt-2 text-sm text-slate-600">{{ $farmer->farmer_code }} · {{ $farmer->farms->count() }} registered {{ $farmer->farms->count() === 1 ? 'farm' : 'farms' }}</p><a class="mt-4 inline-block text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.farms') }}">View details</a></div><div class="farmer-card"><p class="farmer-eyebrow">Updates</p><h2 class="mt-2 text-xl font-bold">{{ $unreadCount }} unread notification{{ $unreadCount === 1 ? '' : 's' }}</h2><p class="mt-2 text-sm text-slate-600">Status changes from the MAO appear here.</p><a class="mt-4 inline-block text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.notifications') }}">View notifications</a></div></div>
    </section>

    <section class="farmer-section grid gap-4 md:grid-cols-2" aria-label="Distributions and official notices">
        <div class="farmer-card"><div class="farmer-section-heading"><div><h2>Assistance received</h2><p>Your completed supply distributions.</p></div></div>@forelse($distributions as $distribution)<p class="border-t border-slate-100 py-3 text-sm"><strong class="text-forest-green">{{ $distribution->application->program->name }}</strong><br>{{ $distribution->batch->item->name }} · {{ $distribution->quantity }} · {{ $distribution->distributed_at?->format('M d, Y') }}</p>@empty<p class="text-sm text-slate-600">No supplies have been distributed to you yet.</p>@endforelse</div>
        @if($announcementsAvailable)<div class="farmer-card"><div class="farmer-section-heading"><div><h2>MAO announcements</h2><p>Official agricultural notices.</p></div><a class="text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.announcements') }}">All notices</a></div>@forelse($announcements as $announcement)<a class="block border-t border-slate-100 py-3 text-sm font-bold text-forest-green" href="{{ route('farmer.announcements.show', $announcement) }}">{{ $announcement->title }}<small class="block font-normal text-slate-600">{{ $announcement->published_at->format('M d, Y') }}</small></a>@empty<p class="text-sm text-slate-600">No official MAO notices are currently published.</p>@endforelse</div>@endif
    </section>
</x-layouts.farmer>
