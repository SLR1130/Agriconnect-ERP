<x-layouts.app>
<x-slot name=title>Farmer Assistance</x-slot>
<x-slot name=header>My Assistance Applications</x-slot>
<x-slot name=description>Track decisions and completed municipal assistance distributions.</x-slot>
<div class="space-y-6">
    @if(session('status'))
        <div class="rounded-xl border-l-4 border-emerald-600 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm text-red-700">
            <strong>Application needs attention.</strong>
            <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-forest-green">Available assistance programs</h2>
                <p class="mt-1 text-sm text-slate-500">Apply using your verified farmer record and review the eligibility requirements before submitting.</p>
            </div>
            <span class="rounded-full bg-sage px-3 py-1 text-xs font-bold text-emerald-800">{{ $programs->count() }} open</span>
        </div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse($programs as $program)
                <article class="rounded-xl border border-slate-200 bg-off-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-forest-green">{{ $program->name }}</h3>
                            <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ $program->category ?: 'Agricultural assistance' }}</p>
                        </div>
                        @if($program->supplyItem)
                            <span class="rounded-full bg-sage px-2.5 py-1 text-xs font-semibold text-emerald-800">{{ $program->supplyItem->name }}</span>
                        @endif
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ $program->description ?: 'Support available to eligible verified farmers.' }}</p>
                    <div class="mt-3 grid gap-2 text-xs text-slate-500 sm:grid-cols-2">
                        <span>Eligibility: verified farmer{{ data_get($program->eligibility_rules, 'requires_farm') ? ' with registered farm' : '' }}</span>
                        <span>Allocation limit: {{ $program->allocation_limit ?: 'Set during review' }}</span>
                    </div>
                    <form method="POST" action="{{ route('farmer.assistance.apply', $program) }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500" for="purpose-{{ $program->id }}">Application purpose</label>
                        <textarea id="purpose-{{ $program->id }}" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" name="purpose" rows="2" placeholder="Explain how this assistance will support your farm."></textarea>
                        <button class="inline-flex rounded-xl bg-agricultural-gold px-4 py-2 text-sm font-bold text-forest-green" type="submit">Submit application</button>
                    </form>
                </article>
            @empty
                <p class="rounded-xl bg-off-white p-6 text-sm text-slate-500 lg:col-span-2">No open assistance programs are currently available.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-forest-green">My applications</h2>
                <p class="mt-1 text-sm text-slate-500">Review status history, MAO notes, and completed distributions.</p>
            </div>
            <span class="rounded-full bg-sage px-3 py-1 text-xs font-bold text-emerald-800">{{ $applications->count() }} records</span>
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Program</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Review</th><th class="px-3 py-3">Distribution</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($applications as $application)
                        <tr class="align-top"><td class="px-3 py-3 font-semibold text-forest-green">{{ $application->program->name }}</td><td class="px-3 py-3">{{ ucfirst($application->status) }}</td><td class="px-3 py-3 text-slate-600">{{ $application->review_notes ?: 'Awaiting MAO review.' }}</td><td class="px-3 py-3 text-slate-600">@forelse($application->distributions as $distribution)<div>{{ $distribution->batch->item->name }} · {{ $distribution->quantity }} · {{ $distribution->reference }}</div>@empty Not yet distributed @endforelse</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">You have not submitted an assistance application.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</x-layouts.app>
