<?php

namespace App\Http\Controllers;

use App\Models\{InventoryLocation, InventoryMovement, SupplyBatch, SupplyCategory, SupplyItem, UnitOfMeasurement};
use App\Services\InventoryService;
use Illuminate\Http\{RedirectResponse, Request};
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
        $search = $request->string('search')->toString();
        $allItems = SupplyItem::query()->where('status', 'active')->orderBy('name')->get();
        $allPositiveBatches = SupplyBatch::with(['item', 'location'])
            ->where('quantity_available', '>', 0)
            ->latest()
            ->get();
        $batches = $allPositiveBatches->filter(fn ($batch) => ! $batch->expires_on || ! $batch->expires_on->isPast());
        $stockByItem = $batches->groupBy('supply_item_id')
            ->map(fn ($rows) => (float) $rows->sum('quantity_available'));

        $items = SupplyItem::with(['category', 'unit'])
            ->withSum(['batches as stock' => fn ($query) => $query
                ->where('quantity_available', '>', 0)
                ->where(fn ($nested) => $nested->whereNull('expires_on')->orWhereDate('expires_on', '>=', today()))], 'quantity_available')
            ->when($search, fn ($query, $value) => $query->where(function ($nested) use ($value) {
                $nested->where('name', 'like', '%'.$value.'%')
                    ->orWhere('code', 'like', '%'.$value.'%');
            }))
            ->paginate(15)
            ->withQueryString();

        $lowStock = $allItems->filter(fn ($item) => $stockByItem->get($item->id, 0) <= (float) $item->minimum_stock)->count();
        $totalStock = (float) $batches->sum('quantity_available');
        $expiring = SupplyBatch::with('item')
            ->where('quantity_available', '>', 0)
            ->whereBetween('expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->get();
        $expired = SupplyBatch::with('item')
            ->where('quantity_available', '>', 0)
            ->whereDate('expires_on', '<', now())
            ->get();
        $movements = InventoryMovement::with(['batch.item', 'batch.location', 'user'])
            ->latest()
            ->paginate(15, ['*'], 'movements_page');
        $itemOptions = $allItems->map(fn ($item) => '<option value='.$item->id.'>'.e($item->code.' — '.$item->name).'</option>')->implode('');
        $locations = InventoryLocation::where('is_active', true)->orderBy('name')->get();
        $locationOptions = $locations->map(fn ($location) => '<option value='.$location->id.'>'.e($location->name).'</option>')->implode('');
        $batchOptions = $allPositiveBatches->map(fn ($batch) => '<option value='.$batch->id.'>'.e($batch->item->name.' — '.$batch->batch_number.' ('.$batch->quantity_available.')').'</option>')->implode('');
        $releaseBatchOptions = $batches->map(fn ($batch) => '<option value='.$batch->id.'>'.e($batch->item->name.' — '.$batch->batch_number.' ('.$batch->quantity_available.')').'</option>')->implode('');

        return view('modules.aisms.dashboard', [
            'header' => 'Agricultural Inventory & Supply Management System',
            'description' => 'Manage agricultural supply inventory and stock transactions',
            'items' => $items,
            'itemsForForms' => $allItems,
            'batches' => $batches,
            'lowStock' => $lowStock,
            'totalStock' => $totalStock,
            'expiring' => $expiring,
            'expired' => $expired,
            'categories' => SupplyCategory::where('is_active', true)->orderBy('name')->get(),
            'units' => UnitOfMeasurement::orderBy('name')->get(),
            'locations' => $locations,
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
            'quantity' => 'required|numeric|min:0.01',
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
            'quantity' => 'required|numeric|not_in:0',
            'adjustment_reason' => 'required|string|max:500',
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
            'quantity' => 'required|numeric|min:0.01',
            'recipient' => 'required|max:150',
            'purpose' => 'required|max:255',
            'notes' => 'nullable|max:1000',
        ]);
        $service->release($data, $request->user()->id);
        return back()->with('status', 'Stock release posted.');
    }

    public function export(Request $request)
    {
        $this->authz($request, 'reports');
        $rows = InventoryMovement::with('batch.item')->latest()->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Type', 'Item', 'Quantity', 'Date']);
            foreach ($rows as $movement) {
                fputcsv($out, [$movement->reference, $movement->movement_type, $movement->batch->item->name, $movement->quantity, $movement->created_at]);
            }
            fclose($out);
        }, 'aisms-movements.csv', ['Content-Type' => 'text/csv']);
    }
}
