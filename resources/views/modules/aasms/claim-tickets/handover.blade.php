<x-layouts.app>
    <x-slot name="title">Review Physical Handover</x-slot>
    <x-slot name="header">Assistance handover review</x-slot>
    <x-slot name="description">Review the approved claim and inventory batch before the separately confirmed release.</x-slot>

    <style>
        .ho-card{border:1px solid #dce8df;border-radius:16px;background:#fff;box-shadow:0 5px 18px #153d2b0a}.ho-input{width:100%;min-height:46px;border:1px solid #cbd9ce;border-radius:9px;padding:.7rem .8rem;background:#fff;color:#24382d}.ho-input:focus{outline:3px solid #26865c55;outline-offset:1px;border-color:#26865c}.ho-button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:.5rem;border-radius:9px;padding:.65rem .9rem;font-size:.875rem;font-weight:700}.ho-primary{background:#153d2b;color:#fff}.ho-light{border:1px solid #cfe0d2;background:#e7f0e8;color:#153d2b}
    </style>

    <nav class="mb-5 flex flex-wrap gap-3" aria-label="Handover navigation">
        <a class="ho-button ho-light" href="{{ route('modules.aasms.claim-tickets.verify-form') }}"><x-ui.icon name="back" size="sm"/>Ticket verification</a>
        <a class="ho-button ho-light" href="{{ route('modules.aasms.claim-tickets.show', $ticket) }}">Ticket history</a>
    </nav>

    @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-950" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mb-5 rounded-xl border-2 border-amber-400 bg-amber-50 p-4 text-sm font-semibold text-amber-950" role="note">
        {{ $handoverLabel }}. {{ $operationalMode ? 'The authorized MAO staff member must confirm the actual physical handover before stock is released.' : 'The confirmation below is synthetic QA evidence only. No live municipal assistance may be handed over through this interface.' }}
    </div>

    <section class="ho-card p-5 sm:p-7" aria-labelledby="ho-summary">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
            <div><p class="text-xs font-bold uppercase tracking-wider text-emerald-800">{{ $operationalMode ? 'Final review before an authorized distribution' : 'Final review before a QA-only distribution' }}</p><h2 id="ho-summary" class="mt-1 text-xl font-extrabold text-[#153d2b]">{{ $application->application_reference ?: 'Application #'.$application->id }}</h2></div>
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-900">Approved allocation</span>
        </div>
        <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
            <div><dt class="font-semibold text-slate-500">Farmer</dt><dd class="mt-1 font-bold text-slate-900">{{ $application->farmer?->user?->name }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Program</dt><dd class="mt-1 font-bold text-slate-900">{{ $application->program?->name }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Ticket</dt><dd class="mt-1 font-mono font-bold text-slate-900">{{ $ticket->ticket_reference }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Authorized assistance item</dt><dd class="mt-1 font-bold text-slate-900">{{ $ticket->approvedSupplyItem?->name }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Full approved quantity</dt><dd class="mt-1 font-bold text-slate-900">{{ number_format((float) $ticket->approved_quantity, 2) }}</dd></div>
            <div><dt class="font-semibold text-slate-500">Identity step</dt><dd class="mt-1 font-bold text-slate-900">{{ $operationalMode ? 'In-person identity confirmation' : 'Synthetic QA result' }} recorded at {{ $confirmation->confirmed_at?->format('M d, Y H:i').' UTC' }}</dd></div>
        </dl>
    </section>

    <section class="ho-card mt-5 p-5 sm:p-7" aria-labelledby="batch-choice">
        <h2 id="batch-choice" class="text-lg font-extrabold text-[#153d2b]">Select the single inventory batch</h2>
        <p class="mt-1 text-sm leading-6 text-slate-600">The batch must match the approved item, be available and unexpired, and contain the entire allocation. The final POST rechecks stock under a database lock.</p>
        @if($batches->isEmpty())
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950" role="status">No eligible batch can satisfy the complete allocation. No inventory has been reserved or changed.</div>
        @else
            <form class="mt-5 space-y-5" method="POST" action="{{ route('modules.aasms.handovers.distribute', [$application, $ticket]) }}">
                @csrf
                <input type="hidden" name="identity_confirmation_id" value="{{ $confirmation->id }}">
                <input type="hidden" name="quantity" value="{{ number_format((float) $ticket->approved_quantity, 2, '.', '') }}">
                <div>
                    <label for="handover-batch" class="mb-1 block text-sm font-bold text-slate-800">Available batch</label>
                    <select id="handover-batch" name="supply_batch_id" class="ho-input" required>
                        <option value="">Select an eligible batch</option>
                        @foreach($batches as $batch)
                            <option value="{{ $batch->id }}" @selected(old('supply_batch_id') == $batch->id)>
                                {{ $batch->batch_number }} · {{ $batch->location?->name }} · available {{ number_format((float) $batch->quantity_available, 2) }} · expires {{ $batch->expires_on?->format('M d, Y') ?: 'not set' }}
                            </option>
                        @endforeach
                    </select>
                    @error('supply_batch_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <h3 class="font-bold text-slate-900">What the final action does</h3>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-700"><li>Deducts exactly {{ number_format((float) $ticket->approved_quantity, 2) }} from the selected AISMS batch.</li><li>Creates one AASMS distribution and marks the ticket redeemed.</li><li>Completes the application and records an acknowledgment.</li><li>Notifies the farmer after the transaction commits.</li></ul>
                </div>
                <label class="flex min-h-12 items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-950">
                    <input class="mt-1" type="checkbox" name="confirm_physical_handover" value="1" required>
                    <span>{{ $operationalMode ? 'I confirm that the listed assistance item was physically handed to the verified farmer. Submitting will deduct inventory, complete the application, and create an immutable distribution record.' : 'I confirm this is a synthetic QA handover. Submitting will deduct inventory and mark this QA claim complete; it does not certify actual physical receipt by a real farmer.' }}</span>
                </label>
                <div class="flex flex-wrap gap-3">
                    <button class="ho-button ho-primary" type="submit" @disabled($batches->isEmpty())><x-ui.icon name="success" size="sm"/>{{ $operationalMode ? 'Confirm handover and release stock' : 'Confirm QA handover and release stock' }}</button>
                    <a class="ho-button ho-light" href="{{ route('modules.aasms.claim-tickets.show', $ticket) }}">Cancel</a>
                </div>
            </form>
        @endif
    </section>
</x-layouts.app>
