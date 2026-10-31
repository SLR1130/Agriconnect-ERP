<x-layouts.farmer>
    <x-slot name="title">My {{ $submission->category }} submission</x-slot>
    <x-slot name="description">{{ $submission->farm->name }} · Sent {{ $submission->created_at->format('M d, Y') }}</x-slot>
    <a class="text-sm font-bold text-emerald-700 underline" href="{{ route('farmer.production', ['section' => $submission->category === 'fishery' ? 'fisheries' : ($submission->category === 'crop' ? 'crops' : 'livestock')]) }}">← Back to agricultural records</a>
    <div class="mt-5 grid gap-5 lg:grid-cols-[1.2fr_.8fr]">
        <section class="farmer-card"><span class="farmer-pill {{ $submission->status === 'rejected' ? 'farmer-pill-bad' : ($submission->status === 'returned' ? 'farmer-pill-warn' : '') }}">{{ ucfirst($submission->status) }}</span><h2 class="mt-3 text-xl font-bold">{{ data_get($submission->payload, 'crop_type') ?: data_get($submission->payload, 'animal_type') ?: data_get($submission->payload, 'fishery_type') }}</h2><p class="mt-2 text-sm text-slate-600">{{ $submission->farm->name }} · {{ ucfirst($submission->category) }} information</p>
            @if($submission->review_notes)<p class="farmer-message farmer-message-success mt-4"><strong>MAO feedback:</strong> {{ $submission->review_notes }}</p>@endif
            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">@foreach($submission->payload as $key => $value)@if($value !== null && $value !== '')<div><dt class="font-bold text-forest-green">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt><dd>{{ $value }}</dd></div>@endif @endforeach</dl>
            @if($submission->status === 'approved')<p class="mt-5 rounded-xl bg-sage p-4 text-sm">The MAO approved this submission. Its official record is now visible in your agricultural records.</p>@endif
            @if($submission->status === 'returned' && $farmer->isVerified())<details class="farmer-disclosure mt-6 border-t border-sage pt-5" open><summary>Correct and resubmit</summary>@include('farmer.partials.production-form', ['category' => $submission->category, 'submission' => $submission])</details>@endif
        </section>
        <aside class="farmer-card self-start"><h2 class="text-lg font-bold">Status history</h2><ol class="mt-4 space-y-4">@foreach($submission->history->sortBy('created_at') as $entry)<li class="border-l-2 border-sage pl-4 text-sm"><strong>{{ ucfirst($entry->to_status) }}</strong><p class="text-xs text-slate-600">{{ $entry->created_at->format('M d, Y h:i A') }}</p>@if($entry->notes)<p class="mt-1">{{ $entry->notes }}</p>@endif</li>@endforeach</ol></aside>
    </div>
</x-layouts.farmer>
