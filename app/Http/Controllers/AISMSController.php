<?php

namespace App\Http\Controllers;

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\SupplyBatch;
use App\Models\SupplyCategory;
use App\Models\SupplyItem;
use App\Models\UnitOfMeasurement;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AISMSController extends Controller
{
    private function authz(Request $request, string $action = 'view'): void
    {
        abort_unless($request->user()->canModuleAction('aisms', $action), 403);
    }

    public function index(Request $request): View
    {
        $this->authz($request);
        $this->validateFilters($request);

        $search = $request->string('search')->toString();
        $allItems = SupplyItem::query()->where('status', 'active')->orderBy('name')->get();
        $allPositiveBatches = SupplyBatch::query()->with(['item', 'location'])
            ->where('quantity_available', '>', 0)
            ->latest()
            ->get();
        $usableBatches = $allPositiveBatches->filter(fn (SupplyBatch $batch) => $batch->status === 'available' && (! $batch->expires_on || $batch->expires_on->gte(today()))
        );
        $stockByItem = $usableBatches->groupBy('supply_item_id')
            ->map(fn ($rows) => (float) $rows->sum('quantity_available'));

        $items = SupplyItem::query()->with(['category', 'unit'])
            ->withSum(['batches as stock' => fn ($query) => $query
                ->where('status', 'available')
                ->where('quantity_available', '>', 0)
                ->where(fn ($nested) => $nested->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))], 'quantity_available')
            ->when($search, fn (Builder $query, string $value) => $query->where(function (Builder $nested) use ($value) {
                $nested->where('name', 'like', '%'.$value.'%')
                    ->orWhere('code', 'like', '%'.$value.'%');
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $lowStock = $allItems->filter(fn (SupplyItem $item) => $stockByItem->get($item->id, 0) <= (float) $item->minimum_stock
        )->count();
        $totalStock = (float) $usableBatches->sum('quantity_available');
        $expiring = SupplyBatch::query()->with('item')
            ->where('status', 'available')
            ->where('quantity_available', '>', 0)
            ->whereBetween('expires_on', [today()->toDateString(), today()->addDays(30)->toDateString()])
            ->get();
        $expired = SupplyBatch::query()->with('item')
            ->where('quantity_available', '>', 0)
            ->whereDate('expires_on', '<', today())
            ->get();
        $movements = $this->filteredMovements($request)
            ->with(['batch.item', 'batch.location', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'movements_page')
            ->withQueryString();

        $locations = InventoryLocation::query()->where('is_active', true)->orderBy('name')->get();
        $itemOptions = $allItems->map(fn (SupplyItem $item) => '<option value="'.$item->id.'">'.e($item->code.' — '.$item->name).'</option>'
        )->implode('');
        $locationOptions = $locations->map(fn (InventoryLocation $location) => '<option value="'.$location->id.'">'.e($location->name).'</option>'
        )->implode('');
        $batchOptions = $allPositiveBatches->map(fn (SupplyBatch $batch) => '<option value="'.$batch->id.'" data-available="'.$batch->quantity_available.'">'.e($batch->item->name.' — '.$batch->batch_number.' ('.$batch->quantity_available.')').'</option>'
        )->implode('');
        $releaseBatchOptions = $usableBatches->map(fn (SupplyBatch $batch) => '<option value="'.$batch->id.'" data-available="'.$batch->quantity_available.'">'.e($batch->item->name.' — '.$batch->batch_number.' ('.$batch->quantity_available.')').'</option>'
        )->implode('');

        return view('modules.aisms.dashboard', [
            'header' => 'Agricultural Inventory & Supply Management System',
            'description' => 'Manage agricultural supply inventory and stock transactions',
            'items' => $items,
            'itemsForForms' => $allItems,
            'filterItems' => SupplyItem::query()->orderBy('name')->get(['id', 'name', 'code']),
            'batches' => $allPositiveBatches,
            'lowStock' => $lowStock,
            'totalStock' => $totalStock,
            'expiring' => $expiring,
            'expired' => $expired,
            'categories' => SupplyCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => UnitOfMeasurement::query()->orderBy('name')->get(),
            'locations' => $locations,
            'filterLocations' => InventoryLocation::query()->orderBy('name')->get(),
            'movements' => $movements,
            'itemOptions' => $itemOptions,
            'locationOptions' => $locationOptions,
            'batchOptions' => $batchOptions,
            'releaseBatchOptions' => $releaseBatchOptions,
            'canCreate' => $request->user()->canModuleAction('aisms', 'create'),
            'canReceive' => $request->user()->canModuleAction('aisms', 'receive'),
            'canRelease' => $request->user()->canModuleAction('aisms', 'release'),
            'canAdjust' => $request->user()->canModuleAction('aisms', 'adjust'),
            'canExport' => $request->user()->canModuleAction('aisms', 'export'),
        ]);
    }

    public function item(Request $request): RedirectResponse
    {
        $this->authz($request, 'create');
        $data = $request->validate([
            'category_id' => 'required|exists:supply_categories,id',
            'unit_id' => 'required|exists:units_of_measurement,id',
            'code' => 'required|max:50',
            'name' => 'required|max:150',
            'minimum_stock' => 'required|numeric|min:0',
        ]);

        SupplyItem::create($data + ['status' => 'active']);

        return back()->with('status', 'Supply item registered.');
    }

    public function receive(Request $request, InventoryService $service): RedirectResponse
    {
        $this->authz($request, 'receive');
        $data = $request->validate([
            'supply_item_id' => 'required|exists:supply_items,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'batch_number' => 'required|max:100',
            'received_on' => 'required|date',
            'expires_on' => 'nullable|date|after_or_equal:received_on',
            'quantity' => 'required|numeric|decimal:0,2|min:0.01',
            'idempotency_key' => 'nullable|string|max:100',
            'notes' => 'nullable|max:1000',
        ]);

        $service->receive($data, $request->user()->id);

        return back()->with('status', 'Stock receipt posted.');
    }

    public function adjust(Request $request, InventoryService $service): RedirectResponse
    {
        $this->authz($request, 'adjust');
        $data = $request->validate([
            'supply_batch_id' => 'required|exists:supply_batches,id',
            'quantity' => 'required|numeric|decimal:0,2|not_in:0',
            'adjustment_reason' => 'required|string|max:500',
            'idempotency_key' => 'required|string|max:100',
            'notes' => 'nullable|max:1000',
        ]);

        $service->adjust($data, $request->user()->id);

        return back()->with('status', 'Stock adjustment posted.');
    }

    public function release(Request $request, InventoryService $service): RedirectResponse
    {
        $this->authz($request, 'release');
        $data = $request->validate([
            'supply_batch_id' => 'required|exists:supply_batches,id',
            'quantity' => 'required|numeric|decimal:0,2|min:0.01',
            'recipient' => 'required|max:150',
            'purpose' => 'required|max:255',
            'idempotency_key' => 'nullable|string|max:100',
            'notes' => 'nullable|max:1000',
        ]);

        $service->release($data, $request->user()->id);

        return back()->with('status', 'Stock release posted.');
    }

    public function export(Request $request)
    {
        $this->authz($request, 'export');
        $this->validateFilters($request);
        $query = $this->filteredMovements($request)
            ->with(['batch.item', 'batch.location', 'user'])
            ->orderBy('created_at')
            ->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date and time', 'Supply code', 'Supply', 'Movement type', 'Quantity', 'Batch', 'Location', 'Responsible personnel', 'Recipient', 'Purpose', 'Adjustment reason', 'Notes']);
            $query->chunkById(500, function ($movements) use ($out) {
                foreach ($movements as $movement) {
                    $batch = $movement->batch;
                    $values = [
                        $movement->reference,
                        $movement->created_at?->format('Y-m-d H:i:s'),
                        $batch?->item?->code,
                        $batch?->item?->name,
                        $movement->movement_type,
                        $movement->quantity,
                        $batch?->batch_number,
                        $batch?->location?->name,
                        $movement->user?->name,
                        $movement->recipient,
                        $movement->purpose,
                        $movement->adjustment_reason,
                        $movement->notes,
                    ];
                    fputcsv($out, array_map(fn ($value) => $this->safeCsvValue($value), $values));
                }
            });
            fclose($out);
        }, 'aisms-movements.csv', ['Content-Type' => 'text/csv']);
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'movement_type' => ['nullable', 'in:receipt,release,adjustment'],
            'supply_item_id' => ['nullable', 'integer', 'exists:supply_items,id'],
            'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function filteredMovements(Request $request): Builder
    {
        return InventoryMovement::query()
            ->when($request->filled('movement_type'), fn (Builder $query) => $query->where('movement_type', $request->input('movement_type')))
            ->when($request->filled('supply_item_id'), fn (Builder $query) => $query->whereHas('batch', fn (Builder $batch) => $batch->where('supply_item_id', $request->integer('supply_item_id'))))
            ->when($request->filled('location_id'), fn (Builder $query) => $query->whereHas('batch', fn (Builder $batch) => $batch->where('location_id', $request->integer('location_id'))))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->input('date_to')));
    }

    private function safeCsvValue(mixed $value): mixed
    {
        return preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value;
    }
}
