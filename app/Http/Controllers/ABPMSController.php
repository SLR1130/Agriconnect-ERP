<?php

namespace App\Http\Controllers;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\BudgetTransaction;
use App\Models\EquipmentAsset;
use App\Models\InventoryLocation;
use App\Models\ProcurementReceipt;
use App\Models\ProcurementRequest;
use App\Models\ProcurementSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SupplyItem;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\ProcurementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ABPMSController extends Controller
{
    public function index(Request $request, BudgetService $budget): View
    {
        $user = $request->user();
        $isStaff = $this->canStaff($user, 'view');
        abort_unless($isStaff, 403);

        $allocations = BudgetAllocation::query()->with('period')->latest()->get();
        $budgetSummaries = $allocations->mapWithKeys(fn (BudgetAllocation $allocation) => [$allocation->id => $budget->summary($allocation)])->all();

        $requestsQuery = ProcurementRequest::query()
            ->with(['allocation.period', 'requester', 'reviewer', 'items.supplyItem'])
            ->latest();
        $requestSearch = trim((string) $request->input('request_search', ''));
        if ($requestSearch !== '') {
            $requestsQuery->where(function ($query) use ($requestSearch) {
                $query->where('request_number', 'like', '%'.$requestSearch.'%')
                    ->orWhere('requesting_office', 'like', '%'.$requestSearch.'%')
                    ->orWhere('purpose', 'like', '%'.$requestSearch.'%')
                    ->orWhereHas('requester', fn ($requester) => $requester->where('name', 'like', '%'.$requestSearch.'%'));
            });
        }
        if (in_array($request->input('request_status'), ['submitted', 'under_review', 'approved', 'rejected'], true)) {
            $requestsQuery->where('status', $request->input('request_status'));
        }
        $requests = $requestsQuery->paginate(15, ['*'], 'requests_page')->withQueryString();

        $ordersQuery = PurchaseOrder::query()
            ->with(['request', 'supplier', 'creator', 'approver', 'items.supplyItem', 'items.equipmentAsset'])
            ->latest();
        $orderSearch = trim((string) $request->input('order_search', ''));
        if ($orderSearch !== '') {
            $ordersQuery->where(function ($query) use ($orderSearch) {
                $query->where('purchase_order_number', 'like', '%'.$orderSearch.'%')
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', '%'.$orderSearch.'%'))
                    ->orWhereHas('request', fn ($procurementRequest) => $procurementRequest->where('request_number', 'like', '%'.$orderSearch.'%'));
            });
        }
        if (in_array($request->input('order_status'), ['draft', 'approved', 'ordered', 'partially_received', 'received', 'cancelled'], true)) {
            $ordersQuery->where('status', $request->input('order_status'));
        }
        $purchaseOrders = $ordersQuery->paginate(15, ['*'], 'orders_page')->withQueryString();
        $receivableItems = PurchaseOrderItem::query()
            ->with(['purchaseOrder', 'supplyItem', 'equipmentAsset'])
            ->whereColumn('received_quantity', '<', 'ordered_quantity')
            ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', ['approved', 'ordered', 'partially_received']))
            ->get();
        $receipts = ProcurementReceipt::query()->with(['purchaseOrder', 'receiver', 'items.inventoryMovement'])->latest()->paginate(15, ['*'], 'receipts_page')->withQueryString();
        $transactions = BudgetTransaction::query()->with(['allocation', 'performer'])->latest()->paginate(15, ['*'], 'transactions_page')->withQueryString();

        return view('modules.abpms.dashboard', [
            'periods' => BudgetPeriod::query()->where('status', 'open')->orderByDesc('year')->get(),
            'allocations' => $allocations,
            'budgetSummaries' => $budgetSummaries,
            'requests' => $requests,
            'purchaseOrders' => $purchaseOrders,
            'receivableItems' => $receivableItems,
            'receipts' => $receipts,
            'transactions' => $transactions,
            'suppliers' => ProcurementSupplier::query()->where('is_active', true)->orderBy('name')->get(),
            'supplyItems' => SupplyItem::query()->where('status', 'active')->orderBy('name')->get(),
            'locations' => InventoryLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'equipmentAssets' => EquipmentAsset::query()->orderBy('name')->get(),
            'stats' => [
                'allocations' => $allocations->count(),
                'authorized' => $allocations->sum(fn ($allocation) => (float) $allocation->authorized_amount),
                'available' => array_sum(array_map(fn ($summary) => (float) $summary['available'], $budgetSummaries)),
                'pending_requests' => ProcurementRequest::whereIn('status', ['submitted', 'under_review'])->count(),
                'open_orders' => PurchaseOrder::whereIn('status', ['draft', 'approved', 'ordered', 'partially_received'])->count(),
                'actual_expenditure' => (float) BudgetTransaction::where('entry_type', 'expenditure')->where('direction', 'debit')->sum('amount'),
            ],
            'canCreate' => $this->canStaff($user, 'create'),
            'canUpdate' => $this->canStaff($user, 'update'),
            'canReview' => $this->canAction($user, 'review'),
            'canApprove' => $this->canApprove($user),
            'canReject' => $this->canAction($user, 'reject'),
            'canManage' => $this->canManage($user),
        ]);
    }

    public function storePeriod(Request $request, BudgetService $budget): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2200', 'unique:budget_periods,year'],
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);
        $budget->createPeriod($data, $request->user());
        return back()->with('status', 'Budget period created.');
    }

    public function storeAllocation(Request $request, BudgetService $budget): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'budget_period_id' => ['required', 'exists:budget_periods,id'],
            'program_code' => ['required', 'string', 'max:80'],
            'funding_category' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'authorized_amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $budget->createAllocation($data, $request->user());
        return back()->with('status', 'Budget allocation created.');
    }

    public function adjustAllocation(Request $request, BudgetAllocation $budgetAllocation, BudgetService $budget): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:2000'],
        ]);
        $budget->adjust($budgetAllocation, $data, $request->user());
        return back()->with('status', 'Budget adjustment recorded in the ledger.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $this->authorizeStaff($request, 'create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', 'unique:procurement_suppliers,name'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'registration_reference' => ['nullable', 'string', 'max:100'],
        ]);
        ProcurementSupplier::create($data + ['is_active' => true]);
        return back()->with('status', 'Supplier registered.');
    }

    public function storeRequest(Request $request, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeStaff($request, 'create');
        $data = $request->validate([
            'budget_allocation_id' => ['required', 'exists:budget_allocations,id'],
            'requesting_office' => ['required', 'string', 'max:150'],
            'purpose' => ['required', 'string', 'max:2000'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_item_id' => ['nullable', 'exists:supply_items,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.estimated_unit_price' => ['required', 'numeric', 'min:0'],
        ]);
        $procurement->createRequest($data, $request->user());
        return back()->with('status', 'Procurement request submitted.');
    }

    public function reviewRequest(Request $request, ProcurementRequest $procurementRequest, ProcurementService $procurement): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:under_review,approved,rejected'],
            'review_notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $this->authorizeAction($request, match ($data['status']) { 'approved' => 'approve', 'rejected' => 'reject', default => 'review' });
        $procurement->reviewRequest($procurementRequest, $data['status'], $data['review_notes'] ?? null, $request->user());
        return back()->with('status', 'Procurement request review saved.');
    }

    public function storePurchaseOrder(Request $request, ProcurementRequest $procurementRequest, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeStaff($request, 'create');
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:procurement_suppliers,id'],
            'expected_delivery_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);
        $procurement->createPurchaseOrder($procurementRequest, $data, $request->user());
        return back()->with('status', 'Purchase order drafted.');
    }

    public function approvePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeApprove($request);
        $procurement->approvePurchaseOrder($purchaseOrder, $request->user());
        return back()->with('status', 'Purchase order approved and budget committed.');
    }

    public function orderPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeApprove($request);
        $procurement->orderPurchaseOrder($purchaseOrder, $request->user());
        return back()->with('status', 'Purchase order marked as ordered.');
    }

    public function cancelPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeApprove($request);
        $procurement->cancelPurchaseOrder($purchaseOrder, $request->user());
        return back()->with('status', 'Purchase order cancelled and commitment handled.');
    }

    public function receive(Request $request, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $request->validate([
            'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'received_on' => ['required', 'date'],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expires_on' => ['nullable', 'date'],
            'location_id' => ['nullable', 'exists:inventory_locations,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        $procurement->receive(PurchaseOrderItem::findOrFail($data['purchase_order_item_id']), $data, $request->user());
        return back()->with('status', 'Goods receipt recorded; AISMS stock was updated only for a physical supply receipt.');
    }

    public function linkEquipment(Request $request, PurchaseOrderItem $purchaseOrderItem, ProcurementService $procurement): RedirectResponse
    {
        $this->authorizeStaff($request, 'update');
        $data = $request->validate(['equipment_asset_id' => ['required', 'exists:equipment_assets,id']]);
        $procurement->linkEquipment($purchaseOrderItem, EquipmentAsset::findOrFail($data['equipment_asset_id']), $request->user());
        return back()->with('status', 'Received purchase item linked to the AEAMS asset registry.');
    }

    public function expenditure(Request $request, BudgetAllocation $budgetAllocation, BudgetService $budget): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
        ]);
        $budget->postExpenditure($budgetAllocation, $data, $request->user());
        return back()->with('status', 'Actual expenditure posted to the budget ledger.');
    }

    public function export(Request $request, BudgetService $budget): Response
    {
        $this->authorizeStaff($request, 'export');
        $rows = BudgetAllocation::with('period')->latest()->get()->map(function (BudgetAllocation $allocation) use ($budget) {
            $summary = $budget->summary($allocation);
            return [$allocation->reference, $allocation->period->year, $allocation->program_code, $allocation->funding_category, $summary['authorized'], $summary['committed'], $summary['actual'], $summary['available']];
        });

        $csv = "Reference,Year,Program,Funding Category,Authorized,Committed,Actual,Available\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', $row))."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="abpms-budget-report.csv"']);
    }

    private function authorizeStaff(Request $request, string $action): void
    {
        abort_unless($this->canStaff($request->user(), $action), 403);
    }

    private function authorizeApprove(Request $request): void
    {
        abort_unless($this->canApprove($request->user()), 403);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->canAction($request->user(), $action), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request->user()), 403);
    }

    private function canStaff(User $user, string $action): bool
    {
        return $user->canModuleAction('abpms', $action);
    }

    private function canApprove(User $user): bool
    {
        return $this->canAction($user, 'approve');
    }

    private function canManage(User $user): bool
    {
        if ($user->isSuperAdministrator()) {
            return true;
        }

        if ($user->hasRole('mao_admin')) {
            return $user->canModuleAction('abpms', 'update');
        }

        return ! in_array($user->roles()->pluck('slug')->first(), ['municipal_agriculturist', 'module_personnel', 'authorized_viewer', 'farmer'], true)
            && ($user->canModuleAction('abpms', 'manage') || $user->canModuleAction('abpms', 'update'));
    }

    private function canAction(User $user, string $action): bool
    {
        if ($user->isSuperAdministrator()) {
            return true;
        }

        if (in_array($action, ['approve', 'reject'], true) && $user->hasRole('mao_admin')) {
            return $user->canModuleAction('abpms', $action);
        }

        return $user->canModuleAction('abpms', $action);
    }
}
