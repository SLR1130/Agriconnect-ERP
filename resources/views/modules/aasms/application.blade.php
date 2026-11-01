<x-layouts.app>
    <x-slot name="title">AASMS Application</x-slot>
    <x-slot name="header">Assistance Application Details</x-slot>
    <x-slot name="description">Traceable application record and the requirements snapshot used at submission.</x-slot>

    <style>.aas-status{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .55rem;font-size:.7rem;font-weight:800;white-space:nowrap}.aas-pending{background:#fff5d8;color:#8a691e}.aas-approved,.aas-completed{background:#e7f0e8;color:#1c6a46}.aas-rejected{background:#fee7e5;color:#a33e36}.aas-returned{background:#e7f0fb;color:#28618e}</style>
    <a class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-emerald-700 underline" href="{{ route('modules.aasms.dashboard', ['workspace' => 'applications']) }}"><x-ui.icon name="back" size="sm"/>Back to application queue</a>
    <div class="mx-auto max-w-5xl space-y-5">
        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-xs font-extrabold uppercase tracking-widest text-emerald-700">AASMS application</p><h1 class="mt-1 text-2xl font-extrabold text-[#153d2b]">{{ $application->application_reference ?: 'Legacy application #'.$application->id }}</h1><p class="mt-2 text-sm text-gray-600">{{ $application->program->name }} · {{ $application->farmer->user->name }} · {{ $application->farmer->farmer_code }}</p></div>
                <span class="aas-status {{ match($application->status) { 'submitted' => 'aas-pending', 'rejected' => 'aas-rejected', 'returned' => 'aas-returned', 'completed' => 'aas-completed', default => 'aas-approved' } }}">{{ ucfirst($application->status) }}</span>
            </div>
            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl bg-[#f8faf7] p-3"><dt class="text-xs font-bold uppercase text-gray-500">Submitted</dt><dd class="mt-1 text-sm font-semibold">@if($application->submitted_at){{ $application->submitted_at->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Manila time @else Historical timestamp not separately recorded @endif</dd></div>
                <div class="rounded-xl bg-[#f8faf7] p-3"><dt class="text-xs font-bold uppercase text-gray-500">Eligibility at submission</dt><dd class="mt-1 text-sm font-semibold">{{ is_null($application->eligibility_passed) ? 'Not recorded' : ($application->eligibility_passed ? 'Checks passed' : 'Needs review') }}</dd></div>
                <div class="rounded-xl bg-[#f8faf7] p-3"><dt class="text-xs font-bold uppercase text-gray-500">Requirements version</dt><dd class="mt-1 text-sm font-semibold">{{ $application->requirementVersion ? 'Version '.$application->requirementVersion->version_number : 'Legacy evidence unavailable' }}</dd></div>
            </dl>
            @if($application->acknowledgment)<a class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-[#153d2b] px-4 py-2 text-sm font-bold text-white" href="{{ route('modules.aasms.applications.acknowledgment', $application) }}" target="_blank" rel="noopener">Open acknowledgment</a>@else<p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Legacy application: no historical acknowledgment or document-receipt evidence is available.</p>@endif
        </section>

        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <h2 class="text-lg font-bold text-[#153d2b]">Applicant and submission</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2"><div><dt class="text-xs font-bold uppercase text-gray-500">Farmer reference</dt><dd>{{ $application->farmer->farmer_code }}</dd></div><div><dt class="text-xs font-bold uppercase text-gray-500">Barangay</dt><dd>{{ $application->farmer->barangay ?: 'Not recorded' }}</dd></div><div><dt class="text-xs font-bold uppercase text-gray-500">Submission channel</dt><dd>{{ $application->submission_channel ? str($application->submission_channel)->replace('_', ' ')->headline() : 'Historical channel unknown' }}</dd></div><div><dt class="text-xs font-bold uppercase text-gray-500">Purpose</dt><dd>{{ $application->purpose ?: 'No purpose note provided' }}</dd></div></dl>
            @if($application->review_notes)<p class="mt-4 rounded-lg bg-[#f8faf7] p-3 text-sm"><strong>Current review feedback:</strong> {{ $application->review_notes }}</p>@endif
        </section>

        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <h2 class="text-lg font-bold text-[#153d2b]">Requirements and eligibility snapshot</h2>
            @if($application->requirementVersion)
                <p class="mt-1 text-sm text-gray-600">Version {{ $application->requirementVersion->version_number }} · {{ $application->requirementVersion->source === 'legacy_snapshot' ? 'Legacy baseline; publisher and original publication date unknown' : 'Published by '.($application->requirementVersion->publisher?->name ?: 'staff account unavailable') }}</p>
                @if($application->requirementVersion->documentRequirements->isNotEmpty())
                    <ul class="mt-4 divide-y divide-gray-100">
                        @foreach($application->requirementVersion->documentRequirements as $requirement)
                            <li class="py-3">
                                <strong>{{ $requirement->display_name }}</strong> · {{ $requirement->is_required ? 'Required' : 'Optional' }}
                                @if($requirement->description)<p class="text-sm text-gray-600">{{ $requirement->description }}</p>@endif
                                @if($requirement->instructions)<p class="text-sm text-gray-600">{{ $requirement->instructions }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-sm text-gray-600">Online upload was not active in Phase 6C.1; the acknowledgment records that no supporting files were received through the portal.</p>
                @else<p class="mt-3 text-sm text-gray-600">No digitized supporting-document requirements were recorded in this version.</p>@endif
            @else
                <p class="mt-3 text-sm text-gray-600">No versioned requirements evidence is available for this historical application.</p>
            @endif
            @if($application->eligibility_notes)<p class="mt-4 text-sm"><strong>Eligibility result:</strong> {{ $application->eligibility_notes }}</p>@endif
        </section>

        <section class="rounded-2xl border border-[#dfe9e1] bg-white p-5 shadow-sm md:p-7">
            <h2 class="text-lg font-bold text-[#153d2b]">Status history</h2>
            <ol class="mt-3 space-y-3">@forelse($application->history->sortBy('created_at') as $entry)<li class="border-l-2 border-emerald-600 pl-4"><p class="font-semibold">{{ $entry->from_status ? ucfirst($entry->from_status).' → ' : '' }}{{ ucfirst($entry->to_status) }}</p><time class="text-xs text-gray-500">{{ $entry->created_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Manila time</time>@if($entry->notes)<p class="mt-1 text-sm text-gray-600">{{ $entry->notes }}</p>@endif</li>@empty<li class="text-sm text-gray-600">No status history is recorded.</li>@endforelse</ol>
        </section>
    </div>
</x-layouts.app>
