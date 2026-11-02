<x-layouts.app>
    <x-slot name="title">Claim Ticket Record</x-slot>
    <x-slot name="header">Claim ticket record</x-slot>
    <x-slot name="description">View the ticket allocation, safe audit history, and authorized replacement controls.</x-slot>

    <style>
        .ct-card{border:1px solid #dce8df;border-radius:16px;background:#fff;box-shadow:0 5px 18px #153d2b0a}.ct-input{width:100%;min-height:44px;border:1px solid #cbd9ce;border-radius:9px;padding:.65rem .75rem;background:#fff}.ct-button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:.45rem;border-radius:9px;padding:.6rem .85rem;font-size:.85rem;font-weight:700}.ct-light{border:1px solid #cfe0d2;background:#e7f0e8;color:#153d2b}.ct-danger{background:#fee7e5;color:#913a34}.ct-badge{display:inline-flex;border-radius:999px;padding:.28rem .65rem;font-size:.75rem;font-weight:800}.ct-issued,.ct-redeemed{background:#e7f0e8;color:#176341}.ct-expired,.ct-revoked{background:#fce9e7;color:#953d36}
    </style>
    <div class="mb-5 flex flex-wrap gap-3"><a class="ct-button ct-light" href="{{ route('modules.aasms.claim-tickets.index') }}"><x-ui.icon name="back" size="sm"/>Ticket workspace</a><a class="ct-button ct-light" href="{{ route('modules.aasms.applications.show', $ticket->application) }}">Open application</a></div>
    @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($verification)<div class="mb-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950" role="status"><strong>Verification result: {{ str($verification['outcome'] ?? '')->replace('_', ' ')->headline() }}</strong><p class="mt-1">{{ $verification['message'] }}</p></div>@endif

    @php($effectiveStatus = $ticket->effectiveStatus())
    <section class="ct-card p-5 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-5"><div><p class="text-sm font-semibold text-slate-500">Public ticket reference</p><h2 class="mt-1 break-all text-2xl font-extrabold tracking-wide text-[#153d2b]">{{ $ticket->ticket_reference }}</h2></div><span class="ct-badge ct-{{ $effectiveStatus }}">{{ str($effectiveStatus)->headline() }}</span></div>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Application</dt><dd class="mt-1 font-semibold">{{ $ticket->application?->application_reference ?: 'Application #'.$ticket->application_id }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Farmer</dt><dd class="mt-1 font-semibold">{{ $ticket->application?->farmer?->user?->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Program</dt><dd class="mt-1 font-semibold">{{ $ticket->application?->program?->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Approved item</dt><dd class="mt-1 font-semibold">{{ $ticket->approvedSupplyItem?->name }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Approved quantity</dt><dd class="mt-1 font-semibold">{{ number_format((float) $ticket->approved_quantity, 2) }}</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Issued by / at</dt><dd class="mt-1 font-semibold">{{ $ticket->issuer?->name }} · {{ $ticket->issued_at?->format('M d, Y H:i') }} UTC</dd></div>
            <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Expiry</dt><dd class="mt-1 font-semibold">{{ $ticket->expires_at?->format('M d, Y H:i').' UTC' ?? 'No expiry configured' }}</dd></div>
            @if($ticket->revoked_at)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Revocation</dt><dd class="mt-1">{{ $ticket->revoked_at->format('M d, Y H:i') }} UTC · {{ $ticket->revoker?->name }}<p class="text-sm text-slate-600">{{ $ticket->revocation_reason }}</p></dd></div>@endif
            @if($ticket->supersedes)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Replaces</dt><dd class="mt-1">{{ $ticket->supersedes->ticket_reference }}</dd></div>@endif
            @if($ticket->successor)<div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Replaced by</dt><dd class="mt-1"><a class="font-semibold text-emerald-800 underline" href="{{ route('modules.aasms.claim-tickets.show', $ticket->successor) }}">{{ $ticket->successor->ticket_reference }}</a></dd></div>@endif
        </dl>
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><strong>Not a redemption or distribution.</strong> This QA ticket does not reserve inventory. Confirm identity separately in person. Inventory availability must be checked again at any future authorized handover. No inventory changes occurred during ticket operations in this phase.</div>
    </section>

    @if($canRevoke && $effectiveStatus === 'issued')
        <section class="ct-card mt-5 p-5"><h2 class="font-bold text-[#153d2b]">Revoke active ticket</h2><form method="POST" action="{{ route('modules.aasms.claim-tickets.revoke', $ticket) }}" class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" onsubmit="return confirm('Revoke this ticket and invalidate its stored QR token?');">@csrf<label class="sr-only" for="ticket-revoke-reason">Revocation reason</label><input id="ticket-revoke-reason" class="ct-input" name="reason" maxlength="1000" placeholder="Document the reason for revocation" required><button class="ct-button ct-danger" type="submit">Revoke ticket</button></form></section>
    @endif

    @if($canReissue && in_array($effectiveStatus, ['issued','expired','revoked'], true) && ! $ticket->successor && $ticket->application?->status === 'approved')
        <section class="ct-card mt-5 p-5"><h2 class="font-bold text-[#153d2b]">Reissue from this application</h2><p class="mt-1 text-sm text-slate-600">The previous token will become unusable; its record and audit events remain. The approved allocation must still match this ticket snapshot.</p><form method="POST" action="{{ route('modules.aasms.claim-tickets.reissue', [$ticket->application, $ticket]) }}" class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]" onsubmit="return confirm('Invalidate the old ticket and issue a replacement?');">@csrf<label class="sr-only" for="ticket-reissue-reason">Reissue reason</label><input id="ticket-reissue-reason" class="ct-input" name="reason" maxlength="1000" placeholder="Document the reason for reissue" required><button class="ct-button ct-light" type="submit">Issue replacement</button></form></section>
    @endif

    @if($canHistory)
        <section class="ct-card mt-5 p-5"><div class="mb-4"><h2 class="font-bold text-[#153d2b]">Immutable ticket history</h2><p class="text-sm text-slate-600">Safe event details only; verification tokens are never recorded here.</p></div><ol class="space-y-3">
            @forelse($ticket->events as $event)<li class="border-l-2 border-emerald-600 pl-4"><p class="font-semibold">{{ str($event->event_type)->replace('_', ' ')->headline() }} @if($event->outcome)<span class="text-sm font-normal text-slate-500">· {{ str($event->outcome)->replace('_', ' ')->headline() }}</span>@endif</p><p class="text-xs text-slate-500">{{ $event->occurred_at?->format('M d, Y H:i:s') }} UTC @if($event->actor) · {{ $event->actor->name }}@endif</p>@if($event->reason_code)<p class="text-sm text-slate-600">{{ str($event->reason_code)->replace('_', ' ')->headline() }}</p>@endif</li>
            @empty<li class="text-sm text-slate-500">No ticket events have been recorded.</li>@endforelse
        </ol>@if($ticket->events instanceof \Illuminate\Contracts\Pagination\Paginator || $ticket->events instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)<div class="mt-4">{{ $ticket->events->links() }}</div>@endif</section>
    @endif
</x-layouts.app>
