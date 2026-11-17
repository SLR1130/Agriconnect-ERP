<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BudgetAllocation;
use App\Models\EquipmentAcquisition;
use App\Models\EquipmentAcquisitionAsset;
use App\Models\EquipmentAsset;
use App\Models\EquipmentCategory;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementReceiptItem;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\ProcurementStatusHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(
        private readonly BudgetService $budget,
        private readonly InventoryService $inventory,
        private readonly EquipmentService $equipment,
    ) {}

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
                $itemType = $item['item_type'] ?? (! empty($item['supply_item_id']) ? 'supply' : 'equipment');
                if (! in_array($itemType, ['supply', 'equipment'], true)
                    || ($itemType === 'supply' && empty($item['supply_item_id']))
                    || ($itemType === 'equipment' && ! empty($item['supply_item_id']))) {
                    throw ValidationException::withMessages(['items' => 'Choose a valid supply or equipment type and matching catalog reference for every item.']);
                }

                $quantityCents = $this->budget->cents($item['quantity']);
                if ($quantityCents <= 0) {
                    throw ValidationException::withMessages(['items' => 'Item quantities must be greater than zero.']);
                }
                if ($itemType === 'equipment' && $quantityCents % 100 !== 0) {
                    throw ValidationException::withMessages(['items' => 'Equipment quantities must use whole physical units.']);
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
                    'item_type' => $itemType,
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
                    'item_type' => $item->item_type,
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
        $this->assertCan($actor, 'abpms', 'update');
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'A goods receipt retry key is required.']);
        }

        try {
            return DB::transaction(function () use ($purchaseOrderItem, $data, $actor, $key) {
                if ($existing = ProcurementReceipt::query()->with('items')->where('idempotency_key', $key)->first()) {
                    return $this->assertSameReceipt($existing, $purchaseOrderItem->id, $data, $actor);
                }

                $item = PurchaseOrderItem::query()->lockForUpdate()->with('purchaseOrder')->findOrFail($purchaseOrderItem->id);
                $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($item->purchase_order_id);
                if (! in_array($order->status, ['approved', 'ordered', 'partially_received'], true)) {
                    throw ValidationException::withMessages(['purchase_order_item_id' => 'This purchase order is not ready for receiving.']);
                }
                if (($item->item_type === 'supply' && $item->supply_item_id === null)
                    || ($item->item_type === 'equipment' && $item->supply_item_id !== null)) {
                    throw ValidationException::withMessages(['purchase_order_item_id' => 'This procurement line has an inconsistent type and catalog reference.']);
                }
                if ($item->item_type === 'supply' && ! $actor->canModuleAction('aisms', 'receive')) {
                    throw new AuthorizationException('AISMS receiving permission is required for a supply goods receipt.');
                }

                $quantityCents = $this->budget->cents($data['quantity']);
                $remainingCents = $this->budget->cents($item->ordered_quantity) - $this->budget->cents($item->received_quantity);
                if ($quantityCents <= 0 || $quantityCents > $remainingCents) {
                    throw ValidationException::withMessages(['quantity' => 'Received quantity exceeds the outstanding purchase order quantity.']);
                }
                if ($item->item_type === 'equipment' && $quantityCents % 100 !== 0) {
                    throw ValidationException::withMessages(['quantity' => 'Equipment receipts must use whole physical units.']);
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
                    'idempotency_key' => $key,
                ]);

                $movement = null;
                if ($item->item_type === 'supply') {
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
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ProcurementReceipt::query()->with('items')->where('idempotency_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->assertSameReceipt($existing, $purchaseOrderItem->id, $data, $actor);
        }
    }

    public function linkEquipment(PurchaseOrderItem $purchaseOrderItem, EquipmentAsset $asset, User $actor): PurchaseOrderItem
    {
        $this->assertCan($actor, 'abpms', 'update');
        $this->assertCan($actor, 'aeams', 'update');

        return DB::transaction(function () use ($purchaseOrderItem, $asset, $actor) {
            $item = PurchaseOrderItem::query()->lockForUpdate()->with('purchaseOrder')->findOrFail($purchaseOrderItem->id);
            if ($item->equipment_asset_id) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'This purchase order item is already linked to an AEAMS asset.']);
            }
            if ($item->item_type !== 'equipment' || $item->supply_item_id !== null) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'Only equipment purchase items may be linked to an AEAMS asset.']);
            }
            if ($item->equipmentAcquisitions()->exists()) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'This received equipment item already has registered AEAMS assets.']);
            }
            if ($this->budget->cents($item->ordered_quantity) !== 100) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'Use the received equipment acquisition workflow for purchase items containing multiple units.']);
            }
            if (PurchaseOrderItem::where('equipment_asset_id', $asset->id)->exists()) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'This AEAMS asset is already linked to another purchase order item.']);
            }
            if ($asset->availability_status !== 'available'
                || EquipmentAcquisitionAsset::query()->where('equipment_asset_id', $asset->id)->exists()) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'Choose an available AEAMS asset that is not already linked to a procurement receipt.']);
            }
            $receivedCents = $this->budget->cents($item->receiptItems()->sum('quantity'));
            if (! $item->receiptItems()->exists() || $receivedCents < $this->budget->cents($item->ordered_quantity)) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'The equipment must be physically received before it can be linked.']);
            }

            $receiptItems = $item->receiptItems()->with('receipt')->orderBy('id')->get();
            $latestReceipt = $receiptItems->last();
            if ($receiptItems->count() !== 1 || $this->budget->cents($latestReceipt?->quantity ?? 0) !== 100) {
                throw ValidationException::withMessages(['equipment_asset_id' => 'A single existing AEAMS asset can only be linked to one physically received unit.']);
            }

            $asset = $this->equipment->updateAsset($asset, [
                'acquired_on' => $latestReceipt?->receipt?->received_on,
                'acquisition_cost' => $item->unit_price,
            ], $actor);
            $item->update(['equipment_asset_id' => $asset->id]);
            $acquisition = EquipmentAcquisition::create([
                'purchase_order_item_id' => $item->id,
                'procurement_receipt_item_id' => $latestReceipt->id,
                'category_id' => $asset->category_id,
                'created_by' => $actor->id,
                'idempotency_key' => 'linked-existing-'.$item->id.'-'.$asset->id,
                'quantity' => 1,
                'asset_name' => $asset->name,
                'condition' => $asset->condition,
                'assigned_location' => $asset->assigned_location,
                'acquired_on' => $latestReceipt->receipt?->received_on,
                'unit_acquisition_cost' => $item->unit_price,
            ]);
            EquipmentAcquisitionAsset::create([
                'procurement_equipment_acquisition_id' => $acquisition->id,
                'equipment_asset_id' => $asset->id,
                'unit_number' => 1,
            ]);
            ActivityLog::log('updated', 'AEAMS asset '.$asset->asset_code.' linked to '.$item->purchaseOrder->purchase_order_number.'.', $asset->asset_code, $actor->id, 'abpms');

            return $item->refresh();
        });
    }

    public function createEquipmentAssetsFromReceipt(ProcurementReceiptItem $receiptItem, array $data, User $actor): EquipmentAcquisition
    {
        $this->assertCan($actor, 'abpms', 'update');
        $this->assertCan($actor, 'aeams', 'create');

        $key = trim((string) ($data['idempotency_key'] ?? ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An equipment acquisition retry key is required.']);
        }

        try {
            return DB::transaction(function () use ($receiptItem, $data, $actor, $key) {
                $lockedReceiptItem = ProcurementReceiptItem::query()->lockForUpdate()->findOrFail($receiptItem->id);
                $existing = EquipmentAcquisition::query()->with('assets.equipmentAsset')->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $this->assertSameEquipmentAcquisition($existing, $lockedReceiptItem, $data, $actor);
                }

                $item = PurchaseOrderItem::query()->lockForUpdate()->with('purchaseOrder')->findOrFail($lockedReceiptItem->purchase_order_item_id);
                if ($item->item_type !== 'equipment' || $item->supply_item_id !== null || $lockedReceiptItem->inventory_movement_id !== null) {
                    throw ValidationException::withMessages(['procurement_receipt_item_id' => 'Only a physically received equipment item can create AEAMS assets.']);
                }
                if ($item->equipment_asset_id !== null) {
                    throw ValidationException::withMessages(['procurement_receipt_item_id' => 'This purchase item is already linked to an existing AEAMS asset.']);
                }

                $requestedUnits = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
                if ($requestedUnits === false || $requestedUnits < 1) {
                    throw ValidationException::withMessages(['quantity' => 'Enter a positive whole number of equipment units.']);
                }

                $receiptUnits = intdiv($this->budget->cents($lockedReceiptItem->quantity), 100);
                $registeredUnits = (int) EquipmentAcquisition::query()
                    ->where('procurement_receipt_item_id', $lockedReceiptItem->id)
                    ->sum('quantity');
                $availableUnits = $receiptUnits - $registeredUnits;
                if ($requestedUnits > $availableUnits) {
                    throw ValidationException::withMessages(['quantity' => 'The requested assets exceed the equipment units physically received on this receipt.']);
                }

                $category = EquipmentCategory::query()
                    ->whereKey($data['category_id'])
                    ->where('is_active', true)
                    ->first();
                if (! $category) {
                    throw ValidationException::withMessages(['category_id' => 'Select an active equipment category.']);
                }

                $assetName = trim((string) ($data['asset_name'] ?? $item->description));
                if ($assetName === '') {
                    throw ValidationException::withMessages(['asset_name' => 'An equipment asset name is required.']);
                }

                $condition = $data['condition'] ?? 'good';
                if (! in_array($condition, ['good', 'fair', 'damaged'], true)) {
                    throw ValidationException::withMessages(['condition' => 'Choose a valid equipment condition.']);
                }

                $receipt = $lockedReceiptItem->receipt()->firstOrFail();
                $acquisition = EquipmentAcquisition::create([
                    'purchase_order_item_id' => $item->id,
                    'procurement_receipt_item_id' => $lockedReceiptItem->id,
                    'category_id' => $category->id,
                    'created_by' => $actor->id,
                    'idempotency_key' => $key,
                    'quantity' => $requestedUnits,
                    'asset_name' => $assetName,
                    'condition' => $condition,
                    'assigned_location' => $data['assigned_location'] ?? null,
                    'acquired_on' => $receipt->received_on,
                    'unit_acquisition_cost' => $item->unit_price,
                ]);

                for ($unitNumber = 1; $unitNumber <= $requestedUnits; $unitNumber++) {
                    $unitName = $requestedUnits === 1 ? $assetName : $assetName.' #'.str_pad((string) $unitNumber, 2, '0', STR_PAD_LEFT);
                    $asset = $this->equipment->registerAsset([
                        'asset_code' => 'AGR-EQ-'.str()->upper((string) str()->ulid()),
                        'name' => $unitName,
                        'category_id' => $category->id,
                        'description' => 'Purchased on '.$item->purchaseOrder->purchase_order_number.'; receipt '.$receipt->receipt_reference.'.',
                        'acquired_on' => $receipt->received_on,
                        'acquisition_cost' => $item->unit_price,
                        'condition' => $condition,
                        'availability_status' => 'available',
                        'assigned_location' => $data['assigned_location'] ?? null,
                        'responsible_user_id' => null,
                    ], $actor);

                    EquipmentAcquisitionAsset::create([
                        'procurement_equipment_acquisition_id' => $acquisition->id,
                        'equipment_asset_id' => $asset->id,
                        'unit_number' => $unitNumber,
                    ]);
                }

                ActivityLog::log(
                    'created',
                    $requestedUnits.' equipment asset(s) registered from '.$item->purchaseOrder->purchase_order_number.' / '.$receipt->receipt_reference.'.',
                    $item->purchaseOrder->purchase_order_number,
                    $actor->id,
                    'abpms',
                );

                return $acquisition->load('assets.equipmentAsset');
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = EquipmentAcquisition::query()->with('assets.equipmentAsset')->where('idempotency_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->assertSameEquipmentAcquisition($existing, $receiptItem, $data, $actor);
        }
    }

    private function assertSameReceipt(ProcurementReceipt $receipt, int $purchaseOrderItemId, array $data, User $actor): ProcurementReceipt
    {
        $receiptItem = $receipt->items->first();
        $receiptItemExpiresOn = $receiptItem?->expires_on?->toDateString();
        $expectedExpiresOn = filled($data['expires_on'] ?? null) ? Carbon::parse($data['expires_on'])->toDateString() : null;
        $same = (int) $receipt->received_by === (int) $actor->id
            && $receipt->received_on?->toDateString() === Carbon::parse($data['received_on'])->toDateString()
            && $this->sameNullableText($receipt->notes, $data['notes'] ?? null)
            && (int) $receiptItem?->purchase_order_item_id === $purchaseOrderItemId
            && $this->budget->cents($receiptItem?->quantity ?? 0) === $this->budget->cents($data['quantity'] ?? 0)
            && $this->sameNullableText($receiptItem?->batch_number, $data['batch_number'] ?? null)
            && $receiptItemExpiresOn === $expectedExpiresOn
            && (int) ($receiptItem?->inventory_location_id ?? 0) === (int) ($data['location_id'] ?? 0)
            && $this->sameNullableText($receiptItem?->notes, $data['notes'] ?? null);

        if (! $same) {
            throw ValidationException::withMessages(['idempotency_key' => 'This receipt key was already used for a different goods receipt.']);
        }

        return $receipt;
    }

    private function assertSameEquipmentAcquisition(EquipmentAcquisition $existing, ProcurementReceiptItem $receiptItem, array $data, User $actor): EquipmentAcquisition
    {
        $same = (int) $existing->procurement_receipt_item_id === (int) $receiptItem->id
            && (int) $existing->created_by === (int) $actor->id
            && (int) $existing->quantity === (int) ($data['quantity'] ?? 0)
            && (int) $existing->category_id === (int) ($data['category_id'] ?? 0)
            && trim((string) $existing->asset_name) === trim((string) ($data['asset_name'] ?? $existing->asset_name))
            && $existing->condition === ($data['condition'] ?? 'good')
            && $this->sameNullableText($existing->assigned_location, $data['assigned_location'] ?? null);

        if (! $same) {
            throw ValidationException::withMessages(['idempotency_key' => 'This equipment acquisition key was already used for a different operation.']);
        }

        return $existing;
    }

    private function sameNullableText(?string $left, ?string $right): bool
    {
        $normalize = static fn (?string $value): ?string => filled($value) ? trim($value) : null;

        return $normalize($left) === $normalize($right);
    }

    private function assertCan(User $actor, string $module, string $action): void
    {
        if (! $actor->canModuleAction($module, $action)) {
            throw new AuthorizationException('The account is not authorized for this integrated ERP operation.');
        }
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
