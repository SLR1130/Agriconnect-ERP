<x-layouts.farmer>
    <x-slot name="title">Assistance claim ticket</x-slot>
    <x-slot name="description">Review your ticket, allocation, and its status history.</x-slot>

    @php($effectiveStatus = $ticket->effectiveStatus())
    <x-farmer.tracking-header
        service="Assistance claim ticket"
        :title="$ticket->ticket_reference"
        :status="$effectiveStatus"
        :submitted-at="$ticket->issued_at"
        date-label="Issued"
        :reference="$ticket->application?->application_reference ?: 'Application '.$ticket->application_id"
        :back-url="route('farmer.applications.show', $ticket->application_id)"
        back-label="Back to application"
        icon="assistance"
    />

    <div class="farmer-tracking-layout">
        <div class="farmer-tracking-main space-y-4">
            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Your approved allocation</h2><p>This ticket reflects the allocation approved for your application.</p></div><x-ui.icon name="ticket" size="lg" /></div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Program</dt><dd class="mt-1 font-semibold">{{ $ticket->application?->program?->name }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Item</dt><dd class="mt-1 font-semibold">{{ $ticket->approvedSupplyItem?->name }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Quantity</dt><dd class="mt-1 font-semibold">{{ number_format((float) $ticket->approved_quantity, 2) }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Issued</dt><dd class="mt-1">{{ $ticket->issued_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Expiry</dt><dd class="mt-1">{{ $ticket->expires_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') ?: 'No expiry configured' }}</dd></div>
                </dl>
            </section>

            @if($ticket->successor || $ticket->supersedes)
                <section class="farmer-card" aria-labelledby="ticket-reissue-heading">
                    <div class="farmer-section-heading"><div><h2 id="ticket-reissue-heading">Replacement history</h2><p>Reissued tickets have a new private QR. The prior ticket cannot be used.</p></div></div>
                    @if($ticket->successor)
                        <p class="mt-3 text-sm text-slate-700">This ticket was superseded. Its token is no longer valid.</p>
                        <a class="farmer-button mt-3" href="{{ route('farmer.claim-tickets.show', $ticket->successor) }}">View replacement {{ $ticket->successor->ticket_reference }}</a>
                    @endif
                    @if($ticket->supersedes)
                        <p class="mt-3 text-sm text-slate-700">This ticket replaced {{ $ticket->supersedes->ticket_reference }}. The earlier ticket remains in the history and cannot be used.</p>
                    @endif
                </section>
            @endif

            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Ticket QR</h2><p>Keep this private and show it only to authorized MAO staff.</p></div></div>
                @if($effectiveStatus === 'issued' && $qrAvailable)
                    <div class="mt-4 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                        <img class="h-56 w-56 rounded-xl border border-slate-200 bg-white p-3" src="{{ route('farmer.claim-tickets.qr', $ticket) }}" alt="Private ticket verification QR code">
                        <div><p class="text-sm text-slate-700">Ticket reference</p><p class="font-mono text-xl font-bold text-[#153d2b]">{{ $ticket->ticket_reference }}</p><p class="mt-2 text-sm text-slate-600">The QR carries only a random verification token. It contains no name, farmer ID, application details, or document data.</p><a class="farmer-button mt-4" href="{{ route('farmer.claim-tickets.print', $ticket) }}" target="_blank" rel="noopener">Print ticket</a></div>
                    </div>
                @else
                    <p class="farmer-message farmer-message-warning mt-4" role="status">This ticket is {{ str($effectiveStatus)->lower() }}. Its QR is unavailable and cannot be used for verification. Contact the MAO if you need help.</p>
                @endif
            </section>

            <section class="farmer-card">
                <div class="farmer-section-heading"><div><h2>Pickup information</h2><p>Follow any official instructions provided to you by the MAO.</p></div></div>
                @if(config('agricultural_claim_tickets.pickup_instructions'))<p class="mt-3">{{ config('agricultural_claim_tickets.pickup_instructions') }}</p>@else<p class="mt-3 text-sm text-slate-600">No pickup instructions are configured for this QA ticket. Contact the municipal agriculture office through its existing official channels before visiting.</p>@endif
                @if(config('agricultural_claim_tickets.office_name'))<p class="mt-2 font-semibold">{{ config('agricultural_claim_tickets.office_name') }}</p>@endif
                @if(config('agricultural_claim_tickets.office_address'))<p class="text-sm text-slate-600">{{ config('agricultural_claim_tickets.office_address') }}</p>@endif
            </section>

            <div class="farmer-message farmer-message-warning" role="note"><strong>QA ticket only — not redeemable.</strong> Issuance does not reserve or guarantee inventory. No supplies have been released by issuing or displaying this ticket. Physical handover and stock deduction remain a separate future Phase 6E workflow.</div>

            <section class="farmer-card" aria-labelledby="lost-ticket-heading">
                <div class="farmer-section-heading"><div><h2 id="lost-ticket-heading">Lost or damaged ticket?</h2><p>Protect your ticket token and QR as you would a private credential.</p></div></div>
                <p class="mt-3 text-sm leading-6 text-slate-700">Contact the Municipal Agriculture Office through its existing official channels. Staff must confirm your farmer record separately. If authorized, they can revoke the old ticket and issue a replacement. A ticket reference alone is not proof of identity.</p>
            </section>

            <section class="farmer-card"><div class="farmer-section-heading"><div><h2>Ticket history</h2><p>Recorded ticket events are separate from your application and distribution history.</p></div></div>
                <ol class="mt-4 space-y-3">@forelse($events as $event)<li class="border-l-2 border-emerald-600 pl-4"><p class="font-semibold text-slate-800">{{ str($event->event_type)->replace('_', ' ')->headline() }} @if($event->outcome && $event->outcome !== 'valid')<span class="text-sm font-normal text-slate-500">· {{ str($event->outcome)->replace('_', ' ')->headline() }}</span>@endif</p><time class="text-xs text-slate-500">{{ $event->occurred_at?->timezone(config('agriculture.business_timezone'))->format('M d, Y g:i A') }}</time></li>@empty<li class="text-sm text-slate-500">No ticket history is available.</li>@endforelse</ol><div class="mt-4">{{ $events->links() }}</div>
            </section>
        </div>
        <aside><x-farmer.next-action :message="$effectiveStatus === 'issued' ? 'Keep your ticket private. Staff must verify the ticket and separately confirm your identity in person. Ticket display does not mean supplies have been released.' : 'This ticket is no longer active. Review the status and contact the MAO if you need assistance.'" :tone="$effectiveStatus === 'issued' ? 'info' : 'warning'" /></aside>
    </div>
</x-layouts.farmer>
