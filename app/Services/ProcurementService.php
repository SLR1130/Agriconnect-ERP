<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BudgetAllocation;
use App\Models\EquipmentAsset;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementReceiptItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\ProcurementStatusHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(
        private readonly BudgetService $budget,
        private readonly InventoryService $inventory,
    ) {
    }

    public function createRequest(array $data, User $actor): ProcurementRequest
    {
        return DB::transaction(function () use ($data, $actor) {
            if (! empty($data['idempotency_key'])) {
                $existing = ProcurementRequest::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing->load('items');
                }
            }

            $items = array_values(array_filter($data['items'] ?? [], fn ($item) => filled($item['description'] ?? null)));
            if ($items === []) {
                throw ValidationException::withMessages(['items' => 'At least one procurement item is required.']);
            }

            $allocation = BudgetAllocation::query()->where('status', 'active')->findOrFail($data['budget_allocation_id']);
            $totalCents = 0;
            foreach ($items as $item) {
                $quantityCents = $this->budget->cents($item['quantity']);
                if ($quantityCents <= 0) {
                    throw ValidationException::withMessages(['items' => 'Item quantities must be greater than zero.']);
                }
                $lineTotal = $this->budget->lineTotal($item['quantity'], $item['estimated_unit_price']);
                $totalCents += $this->budget->cents($lineTotal);
            }

            if ($totalCents > $this->budget->summary($allocation)['available_cents']) {
                throw ValidationException::withMessages(['budget_allocation_id' => 'The estimated request exceeds the available budget.']);
            }

            $procurementRequest = ProcurementRequest::create([
                'request_number' => $this->reference('PR'),
                'budget_allocation_id' => $allocation->id,
                'requester_id' => $actor->id,
                'requesting_office' => $data['requesting_office'],
                'purpose' => $data['purpose'],
                'justification' => $data['justification'] ?? null,
                'estimated_total' => $this->budget->money($totalCents),
                'status' => 'submitted',
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($items as $item) {
                ProcurementRequestItem::create([
                    'procurement_request_id' => $procurementRequest->id,
                    'supply_item_id' => $item['supply_item_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'estimated_unit_price' => $item['estimated_unit_price'],
                    'estimated_total' => $this->budget->lineTotal($item['quantity'], $item['estimated_unit_price']),
                ]);
            }

            $this->requestHistory($procurementRequest, null, 'submitted', $actor, 'Procurement request submitted.');
            ActivityLog::log('created', 'Procurement request '.$procurementRequest->request_number.' submitted.', $procurementRequest->request_number, $actor->id, 'abpms');

            return $procurementRequest->load('items');
        });
    }

    public function reviewRequest(ProcurementRequest $procurementRequest, string $status, ?string $notes, User $actor): ProcurementRequest
    {
        if (! in_array($status, ['under_review', 'approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported procurement request status.']);
        }
        if ($status === 'rejected' && blank($notes)) {
            throw ValidationException::withMessages(['review_notes' => 'A rejection reason is required.']);
        }

        return DB::transaction(function () use ($procurementRequest, $status, $notes, $actor) {
            $request = ProcurementRequest::query()->lockForUpdate()->findOrFail($procurementRequest->id);
            if (! in_array($request->status, ['submitted', 'under_review'], true)) {
                throw ValidationException::withMessages(['status' => 'This procurement request is no longer reviewable.']);
            }
            if ($status === 'approved' && (int) $request->requester_id === (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'A requester cannot approve their own procurement request.']);
            }
            if ($status === 'approved') {
                $allocation = BudgetAllocation::query()->lockForUpdate()->findOrFail($request->budget_allocation_id);
                $this->budget->assertAvailable($allocation, $this->budget->cents($request->estimated_total));
            }

            $from = $request->status;
            $request->update([
                'status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);
            $this->requestHistory($request, $from, $status, $actor, $notes);
            ActivityLog::log($status, 'Procurement request '.$request->request_number.' '.$status.'.', $request->request_number, $actor->id, 'abpms');

            return $request->refresh();
        });
    }

    public function createPurchaseOrder(ProcurementRequest $procurementRequest, array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($procurementRequest, $data, $actor) {
            $request = ProcurementRequest::query()->lockForUpdate()->with('items')->findOrFail($procurementRequest->id);
            if ($request->status !== 'approved') {
                throw ValidationException::withMessages(['procurement_request_id' => 'Only approved requests may create purchase orders.']);
            }
            if (! empty($data['idempotency_key'])) {
                $existing = PurchaseOrder::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing->load('items');
                }
            }
            if ($request->purchaseOrders()->whereNotIn('status', ['cancelled'])->exists()) {
                throw ValidationException::withMessages(['procurement_request_id' => 'An active purchase order already exists for this request.']);
            }

            $totalCents = $this->budget->cents($request->estimated_total);
            $purchaseOrder = PurchaseOrder::create([
                'purchase_order_number' => $this->reference('PO'),
                'procurement_request_id' => $request->id,
                'supplier_id' => $data['supplier_id'],
                'created_by' => $actor->id,
                'total_amount' => $this->budget->money($totalCents),
                'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'procurement_request_item_id' => $item->id,
                    'supply_item_id' => $item->supply_item_id,
                    'description' => $item->description,
                    'ordered_quantity' => $item->quantity,
                    'unit_price' => $item->estimated_unit_price,
                    'line_total' => $item->estimated_total,
                ]);
            }

            $this->purchaseOrderHistory($purchaseOrder, null, 'draft', $actor, 'Purchase order drafted.');
            return $purchaseOrder->load('items');
        });
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->with('request')->findOrFail($purchaseOrder->id);
            if ($order->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft purchase orders can be approved.']);
            }
            if ((int) $order->request->requester_id === (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'A requester cannot approve the related purchase order.']);
            }

            $allocation = BudgetAllocation::query()->lockForUpdate()->findOrFail($order->request->budget_allocation_id);
            $this->budget->commit($allocation, $this->budget->cents($order->total_amount), $order, $actor);
            $order->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->purchaseOrderHistory($order, 'draft', 'approved', $actor, 'Purchase order approved and budget committed.');
            return $order->refresh();
        });
    }

    public function orderPurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($order->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Only approved purchase orders can be placed.']);
            }
            $order->update(['status' => 'ordered', 'ordered_on' => now()->toDateString()]);
            $this->purchaseOrderHistory($order, 'approved', 'ordered', $actor, 'Purchase order placed with supplier.');
            return $order->refresh();
        });
    }

    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor) {
            $order = PurchaseOrder::query()->lockForUpdate()->with('request')->findOrFail($purchaseOrder->id);
            if (! in_array($order->status, ['draft', 'approved', 'ordered'], true)) {
                throw ValidationException::withMessages(['status' => 'This purchase order cannot be cancelled in its current state.']);
            }
            if ($order->items()->where('received_quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages(['status' => 'A purchase order with received goods cannot be cancelled.']);
            }
            $from = $order->status;
            if (in_array($from, ['approved', 'ordered'], true)) {
                $allocation = BudgetAllocation::query()->lockForUpdate()->findOrFail($order->request->budget_allocation_id);
                $this->budget->releaseCommitment($allocation, $this->budget->cents($order->total_amount), $order, $actor);
            }
            $order->update(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now()]);
            $this->purchaseOrderHistory($order, $from, 'cancelled', $actor, 'Purchase order cancelled.');
            return $order->refresh();
        });
    }

    public function receive(PurchaseOrderItem $purchaseOrderItem, array $data, User $actor): ProcurementReceipt
    {
        return DB::transaction(function () use ($purchaseOrderItem, $data, $actor) {
            $existing = ProcurementReceipt::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->load('items');
            }

            $item = PurchaseOrderItem::query()->lockForUpdate()->with('purchaseOrder')->findOrFail($purchaseOrderItem->id);
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($item->purchase_order_id);
            if (! in_array($order->status, ['approved', 'ordered', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchase_order_item_id' => 'This purchase order is not ready for receiving.']);
            }

            $quantityCents = $this->budget->cents($data['quantity']);
            $remainingCents = $this->budget->cents($item->ordered_quantity) - $this->budget->cents($item->received_quantity);
            if ($quantityCents <= 0 || $quantityCents > $remainingCents) {
                throw ValidationException::withMessages(['quantity' => 'Received quantity exceeds the outstanding purchase order quantity.']);
            }

            if ($item->supply_item_id && (blank($data['batch_number']) || blank($data['location_id']))) {
                throw ValidationException::withMessages(['batch_number' => 'Supply receipts require a batch number and AISMS location.']);
            }

            $receipt = ProcurementReceipt::create([
                'receipt_reference' => $this->reference('GRN'),
                'purchase_order_id' => $order->id,
                'received_by' => $actor->id,
                'received_on' => $data['received_on'],
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            $movement = null;
            if ($item->supply_item_id) {
                $movement = $this->inventory->receive([
                    'supply_item_id' => $item->supply_item_id,
                    'location_id' => $data['location_id'],
                    'batch_number' => $data['batch_number'],
                    'received_on' => $data['received_on'],
                    'expires_on' => $data['expires_on'] ?? null,
                    'quantity' => $data['quantity'],
                    'idempotency_key' => 'ABPMS-'.$receipt->receipt_reference,
                    'notes' => 'ABPMS goods receipt '.$receipt->receipt_reference,
                ], $actor->id);
            }

            ProcurementReceiptItem::create([
                'procurement_receipt_id' => $receipt->id,
                'purchase_order_item_id' => $item->id,
                'quantity' => $data['quantity'],
                'inventory_movement_id' => $movement?->id,
                'batch_number' => $data['batch_number'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'inventory_location_id' => $data['location_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $newReceived = $this->budget->cents($item->received_quantity) + $quantityCents;
            $item->update(['received_quantity' => $this->budget->money($newReceived)]);
            $allReceived = ! PurchaseOrderItem::where('purchase_order_id', $order->id)
                ->whereColumn('received_quantity', '<', 'ordered_quantity')
                ->exists();
            $from = $order->status;
            $newStatus = $allReceived ? 'received' : 'partially_received';
            $order->update(['status' => $newStatus]);
            if ($from !== $newStatus) {
                $this->purchaseOrderHistory($order, $from, $newStatus, $actor, 'Goods receipt recorded.');
            }

            ActivityLog::log('created', 'Goods receipt '.$receipt->receipt_reference.' recorded.', $receipt->receipt_reference, $actor->id, 'abpms');
            return $receipt->load('items');
        });
    }

    public function linkEquipment(PurchaseOrderItem $purchaseOrderItem, EquipmentAsset $asset, User $actor): PurchaseOrderItem
    {
        return DB::transaction(function () use ($purchaseOrderItem, $asset, $actor) {
            $item = PurchaseOrderItem::query()->lockForUpdate()->with('purchaseOrder')->findOrFail($purchaseOrderItem->id);
            if ($item->equipment_asset_id) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'This purchase order item is already linked to an AEAMS asset.']);
            }
            if (PurchaseOrderItem::where('equipment_asset_id', $asset->id)->exists()) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'This AEAMS asset is already linked to another purchase order item.']);
            }
            $receivedCents = $this->budget->cents($item->receiptItems()->sum('quantity'));
            if (! $item->receiptItems()->exists() || $receivedCents < $this->budget->cents($item->ordered_quantity)) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'The equipment must be physically received before it can be linked.']);
            }

            $latestReceipt = $item->receiptItems()->with('receipt')->latest()->first();
            $asset->update([
                'acquired_on' => $asset->acquired_on ?: $latestReceipt?->receipt?->received_on,
                'acquisition_cost' => $asset->acquisition_cost ?: $item->line_total,
            ]);
            $item->update(['equipment_asset_id' => $asset->id]);
            ActivityLog::log('updated', 'AEAMS asset '.$asset->asset_code.' linked to '.$item->purchaseOrder->purchase_order_number.'.', $asset->asset_code, $actor->id, 'abpms');
            return $item->refresh();
        });
    }

    private function requestHistory(ProcurementRequest $request, ?string $from, string $to, User $actor, ?string $notes): void
    {
        ProcurementStatusHistory::create([
            'procurement_request_id' => $request->id,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'processed_by' => $actor->id,
        ]);
    }

    private function purchaseOrderHistory(PurchaseOrder $order, ?string $from, string $to, User $actor, ?string $notes): void
    {
        ProcurementStatusHistory::create([
            'purchase_order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'processed_by' => $actor->id,
        ]);
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(7));
    }
}
