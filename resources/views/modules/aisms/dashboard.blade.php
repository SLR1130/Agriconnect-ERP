<x-layouts.app>
    <x-slot name="title">AISMS Inventory</x-slot>
    <x-slot name="header">Agricultural Inventory &amp; Supply Management</x-slot>
    <x-slot name="description">Agricultural supply stock, movement control, and batch monitoring.</x-slot>

    <style>
        .inv-card{background:#fff;border:1px solid #e1e9e2;border-radius:18px;box-shadow:0 8px 24px #153d2b0d}
        .inv-input{width:100%;border:1px solid #cfdcd2;border-radius:10px;padding:.65rem .75rem;background:#fff}
        .inv-input:focus{outline:2px solid #26865c;outline-offset:1px}
        .inv-btn{display:inline-flex;justify-content:center;align-items:center;border-radius:10px;padding:.65rem 1rem;font-size:.8rem;font-weight:700}
        .inv-primary{background:#153d2b;color:#fff}.inv-gold{background:#d5ad55;color:#153d2b}.inv-sage{background:#e7f0e8;color:#153d2b}
        .inv-table{width:100%;border-collapse:collapse}.inv-table th{padding:.7rem;text-align:left;color:#718177;font-size:.66rem;text-transform:uppercase;white-space:nowrap;border-bottom:1px solid #e1e9e2}.inv-table td{padding:.75rem;color:#405048;font-size:.8rem;vertical-align:top;border-bottom:1px solid #edf2ee}
        .inv-pill{display:inline-flex;border-radius:999px;padding:.25rem .6rem;font-size:.68rem;font-weight:800;white-space:nowrap}.inv-green{background:#e7f0e8;color:#1c6a46}.inv-yellow{background:#fff5d8;color:#8a691e}.inv-red{background:#fee7e5;color:#a33e36}.inv-gray{background:#eef1ee;color:#53615a}
        @media(max-width:900px){.inv-forms,.inv-panels{grid-template-columns:1fr!important}.inv-table-wrap{overflow-x:auto}.inv-table{min-width:1000px}}
    </style>

    <div class="space-y-6">
        @if(session('status'))
            <div role="status" class="inv-card border-l-4 border-emerald-green px-5 py-4 text-sm text-emerald-green">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div role="alert" class="inv-card border-l-4 border-red-500 px-5 py-4 text-sm text-red-700">
                <b>Transaction needs attention.</b>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="inv-card p-5"><p class="text-xs font-bold uppercase text-gray-500">Registered supplies</p><p class="mt-2 text-3xl font-bold text-forest-green">{{ $items->total() }}</p></div>
            <div class="inv-card p-5"><p class="text-xs font-bold uppercase text-gray-500">Usable stock</p><p class="mt-2 text-3xl font-bold text-emerald-green">{{ number_format($totalStock, 2) }}</p><p class="mt-1 text-xs text-gray-500">Excludes stock past its expiry date.</p></div>
            <div class="inv-card p-5"><p class="text-xs font-bold uppercase text-gray-500">Low stock items</p><p data-aisms-stat="low-stock" class="mt-2 text-3xl font-bold text-agricultural-gold">{{ $lowStock }}</p></div>
            <div class="inv-card p-5"><p class="text-xs font-bold uppercase text-gray-500">Expiry watch</p><p class="mt-2 text-3xl font-bold text-red-600">{{ $expiring->count() + $expired->count() }}</p><p class="mt-1 text-xs text-gray-500">{{ $expiring->count() }} expiring soon · {{ $expired->count() }} expired</p></div>
        </div>

        <section class="inv-card p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div><h2 class="text-lg font-bold text-forest-green">Supply management</h2><p class="mt-1 text-sm text-gray-500">Search the live supply catalog and review current usable balances.</p></div>
                <div class="flex flex-wrap gap-2">
                    @if($canExport)<a class="inv-btn inv-sage" href="{{ route('modules.aisms.export', request()->query()) }}">Export filtered transactions</a>@endif
                    @if($canReceive)<a class="inv-btn inv-gold" href="#receive-stock">Receive stock</a>@endif
                </div>
            </div>
            <form method="GET" action="{{ route('modules.aisms.dashboard') }}" class="mt-5 flex flex-col gap-3 sm:flex-row">
                <label class="sr-only" for="supply-search">Search supply catalog</label>
                <input id="supply-search" class="inv-input sm:max-w-sm" name="search" value="{{ request('search') }}" placeholder="Search code or supply name">
                <button class="inv-btn inv-primary" type="submit">Search catalog</button>
            </form>
            <div class="inv-table-wrap mt-5"><table class="inv-table">
                <thead><tr><th>Supply</th><th>Category</th><th>Unit</th><th>Usable stock</th><th>Status</th></tr></thead>
                <tbody>@forelse($items as $item)
                    <tr><td><b class="text-forest-green">{{ $item->name }}</b><div class="text-xs text-gray-500">{{ $item->code }}</div></td><td>{{ $item->category?->name ?? 'Uncategorized' }}</td><td>{{ $item->unit?->symbol ?? $item->unit?->name ?? '—' }}</td><td><b>{{ number_format((float) ($item->stock ?? 0), 2) }}</b></td><td><span class="inv-pill inv-green">{{ ucfirst($item->status) }}</span></td></tr>
                @empty<tr><td colspan="5" class="py-8 text-center text-gray-500">No supplies match the current search.</td></tr>@endforelse</tbody>
            </table></div>
            <div class="mt-4">{{ $items->links() }}</div>
        </section>

        <div id="receive-stock" class="inv-forms grid grid-cols-3 gap-5">
            @if($canReceive)
                <section class="inv-card p-5"><h2 class="text-lg font-bold text-forest-green">Receive stock</h2><p class="mt-1 text-sm text-gray-500">Post a delivery to an active inventory location.</p>
                    <form method="POST" action="{{ route('modules.aisms.receipts.store') }}" class="mt-5 space-y-3">@csrf
                        <select class="inv-input" name="supply_item_id" aria-label="Supply item" required><option value="">Select supply</option>{!! $itemOptions !!}</select>
                        <select class="inv-input" name="location_id" aria-label="Inventory location" required><option value="">Select location</option>{!! $locationOptions !!}</select>
                        <input class="inv-input" name="batch_number" aria-label="Batch number" placeholder="Batch number" required>
                        <div class="grid grid-cols-2 gap-3"><input class="inv-input" name="received_on" type="date" value="{{ old('received_on', today()->toDateString()) }}" aria-label="Received date" required><input class="inv-input" name="expires_on" type="date" aria-label="Expiration date"></div>
                        <input class="inv-input" name="quantity" type="number" min=".01" step=".01" placeholder="Quantity" required>
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button class="inv-btn inv-primary w-full" type="submit">Post receipt</button>
                    </form>
                </section>
            @endif

            @if($canRelease)
                <section class="inv-card p-5"><h2 class="text-lg font-bold text-forest-green">Release stock</h2><p class="mt-1 text-sm text-gray-500">Expired batches are excluded; available stock is checked again on submission.</p>
                    <form method="POST" action="{{ route('modules.aisms.releases.store') }}" class="mt-5 space-y-3">@csrf
                        <select class="inv-input" name="supply_batch_id" data-stock-select="release" aria-label="Usable batch" required><option value="">Select usable batch</option>{!! $releaseBatchOptions !!}</select>
                        <p data-stock-availability="release" class="text-xs text-gray-500" aria-live="polite">Choose a batch to see its current available quantity.</p>
                        <input class="inv-input" name="quantity" type="number" min=".01" step=".01" placeholder="Quantity" required>
                        <input class="inv-input" name="recipient" placeholder="Recipient" required>
                        <textarea class="inv-input" name="purpose" rows="3" placeholder="Purpose" required></textarea>
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button class="inv-btn inv-primary w-full" type="submit">Post release</button>
                    </form>
                </section>
            @endif

            @if($canAdjust)
                <section class="inv-card p-5"><h2 class="text-lg font-bold text-forest-green">Adjust stock</h2><p class="mt-1 text-sm text-gray-500">Record a signed physical-count correction. Negative adjustments cannot exceed the balance.</p>
                    <form method="POST" action="{{ route('modules.aisms.adjustments.store') }}" class="mt-5 space-y-3">@csrf
                        <select class="inv-input" name="supply_batch_id" data-stock-select="adjust" aria-label="Batch to adjust" required><option value="">Select batch</option>{!! $batchOptions !!}</select>
                        <p data-stock-availability="adjust" class="text-xs text-gray-500" aria-live="polite">Choose a batch to see its current available quantity.</p>
                        <input class="inv-input" name="quantity" type="number" step=".01" placeholder="Adjustment quantity, e.g. 2 or -2" required>
                        <textarea class="inv-input" name="adjustment_reason" rows="3" placeholder="Mandatory reason" required></textarea>
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button class="inv-btn inv-gold w-full" type="submit">Save adjustment</button>
                    </form>
                </section>
            @endif
            @unless($canReceive || $canRelease || $canAdjust)
                <div class="inv-card p-5 text-sm text-gray-600 sm:col-span-3">Read-only access: balances and transaction history are visible, but no stock-changing actions are assigned to this account.</div>
            @endunless
        </div>

        @if($canCreate)
            <section class="inv-card p-5"><h2 class="text-lg font-bold text-forest-green">Register a supply item</h2><p class="mt-1 text-sm text-gray-500">Add an item to the shared supply catalog before receiving its first batch.</p>
                <form method="POST" action="{{ route('modules.aisms.items.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">@csrf
                    <input class="inv-input" name="code" placeholder="Item code" required><input class="inv-input" name="name" placeholder="Supply name" required>
                    <select class="inv-input" name="category_id" aria-label="Category" required><option value="">Category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                    <select class="inv-input" name="unit_id" aria-label="Unit" required><option value="">Unit</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach</select>
                    <input class="inv-input" name="minimum_stock" type="number" min="0" step=".01" value="0" placeholder="Minimum stock" required>
                    <button class="inv-btn inv-sage" type="submit">Register supply</button>
                </form>
            </section>
        @endif

        <div class="inv-panels grid grid-cols-2 gap-5">
            <section class="inv-card p-5"><div class="flex items-center justify-between gap-2"><div><h2 class="text-lg font-bold text-forest-green">Batch and expiration monitoring</h2><p class="mt-1 text-sm text-gray-500">Balances include expired batches for visibility; those batches cannot be released.</p></div><span class="inv-pill inv-gray">{{ $batches->count() }} positive balances</span></div>
                <div class="inv-table-wrap mt-4"><table class="inv-table"><thead><tr><th>Supply / batch</th><th>Location</th><th>Available</th><th>Expiry</th><th>Condition</th></tr></thead><tbody>
                    @forelse($batches as $batch)
                        @php($isUnavailable = $batch->status !== 'available')
                        @php($isExpired = $batch->expires_on && $batch->expires_on->lt(today()))
                        @php($isExpiring = ! $isExpired && $batch->expires_on && $batch->expires_on->lte(today()->addDays(30)))
                        <tr><td><b>{{ $batch->item?->name }}</b><div class="text-xs text-gray-500">{{ $batch->batch_number }}</div></td><td>{{ $batch->location?->name }}</td><td>{{ number_format((float) $batch->quantity_available, 2) }}</td><td>{{ $batch->expires_on?->format('M d, Y') ?? 'No expiry' }}</td><td><span class="inv-pill {{ $isUnavailable ? 'inv-gray' : ($isExpired ? 'inv-red' : ($isExpiring ? 'inv-yellow' : 'inv-green')) }}">{{ $isUnavailable ? 'Unavailable' : ($isExpired ? 'Expired' : ($isExpiring ? 'Expiring soon' : 'Usable')) }}</span></td></tr>
                    @empty<tr><td colspan="5" class="py-6 text-center text-gray-500">No positive batch balances.</td></tr>@endforelse
                </tbody></table></div>
            </section>

            <section class="inv-card p-5"><div class="flex items-center justify-between gap-2"><div><h2 class="text-lg font-bold text-forest-green">Inventory transaction history</h2><p class="mt-1 text-sm text-gray-500">Filter, review and export the saved stock movements.</p></div><span class="inv-pill inv-green">{{ $movements->total() }} results</span></div>
                <form method="GET" action="{{ route('modules.aisms.dashboard') }}" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <input type="hidden" name="search" value="{{ request('search') }}">
                    <select class="inv-input" name="movement_type" aria-label="Movement type"><option value="">All movement types</option>@foreach(['receipt','release','adjustment'] as $type)<option value="{{ $type }}" @selected(request('movement_type') === $type)>{{ ucfirst($type) }}</option>@endforeach</select>
                    <select class="inv-input" name="supply_item_id" aria-label="Supply filter"><option value="">All supplies</option>@foreach($filterItems as $filterItem)<option value="{{ $filterItem->id }}" @selected((string)request('supply_item_id') === (string)$filterItem->id)>{{ $filterItem->code }} · {{ $filterItem->name }}</option>@endforeach</select>
                    <select class="inv-input" name="location_id" aria-label="Location filter"><option value="">All locations</option>@foreach($filterLocations as $location)<option value="{{ $location->id }}" @selected((string)request('location_id') === (string)$location->id)>{{ $location->name }}{{ $location->is_active ? '' : ' (inactive)' }}</option>@endforeach</select>
                    <input class="inv-input" type="date" name="date_from" aria-label="From date" value="{{ request('date_from') }}"><input class="inv-input" type="date" name="date_to" aria-label="To date" value="{{ request('date_to') }}">
                    <button class="inv-btn inv-primary" type="submit">Filter transactions</button>
                </form>
                <div class="inv-table-wrap mt-4"><table class="inv-table"><thead><tr><th>Reference / supply</th><th>Movement</th><th>Quantity</th><th>Batch / location</th><th>Responsible / time</th><th>Details</th></tr></thead><tbody>
                    @forelse($movements as $movement)
                        <tr><td><b>{{ $movement->reference }}</b><div class="text-xs text-gray-500">{{ $movement->batch?->item?->name }}</div></td><td><span class="inv-pill {{ $movement->movement_type === 'adjustment' ? 'inv-yellow' : 'inv-green' }}">{{ ucfirst($movement->movement_type) }}</span></td><td>{{ number_format((float)$movement->quantity, 2) }}</td><td>{{ $movement->batch?->batch_number }}<div class="text-xs text-gray-500">{{ $movement->batch?->location?->name }}</div></td><td>{{ $movement->user?->name ?? 'Unknown' }}<div class="text-xs text-gray-500">{{ $movement->created_at?->format('M d, Y H:i') }}</div></td><td class="max-w-xs">@if($movement->adjustment_reason)<b>Reason:</b> {{ $movement->adjustment_reason }}<br>@endif @if($movement->recipient)<b>Recipient:</b> {{ $movement->recipient }}<br>@endif @if($movement->purpose)<b>Purpose:</b> {{ $movement->purpose }}<br>@endif{{ $movement->notes }}</td></tr>
                    @empty<tr><td colspan="6" class="py-6 text-center text-gray-500">No movements match these filters.</td></tr>@endforelse
                </tbody></table></div>
                <div class="mt-4">{{ $movements->links() }}</div>
            </section>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-stock-select]').forEach(function (select) {
            const kind = select.dataset.stockSelect;
            const availability = document.querySelector('[data-stock-availability="' + kind + '"]');
            const quantity = select.form.querySelector('input[name="quantity"]');
            const updateAvailability = function () {
                const option = select.options[select.selectedIndex];
                const amount = option ? option.dataset.available : '';
                if (!amount) {
                    availability.textContent = 'Choose a batch to see its current available quantity.';
                    quantity.removeAttribute('max');
                    return;
                }
                availability.textContent = 'Available now: ' + amount;
                quantity.max = amount;
            };
            select.addEventListener('change', updateAvailability);
            updateAvailability();
        });
    </script>
</x-layouts.app>
