<x-layouts.app>
    <x-slot name="header">Equipment asset details</x-slot>
    <x-slot name="description">Asset history within the shared AgriConnect ERP equipment register.</x-slot>

    <style>
        .ae-card{background:#fff;border:1px solid #dfe9e1;border-radius:18px;box-shadow:0 8px 24px #153d2b0d}
        .ae-pill{display:inline-flex;border-radius:999px;padding:.25rem .6rem;font-size:.66rem;font-weight:800;text-transform:capitalize}.ae-green{background:#e7f0e8;color:#1c6a46}.ae-yellow{background:#fff5d8;color:#8a691e}.ae-red{background:#fee7e5;color:#a33e36}
        .ae-table{width:100%;border-collapse:collapse}.ae-table th{padding:.7rem;text-align:left;color:#718177;font-size:.65rem;text-transform:uppercase;border-bottom:1px solid #e1e9e2}.ae-table td{padding:.75rem;color:#405048;font-size:.78rem;border-bottom:1px solid #edf2ee;vertical-align:top}
        @media(max-width:800px){.ae-table-wrap{overflow-x:auto}.ae-table{min-width:700px}}
    </style>

    <div class="space-y-6">
        @if(session('status'))<div class="ae-card border-l-4 border-emerald-600 px-5 py-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="ae-card border-l-4 border-red-500 px-5 py-4 text-sm text-red-700" role="alert">
                <b>Asset action needs attention.</b>
                <ul class="mt-1 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <a href="{{ route('modules.aeams.dashboard') }}" class="inline-flex rounded-xl border border-emerald-200 px-4 py-2 text-sm font-bold text-forest-green hover:bg-emerald-50">← Back to equipment workspace</a>

        <section class="ae-card p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div><p class="text-xs font-bold uppercase tracking-[.16em] text-emerald-700">{{ $asset->asset_code }}</p><h1 class="mt-2 text-2xl font-bold text-forest-green">{{ $asset->name }}</h1><p class="mt-2 text-sm leading-6 text-slate-600">{{ $asset->description ?: 'No description recorded.' }}</p></div>
                <span class="ae-pill {{ $asset->availability_status === 'available' ? 'ae-green' : ($asset->availability_status === 'maintenance' ? 'ae-red' : 'ae-yellow') }}">{{ str_replace('_', ' ', $asset->availability_status) }}</span>
            </div>
            <div class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-xs uppercase text-slate-500">Category</p><p class="mt-1 font-semibold text-slate-800">{{ $asset->category->name }}</p></div>
                <div><p class="text-xs uppercase text-slate-500">Condition</p><p class="mt-1 font-semibold text-slate-800">{{ ucfirst($asset->condition) }}</p></div>
                <div><p class="text-xs uppercase text-slate-500">Location</p><p class="mt-1 font-semibold text-slate-800">{{ $asset->assigned_location ?: 'Unassigned' }}</p></div>
                <div><p class="text-xs uppercase text-slate-500">Responsible personnel</p><p class="mt-1 font-semibold text-slate-800">{{ $asset->responsibleUser?->name ?: 'Not assigned' }}</p></div>
            </div>
        </section>

        @if($isStaff && $canUpdate)
            <section class="ae-card p-5"><div class="flex items-center justify-between gap-3"><h2 class="text-lg font-bold text-forest-green">Asset controls</h2><div class="flex gap-2">@if($asset->availability_status === 'archived')<form method="POST" action="{{ route('modules.aeams.equipment.restore',$asset) }}">@csrf<button class="rounded-xl bg-emerald-100 px-3 py-2 text-xs font-bold text-emerald-800" type="submit">Restore asset</button></form>@elseif($asset->availability_status !== 'issued')<form method="POST" action="{{ route('modules.aeams.equipment.archive',$asset) }}">@csrf<button class="rounded-xl bg-amber-100 px-3 py-2 text-xs font-bold text-amber-800" type="submit">Archive asset</button></form>@endif</div></div><form method="POST" action="{{ route('modules.aeams.equipment.update',$asset) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf @method('PATCH')<input class="ae-input" name="name" value="{{ $asset->name }}" required><select class="ae-input" name="category_id" required>@foreach($equipmentCategories as $category)<option value="{{ $category->id }}" @selected((int)$asset->category_id === (int)$category->id)>{{ $category->name }}</option>@endforeach</select><input class="ae-input" name="acquired_on" type="date" value="{{ $asset->acquired_on?->format('Y-m-d') }}"><input class="ae-input" name="acquisition_cost" type="number" min="0" step=".01" value="{{ $asset->acquisition_cost }}"><select class="ae-input" name="condition"><option value="good" @selected($asset->condition === 'good')>Good</option><option value="fair" @selected($asset->condition === 'fair')>Fair</option><option value="damaged" @selected($asset->condition === 'damaged')>Damaged</option></select><input class="ae-input" name="assigned_location" value="{{ $asset->assigned_location }}" placeholder="Assigned location"><select class="ae-input" name="responsible_user_id"><option value="">No responsible personnel</option>@foreach($personnelUsers as $personnel)<option value="{{ $personnel->id }}" @selected((int)$asset->responsible_user_id === (int)$personnel->id)>{{ $personnel->name }}</option>@endforeach</select><textarea class="ae-input sm:col-span-2" name="description" rows="2">{{ $asset->description }}</textarea><button class="ae-btn ae-primary sm:col-span-2" type="submit">Save audited asset update</button></form></section>
        @endif

        <section class="ae-card p-5">
            <h2 class="text-lg font-bold text-forest-green">Request history</h2>
            <div class="ae-table-wrap mt-4"><table class="ae-table"><thead><tr><th>Reference</th><th>Farmer</th><th>Schedule</th><th>Status</th><th>Review notes</th></tr></thead><tbody>
                @forelse($requests as $equipmentRequest)
                    <tr><td><b>{{ $equipmentRequest->reference }}</b><div class="text-xs text-slate-500">{{ $equipmentRequest->created_at?->format('M d, Y H:i') }}</div></td><td>{{ $equipmentRequest->farmer->user->name }}<div class="text-xs text-slate-500">{{ $equipmentRequest->farmer->farmer_code }}</div></td><td>{{ $equipmentRequest->schedule_start?->format('M d, Y H:i') }}<br>to {{ $equipmentRequest->schedule_end?->format('M d, Y H:i') }}</td><td><span class="ae-pill {{ in_array($equipmentRequest->status, ['approved', 'returned']) ? 'ae-green' : ($equipmentRequest->status === 'rejected' ? 'ae-red' : 'ae-yellow') }}">{{ $equipmentRequest->status }}</span></td><td>{{ $equipmentRequest->review_notes ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No request history is available for this asset.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="ae-card p-5">
            <h2 class="text-lg font-bold text-forest-green">Asset transaction history</h2>
            <div class="ae-table-wrap mt-4"><table class="ae-table"><thead><tr><th>When</th><th>Action</th><th>Responsible personnel</th><th>Request</th><th>Notes</th></tr></thead><tbody>
                @forelse($transactions as $transaction)
                    <tr><td>{{ $transaction->created_at?->format('M d, Y H:i') }}</td><td><span class="ae-pill {{ in_array($transaction->action, ['returned', 'maintenance_completed']) ? 'ae-green' : ($transaction->action === 'rejected' ? 'ae-red' : 'ae-yellow') }}">{{ str_replace('_', ' ', $transaction->action) }}</span></td><td>{{ $transaction->performedBy->name }}</td><td>{{ $transaction->request?->reference ?: 'Asset record' }}</td><td>{{ $transaction->notes ?: '—' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">No asset transactions have been recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </div>
</x-layouts.app>
