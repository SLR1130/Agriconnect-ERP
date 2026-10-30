<x-layouts.app>
    <x-slot name="header">Agricultural Equipment &amp; Asset Management</x-slot>
    <x-slot name="description">Register, reserve, release, return, and maintain shared agricultural equipment.</x-slot>

    <style>
        .ae-card{background:#fff;border:1px solid #dfe9e1;border-radius:18px;box-shadow:0 8px 24px #153d2b0d}
        .ae-input{width:100%;border:1px solid #cbd9cf;border-radius:10px;padding:.65rem .75rem;background:#fff}
        .ae-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:.62rem .9rem;font-size:.78rem;font-weight:800}
        .ae-primary{background:#153d2b;color:#fff}.ae-gold{background:#d5ad55;color:#153d2b}.ae-muted{background:#e7f0e8;color:#153d2b}
        .ae-table{width:100%;border-collapse:collapse}.ae-table th{padding:.7rem;text-align:left;color:#718177;font-size:.65rem;text-transform:uppercase;border-bottom:1px solid #e1e9e2}.ae-table td{padding:.75rem;color:#405048;font-size:.78rem;border-bottom:1px solid #edf2ee;vertical-align:top}
        .ae-pill{display:inline-flex;border-radius:999px;padding:.25rem .6rem;font-size:.66rem;font-weight:800;text-transform:capitalize}.ae-green{background:#e7f0e8;color:#1c6a46}.ae-yellow{background:#fff5d8;color:#8a691e}.ae-red{background:#fee7e5;color:#a33e36}.ae-blue{background:#e7f0fb;color:#28618e}
        @media(max-width:900px){.ae-two,.ae-forms{grid-template-columns:1fr!important}.ae-table-wrap{overflow-x:auto}.ae-table{min-width:820px}}
    </style>

    <div class="space-y-6">
        @if(session('status'))
            <div class="ae-card border-l-4 border-emerald-600 px-5 py-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="ae-card border-l-4 border-red-500 px-5 py-4 text-sm text-red-700">
                <b>Equipment workflow needs attention.</b>
                <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['label' => 'Equipment assets', 'value' => $stats['assets'], 'class' => 'text-forest-green'],
                ['label' => 'Available', 'value' => $stats['available'], 'class' => 'text-emerald-green'],
                ['label' => 'Pending requests', 'value' => $stats['pending'], 'class' => 'text-agricultural-gold'],
                ['label' => 'Reserved', 'value' => $stats['reserved'], 'class' => 'text-blue-700'],
                ['label' => 'Issued', 'value' => $stats['issued'], 'class' => 'text-emerald-green'],
                ['label' => 'Maintenance', 'value' => $stats['maintenance'], 'class' => 'text-red-600'],
            ] as $stat)
                <div class="ae-card p-4"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $stat['label'] }}</p><p class="mt-2 text-2xl font-bold {{ $stat['class'] }}">{{ $stat['value'] }}</p></div>
            @endforeach
        </div>

        @if($canCreate)
            <div class="ae-forms grid grid-cols-2 gap-5">
                <section class="ae-card p-5">
                    <h2 class="text-lg font-bold text-forest-green">Register equipment</h2>
                    <p class="mt-1 text-sm text-slate-500">Create one authoritative asset record for each managed item.</p>
                    <form method="POST" action="{{ route('modules.aeams.equipment.store') }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input class="ae-input" name="asset_code" placeholder="Asset code" required>
                        <input class="ae-input" name="name" placeholder="Equipment name" required>
                        <select class="ae-input" name="category_id" required><option value="">Category</option>@foreach($equipmentCategories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                        <select class="ae-input" name="condition" required><option value="good">Good condition</option><option value="fair">Fair condition</option><option value="damaged">Damaged</option></select>
                        <input class="ae-input" name="acquired_on" type="date" aria-label="Acquisition date">
                        <input class="ae-input" name="acquisition_cost" type="number" min="0" step=".01" placeholder="Acquisition cost">
                        <input class="ae-input" name="assigned_location" placeholder="Assigned location">
                        <select class="ae-input" name="responsible_user_id"><option value="">Responsible MAO personnel</option>@foreach($personnelUsers as $personnelUser)<option value="{{ $personnelUser->id }}">{{ $personnelUser->name }}</option>@endforeach</select>
                        <textarea class="ae-input sm:col-span-2" name="description" rows="2" placeholder="Description"></textarea>
                        <button class="ae-btn ae-primary sm:col-span-2" type="submit">Register asset</button>
                    </form>
                </section>
                <section class="ae-card bg-sage p-5">
                    <h2 class="text-lg font-bold text-forest-green">Operational controls</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Every issue, return, review, and maintenance action is recorded against the same asset and authenticated personnel account.</p>
                    <div class="mt-5 space-y-3 text-sm text-slate-700">
                        <p><b class="text-emerald-800">Availability:</b> equipment under maintenance cannot be issued.</p>
                        <p><b class="text-emerald-800">Reservations:</b> overlapping pending, approved, or issued schedules are rejected.</p>
                        <p><b class="text-emerald-800">Ownership:</b> requests are linked to existing verified FFRMS farmer records.</p>
                    </div>
                </section>
            </div>
        @elseif($farmer?->isVerified())
            <section class="ae-card p-5">
                <h2 class="text-lg font-bold text-forest-green">Request equipment</h2>
                <p class="mt-1 text-sm text-slate-500">Your request is linked to your verified FFRMS farmer record and will be reviewed by authorized MAO personnel.</p>
                <form method="POST" action="{{ route('modules.aeams.requests.store') }}" class="mt-5 grid gap-3 md:grid-cols-2">
                    @csrf
                    <select class="ae-input" name="equipment_asset_id" required><option value="">Select available equipment</option>@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} — {{ $asset->name }} ({{ ucfirst($asset->condition) }})</option>@endforeach</select>
                    <input class="ae-input" name="idempotency_key" placeholder="Optional retry key">
                    <input class="ae-input" name="schedule_start" type="datetime-local" required>
                    <input class="ae-input" name="schedule_end" type="datetime-local" required>
                    <textarea class="ae-input md:col-span-2" name="intended_use" rows="3" placeholder="Intended agricultural use" required></textarea>
                    <button class="ae-btn ae-primary md:col-span-2" type="submit">Submit equipment request</button>
                </form>
            </section>
        @endif

        <section class="ae-card p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div><h2 class="text-lg font-bold text-forest-green">{{ $isStaff ? 'Equipment catalog' : 'Available equipment' }}</h2><p class="mt-1 text-sm text-slate-500">Search authoritative asset records and current availability.</p></div><div class="flex flex-wrap gap-2">@if($isStaff && $canUpdate)<a href="{{ route('modules.aeams.export') }}" class="ae-btn ae-muted">Utilization CSV</a>@endif
                </div><form method="GET" class="flex flex-wrap gap-2"><input class="ae-input" name="search" value="{{ request('search') }}" placeholder="Search code, name, location"><select class="ae-input" name="category_id"><option value="">All categories</option>@foreach($equipmentCategories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select><select class="ae-input" name="status"><option value="">All availability</option><option value="available" @selected(request('status') === 'available')>Available</option><option value="issued" @selected(request('status') === 'issued')>Issued</option><option value="maintenance" @selected(request('status') === 'maintenance')>Maintenance</option><option value="archived" @selected(request('status') === 'archived')>Archived</option></select><button class="ae-btn ae-muted" type="submit">Filter</button></form>
            </div>
            <div class="ae-table-wrap mt-5"><table class="ae-table"><thead><tr><th>Asset</th><th>Category</th><th>Condition</th><th>Location</th><th>Availability</th>@if($canMaintenance)<th>Maintenance</th>@endif</tr></thead><tbody>
                @forelse($assets as $asset)
                    <tr><td><a href="{{ route('modules.aeams.equipment.show', $asset) }}" class="font-bold text-forest-green hover:text-emerald-green">{{ $asset->name }}</a><div class="text-xs text-slate-500">{{ $asset->asset_code }}</div></td><td>{{ $asset->category->name }}</td><td>{{ ucfirst($asset->condition) }}</td><td>{{ $asset->assigned_location ?: 'Unassigned' }}</td><td><span class="ae-pill {{ $asset->availability_status === 'available' ? 'ae-green' : ($asset->availability_status === 'maintenance' ? 'ae-red' : 'ae-yellow') }}">{{ str_replace('_', ' ', $asset->availability_status) }}</span></td>@if($canMaintenance)<td>@if($asset->maintenances->isNotEmpty())<span class="ae-pill ae-red">Open maintenance</span>@else<form method="POST" action="{{ route('modules.aeams.maintenance.store', $asset) }}" class="flex min-w-[230px] gap-2">@csrf<input class="ae-input" name="description" placeholder="Maintenance note" required><button class="ae-btn ae-muted" type="submit">Open</button></form>@endif</td>@endif</tr>
                @empty
                    <tr><td colspan="{{ $canMaintenance ? 6 : 5 }}" class="py-8 text-center text-slate-500">No equipment matches the current filter.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $assets->links() }}</div>
        </section>

        <section class="ae-card p-5">
            <div class="flex items-end justify-between"><div><h2 class="text-lg font-bold text-forest-green">{{ $isStaff ? 'Requests and reservations' : 'My equipment requests' }}</h2><p class="mt-1 text-sm text-slate-500">Review, issue, and return actions are controlled by the request state.</p></div><span class="ae-pill ae-green">{{ $requests->total() }} records</span></div>
            <div class="ae-table-wrap mt-5"><table class="ae-table"><thead><tr><th>Reference</th><th>Farmer / equipment</th><th>Schedule</th><th>Status</th><th>Details</th>@if($canReview || $canIssue || $canReturn || $isFarmer)<th>Action</th>@endif</tr></thead><tbody>
                @forelse($requests as $equipmentRequest)
                    <tr>
                        <td><b>{{ $equipmentRequest->reference }}</b><div class="text-xs text-slate-500">{{ $equipmentRequest->created_at?->format('M d, Y H:i') }}</div></td>
                        <td><b>{{ $equipmentRequest->asset->name }}</b><div class="text-xs text-slate-500">{{ $equipmentRequest->farmer->user->name }} · {{ $equipmentRequest->farmer->farmer_code }}</div></td>
                        <td>{{ $equipmentRequest->schedule_start?->format('M d, Y H:i') }}<br><span class="text-xs text-slate-500">to {{ $equipmentRequest->schedule_end?->format('M d, Y H:i') }}</span></td>
                        <td><span class="ae-pill {{ in_array($equipmentRequest->status, ['approved', 'returned']) ? 'ae-green' : (in_array($equipmentRequest->status, ['rejected']) ? 'ae-red' : 'ae-yellow') }}">{{ $equipmentRequest->status }}</span></td>
                        <td class="max-w-xs">{{ $equipmentRequest->intended_use }}@if($equipmentRequest->review_notes)<div class="mt-1 text-xs text-slate-500">Review: {{ $equipmentRequest->review_notes }}</div>@endif</td>
                        @if($canReview || $canIssue || $canReturn || $isFarmer)
                            <td class="min-w-[250px]">
                                @if($isFarmer && in_array($equipmentRequest->status, ['pending', 'approved']))
                                    <form method="POST" action="{{ route('modules.aeams.requests.cancel', $equipmentRequest) }}">@csrf<button class="ae-btn ae-gold" type="submit">Cancel request</button></form>
                                @elseif($canReview && $equipmentRequest->status === 'pending')
                                    <div class="space-y-2"><form method="POST" action="{{ route('modules.aeams.requests.review', $equipmentRequest) }}" class="flex gap-2">@csrf<input type="hidden" name="status" value="approved"><button class="ae-btn ae-primary" type="submit">Approve</button></form><form method="POST" action="{{ route('modules.aeams.requests.review', $equipmentRequest) }}" class="space-y-2">@csrf<input type="hidden" name="status" value="rejected"><input class="ae-input" name="review_notes" placeholder="Rejection reason" required><button class="ae-btn ae-gold" type="submit">Reject</button></form></div>
                                @elseif($canIssue && $equipmentRequest->status === 'approved')
                                    <form method="POST" action="{{ route('modules.aeams.requests.issue', $equipmentRequest) }}" class="flex gap-2">@csrf<select class="ae-input" name="condition"><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><button class="ae-btn ae-primary" type="submit">Issue</button></form>
                                @elseif($canReturn && $equipmentRequest->status === 'issued')
                                    <form method="POST" action="{{ route('modules.aeams.requests.return', $equipmentRequest) }}" class="space-y-2">@csrf<div class="flex gap-2"><select class="ae-input" name="condition"><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><button class="ae-btn ae-primary" type="submit">Return</button></div><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="needs_maintenance" value="1"> Needs maintenance</label><input class="ae-input" name="notes" placeholder="Return notes"></form>
                                @else
                                    <span class="text-xs text-slate-500">No action available for this state.</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ ($canReview || $canIssue || $canReturn || $isFarmer) ? 6 : 5 }}" class="py-8 text-center text-slate-500">No equipment requests have been recorded.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="mt-4">{{ $requests->links() }}</div>
        </section>

        @if($canMaintenance)
            <section class="ae-card p-5">
                <div class="flex items-end justify-between"><div><h2 class="text-lg font-bold text-forest-green">Open maintenance</h2><p class="mt-1 text-sm text-slate-500">Complete maintenance to return equipment to available status.</p></div><span class="ae-pill ae-red">{{ $maintenances->count() }} open</span></div>
                <div class="ae-table-wrap mt-5"><table class="ae-table"><thead><tr><th>Asset</th><th>Issue</th><th>Reported by</th><th>Started</th><th>Action</th></tr></thead><tbody>
                    @forelse($maintenances as $maintenance)
                        <tr><td><b>{{ $maintenance->asset->name }}</b><div class="text-xs text-slate-500">{{ $maintenance->asset->asset_code }}</div></td><td>{{ $maintenance->description }}</td><td>{{ $maintenance->reportedBy->name }}</td><td>{{ $maintenance->started_at?->format('M d, Y H:i') }}</td><td><form method="POST" action="{{ route('modules.aeams.maintenance.complete', $maintenance) }}" class="flex gap-2">@csrf<select class="ae-input" name="condition"><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><button class="ae-btn ae-primary" type="submit">Complete</button></form></td></tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">No open maintenance records.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        @endif
    </div>
</x-layouts.app>
