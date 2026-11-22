<x-layouts.app>
    <x-slot name="title">Handover Acknowledgment</x-slot>
    <x-slot name="header">Distribution acknowledgment</x-slot>
    <x-slot name="description">Immutable summary of the committed handover transaction.</x-slot>
    <style>@media print{.no-print{display:none!important}body{background:#fff!important}.ack-card{box-shadow:none!important}}</style>
    <div class="no-print mb-5 flex flex-wrap gap-3"><a class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 font-semibold text-[#153d2b]" href="{{ route('modules.aasms.applications.show', $acknowledgment->application) }}"><x-ui.icon name="back" size="sm"/>Application</a><button class="min-h-11 rounded-lg bg-[#153d2b] px-4 py-2 font-bold text-white" type="button" onclick="window.print()">Print acknowledgment</button></div>
    <article class="ack-card mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-9">
        <div class="border-b border-slate-200 pb-5"><p class="text-xs font-bold uppercase tracking-widest text-emerald-800">AgriConnect · Agricultural Assistance</p><h2 class="mt-2 text-2xl font-extrabold text-[#153d2b]">Physical handover acknowledgment</h2><p class="mt-2 font-mono text-sm font-bold text-slate-600">{{ $acknowledgment->reference }}</p></div>
        <div class="my-5 rounded-xl border-2 border-amber-400 bg-amber-50 p-4 text-sm font-bold text-amber-950">{{ $handoverLabel }}. {{ $operationalMode ? 'This immutable staff-recorded acknowledgment documents the completed handover. It does not capture a biometric or stored farmer signature.' : 'This synthetic record is not a farmer signature, consent, or proof that real supplies were received.' }}</div>
        <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-slate-500">Application reference</dt><dd class="mt-1 font-bold">{{ $acknowledgment->application_reference_snapshot ?: 'Application #'.$acknowledgment->application_id }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Claim ticket reference</dt><dd class="mt-1 font-mono font-bold">{{ $acknowledgment->ticket_reference_snapshot }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Farmer</dt><dd class="mt-1 font-bold">{{ $acknowledgment->farmer_name_snapshot }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Program</dt><dd class="mt-1 font-bold">{{ $acknowledgment->program_name_snapshot }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Assistance item and quantity</dt><dd class="mt-1 font-bold">{{ $acknowledgment->item_name_snapshot }} · {{ number_format((float) $acknowledgment->quantity, 2) }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Inventory batch</dt><dd class="mt-1 font-mono font-bold">{{ $acknowledgment->batch_reference_snapshot }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Distribution reference</dt><dd class="mt-1 font-mono font-bold">{{ $acknowledgment->distribution?->reference }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Processed by</dt><dd class="mt-1 font-bold">{{ $acknowledgment->distributor?->name }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Distributed at</dt><dd class="mt-1">{{ $acknowledgment->distributed_at?->format('M d, Y g:i A').' UTC' }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Beneficiary confirmation</dt><dd class="mt-1">{{ $acknowledgment->confirmation_method }}</dd></div>
        </dl>
        <p class="mt-7 border-t border-slate-200 pt-4 text-xs leading-5 text-slate-500">Generated from the committed AISMS inventory movement and AASMS distribution. No beneficiary signature was captured. The original application submission acknowledgment remains separate.</p>
    </article>
</x-layouts.app>
