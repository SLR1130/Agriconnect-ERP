<x-layouts.app>
    <x-slot name="title">Validate Assistance Claim Ticket</x-slot>
    <x-slot name="header">Claim ticket validation</x-slot>
    <x-slot name="description">Check a ticket credential and inspect its approved allocation. Validation does not confirm identity or release assistance.</x-slot>

    <style>
        .ct-card{border:1px solid #dce8df;border-radius:16px;background:#fff;box-shadow:0 5px 18px #153d2b0a}
        .ct-input{width:100%;min-height:46px;border:1px solid #cbd9ce;border-radius:9px;padding:.7rem .8rem;background:#fff;color:#24382d}
        .ct-input:focus{outline:3px solid #26865c55;outline-offset:1px;border-color:#26865c}
        .ct-button{display:inline-flex;min-height:44px;align-items:center;justify-content:center;gap:.5rem;border-radius:9px;padding:.65rem .9rem;font-size:.875rem;font-weight:700}
        .ct-primary{background:#153d2b;color:#fff}.ct-light{border:1px solid #cfe0d2;background:#e7f0e8;color:#153d2b}
    </style>

    <nav class="mb-5 flex flex-wrap gap-3" aria-label="Claim ticket navigation">
        <a class="ct-button ct-light" href="{{ route('modules.aasms.claim-tickets.index') }}"><x-ui.icon name="back" size="sm"/>Ticket workspace</a>
        <a class="ct-button ct-light" href="{{ route('modules.aasms.dashboard') }}">Back to AASMS</a>
    </nav>

    @if(session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-950" role="alert">
            <p class="font-bold">The ticket could not be checked.</p>
            <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="ct-card p-5 sm:p-7" aria-labelledby="validation-heading">
        <div class="max-w-3xl">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-800">MAO staff workspace</p>
            <h2 id="validation-heading" class="mt-1 text-xl font-extrabold text-[#153d2b]">Validate a ticket credential</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Enter the QR payload or token from the farmer's ticket. If the QR cannot be read, look up the public reference. A reference locates a record only; it does not prove possession of the secret token or the presenter's identity.</p>
        </div>

        <form class="mt-5 grid max-w-4xl gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" method="POST" action="{{ route('modules.aasms.claim-tickets.verify') }}" autocomplete="off">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-800" for="claim-ticket-input">QR payload, verification token, or ticket reference</label>
                <input id="claim-ticket-input" class="ct-input font-mono" name="ticket_input" maxlength="256" autocapitalize="off" spellcheck="false" autocomplete="off" inputmode="text" required aria-describedby="claim-ticket-input-help">
                <p id="claim-ticket-input-help" class="mt-1 text-xs text-slate-500">Manual entry is always available. This page has no camera scanner and does not request camera permission.</p>
            </div>
            <button class="ct-button ct-primary" type="submit"><x-ui.icon name="security" size="sm"/>Check ticket</button>
        </form>
    </section>

    <section class="mt-5 grid gap-4 md:grid-cols-3" aria-label="Separate handover steps">
        <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-emerald-800">Implemented in QA</p>
            <h3 class="mt-1 font-bold text-[#153d2b]">1. Ticket validation</h3>
            <p class="mt-1 text-sm text-slate-700">Checks token status, expiry, revocation, and whether the approved allocation still matches.</p>
        </article>
        <article class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-amber-900">{{ $handoverEnabled ? 'Synthetic QA only' : 'Policy pending' }}</p>
            <h3 class="mt-1 font-bold text-[#153d2b]">2. Beneficiary identity confirmation</h3>
            <p class="mt-1 text-sm text-slate-700">A QR token or reference alone is not identity evidence. Real-world verification method and evidence rules require MAO policy approval.</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-600">{{ $handoverEnabled ? 'Isolated QA only' : 'Not enabled' }}</p>
            <h3 class="mt-1 font-bold text-[#153d2b]">3. Physical distribution</h3>
            <p class="mt-1 text-sm text-slate-700">A separate review and final confirmation are required. No operation occurs automatically after ticket validation.</p>
        </article>
    </section>

    @if(is_array($verification))
        @php
            $outcome = $verification['outcome'] ?? 'unknown';
            $isTokenValid = $outcome === 'valid';
            $isReferenceLookup = $outcome === 'reference_only';
            $tone = $isTokenValid ? 'border-emerald-300 bg-emerald-50 text-emerald-950' : 'border-amber-300 bg-amber-50 text-amber-950';
            $label = match ($outcome) {
                'valid' => 'Token valid — identity not confirmed',
                'reference_only' => 'Reference located — token not checked',
                'expired' => 'Ticket expired',
                'revoked' => 'Ticket revoked or superseded',
                'approval_changed' => 'Approved allocation changed',
                'not_found' => 'Reference not found',
                'invalid' => 'Token invalid',
                default => str($outcome)->replace('_', ' ')->headline(),
            };
        @endphp
        <section class="mt-5 rounded-xl border p-5 {{ $tone }}" aria-live="polite" aria-labelledby="verification-result-heading">
            <p class="text-xs font-bold uppercase tracking-wide">{{ str($verification['method'] ?? '')->headline() }} check</p>
            <h2 id="verification-result-heading" class="mt-1 text-lg font-extrabold">{{ $label }}</h2>
            <p class="mt-2 text-sm">{{ $verification['message'] ?? 'Ticket check recorded.' }}</p>
            @if($isTokenValid)
                <p class="mt-3 rounded-lg border border-emerald-300 bg-white/70 p-3 text-sm font-semibold">This validates the bearer token only. Confirm the beneficiary's identity separately in person under an approved policy. This result is not authorization to hand over supplies.</p>
            @elseif(($verification['method'] ?? '') === 'reference')
                <p class="mt-3 rounded-lg border border-amber-300 bg-white/70 p-3 text-sm font-semibold">A reference is not a secret credential and is not proof of possession or identity. Ask the beneficiary to present the ticket token and follow the future identity-confirmation procedure.</p>
            @endif

            @if($verificationTicket)
                <dl class="mt-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div><dt class="font-semibold text-slate-500">Ticket status</dt><dd class="mt-1 font-bold text-slate-900">{{ str($verificationTicket->effectiveStatus())->headline() }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Farmer record</dt><dd class="mt-1 font-bold text-slate-900">{{ $verificationTicket->application?->farmer?->user?->name }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Approved program</dt><dd class="mt-1 font-bold text-slate-900">{{ $verificationTicket->application?->program?->name }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Approved item</dt><dd class="mt-1 font-bold text-slate-900">{{ $verificationTicket->approvedSupplyItem?->name }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Approved quantity</dt><dd class="mt-1 font-bold text-slate-900">{{ number_format((float) $verificationTicket->approved_quantity, 2) }}</dd></div>
                    <div><dt class="font-semibold text-slate-500">Expires</dt><dd class="mt-1 font-bold text-slate-900">{{ $verificationTicket->expires_at?->format('M d, Y H:i').' UTC' ?? 'No expiry configured' }}</dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a class="ct-button ct-light" href="{{ route('modules.aasms.claim-tickets.show', $verificationTicket) }}">Open ticket history</a>
                    <a class="ct-button ct-light" href="{{ route('modules.aasms.applications.show', $verificationTicket->application) }}">Open approved application</a>
                </div>
            @if($isTokenValid && $handoverEnabled && $canConfirmIdentity && !$identityConfirmation && !empty($verification['verification_event_id']))
                    <form class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4" method="POST" action="{{ route('modules.aasms.claim-tickets.identity-confirm', $verificationTicket) }}">
                        @csrf
                        <input type="hidden" name="verification_event_id" value="{{ $verification['verification_event_id'] }}">
                        <p class="font-bold text-amber-950">Synthetic QA identity-confirmation foundation</p>
                        <p class="mt-1 text-sm leading-6 text-amber-950">This records only a synthetic QA match/no-match scenario. It does not verify legal identity, constitute consent, use biometrics, or capture a signature. Do not use this action for real beneficiaries.</p>
                        <fieldset class="mt-3 flex flex-wrap gap-4 text-sm text-slate-800">
                            <legend class="sr-only">Synthetic identity scenario</legend>
                            <label class="inline-flex min-h-11 items-center gap-2"><input type="radio" name="outcome" value="confirmed" required> Synthetic QA match</label>
                            <label class="inline-flex min-h-11 items-center gap-2"><input type="radio" name="outcome" value="not_confirmed" required> Synthetic QA no match</label>
                        </fieldset>
                        <button class="ct-button ct-primary mt-3" type="submit">Record QA identity result</button>
                    </form>
                @endif
            @endif

            @if($identityConfirmation && $identityConfirmation->outcome === 'confirmed')
                <div class="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-950" role="status">
                    <p class="font-bold">Synthetic QA confirmation recorded for this ticket.</p>
                    <p class="mt-1">It is recent, ticket-specific QA evidence only. No stock has changed yet.</p>
                    @if($canHandover)
                        <a class="ct-button ct-primary mt-3" href="{{ route('modules.aasms.handovers.review', ['ticket' => $verificationTicket, 'confirmation' => $identityConfirmation->id]) }}">Continue to authorized handover review</a>
                    @else
                        <p class="mt-2">A staff member with both distribution permissions and the exact handover grant must continue.</p>
                    @endif
                </div>
            @endif
        </section>
    @endif
</x-layouts.app>
