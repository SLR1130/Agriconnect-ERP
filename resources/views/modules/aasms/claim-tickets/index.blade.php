<x-layouts.app>
    <x-slot name="title">AASMS Claim Tickets</x-slot>
    <x-slot name="header">Assistance claim tickets</x-slot>
    <x-slot name="description">Issue and verify QA claim tickets for approved assistance allocations. Ticket verification is not redemption or stock release.</x-slot>

    <style>
        .ct-card{border:1px solid #dce8df;border-radius:16px;background:#fff;box-shadow:0 5px 18px #153d2b0a}
        .ct-input{width:100%;min-height:44px;border:1px solid #cbd9ce;border-radius:9px;padding:.65rem .75rem;background:#fff;color:#24382d}
        .ct-input:focus{outline:3px solid #26865c55;outline-offset:1px;border-color:#26865c}
        .ct-button{display:inline-flex;min-height:42px;align-items:center;justify-content:center;gap:.5rem;border-radius:9px;padding:.6rem .85rem;font-size:.85rem;font-weight:700}
        .ct-primary{background:#153d2b;color:#fff}.ct-light{border:1px solid #cfe0d2;background:#e7f0e8;color:#153d2b}.ct-danger{background:#fee7e5;color:#913a34}
        .ct-table-wrap{overflow-x:auto;border:1px solid #e5ede7;border-radius:12px}.ct-table{width:100%;min-width:740px;border-collapse:collapse}.ct-table th,.ct-table td{padding:.8rem .75rem;text-align:left;vertical-align:top;border-bottom:1px solid #edf2ee}.ct-table th{font-size:.68rem;letter-spacing:.05em;text-transform:uppercase;color:#637269}.ct-table td{font-size:.85rem;color:#35463d}
        .ct-badge{display:inline-flex;border-radius:999px;padding:.25rem .6rem;font-size:.72rem;font-weight:750}.ct-issued{background:#e7f0e8;color:#176341}.ct-expired,.ct-revoked{background:#fce9e7;color:#953d36}
        @media(max-width:640px){.ct-table{min-width:680px}}
    </style>

    <div class="mb-5 flex flex-wrap gap-3">
        <a class="ct-button ct-light" href="{{ route('modules.aasms.dashboard') }}"><x-ui.icon name="back" size="sm"/>Back to AASMS</a>
        <a class="ct-button ct-light" href="#ticket-records">Ticket records</a>
        @if($canVerify)<a class="ct-button ct-primary" href="{{ route('modules.aasms.claim-tickets.verify-form') }}"><x-ui.icon name="qr" size="sm"/>Validate a ticket</a>@endif
    </div>

    @if(session('status'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert"><p class="font-bold">Please review the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if($canIssue)
        <section class="ct-card mb-6 p-5" aria-labelledby="eligible-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 id="eligible-heading" class="text-lg font-bold text-[#153d2b]">Approved applications eligible for first issue</h2><p class="mt-1 text-sm text-slate-600">Only verified farmers with a confirmed allocation and no distribution or previous ticket appear.</p></div><span class="text-xs font-semibold text-slate-500">{{ $eligibleApplications->total() }} records</span></div>
            <div class="ct-table-wrap"><table class="ct-table"><thead><tr><th>Application / program</th><th>Farmer</th><th>Approved allocation</th><th>Decision</th><th>Action</th></tr></thead><tbody>
                @forelse($eligibleApplications as $application)
                    <tr><td><a class="font-bold text-emerald-800 underline" href="{{ route('modules.aasms.applications.show', $application) }}">{{ $application->application_reference ?: 'Application #'.$application->id }}</a><div class="mt-1">{{ $application->program?->name }}</div></td><td>{{ $application->farmer?->user?->name }}<div class="text-xs text-slate-500">{{ $application->farmer?->farmer_code }}</div></td><td>{{ $application->approved_quantity }} {{ $application->approvedSupplyItem?->name }}</td><td>{{ $application->allocation_confirmed_at?->format('M d, Y H:i') }}</td><td><form method="POST" action="{{ route('modules.aasms.claim-tickets.issue', $application) }}" onsubmit="return confirm('Issue a QA claim ticket? This will not reserve or deduct stock.');">@csrf<button class="ct-button ct-primary" type="submit"><x-ui.icon name="ticket" size="sm"/>Issue ticket</button></form></td></tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No applications currently meet the first-issue requirements.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $eligibleApplications->links() }}</div>
        </section>
    @endif

    <section id="ticket-records" class="ct-card p-5" aria-labelledby="ticket-records-heading">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 id="ticket-records-heading" class="text-lg font-bold text-[#153d2b]">Ticket records</h2><p class="mt-1 text-sm text-slate-600">Issued and terminal tickets remain available for traceable history.</p></div><span class="text-xs font-semibold text-slate-500">{{ $tickets->total() }} records</span></div>
        <div class="ct-table-wrap"><table class="ct-table"><thead><tr><th>Reference</th><th>Application / farmer</th><th>Allocation</th><th>Status</th><th>Issued</th><th>Action</th></tr></thead><tbody>
            @forelse($tickets as $ticket)
                @php($effectiveStatus = $ticket->effectiveStatus())
                <tr><td><strong>{{ $ticket->ticket_reference }}</strong></td><td>{{ $ticket->application?->application_reference ?: 'Application #'.$ticket->application_id }}<div class="text-xs text-slate-500">{{ $ticket->application?->farmer?->user?->name }} · {{ $ticket->application?->program?->name }}</div></td><td>{{ $ticket->approved_quantity }} {{ $ticket->approvedSupplyItem?->name }}</td><td><span class="ct-badge ct-{{ $effectiveStatus }}">{{ str($effectiveStatus)->headline() }}</span>@if($ticket->expires_at)<div class="mt-1 text-xs text-slate-500">Expires {{ $ticket->expires_at->format('M d, Y H:i') }} UTC</div>@endif</td><td>{{ $ticket->issued_at?->format('M d, Y H:i') }} UTC</td><td><a class="ct-button ct-light" href="{{ route('modules.aasms.claim-tickets.show', $ticket) }}">View record</a></td></tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">No claim tickets have been issued.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $tickets->links() }}</div>
    </section>
</x-layouts.app>
