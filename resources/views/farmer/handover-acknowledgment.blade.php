<x-layouts.farmer>
    <x-slot name="title">Assistance distribution record</x-slot>
    <x-slot name="description">A private record of the distribution transaction linked to your assistance application.</x-slot>
    <div class="mb-4"><a class="farmer-text-link" href="{{ route('farmer.applications.show', $acknowledgment->application) }}"><x-ui.icon name="back" size="sm"/> Back to assistance application</a></div>
    <article class="farmer-card mx-auto max-w-3xl">
        <div class="farmer-section-heading"><div><p class="text-xs font-bold uppercase tracking-wide text-emerald-800">Assistance distribution</p><h2>Handover acknowledgment</h2><p class="font-mono">{{ $acknowledgment->reference }}</p></div><x-ui.icon name="check" size="lg" /></div>
        @php($operationalRecord = $acknowledgment->confirmation_method === \App\Models\AssistanceIdentityConfirmation::IN_PERSON_METHOD)
        <div class="farmer-message farmer-message-warning mt-4"><strong>{{ $acknowledgment->environment_label }}</strong><br>{{ $operationalRecord ? 'This is the MAO staff-recorded distribution acknowledgment. It does not capture a biometric or stored farmer signature.' : 'This is a synthetic QA record, not evidence of actual physical receipt or a farmer signature.' }}</div>
        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-slate-500">Application</dt><dd class="mt-1 font-bold">{{ $acknowledgment->application_reference_snapshot ?: 'Application #'.$acknowledgment->application_id }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Program</dt><dd class="mt-1 font-bold">{{ $acknowledgment->program_name_snapshot }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Item and quantity</dt><dd class="mt-1 font-bold">{{ $acknowledgment->item_name_snapshot }} · {{ number_format((float) $acknowledgment->quantity, 2) }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Distribution reference</dt><dd class="mt-1 font-mono font-bold">{{ $acknowledgment->distribution?->reference }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Handover date</dt><dd class="mt-1">{{ $acknowledgment->distributed_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Ticket</dt><dd class="mt-1"><a class="farmer-text-link" href="{{ route('farmer.claim-tickets.show', $acknowledgment->ticket) }}">{{ $acknowledgment->ticket_reference_snapshot }}</a></dd></div>
        </dl>
        <div class="mt-5 flex flex-wrap gap-3"><button class="farmer-button" type="button" onclick="window.print()">Print this record</button><a class="farmer-button farmer-button-light" href="{{ route('farmer.applications.acknowledgment', $acknowledgment->application) }}">Original application acknowledgment</a></div>
    </article>
</x-layouts.farmer>
