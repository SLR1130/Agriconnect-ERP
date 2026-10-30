<x-layouts.app>
    <x-slot name="header">Agricultural Equipment &amp; Asset Management</x-slot>
    <x-slot name="description">Manage shared equipment, farmer reservations, issue and return records, and maintenance.</x-slot>

    <style>
        .ae-card{background:#fff;border:1px solid #dfe9e1;border-radius:16px;box-shadow:0 5px 18px #153d2b0a}
        .ae-input{width:100%;min-height:2.65rem;border:1px solid #cbd9cf;border-radius:9px;padding:.6rem .7rem;background:#fff;color:#26382f;font-size:.78rem}
        .ae-input:focus{outline:3px solid #26865c55;outline-offset:1px;border-color:#26865c}
        .ae-btn{display:inline-flex;min-height:2.4rem;align-items:center;justify-content:center;border-radius:9px;padding:.55rem .8rem;font-size:.74rem;font-weight:750;text-align:center}
        .ae-primary{background:#153d2b;color:#fff}.ae-gold{background:#d5ad55;color:#153d2b}.ae-muted{background:#e7f0e8;color:#153d2b}.ae-danger{background:#fee7e5;color:#9f3b36}
        .ae-table-wrap{width:100%;overflow-x:auto;border:1px solid #e7eee8;border-radius:12px;scrollbar-width:thin}
        .ae-table{width:100%;min-width:760px;border-collapse:collapse}.ae-table th{padding:.7rem;text-align:left;color:#718177;font-size:.64rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;border-bottom:1px solid #e1e9e2;white-space:nowrap}.ae-table td{padding:.7rem;color:#405048;font-size:.74rem;border-bottom:1px solid #edf2ee;vertical-align:top}
        .ae-pill{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .55rem;font-size:.65rem;font-weight:800;text-transform:capitalize;white-space:nowrap}.ae-green{background:#e7f0e8;color:#1c6a46}.ae-yellow{background:#fff5d8;color:#8a691e}.ae-red{background:#fee7e5;color:#a33e36}.ae-blue{background:#e7f0fb;color:#28618e}.ae-gray{background:#eef2f0;color:#58685e}
        .ae-disclosure{padding:.85rem;background:#f8faf7;border:1px solid #dfe9e1;border-radius:.75rem}.ae-disclosure>summary{list-style:none;cursor:pointer;color:#1c6a46;font-size:.75rem;font-weight:800}.ae-disclosure>summary::-webkit-details-marker{display:none}.ae-disclosure>summary:focus-visible{outline:3px solid #26865c88;outline-offset:2px}
        .ae-label{display:block;margin-bottom:.3rem;color:#53645a;font-size:.67rem;font-weight:750}.ae-stat{padding:.85rem}.ae-stat p{margin:0;color:#718177;font-size:.63rem;font-weight:800;letter-spacing:.03em;text-transform:uppercase}.ae-stat strong{display:block;margin-top:.35rem;font-size:1.35rem;line-height:1.2}
        @media(max-width:640px){.ae-stat{padding:.7rem}.ae-stat strong{font-size:1.15rem}.ae-table{min-width:760px}}
    </style>

    <div class="space-y-5">
        @if(session('status'))<div class="ae-card border-l-4 border-emerald-600 px-5 py-4 text-sm text-emerald-800" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="ae-card border-l-4 border-red-500 px-5 py-4 text-sm text-red-700" role="alert" tabindex="-1"><b>Equipment workflow needs attention.</b><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['label' => 'Equipment assets', 'value' => $stats['assets'], 'class' => 'text-forest-green'],
                ['label' => 'Available', 'value' => $stats['available'], 'class' => 'text-emerald-green'],
                ['label' => 'Pending requests', 'value' => $stats['pending'], 'class' => 'text-agricultural-gold'],
                ['label' => 'Reserved requests', 'value' => $stats['reserved'], 'class' => 'text-blue-700'],
                ['label' => 'Issued', 'value' => $stats['issued'], 'class' => 'text-emerald-green'],
                ['label' => 'Maintenance', 'value' => $stats['maintenance'], 'class' => 'text-red-600'],
            ] as $stat)
                <article class="ae-card ae-stat"><p>{{ $stat['label'] }}</p><strong class="{{ $stat['class'] }}">{{ $stat['value'] }}</strong></article>
            @endforeach
        </div>

        <div class="erp-workspace" data-erp-workspace data-workspace-param="workspace" data-workspace-default="{{ $isFarmer ? 'requests' : 'inventory' }}" data-workspace-initial="{{ old('_erp_workspace', old('_aeams_workspace', request('workspace', $isFarmer ? 'requests' : 'inventory'))) }}">
            <div class="erp-workspace-tabs" role="tablist" aria-label="Equipment management workspaces">
                <button id="aeams-tab-inventory" class="erp-workspace-tab" type="button" role="tab" aria-selected="true" aria-controls="aeams-panel-inventory" data-erp-workspace-tab="inventory">Equipment inventory</button>
                <button id="aeams-tab-requests" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="aeams-panel-requests" data-erp-workspace-tab="requests">Requests &amp; returns <span class="ae-pill ae-yellow">{{ $stats['pending'] }}</span></button>
                @if($canMaintenance || ($isStaff && $canExport))
                    <button id="aeams-tab-maintenance" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="aeams-panel-maintenance" data-erp-workspace-tab="maintenance">Maintenance &amp; reports</button>
                @endif
            </div>

            <section id="aeams-panel-inventory" class="erp-workspace-panel space-y-4" role="tabpanel" aria-labelledby="aeams-tab-inventory" tabindex="0" data-erp-workspace-panel="inventory">
                @if($canCreate)
                    <section class="ae-card p-5"><details class="ae-disclosure" @if(old('_aeams_workspace') === 'inventory' && old('_aeams_form') === 'asset') open @endif>
                        <summary>Register equipment asset</summary><p class="mt-1 text-xs text-slate-500">Create one authoritative asset record for each managed item.</p>
                        <form method="POST" action="{{ route('modules.aeams.equipment.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="_aeams_workspace" value="inventory"><input type="hidden" name="_aeams_form" value="asset">
                            <div><label class="ae-label" for="aeams-asset-code">Asset code</label><input id="aeams-asset-code" class="ae-input" name="asset_code" value="{{ old('_aeams_form') === 'asset' ? old('asset_code') : '' }}" required></div>
                            <div><label class="ae-label" for="aeams-asset-name">Equipment name</label><input id="aeams-asset-name" class="ae-input" name="name" value="{{ old('_aeams_form') === 'asset' ? old('name') : '' }}" required></div>
                            <div><label class="ae-label" for="aeams-category">Category</label><select id="aeams-category" class="ae-input" name="category_id" required><option value="">Select category</option>@foreach($equipmentCategories as $category)<option value="{{ $category->id }}" @selected(old('_aeams_form') === 'asset' && old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                            <div><label class="ae-label" for="aeams-condition">Condition</label><select id="aeams-condition" class="ae-input" name="condition" required><option value="good">Good</option><option value="fair" @selected(old('condition') === 'fair' && old('_aeams_form') === 'asset')>Fair</option><option value="damaged" @selected(old('condition') === 'damaged' && old('_aeams_form') === 'asset')>Damaged</option></select></div>
                            <div><label class="ae-label" for="aeams-acquired-on">Acquisition date</label><input id="aeams-acquired-on" class="ae-input" name="acquired_on" type="date" value="{{ old('_aeams_form') === 'asset' ? old('acquired_on') : '' }}"></div>
                            <div><label class="ae-label" for="aeams-acquisition-cost">Acquisition cost</label><input id="aeams-acquisition-cost" class="ae-input" name="acquisition_cost" type="number" min="0" step=".01" value="{{ old('_aeams_form') === 'asset' ? old('acquisition_cost') : '' }}"></div>
                            <div><label class="ae-label" for="aeams-location">Assigned location</label><input id="aeams-location" class="ae-input" name="assigned_location" value="{{ old('_aeams_form') === 'asset' ? old('assigned_location') : '' }}"></div>
                            <div><label class="ae-label" for="aeams-responsible">Responsible MAO personnel</label><select id="aeams-responsible" class="ae-input" name="responsible_user_id"><option value="">Not assigned</option>@foreach($personnelUsers as $personnelUser)<option value="{{ $personnelUser->id }}" @selected(old('_aeams_form') === 'asset' && old('responsible_user_id') == $personnelUser->id)>{{ $personnelUser->name }}</option>@endforeach</select></div>
                            <div class="sm:col-span-2"><label class="ae-label" for="aeams-description">Description</label><textarea id="aeams-description" class="ae-input" name="description" rows="2">{{ old('_aeams_form') === 'asset' ? old('description') : '' }}</textarea></div>
                            <button class="ae-btn ae-primary sm:col-span-2" type="submit">Register asset</button>
                        </form>
                    </details></section>
                @endif

                <section class="ae-card p-5">
                    <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold text-forest-green">{{ $isStaff ? 'Equipment catalog' : 'Available equipment' }}</h2><p class="mt-1 text-sm text-slate-500">Search the shared asset register and current availability.</p></div><span class="ae-pill ae-blue">{{ $assets->total() }} records</span></div>
                    <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,1.4fr)_minmax(10rem,.8fr)_minmax(10rem,.8fr)_auto]" aria-label="Filter equipment assets">
                        <input type="hidden" name="workspace" value="inventory">
                        <div><label class="ae-label" for="aeams-asset-search">Search inventory</label><input id="aeams-asset-search" class="ae-input" name="search" value="{{ request('search') }}" placeholder="Code, name, or location"></div>
                        <div><label class="ae-label" for="aeams-asset-category-filter">Category</label><select id="aeams-asset-category-filter" class="ae-input" name="category_id"><option value="">All categories</option>@foreach($equipmentCategories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                        <div><label class="ae-label" for="aeams-asset-status-filter">Availability</label><select id="aeams-asset-status-filter" class="ae-input" name="status"><option value="">All availability</option>@foreach(['available'=>'Available','issued'=>'Issued','maintenance'=>'Maintenance','archived'=>'Archived'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="flex items-end gap-2"><button class="ae-btn ae-primary" type="submit">Apply filters</button><a class="ae-btn ae-muted" href="{{ route('modules.aeams.dashboard', ['workspace' => 'inventory']) }}">Clear</a></div>
                    </form>
                    <div class="ae-table-wrap mt-4" tabindex="0" role="region" aria-label="Scrollable equipment catalog">
                        <table class="ae-table"><thead><tr><th>Asset</th><th>Category</th><th>Condition</th><th>Location</th><th>Availability</th>@if($canMaintenance)<th>Maintenance</th>@endif</tr></thead><tbody>
                            @forelse($assets as $asset)
                                <tr><td><a href="{{ route('modules.aeams.equipment.show', $asset) }}" class="font-bold text-forest-green hover:text-emerald-green">{{ $asset->name }}</a><div class="text-xs text-slate-500">{{ $asset->asset_code }}</div></td><td>{{ $asset->category->name }}</td><td>{{ ucfirst($asset->condition) }}</td><td>{{ $asset->assigned_location ?: 'Unassigned' }}</td><td><span class="ae-pill {{ $asset->availability_status === 'available' ? 'ae-green' : ($asset->availability_status === 'maintenance' ? 'ae-red' : ($asset->availability_status === 'archived' ? 'ae-gray' : 'ae-yellow')) }}">{{ ucfirst(str_replace('_', ' ', $asset->availability_status)) }}</span></td>@if($canMaintenance)<td>@if($asset->maintenances->isNotEmpty())<span class="ae-pill ae-red">Open maintenance</span>@elseif($asset->availability_status !== 'issued' && $asset->availability_status !== 'archived')<details class="ae-disclosure min-w-[190px]"><summary>Record maintenance</summary><form method="POST" action="{{ route('modules.aeams.maintenance.store', $asset) }}" class="mt-3 space-y-2">@csrf<input type="hidden" name="_aeams_workspace" value="maintenance"><label class="ae-label" for="aeams-maintenance-{{ $asset->id }}">Maintenance issue</label><input id="aeams-maintenance-{{ $asset->id }}" class="ae-input" name="description" required><label class="ae-label" for="aeams-maintenance-cost-{{ $asset->id }}">Estimated cost</label><input id="aeams-maintenance-cost-{{ $asset->id }}" class="ae-input" name="cost" type="number" min="0" step=".01"><button class="ae-btn ae-muted w-full" type="submit">Start maintenance</button></form></details>@else<span class="text-xs text-slate-500">Unavailable</span>@endif</td>@endif</tr>
                            @empty
                                <tr><td colspan="{{ $canMaintenance ? 6 : 5 }}" class="py-8 text-center text-slate-500">No equipment matches these filters.</td></tr>
                            @endforelse
                        </tbody></table>
                    </div>
                    <div class="mt-4">{{ $assets->links() }}</div>
                </section>
            </section>

            <section id="aeams-panel-requests" class="erp-workspace-panel space-y-4" role="tabpanel" aria-labelledby="aeams-tab-requests" tabindex="0" data-erp-workspace-panel="requests" hidden>
                @if($isFarmer)
                    <section class="ae-card p-5"><details class="ae-disclosure" @if(old('_aeams_workspace') === 'requests' && old('_aeams_form') === 'request') open @endif>
                        <summary>Request equipment</summary><p class="mt-1 text-sm text-slate-500">Your request uses your verified FFRMS farmer record and is subject to MAO review and schedule conflict checks.</p>
                        <form method="POST" action="{{ route('modules.aeams.requests.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">@csrf<input type="hidden" name="_aeams_workspace" value="requests"><input type="hidden" name="_aeams_form" value="request">
                            <div><label class="ae-label" for="aeams-request-asset">Available equipment</label><select id="aeams-request-asset" class="ae-input" name="equipment_asset_id" required><option value="">Select equipment</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected(old('_aeams_form') === 'request' && old('equipment_asset_id') == $asset->id)>{{ $asset->asset_code }} · {{ $asset->name }} ({{ ucfirst($asset->condition) }})</option>@endforeach</select></div>
                            <div><label class="ae-label" for="aeams-request-key">Retry reference (optional)</label><input id="aeams-request-key" class="ae-input" name="idempotency_key" value="{{ old('_aeams_form') === 'request' ? old('idempotency_key') : '' }}"></div>
                            <div><label class="ae-label" for="aeams-schedule-start">Schedule starts</label><input id="aeams-schedule-start" class="ae-input" name="schedule_start" type="datetime-local" value="{{ old('_aeams_form') === 'request' ? old('schedule_start') : '' }}" required></div>
                            <div><label class="ae-label" for="aeams-schedule-end">Schedule ends</label><input id="aeams-schedule-end" class="ae-input" name="schedule_end" type="datetime-local" value="{{ old('_aeams_form') === 'request' ? old('schedule_end') : '' }}" required></div>
                            <div class="md:col-span-2"><label class="ae-label" for="aeams-intended-use">Intended agricultural use</label><textarea id="aeams-intended-use" class="ae-input" name="intended_use" rows="3" required>{{ old('_aeams_form') === 'request' ? old('intended_use') : '' }}</textarea></div>
                            <button class="ae-btn ae-primary md:col-span-2" type="submit">Submit equipment request</button>
                        </form>
                    </details></section>
                @endif

                <section class="ae-card p-5">
                    <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold text-forest-green">{{ $isStaff ? 'Requests, reservations & issued equipment' : 'My equipment requests' }}</h2><p class="mt-1 text-sm text-slate-500">Approved requests reserve a schedule; issued requests remain open until recorded return.</p></div><span class="ae-pill ae-blue">{{ $requests->total() }} records</span></div>
                    <form method="GET" class="mt-4 grid gap-3 sm:grid-cols-[minmax(12rem,1.3fr)_minmax(10rem,.8fr)_auto]" aria-label="Filter equipment requests">
                        <input type="hidden" name="workspace" value="requests">
                        <div><label class="ae-label" for="aeams-request-search">Search requests</label><input id="aeams-request-search" class="ae-input" name="request_search" value="{{ request('request_search') }}" placeholder="Reference, farmer, or equipment"></div>
                        <div><label class="ae-label" for="aeams-request-status">Request status</label><select id="aeams-request-status" class="ae-input" name="request_status"><option value="">All request states</option>@foreach(['pending'=>'Pending review','approved'=>'Approved · reserved','rejected'=>'Rejected','issued'=>'Issued','returned'=>'Returned','cancelled'=>'Cancelled'] as $value => $label)<option value="{{ $value }}" @selected(request('request_status') === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="flex items-end gap-2"><button class="ae-btn ae-primary" type="submit">Apply filters</button><a class="ae-btn ae-muted" href="{{ route('modules.aeams.dashboard', ['workspace' => 'requests']) }}">Clear</a></div>
                    </form>
                    <div class="ae-table-wrap mt-4" tabindex="0" role="region" aria-label="Scrollable equipment requests">
                        <table class="ae-table"><thead><tr><th>Reference</th><th>Farmer / equipment</th><th>Schedule</th><th>Status</th><th>Intended use &amp; notes</th>@if($canReview || $canIssue || $canReturn || $isFarmer)<th>Authorized action</th>@endif</tr></thead><tbody>
                            @forelse($requests as $equipmentRequest)
                                @php($requestStatusLabel = match($equipmentRequest->status) { 'approved' => 'Approved · reserved', default => ucfirst($equipmentRequest->status) })
                                <tr>
                                    <td><strong>{{ $equipmentRequest->reference }}</strong><div class="text-xs text-slate-500">{{ $equipmentRequest->created_at?->format('M d, Y H:i') }}</div></td>
                                    <td><strong>{{ $equipmentRequest->asset->name }}</strong><div class="text-xs text-slate-500">{{ $equipmentRequest->farmer->user->name }} · {{ $equipmentRequest->farmer->farmer_code }}</div></td>
                                    <td>{{ $equipmentRequest->schedule_start?->format('M d, Y H:i') }}<br><span class="text-xs text-slate-500">to {{ $equipmentRequest->schedule_end?->format('M d, Y H:i') }}</span></td>
                                    <td><span class="ae-pill {{ in_array($equipmentRequest->status, ['approved', 'returned']) ? 'ae-green' : (in_array($equipmentRequest->status, ['rejected','cancelled']) ? 'ae-red' : ($equipmentRequest->status === 'issued' ? 'ae-blue' : 'ae-yellow')) }}">{{ $requestStatusLabel }}</span></td>
                                    <td class="max-w-xs">{{ $equipmentRequest->intended_use }}@if($equipmentRequest->review_notes)<div class="mt-1 text-xs text-slate-500">Note: {{ $equipmentRequest->review_notes }}</div>@endif</td>
                                    @if($canReview || $canIssue || $canReturn || $isFarmer)
                                        <td class="min-w-[210px]">
                                            @if($isFarmer && in_array($equipmentRequest->status, ['pending', 'approved'], true))
                                                <form method="POST" action="{{ route('modules.aeams.requests.cancel', $equipmentRequest) }}" data-confirm-submit="Cancel this equipment request?">@csrf<button class="ae-btn ae-gold" type="submit">Cancel request</button></form>
                                            @elseif($canReview && $equipmentRequest->status === 'pending')
                                                <div class="space-y-2"><form method="POST" action="{{ route('modules.aeams.requests.review', $equipmentRequest) }}" data-confirm-submit="Approve and reserve this equipment schedule?">@csrf<input type="hidden" name="status" value="approved"><input type="hidden" name="_aeams_workspace" value="requests"><button class="ae-btn ae-primary w-full" type="submit">Approve &amp; reserve</button></form><form method="POST" action="{{ route('modules.aeams.requests.review', $equipmentRequest) }}" class="space-y-2" data-confirm-submit="Reject this request using the reason entered?">@csrf<input type="hidden" name="status" value="rejected"><input type="hidden" name="_aeams_workspace" value="requests"><label class="ae-label" for="aeams-reject-{{ $equipmentRequest->id }}">Rejection reason</label><input id="aeams-reject-{{ $equipmentRequest->id }}" class="ae-input" name="review_notes" required><button class="ae-btn ae-gold w-full" type="submit">Reject request</button></form></div>
                                            @elseif($canIssue && $equipmentRequest->status === 'approved')
                                                <form method="POST" action="{{ route('modules.aeams.requests.issue', $equipmentRequest) }}" class="space-y-2" data-confirm-submit="Record physical issue of this equipment to the farmer?">@csrf<input type="hidden" name="_aeams_workspace" value="requests"><label class="ae-label" for="aeams-issue-condition-{{ $equipmentRequest->id }}">Condition at issue</label><select id="aeams-issue-condition-{{ $equipmentRequest->id }}" class="ae-input" name="condition" required><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><button class="ae-btn ae-primary w-full" type="submit">Record issue</button></form>
                                            @elseif($canReturn && $equipmentRequest->status === 'issued')
                                                <form method="POST" action="{{ route('modules.aeams.requests.return', $equipmentRequest) }}" class="space-y-2" data-confirm-submit="Record return of this issued equipment?">@csrf<input type="hidden" name="_aeams_workspace" value="requests"><label class="ae-label" for="aeams-return-condition-{{ $equipmentRequest->id }}">Condition at return</label><select id="aeams-return-condition-{{ $equipmentRequest->id }}" class="ae-input" name="condition" required><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="needs_maintenance" value="1"> Needs maintenance</label><label class="ae-label" for="aeams-return-notes-{{ $equipmentRequest->id }}">Return notes</label><input id="aeams-return-notes-{{ $equipmentRequest->id }}" class="ae-input" name="notes"><button class="ae-btn ae-primary w-full" type="submit">Record return</button></form>
                                            @else
                                                <span class="text-xs text-slate-500">No action is available for this role and request state.</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ ($canReview || $canIssue || $canReturn || $isFarmer) ? 6 : 5 }}" class="py-8 text-center text-slate-500">No equipment requests match these filters.</td></tr>
                            @endforelse
                        </tbody></table>
                    </div>
                    <div class="mt-4">{{ $requests->links() }}</div>
                </section>
            </section>

            @if($canMaintenance || ($isStaff && $canExport))
                <section id="aeams-panel-maintenance" class="erp-workspace-panel space-y-4" role="tabpanel" aria-labelledby="aeams-tab-maintenance" tabindex="0" data-erp-workspace-panel="maintenance" hidden>
                    @if($canMaintenance)
                        <section class="ae-card p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-bold text-forest-green">Open maintenance</h2><p class="mt-1 text-sm text-slate-500">Equipment remains unavailable until maintenance is completed.</p></div><span class="ae-pill ae-red">{{ $maintenances->count() }} open</span></div>
                            <div class="ae-table-wrap mt-4" tabindex="0" role="region" aria-label="Scrollable open maintenance records"><table class="ae-table"><thead><tr><th>Asset</th><th>Issue</th><th>Reported by</th><th>Started</th><th>Complete work</th></tr></thead><tbody>
                                @forelse($maintenances as $maintenance)
                                    <tr><td><a class="font-bold text-forest-green" href="{{ route('modules.aeams.equipment.show', $maintenance->asset) }}">{{ $maintenance->asset->name }}</a><div class="text-xs text-slate-500">{{ $maintenance->asset->asset_code }}</div></td><td>{{ $maintenance->description }}</td><td>{{ $maintenance->reportedBy->name }}</td><td>{{ $maintenance->started_at?->format('M d, Y H:i') }}</td><td><form method="POST" action="{{ route('modules.aeams.maintenance.complete', $maintenance) }}" class="flex min-w-[220px] gap-2" data-confirm-submit="Mark this maintenance work complete and update asset availability?">@csrf<select class="ae-input" name="condition" aria-label="Condition after maintenance"><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><button class="ae-btn ae-primary" type="submit">Complete</button></form></td></tr>
                                @empty
                                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No open maintenance records.</td></tr>
                                @endforelse
                            </tbody></table></div>
                        </section>
                    @endif
                    @if($isStaff && $canExport)
                        <section class="ae-card flex flex-wrap items-center justify-between gap-4 p-5"><div><p class="erp-page-eyebrow">Equipment reporting</p><h2 class="text-lg font-bold text-forest-green">Utilization and condition register</h2><p class="mt-1 text-sm text-slate-500">Export the asset register with request, transaction, and maintenance counts.</p></div><a class="ae-btn ae-gold" href="{{ route('modules.aeams.export') }}">Download utilization CSV</a></section>
                    @endif
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
