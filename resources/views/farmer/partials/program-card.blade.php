@php($availabilityState = $program->availabilityState())
@php($availabilityCopy = ['available' => 'Accepting applications', 'upcoming' => 'Opens soon', 'ended' => 'Application period ended', 'unpublished' => 'Not published'][$availabilityState])
<article class="farmer-card farmer-program-card">
    <div class="farmer-program-card-heading">
        <span class="farmer-program-category">{{ $program->category ?: 'Agricultural assistance' }}</span>
        <span class="farmer-pill {{ $availabilityState === 'available' ? '' : 'farmer-pill-warn' }}">{{ $availabilityCopy }}</span>
    </div>
    <h3>{{ $program->name }}</h3>
    <p class="farmer-program-description">{{ $program->description ?: 'Review the program details and eligibility requirements.' }}</p>
    <dl class="farmer-program-dates">
        <div><dt>Opens</dt><dd>{{ $program->opens_on?->format('M d, Y') ?: 'No start date' }}</dd></div>
        <div><dt>Closes</dt><dd>{{ $program->closes_on?->format('M d, Y') ?: 'No closing date' }}</dd></div>
    </dl>
    @isset($eligibility)
        <p class="farmer-program-eligibility {{ $eligibility['passed'] ? 'is-eligible' : 'is-guidance' }}">
            <x-ui.icon :name="$eligibility['passed'] ? 'success' : 'office'" size="sm" />
            {{ $eligibility['passed'] ? 'Your profile currently meets the listed checks.' : $eligibility['notes'] }}
        </p>
    @else
        <p class="farmer-program-eligibility is-guidance"><x-ui.icon name="office" size="sm" />The MAO reviews your registration and program requirements.</p>
    @endisset
    <a class="farmer-button farmer-button-light farmer-program-action" href="{{ route('farmer.programs.show', $program) }}">
        View details and requirements <x-ui.icon name="next" size="sm" />
    </a>
</article>
