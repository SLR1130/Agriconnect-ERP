<x-layouts.farmer>
    <x-slot name="title">{{ $program->name }}</x-slot>
    <x-slot name="description">{{ $program->category ?: 'Agricultural assistance' }} · Municipal Agriculture Office</x-slot>
    <a class="mb-5 inline-block text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.services') }}">← Back to services</a>
    <div class="grid gap-5 lg:grid-cols-[1.4fr_.8fr]">
        <div class="farmer-card">
            <span class="farmer-pill {{ $program->isAvailable() ? '' : 'farmer-pill-warn' }}">{{ $program->isAvailable() ? 'Accepting applications' : 'Applications unavailable' }}</span>
            <h2 class="mt-4 text-xl font-bold">About this program</h2><p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $program->description ?: 'The MAO has not provided an additional description. Contact the office for program details.' }}</p>
            <dl class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 text-sm"><div><dt class="font-bold text-forest-green">Application opens</dt><dd>{{ $program->opens_on?->format('M d, Y') ?: 'No start date specified' }}</dd></div><div><dt class="font-bold text-forest-green">Application closes</dt><dd>{{ $program->closes_on?->format('M d, Y') ?: 'No end date specified' }}</dd></div><div><dt class="font-bold text-forest-green">Assistance</dt><dd>{{ $program->supplyItem?->name ?: 'Determined by the MAO during review' }}</dd></div><div><dt class="font-bold text-forest-green">Per-farmer limit</dt><dd>{{ $program->allocation_limit ?: 'Determined during review' }}</dd></div></dl>
            <h2 class="mt-6 text-lg font-bold">Eligibility requirements</h2><ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-slate-700"><li>Verified farmer registration</li>@if(data_get($program->eligibility_rules, 'requires_farm'))<li>At least one registered farm</li>@endif
@if(data_get($program->eligibility_rules, 'barangays'))<li>Eligible barangays: {{ implode(', ', data_get($program->eligibility_rules, 'barangays')) }}</li>@endif
@if(data_get($program->eligibility_rules, 'activities'))<li>Farm activities: {{ implode(', ', data_get($program->eligibility_rules, 'activities')) }}</li>@endif
@if(data_get($program->eligibility_rules, 'farm_categories'))<li>Farm categories: {{ implode(', ', data_get($program->eligibility_rules, 'farm_categories')) }}</li>@endif
@if(data_get($program->eligibility_rules, 'previous_beneficiary_restricted'))<li>Previous beneficiaries cannot apply again</li>@endif</ul>
        </div>
        <aside class="farmer-card self-start"><h2 class="text-lg font-bold">Your next step</h2>
            @if($application)<p class="mt-2 text-sm text-slate-600">You already applied. Your current status is <strong>{{ ucfirst($application->status) }}</strong>.</p><a class="farmer-button mt-4" href="{{ route('farmer.applications.show', $application) }}">Track application</a>
            @elseif(!$eligibility['passed'])<p class="mt-2 text-sm leading-6 text-slate-600">{{ $eligibility['notes'] }}</p><a class="farmer-button farmer-button-light mt-4" href="{{ route('farmer.farms') }}">Check my registration</a>
            @else<form method="POST" action="{{ route('farmer.assistance.apply', $program) }}" class="mt-4 space-y-4">@csrf<div class="farmer-field"><label for="application-purpose">How will this support your farm? (optional)</label><textarea id="application-purpose" name="purpose" maxlength="2000" rows="4">{{ old('purpose') }}</textarea></div><button class="farmer-button farmer-button-gold w-full" type="submit">Submit application</button></form>@endif
        </aside>
    </div>
</x-layouts.farmer>
