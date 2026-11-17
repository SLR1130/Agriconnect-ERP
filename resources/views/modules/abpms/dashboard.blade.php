<x-layouts.app>
    <x-slot name="header">Budget &amp; Procurement Management</x-slot>
    <x-slot name="description">Plan agricultural budgets, control procurement, and receive approved goods into the shared ERP records.</x-slot>

    <style>
        .ab-card{background:#fff;border:1px solid #dfe9e1;border-radius:18px;box-shadow:0 8px 24px #153d2b0d}
        .ab-input{width:100%;border:1px solid #cbd9cf;border-radius:10px;padding:.65rem .75rem;background:#fff;color:#263b30;font-size:.83rem}
        .ab-input:focus{outline:2px solid #d5ad55;outline-offset:1px;border-color:#26865c}
        .ab-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:.62rem .9rem;font-size:.76rem;font-weight:800;transition:.15s}
        .ab-btn:hover{filter:brightness(.97);transform:translateY(-1px)}
        .ab-primary{background:#153d2b;color:#fff}.ab-gold{background:#d5ad55;color:#153d2b}.ab-muted{background:#e7f0e8;color:#153d2b}.ab-danger{background:#fee7e5;color:#9f3b36}
        .ab-table{width:100%;border-collapse:collapse}.ab-table th{padding:.7rem;text-align:left;color:#718177;font-size:.64rem;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #e1e9e2;white-space:nowrap}.ab-table td{padding:.75rem;color:#405048;font-size:.78rem;border-bottom:1px solid #edf2ee;vertical-align:top}.ab-table tr:last-child td{border-bottom:0}
        .ab-pill{display:inline-flex;border-radius:999px;padding:.25rem .6rem;font-size:.65rem;font-weight:800;text-transform:capitalize;white-space:nowrap}.ab-green{background:#e7f0e8;color:#1c6a46}.ab-yellow{background:#fff5d8;color:#8a691e}.ab-red{background:#fee7e5;color:#a33e36}.ab-blue{background:#e7f0fb;color:#28618e}.ab-gray{background:#eef2f0;color:#58685e}
        .ab-label{display:block;margin-bottom:.35rem;color:#53645a;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .ab-disclosure>summary{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;list-style:none;cursor:pointer}
        .ab-disclosure>summary::-webkit-details-marker{display:none}
        .ab-disclosure>summary:focus-visible{outline:2px solid #26865c;outline-offset:4px;border-radius:8px}
        .ab-disclosure-title{margin:0;color:#153d2b;font-size:1.125rem;font-weight:800}
        .ab-disclosure-copy{margin:.25rem 0 0;color:#718177;font-size:.82rem;line-height:1.5}
        .ab-disclosure-toggle{display:inline-flex;align-items:center;gap:.4rem;flex:none;color:#1c6a46;font-size:.75rem;font-weight:800}
        .ab-disclosure-toggle::before{content:'+';display:grid;width:1.35rem;height:1.35rem;place-items:center;border-radius:999px;background:#e7f0e8;font-size:1rem}
        .ab-disclosure[open] .ab-disclosure-toggle::before{content:'−'}
        .ab-disclosure-body{margin-top:1rem;padding-top:1rem;border-top:1px solid #e1e9e2}
        .ab-row-disclosure>summary{list-style:none;cursor:pointer}
        .ab-row-disclosure>summary::-webkit-details-marker{display:none}
        .ab-row-disclosure>summary:focus-visible{outline:2px solid #26865c;outline-offset:3px}
        .ab-row-disclosure-body{margin-top:.5rem;min-width:200px}
        @media(max-width:900px){.ab-two,.ab-forms{grid-template-columns:1fr!important}.ab-table-wrap{overflow-x:auto}.ab-table{min-width:900px}}
    </style>

    @php
        $money = fn ($value) => number_format((float) $value, 2);
        $statusClass = fn ($status) => in_array($status, ['approved', 'ordered', 'partially_received', 'active'], true) ? 'ab-green' : (in_array($status, ['rejected', 'cancelled'], true) ? 'ab-red' : (in_array($status, ['under_review', 'submitted', 'draft'], true) ? 'ab-yellow' : 'ab-blue'));
    @endphp

    <div class="space-y-6">
        @if(session('status'))
            <div class="ab-card border-l-4 border-emerald-600 px-5 py-4 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="ab-card border-l-4 border-red-500 px-5 py-4 text-sm text-red-700" role="alert">
                <b>Budget or procurement action needs attention.</b>
                <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['label' => 'Allocations', 'value' => $stats['allocations'], 'class' => 'text-forest-green'],
                ['label' => 'Authorized', 'value' => '₱'.$money($stats['authorized']), 'class' => 'text-forest-green'],
                ['label' => 'Available', 'value' => '₱'.$money($stats['available']), 'class' => 'text-emerald-green'],
                ['label' => 'Pending requests', 'value' => $stats['pending_requests'], 'class' => 'text-agricultural-gold'],
                ['label' => 'Open orders', 'value' => $stats['open_orders'], 'class' => 'text-blue-700'],
                ['label' => 'Actual expenditure', 'value' => '₱'.$money($stats['actual_expenditure']), 'class' => 'text-red-600'],
            ] as $stat)
                <div class="ab-card p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-xl font-bold {{ $stat['class'] }}">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="erp-workspace" data-erp-workspace data-workspace-param="workspace" data-workspace-default="budget" data-workspace-initial="{{ old('_erp_workspace', request('workspace', 'budget')) }}">
            <div class="erp-workspace-tabs" role="tablist" aria-label="Budget and procurement workspaces">
                <button id="abpms-tab-budget" class="erp-workspace-tab" type="button" role="tab" aria-selected="true" aria-controls="abpms-panel-budget" data-erp-workspace-tab="budget">Budget management</button>
                <button id="abpms-tab-procurement" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="abpms-panel-procurement" data-erp-workspace-tab="procurement">Procurement requests</button>
                <button id="abpms-tab-orders" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="abpms-panel-orders" data-erp-workspace-tab="orders">Purchase orders &amp; receiving</button>
                <button id="abpms-tab-equipment" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="abpms-panel-equipment" data-erp-workspace-tab="equipment">Equipment acquisitions</button>
                <button id="abpms-tab-reports" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="abpms-panel-reports" data-erp-workspace-tab="reports">Reports &amp; history</button>
            </div>

            <section id="abpms-panel-budget" class="erp-workspace-panel space-y-5" role="tabpanel" aria-labelledby="abpms-tab-budget" tabindex="0" data-erp-workspace-panel="budget">
        @if($canManage)
            <div class="ab-forms grid grid-cols-2 gap-5">
                <section class="ab-card p-5"><details class="ab-disclosure" @if(old('_abpms_form') === 'period' || $errors->has('year') || $errors->has('name') || $errors->has('starts_on') || $errors->has('ends_on')) open @endif><summary><span><span class="ab-disclosure-title">Open a budget period</span><span class="ab-disclosure-copy block">A period is the authoritative time boundary for budget allocations.</span></span><span class="ab-disclosure-toggle" aria-hidden="true"></span></summary><div class="ab-disclosure-body"><form method="POST" action="{{ route('modules.abpms.periods.store') }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_abpms_form" value="period">

                        <div><label class="ab-label" for="period-year">Year</label><input id="period-year" class="ab-input" name="year" type="number" min="2000" max="2200" value="{{ old('year', now()->year) }}" required></div>
                        <div><label class="ab-label" for="period-name">Period name</label><input id="period-name" class="ab-input" name="name" placeholder="FY {{ now()->year }} Agricultural Program" required></div>
                        <div><label class="ab-label" for="period-start">Starts</label><input id="period-start" class="ab-input" name="starts_on" type="date" required></div>
                        <div><label class="ab-label" for="period-end">Ends</label><input id="period-end" class="ab-input" name="ends_on" type="date" required></div>
                        <button class="ab-btn ab-primary sm:col-span-2" type="submit">Create budget period</button>
                    </form></div></details></section>
                <section class="ab-card bg-sage p-5"><details class="ab-disclosure" @if(old('_abpms_form') === 'allocation' || $errors->has('budget_period_id') || $errors->has('program_code') || $errors->has('funding_category') || $errors->has('authorized_amount') || $errors->has('description')) open @endif><summary><span><span class="ab-disclosure-title">Create an allocation</span><span class="ab-disclosure-copy block">Funds are tracked through an auditable ledger. Available balances are calculated from recorded entries.</span></span><span class="ab-disclosure-toggle" aria-hidden="true"></span></summary><div class="ab-disclosure-body"><form method="POST" action="{{ route('modules.abpms.allocations.store') }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_abpms_form" value="allocation">

                        <div><label class="ab-label" for="allocation-period">Budget period</label><select id="allocation-period" class="ab-input" name="budget_period_id" required><option value="">Select period</option>@foreach($periods as $period)<option value="{{ $period->id }}">{{ $period->year }} — {{ $period->name }}</option>@endforeach</select></div>
                        <div><label class="ab-label" for="allocation-program">Program code</label><input id="allocation-program" class="ab-input" name="program_code" placeholder="e.g. FFRMS-2026" required></div>
                        <div><label class="ab-label" for="allocation-category">Funding category</label><input id="allocation-category" class="ab-input" name="funding_category" placeholder="Agricultural supplies" required></div>
                        <div><label class="ab-label" for="allocation-amount">Authorized amount</label><input id="allocation-amount" class="ab-input" name="authorized_amount" type="number" min=".01" step=".01" placeholder="0.00" required></div>
                        <div class="sm:col-span-2"><label class="ab-label" for="allocation-description">Description</label><textarea id="allocation-description" class="ab-input" name="description" rows="2" required></textarea></div>
                        <button class="ab-btn ab-gold sm:col-span-2" type="submit">Authorize allocation</button>
                    </form></div></details></section>
            </div>
        @endif

        <section class="ab-card p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div><h2 class="text-lg font-bold text-forest-green">Budget allocations</h2><p class="mt-1 text-sm text-slate-500">Authorized, committed, actual, and available amounts come from the budget ledger.</p></div>
                @if($canExport)
                    <div class="flex flex-wrap gap-2">
                        <a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'budget']) }}">Budget balances CSV</a>
                        <a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'requests']) }}">Requests CSV</a>
                        <a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'orders']) }}">Orders CSV</a>
                        <a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'receipts']) }}">Receiving CSV</a>
                        <a class="ab-btn ab-gold" href="{{ route('modules.abpms.export', ['report' => 'equipment']) }}">Equipment acquisitions CSV</a>
                    </div>
                @endif
            </div>
            <div class="ab-table-wrap mt-5"><table class="ab-table"><thead><tr><th>Reference</th><th>Period / program</th><th>Authorized</th><th>Committed</th><th>Actual</th><th>Available</th>@if($canManage)<th>Ledger action</th>@endif</tr></thead><tbody>
                @forelse($allocations as $allocation)
                    @php($summary = $budgetSummaries[$allocation->id] ?? [])
                    <tr>
                        <td><b class="text-forest-green">{{ $allocation->reference }}</b><div class="mt-1 text-xs text-slate-500">{{ ucfirst($allocation->status) }}</div></td>
                        <td><b>{{ $allocation->program_code }}</b><div class="text-xs text-slate-500">{{ $allocation->period?->year }} · {{ $allocation->funding_category }}</div><div class="mt-1 max-w-xs text-xs text-slate-500">{{ $allocation->description }}</div></td>
                        <td>₱{{ $money($summary['authorized'] ?? 0) }}</td><td>₱{{ $money($summary['committed'] ?? 0) }}</td><td>₱{{ $money($summary['actual'] ?? 0) }}</td><td><b class="{{ (float)($summary['available'] ?? 0) < 0 ? 'text-red-600' : 'text-emerald-green' }}">₱{{ $money($summary['available'] ?? 0) }}</b></td>
                        @if($canManage)
                            <td class="min-w-[250px]">
                                <form method="POST" action="{{ route('modules.abpms.allocations.adjust', $allocation) }}" class="flex flex-wrap gap-2">
                                    @csrf
                                    <select class="ab-input !w-auto" name="direction" aria-label="Adjustment direction"><option value="credit">Credit</option><option value="debit">Debit</option></select>
                                    <input class="ab-input !w-24" name="amount" type="number" min=".01" step=".01" placeholder="Amount" required>
                                    <input class="ab-input" name="description" placeholder="Reason" required>
                                    <button class="ab-btn ab-muted" type="submit">Adjust</button>
                                </form>
                                <form method="POST" action="{{ route('modules.abpms.allocations.expenditure', $allocation) }}" class="mt-2 flex flex-wrap gap-2">
                                    @csrf
                                    <input class="ab-input !w-24" name="amount" type="number" min=".01" step=".01" placeholder="Spent" required>
                                    <input class="ab-input" name="reference" placeholder="Expenditure ref" required>
                                    <input class="ab-input" name="description" placeholder="Description" required>
                                    <button class="ab-btn ab-gold" type="submit">Post expense</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="py-10 text-center text-slate-500">No budget allocations have been recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

            </section>
            <section id="abpms-panel-procurement" class="erp-workspace-panel space-y-5" role="tabpanel" aria-labelledby="abpms-tab-procurement" tabindex="0" data-erp-workspace-panel="procurement" hidden>
        @if($canCreate)
            <div class="ab-forms grid grid-cols-2 gap-5">
                <section class="ab-card p-5"><details class="ab-disclosure" @if(old('_abpms_form') === 'supplier' || $errors->has('contact_person') || $errors->has('registration_reference') || $errors->has('email') || $errors->has('phone') || $errors->has('address') || $errors->has('name')) open @endif><summary><span><span class="ab-disclosure-title">Supplier registry</span><span class="ab-disclosure-copy block">Keep supplier identity and registration details in one shared procurement record.</span></span><span class="ab-disclosure-toggle" aria-hidden="true"></span></summary><div class="ab-disclosure-body"><form method="POST" action="{{ route('modules.abpms.suppliers.store') }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="_abpms_form" value="supplier">

                        <div class="sm:col-span-2"><label class="ab-label" for="supplier-name">Supplier name</label><input id="supplier-name" class="ab-input" name="name" required></div>
                        <input class="ab-input" name="contact_person" placeholder="Contact person"><input class="ab-input" name="registration_reference" placeholder="Registration reference">
                        <input class="ab-input" name="email" type="email" placeholder="Email"><input class="ab-input" name="phone" placeholder="Phone">
                        <input class="ab-input sm:col-span-2" name="address" placeholder="Business address">
                        <button class="ab-btn ab-primary sm:col-span-2" type="submit">Register supplier</button>
                    </form></div></details></section>
                <section class="ab-card p-5"><details class="ab-disclosure" @if(old('_abpms_form') === 'request' || $errors->has('budget_allocation_id') || $errors->has('requesting_office') || $errors->has('purpose') || $errors->has('justification') || $errors->has('items.0.item_type') || $errors->has('items.0.supply_item_id') || $errors->has('items.0.description') || $errors->has('items.0.quantity') || $errors->has('items.0.estimated_unit_price')) open @endif><summary><span><span class="ab-disclosure-title">New procurement request</span><span class="ab-disclosure-copy block">Requests reserve no funds until an authorized purchase order is approved.</span></span><span class="ab-disclosure-toggle" aria-hidden="true"></span></summary><div class="ab-disclosure-body"><form method="POST" action="{{ route('modules.abpms.requests.store') }}" class="space-y-3" id="procurement-request-form">
                        @csrf
                        <input type="hidden" name="_abpms_form" value="request">

                        <div class="grid gap-3 sm:grid-cols-2">
                            <select class="ab-input" name="budget_allocation_id" required><option value="">Budget allocation</option>@foreach($allocations as $allocation)<option value="{{ $allocation->id }}">{{ $allocation->reference }} · {{ $allocation->program_code }}</option>@endforeach</select>
                            <input class="ab-input" name="requesting_office" placeholder="Requesting office" required>
                        </div>
                        <input class="ab-input" name="purpose" placeholder="Purpose" required>
                        <textarea class="ab-input" name="justification" rows="2" placeholder="Justification"></textarea>
                        <input class="ab-input" name="idempotency_key" placeholder="Optional retry key (recommended for API/retry clients)">
                        <div class="rounded-xl border border-sage bg-off-white p-3">
                            <div class="mb-2 flex items-center justify-between"><b class="text-sm text-forest-green">Requested item</b><button type="button" class="ab-btn ab-muted text-xs" id="add-procurement-item">Add item</button></div>
                            <div id="procurement-items" class="space-y-2">
                <div class="procurement-item grid gap-2 md:grid-cols-5">
                                    <select class="ab-input" name="items[0][item_type]" data-item-type-select required><option value="supply">AISMS supply</option><option value="equipment">Equipment asset</option></select>
                                    <select class="ab-input" name="items[0][supply_item_id]" data-supply-select required><option value="">Select AISMS supply</option>@foreach($supplyItems as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>
                                    <input class="ab-input" name="items[0][description]" placeholder="Item description" required>
                                    <input class="ab-input" name="items[0][quantity]" type="number" min=".01" step=".01" placeholder="Quantity" required>
                                    <input class="ab-input" name="items[0][estimated_unit_price]" type="number" min="0" step=".01" placeholder="Unit price" required>
                                </div>
                            </div>
                        </div>
                        <button class="ab-btn ab-primary w-full" type="submit">Submit procurement request</button>
                    </form></div></details></section>
            </div>
        @endif

        <section class="ab-card p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 class="text-lg font-bold text-forest-green">Procurement requests</h2><p class="mt-1 text-sm text-slate-500">Review state changes are recorded with the authenticated personnel account.</p></div><form method="GET" action="{{ route('modules.abpms.dashboard') }}" class="flex flex-wrap gap-2"><input class="ab-input !w-48" name="request_search" value="{{ request('request_search') }}" placeholder="Search requests"><select class="ab-input !w-auto" name="request_status"><option value="">All request status</option>@foreach(['submitted','under_review','approved','rejected'] as $requestStatus)<option value="{{ $requestStatus }}" @selected(request('request_status') === $requestStatus)>{{ str_replace('_', ' ', ucfirst($requestStatus)) }}</option>@endforeach</select><button class="ab-btn ab-muted" type="submit">Filter</button></form><span class="ab-pill ab-green">{{ $requests->total() }} records</span></div>
            <div class="ab-table-wrap mt-5"><table class="ab-table"><thead><tr><th>Request</th><th>Requester / office</th><th>Items</th><th>Total</th><th>Status</th><th>Review</th></tr></thead><tbody>
                @forelse($requests as $procurementRequest)
                    <tr>
                        <td><b class="text-forest-green">{{ $procurementRequest->request_number }}</b><div class="mt-1 text-xs text-slate-500">{{ $procurementRequest->created_at?->format('M d, Y H:i') }}</div><div class="mt-1 max-w-xs text-xs text-slate-500">{{ $procurementRequest->purpose }}</div></td>
                        <td>{{ $procurementRequest->requester?->name ?: 'Unknown' }}<div class="text-xs text-slate-500">{{ $procurementRequest->requesting_office }}</div></td>
                        <td><ul class="space-y-1">@foreach($procurementRequest->items as $item)<li><span class="ab-pill {{ $item->item_type === 'equipment' ? 'ab-blue' : 'ab-green' }}">{{ ucfirst($item->item_type) }}</span> {{ $item->description }} · {{ $item->quantity }} × ₱{{ $money($item->estimated_unit_price) }}</li>@endforeach</ul></td>
                        <td>₱{{ $money($procurementRequest->estimated_total) }}</td>
                        <td><span class="ab-pill {{ $statusClass($procurementRequest->status) }}">{{ str_replace('_', ' ', $procurementRequest->status) }}</span>@if($procurementRequest->review_notes)<div class="mt-2 max-w-xs text-xs text-slate-500">Note: {{ $procurementRequest->review_notes }}</div>@endif</td>
                        <td class="min-w-[250px]">
                            @if(($canReview || $canApprove || $canReject) && in_array($procurementRequest->status, ['submitted', 'under_review'], true))
                                <div class="flex flex-wrap gap-2">
                                    @if($canReview)<form method="POST" action="{{ route('modules.abpms.requests.review', $procurementRequest) }}">@csrf<input type="hidden" name="status" value="under_review"><button class="ab-btn ab-muted" type="submit">Review</button></form>@endif
                                    @if($canApprove)<form method="POST" action="{{ route('modules.abpms.requests.review', $procurementRequest) }}">@csrf<input type="hidden" name="status" value="approved"><button class="ab-btn ab-primary" type="submit">Approve</button></form>@endif
                                </div>
                                @if($canReject)<form method="POST" action="{{ route('modules.abpms.requests.review', $procurementRequest) }}" class="mt-2 space-y-2">@csrf<input type="hidden" name="status" value="rejected"><textarea class="ab-input" name="review_notes" rows="2" placeholder="Required rejection reason" required></textarea><button class="ab-btn ab-danger" type="submit">Reject request</button></form>@endif
                            @elseif($procurementRequest->status === 'approved' && $procurementRequest->purchaseOrders->isNotEmpty())
                                <span class="ab-pill ab-blue">Purchase order exists</span><div class="mt-1 text-xs text-slate-500">{{ $procurementRequest->purchaseOrders->first()->purchase_order_number }}</div>
                            @elseif($canCreate && $procurementRequest->status === 'approved')
                                <details class="ab-row-disclosure" @if(old('_abpms_form') === 'purchase_order' && (string) old('procurement_request_id') === (string) $procurementRequest->id) open @endif>
    <summary class="ab-btn ab-gold">Set up purchase order</summary>
    <div class="ab-row-disclosure-body">
        <form method="POST" action="{{ route('modules.abpms.purchase-orders.store', $procurementRequest) }}" class="space-y-2">
            @csrf
            <input type="hidden" name="_abpms_form" value="purchase_order"><input type="hidden" name="procurement_request_id" value="{{ $procurementRequest->id }}">
            <label class="ab-label">Supplier<select class="ab-input" name="supplier_id" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label>
            <label class="ab-label">Expected delivery<input class="ab-input" name="expected_delivery_on" type="date"></label>
            <label class="ab-label">Retry key <span class="font-normal normal-case tracking-normal">(optional)</span><input class="ab-input" name="idempotency_key" placeholder="Enter a unique retry key"></label>
            <label class="ab-label">Purchase order notes<textarea class="ab-input" name="notes" rows="2"></textarea></label>
            <button class="ab-btn ab-primary" type="submit">Create purchase order</button>
        </form>
    </div>
</details>
                            @else
                                <span class="text-xs text-slate-500">No action available for this state.</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">No procurement requests have been recorded.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $requests->links() }}</div>
        </section>

            </section>
            <section id="abpms-panel-orders" class="erp-workspace-panel space-y-5" role="tabpanel" aria-labelledby="abpms-tab-orders" tabindex="0" data-erp-workspace-panel="orders" hidden>
        <section class="ab-card p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 class="text-lg font-bold text-forest-green">Purchase orders &amp; receiving</h2><p class="mt-1 text-sm text-slate-500">Approval commits budget. Inventory changes only when goods are physically received.</p></div><form method="GET" action="{{ route('modules.abpms.dashboard') }}" class="flex flex-wrap gap-2"><input class="ab-input !w-48" name="order_search" value="{{ request('order_search') }}" placeholder="Search purchase orders"><select class="ab-input !w-auto" name="order_status"><option value="">All order status</option>@foreach(['draft','approved','ordered','partially_received','received','cancelled'] as $orderStatus)<option value="{{ $orderStatus }}" @selected(request('order_status') === $orderStatus)>{{ str_replace('_', ' ', ucfirst($orderStatus)) }}</option>@endforeach</select><button class="ab-btn ab-muted" type="submit">Filter</button></form><span class="ab-pill ab-blue">{{ $purchaseOrders->total() }} orders</span></div>
            @if($canUpdate && $receivableItems->isNotEmpty())
                <div class="mt-5 rounded-xl border border-sage bg-sage p-4">
                    <h3 class="font-bold text-forest-green">Record goods receipt</h3>
                    <p class="mt-1 text-xs text-slate-600">For AISMS supplies, select a batch and inventory location. The existing InventoryService records the stock movement exactly once per idempotency key.</p>
                    <form method="POST" action="{{ route('modules.abpms.receipts.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">
                        @csrf
                        <select class="ab-input md:col-span-2" name="purchase_order_item_id" data-receive-item required><option value="">Purchase order item</option>@foreach($receivableItems as $item)<option value="{{ $item->id }}" data-item-type="{{ $item->item_type }}">{{ $item->purchaseOrder?->purchase_order_number }} · {{ ucfirst($item->item_type) }} · {{ $item->description }} · remaining {{ number_format((float)$item->ordered_quantity - (float)$item->received_quantity, 2) }}</option>@endforeach</select>
                        <input class="ab-input" name="quantity" type="number" min=".01" step=".01" placeholder="Received quantity" required>
                        <input class="ab-input" name="received_on" type="date" value="{{ now()->toDateString() }}" required>
                        <input class="ab-input" name="batch_number" data-receive-supply-field placeholder="AISMS batch number">
                        <input class="ab-input" name="expires_on" type="date" placeholder="Expiration date">
                        <select class="ab-input" name="location_id" data-receive-supply-field><option value="">AISMS inventory location</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input class="ab-input md:col-span-3" name="notes" placeholder="Receiving notes">
                        <button class="ab-btn ab-primary md:col-span-3" type="submit">Record physical receipt</button>
                    </form>
                </div>
            @elseif($canUpdate)
                <div class="mt-5 rounded-xl bg-off-white p-4 text-sm text-slate-500">There are no approved purchase-order items awaiting receipt.</div>
            @endif
            <div class="ab-table-wrap mt-5"><table class="ab-table"><thead><tr><th>Purchase order</th><th>Supplier</th><th>Value</th><th>Status</th><th>Items / asset integration</th><th>Actions</th></tr></thead><tbody>
                @forelse($purchaseOrders as $purchaseOrder)
                    <tr>
                        <td><b class="text-forest-green">{{ $purchaseOrder->purchase_order_number }}</b><div class="text-xs text-slate-500">{{ $purchaseOrder->request?->request_number }}</div></td>
                        <td>{{ $purchaseOrder->supplier?->name }}</td><td>₱{{ $money($purchaseOrder->total_amount) }}</td>
                        <td><span class="ab-pill {{ $statusClass($purchaseOrder->status) }}">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span></td>
                        <td>
                            <ul class="space-y-2">
                                @foreach($purchaseOrder->items as $item)
                                    <li>
                                        <b>{{ $item->description }}</b>
                                        <div class="text-xs text-slate-500">{{ $item->received_quantity }} / {{ $item->ordered_quantity }} received @if($item->equipmentAsset) · linked to {{ $item->equipmentAsset->asset_code }} @endif</div>
                                        @if($item->equipmentAcquisitions->isNotEmpty())
                                            <div class="mt-2 space-y-1 text-xs text-emerald-800">@foreach($item->equipmentAcquisitions as $acquisition)@foreach($acquisition->assets as $acquiredAsset)<div>{{ $acquiredAsset->equipmentAsset?->asset_code }} · {{ $acquiredAsset->equipmentAsset?->name }} · ₱{{ $money($acquisition->unit_acquisition_cost) }}</div>@endforeach @endforeach</div>
                                        @endif
                                        @if($canLinkEquipment && $item->item_type === 'equipment' && $item->equipment_asset_id === null && $item->equipmentAcquisitions->isEmpty() && (float)$item->ordered_quantity === 1.0 && (float)$item->received_quantity >= (float)$item->ordered_quantity && $equipmentAssets->isNotEmpty())
                                            <form method="POST" action="{{ route('modules.abpms.purchase-order-items.link-equipment', $item) }}" class="mt-2 flex gap-2">
                                                @csrf
                                                <select class="ab-input" name="equipment_asset_id" required><option value="">Link existing AEAMS asset</option>@foreach($equipmentAssets as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} · {{ $asset->name }}</option>@endforeach</select>
                                                <button class="ab-btn ab-muted" type="submit">Link</button>
                                            </form>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="min-w-[180px]">@if($canApprove && $purchaseOrder->status === 'draft')<form method="POST" action="{{ route('modules.abpms.purchase-orders.approve', $purchaseOrder) }}">@csrf<button class="ab-btn ab-primary" type="submit">Approve &amp; commit</button></form>@endif @if($canApprove && $purchaseOrder->status === 'approved')<form method="POST" action="{{ route('modules.abpms.purchase-orders.order', $purchaseOrder) }}" class="mt-2">@csrf<button class="ab-btn ab-gold" type="submit">Mark ordered</button></form>@endif @if($canApprove && in_array($purchaseOrder->status, ['draft','approved','ordered'], true))<form method="POST" action="{{ route('modules.abpms.purchase-orders.cancel', $purchaseOrder) }}" class="mt-2">@csrf<button class="ab-btn ab-danger" type="submit">Cancel order</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">No purchase orders have been created.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $purchaseOrders->links() }}</div>
        </section>

            </section>
            <section id="abpms-panel-equipment" class="erp-workspace-panel space-y-5" role="tabpanel" aria-labelledby="abpms-tab-equipment" tabindex="0" data-erp-workspace-panel="equipment" hidden>
        @if($canAcquireEquipment)
            <section class="ab-card p-5">
                <div><h2 class="text-lg font-bold text-forest-green">Register equipment from a physical receipt</h2><p class="mt-1 text-sm text-slate-500">Choose only a received equipment line. Each unit becomes an individual AEAMS asset with its own identifier and shared PO, receipt, date, and per-unit cost references.</p></div>
                @if($equipmentReceiptItems->isNotEmpty())
                    <form method="POST" action="{{ route('modules.abpms.receipts.equipment-assets.store', ['procurementReceiptItem' => '__receipt__']) }}" data-equipment-acquisition-form class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @csrf
                        <select class="ab-input sm:col-span-2" name="procurement_receipt_item_id" data-acquisition-receipt required aria-label="Equipment receipt">
                            <option value="">Select physical equipment receipt</option>
                            @foreach($equipmentReceiptItems as $receiptItem)
                                @php($registeredUnits = (int) $receiptItem->purchaseOrderItem->equipmentAcquisitions->where('procurement_receipt_item_id', $receiptItem->id)->sum('quantity'))
                                @php($availableUnits = max(0, (int) floor((float) $receiptItem->quantity) - $registeredUnits))
                                <option value="{{ $receiptItem->id }}" data-action="{{ route('modules.abpms.receipts.equipment-assets.store', $receiptItem) }}" data-available-units="{{ $availableUnits }}">
                                    {{ $receiptItem->receipt?->receipt_reference }} · {{ $receiptItem->purchaseOrderItem?->purchaseOrder?->purchase_order_number }} · {{ $receiptItem->purchaseOrderItem?->description }} · {{ $availableUnits }} unit(s) not registered
                                </option>
                            @endforeach
                        </select>
                        <select class="ab-input" name="category_id" required aria-label="Equipment category"><option value="">Asset category</option>@foreach($equipmentCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                        <input class="ab-input" type="number" name="quantity" min="1" max="1" value="1" data-acquisition-quantity required aria-label="Number of units to register">
                        <input class="ab-input sm:col-span-2" name="asset_name" maxlength="150" placeholder="Asset name or naming prefix" required>
                        <select class="ab-input" name="condition" required aria-label="Initial condition"><option value="good">Good condition</option><option value="fair">Fair condition</option><option value="damaged">Damaged condition</option></select>
                        <input class="ab-input" name="assigned_location" maxlength="150" placeholder="Assigned location (optional)">
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button class="ab-btn ab-primary sm:col-span-2 xl:col-span-4" type="submit">Create AEAMS asset record(s)</button>
                    </form>
                @else
                    <div class="mt-4 rounded-xl bg-off-white p-4 text-sm text-slate-500">No unregistered equipment units have a physical goods receipt.</div>
                @endif
            </section>
        @endif

        <section class="ab-card p-5">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold text-forest-green">Equipment acquisition register</h2><p class="mt-1 text-sm text-slate-500">Each listed asset traces to its procurement line and physical receipt.</p></div><span class="ab-pill ab-blue">{{ $equipmentAcquisitions->total() }} linked asset(s)</span></div>
            <div class="ab-table-wrap mt-4"><table class="ab-table"><thead><tr><th>AEAMS asset</th><th>PO / physical receipt</th><th>Acquired on</th><th>Unit cost</th><th>Recorded by</th></tr></thead><tbody>
                @forelse($equipmentAcquisitions as $assetLink)
                    @php($acquisition = $assetLink->acquisition)
                    @php($asset = $assetLink->equipmentAsset)
                    <tr><td><b class="text-forest-green">{{ $asset?->asset_code }}</b><div>{{ $asset?->name }}</div><div class="text-xs text-slate-500">{{ $asset?->category?->name }} · {{ ucfirst($asset?->condition ?? 'unknown') }} · {{ $asset?->assigned_location ?: 'No assigned location' }}</div></td><td><b>{{ $acquisition?->purchaseOrderItem?->purchaseOrder?->purchase_order_number }}</b><div class="text-xs text-slate-500">{{ $acquisition?->receiptItem?->receipt?->receipt_reference }}</div></td><td>{{ $acquisition?->acquired_on?->format('M d, Y') }}</td><td>₱{{ $money($acquisition?->unit_acquisition_cost ?? 0) }}</td><td>{{ $acquisition?->creator?->name ?: 'Unknown' }}</td></tr>
                @empty<tr><td colspan="5" class="py-8 text-center text-slate-500">No procurement-linked AEAMS assets yet.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $equipmentAcquisitions->links() }}</div>
        </section>

            </section>
            <section id="abpms-panel-reports" class="erp-workspace-panel space-y-5" role="tabpanel" aria-labelledby="abpms-tab-reports" tabindex="0" data-erp-workspace-panel="reports" hidden>
        @if($canExport)
            <section class="ab-card flex flex-wrap items-center justify-between gap-4 p-5"><div><p class="text-xs font-bold uppercase tracking-wider text-emerald-green">Operational reports</p><h2 class="mt-1 text-lg font-bold text-forest-green">Budget, procurement and receiving exports</h2><p class="mt-1 text-sm text-slate-500">Download the existing report for the workflow you need to reconcile.</p></div><div class="flex flex-wrap gap-2"><a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'budget']) }}">Budget</a><a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'requests']) }}">Requests</a><a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'orders']) }}">Orders</a><a class="ab-btn ab-muted" href="{{ route('modules.abpms.export', ['report' => 'receipts']) }}">Receiving</a><a class="ab-btn ab-gold" href="{{ route('modules.abpms.export', ['report' => 'equipment']) }}">Equipment acquisitions</a></div></section>
        @endif
        <div class="ab-two grid grid-cols-2 gap-5">
            <section class="ab-card p-5">
                <h2 class="text-lg font-bold text-forest-green">Supplier directory</h2>
                <div class="ab-table-wrap mt-4"><table class="ab-table"><thead><tr><th>Supplier</th><th>Contact</th><th>Status</th></tr></thead><tbody>@forelse($suppliers as $supplier)<tr><td><b>{{ $supplier->name }}</b><div class="text-xs text-slate-500">{{ $supplier->registration_reference ?: 'No registration reference' }}</div></td><td>{{ $supplier->contact_person ?: '—' }}<div class="text-xs text-slate-500">{{ $supplier->email ?: $supplier->phone ?: 'No contact details' }}</div></td><td><span class="ab-pill {{ $supplier->is_active ? 'ab-green' : 'ab-gray' }}">{{ $supplier->is_active ? 'active' : 'inactive' }}</span></td></tr>@empty<tr><td colspan="3" class="py-8 text-center text-slate-500">No suppliers registered.</td></tr>@endforelse</tbody></table></div>
            </section>
            <section class="ab-card p-5">
                <h2 class="text-lg font-bold text-forest-green">Budget ledger history</h2>
                <div class="ab-table-wrap mt-4"><table class="ab-table"><thead><tr><th>Reference</th><th>Entry</th><th>Amount</th><th>Personnel</th></tr></thead><tbody>@forelse($transactions as $transaction)<tr><td><b>{{ $transaction->reference }}</b><div class="text-xs text-slate-500">{{ $transaction->created_at?->format('M d, Y H:i') }}</div></td><td>{{ str_replace('_', ' ', $transaction->entry_type) }}<div class="text-xs text-slate-500">{{ $transaction->description }}</div></td><td class="{{ $transaction->direction === 'debit' ? 'text-red-600' : 'text-emerald-green' }}">{{ $transaction->direction === 'debit' ? '-' : '+' }}₱{{ $money($transaction->amount) }}</td><td>{{ $transaction->performer?->name ?: '—' }}</td></tr>@empty<tr><td colspan="4" class="py-8 text-center text-slate-500">No budget transactions recorded.</td></tr>@endforelse</tbody></table></div>
                <div class="mt-4">{{ $transactions->links() }}</div>
            </section>
        </div>

        <section class="ab-card p-5">
            <div class="flex items-end justify-between"><div><h2 class="text-lg font-bold text-forest-green">Goods receipt history</h2><p class="mt-1 text-sm text-slate-500">Receipt records connect procurement to AISMS movement references where the item is an agricultural supply.</p></div><span class="ab-pill ab-blue">{{ $receipts->total() }} receipts</span></div>
            <div class="ab-table-wrap mt-4"><table class="ab-table"><thead><tr><th>Receipt</th><th>Purchase order</th><th>Received by</th><th>Items</th></tr></thead><tbody>@forelse($receipts as $receipt)<tr><td><b>{{ $receipt->receipt_reference }}</b><div class="text-xs text-slate-500">{{ $receipt->received_on?->format('M d, Y') }}</div></td><td>{{ $receipt->purchaseOrder?->purchase_order_number }}</td><td>{{ $receipt->receiver?->name }}</td><td><ul>@foreach($receipt->items as $item)<li>{{ $item->quantity }} · {{ $item->batch_number ?: 'No batch' }}@if($item->inventory_movement_id) <span class="text-emerald-green">· AISMS movement #{{ $item->inventory_movement_id }}</span>@endif</li>@endforeach</ul></td></tr>@empty<tr><td colspan="4" class="py-8 text-center text-slate-500">No physical receipts recorded.</td></tr>@endforelse</tbody></table></div>
            <div class="mt-4">{{ $receipts->links() }}</div>
        </section>
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const addButton = document.getElementById('add-procurement-item');
            const items = document.getElementById('procurement-items');
            const configureItemRow = function (row) {
                const type = row.querySelector('[data-item-type-select]');
                const supply = row.querySelector('[data-supply-select]');
                const quantity = row.querySelector('input[name$="[quantity]"]');
                const updateType = function () {
                    const isSupply = type.value === 'supply';
                    supply.required = isSupply;
                    supply.disabled = !isSupply;
                    supply.classList.toggle('hidden', !isSupply);
                    if (!isSupply) supply.value = '';
                    quantity.step = isSupply ? '.01' : '1';
                    quantity.min = isSupply ? '.01' : '1';
                };
                type.addEventListener('change', updateType);
                updateType();
            };
            if (items) {
                items.querySelectorAll('.procurement-item').forEach(configureItemRow);
            }
            if (addButton && items) {
                addButton.addEventListener('click', function () {
                    const index = items.querySelectorAll('.procurement-item').length;
                    const row = items.querySelector('.procurement-item').cloneNode(true);
                    row.querySelectorAll('input, select').forEach(function (field) {
                        field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
                        field.value = '';
                    });
                    row.querySelector('[data-item-type-select]').value = 'supply';
                    configureItemRow(row);
                    items.appendChild(row);
                });
            }

            const receiveItem = document.querySelector('[data-receive-item]');
            if (receiveItem) {
                const supplyFields = document.querySelectorAll('[data-receive-supply-field]');
                const configureReceipt = function () {
                    const isSupply = receiveItem.options[receiveItem.selectedIndex]?.dataset.itemType === 'supply';
                    supplyFields.forEach(function (field) {
                        field.required = isSupply;
                        field.disabled = !isSupply;
                        field.classList.toggle('hidden', !isSupply);
                        if (!isSupply) field.value = '';
                    });
                };
                receiveItem.addEventListener('change', configureReceipt);
                configureReceipt();
            }

            const acquisitionForm = document.querySelector('[data-equipment-acquisition-form]');
            const receiptSelect = document.querySelector('[data-acquisition-receipt]');
            const acquisitionQuantity = document.querySelector('[data-acquisition-quantity]');
            if (acquisitionForm && receiptSelect && acquisitionQuantity) {
                const configureAcquisition = function () {
                    const option = receiptSelect.options[receiptSelect.selectedIndex];
                    acquisitionForm.action = option?.dataset.action || acquisitionForm.action;
                    const maxUnits = option?.dataset.availableUnits || '1';
                    acquisitionQuantity.max = maxUnits;
                    acquisitionQuantity.value = Math.min(Number(acquisitionQuantity.value || 1), Number(maxUnits));
                };
                receiptSelect.addEventListener('change', configureAcquisition);
                configureAcquisition();
            }
        });
    </script>
</x-layouts.app>
