<article class="farmer-card flex flex-col">
    <div class="flex flex-wrap items-center justify-between gap-2"><span class="farmer-pill">{{ $program->category ?: 'Agricultural assistance' }}</span><span class="farmer-pill {{ $program->isAvailable() ? '' : 'farmer-pill-warn' }}">{{ $program->isAvailable() ? 'Accepting applications' : ($program->opens_on?->isFuture() ? 'Opens soon' : 'Application period ended') }}</span></div>
    <h3 class="mt-3 text-lg font-bold">{{ $program->name }}</h3>
    <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">{{ $program->description ?: 'Review eligibility and application details.' }}</p>
    <p class="mt-3 text-xs text-slate-600">{{ $program->opens_on?->format('M d, Y') ?: 'Open date not set' }} – {{ $program->closes_on?->format('M d, Y') ?: 'No closing date set' }}</p>
    <p class="mt-1 text-xs text-slate-600">{{ data_get($program->eligibility_rules, 'requires_farm') ? 'A registered farm is required.' : 'Verified farmer registration is required.' }}</p>
    @isset($eligibility)
        <p class="mt-2 rounded-lg {{ $eligibility['passed'] ? 'bg-sage text-forest-green' : 'bg-amber-50 text-amber-900' }} p-2 text-xs leading-5">
            {{ $eligibility['passed'] ? 'Your current profile meets the listed requirements.' : $eligibility['notes'] }}
        </p>
    @endisset
    <a class="farmer-button farmer-button-light mt-4 self-start" href="{{ route('farmer.programs.show', $program) }}">View program details</a>
</article>
