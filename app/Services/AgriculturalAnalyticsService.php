<?php

namespace App\Services;

use App\Models\BudgetAllocation;
use App\Models\EquipmentRequest;
use App\Models\Farmer;
use App\Models\PestDiseaseIncident;
use App\Models\SupplyItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgriculturalAnalyticsService
{
    public function __construct(private readonly BudgetService $budget) {}

    /**
     * Return current operational summaries and period activity only for modules
     * the signed-in account can view under the effective authorization policy.
     *
     * @return array<string, mixed>
     */
    public function dashboard(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $seasonId = null): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $sections = [];

        if ($user->canModuleAction('ffrms', 'view')) {
            $sections['ffrms'] = $this->farmerAnalytics($from, $to);
        }

        if ($user->canModuleAction('apms', 'view')) {
            $sections['apms'] = $this->productionAnalytics($from, $to, $seasonId);
        }

        if ($user->canModuleAction('aisms', 'view')) {
            $sections['aisms'] = $this->inventoryAnalytics($from, $to);
        }

        if ($user->canModuleAction('aasms', 'view')) {
            $sections['aasms'] = $this->assistanceAnalytics($from, $to);
        }

        if ($user->canModuleAction('aeams', 'view')) {
            $sections['aeams'] = $this->equipmentAnalytics($from, $to);
        }

        if ($user->canModuleAction('abpms', 'view')) {
            $sections['abpms'] = $this->financeAnalytics($from, $to);
        }

        return [
            'sections' => $sections,
            'seasons' => isset($sections['apms'])
                ? DB::table('agricultural_seasons')->orderByDesc('starts_on')->get(['id', 'name', 'starts_on', 'ends_on'])
                : collect(),
            'selected_season' => $seasonId,
            'period' => ['from' => $fromDate, 'to' => $toDate],
        ];
    }

    /** @return array<string, string> */
    public function availableReports(User $user): array
    {
        $reports = [
            'ffrms' => ['ffrms' => 'Farmer and farm registrations'],
            'apms' => ['apms' => 'Agricultural production and monitoring'],
            'aisms' => ['aisms' => 'Inventory balance and stock movements'],
            'aasms' => ['aasms' => 'Assistance applications and distributions'],
            'aeams' => ['aeams' => 'Equipment and utilization'],
            'abpms' => ['abpms' => 'Budget allocations and ledger balances', 'procurement' => 'Procurement and receiving'],
        ];
        $available = [];

        foreach ($reports as $module => $options) {
            if ($user->canModuleAction($module, 'view')) {
                $available += $options;
            }
        }

        return $available;
    }

    /**
     * Build an aggregate report without including farmer names, emails, phone
     * numbers, or other direct personal identifiers.
     *
     * @return array{title: string, module: string, columns: list<string>, rows: list<list<string|int|float>>}
     */
    public function report(User $user, string $report, CarbonImmutable $from, CarbonImmutable $to, ?int $seasonId = null, ?string $status = null): array
    {
        $module = $report === 'procurement' ? 'abpms' : $report;
        abort_unless(in_array($module, ['ffrms', 'apms', 'aisms', 'aasms', 'aeams', 'abpms'], true)
            && $user->canModuleAction($module, 'view'), 403);

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return match ($report) {
            'ffrms' => $this->farmerReport($from, $to, $status),
            'apms' => $this->productionReport($fromDate, $toDate, $seasonId, $status),
            'aisms' => $this->inventoryReport($from, $to),
            'aasms' => $this->assistanceReport($from, $to, $status),
            'aeams' => $this->equipmentReport($from, $to, $status),
            'abpms' => $this->budgetReport($from, $to),
            'procurement' => $this->procurementReport($from, $to, $status),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    private function farmerAnalytics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $farmerSummary = DB::table('farmers')->selectRaw(
            "COUNT(*) as total, SUM(CASE WHEN registration_status = 'verified' THEN 1 ELSE 0 END) as verified, SUM(CASE WHEN registration_status = 'pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN registration_status = 'rejected' THEN 1 ELSE 0 END) as rejected, SUM(CASE WHEN registration_status = 'returned' THEN 1 ELSE 0 END) as returned, SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_in_period",
            [$from->startOfDay(), $to->endOfDay()],
        )->first();
        $farmSummary = DB::table('farms')->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")->first();
        $barangays = Farmer::query()->select('barangay', DB::raw('COUNT(*) as total'))
            ->whereNotNull('barangay')->where('barangay', '<>', '')
            ->groupBy('barangay')->orderByDesc('total')->limit(8)->get();
        $farmClassifications = DB::table('farms')->select('classification', DB::raw('COUNT(*) as total'))
            ->groupBy('classification')->orderByDesc('total')->limit(8)->get();

        return [
            'title' => 'Farmer and farm records',
            'cards' => [
                $this->card('Registered farmers', (int) $farmerSummary->total, 'All FFRMS identities; one row per farmer.'),
                $this->card('Verified farmers', (int) $farmerSummary->verified, 'Current verified registration status.'),
                $this->card('Pending review', (int) $farmerSummary->pending, 'Current registrations awaiting an initial decision.'),
                $this->card('New in period', (int) $farmerSummary->new_in_period, 'Registrations created during the selected dates.'),
                $this->card('Registered farms', (int) $farmSummary->total, 'Farm records; farmers with multiple farms are not duplicated.'),
                $this->card('Active farms', (int) $farmSummary->active, 'Farm records whose current FFRMS status is active.'),
            ],
            'breakdowns' => [[
                'title' => 'Farmers by recorded barangay',
                'rows' => $barangays->map(fn ($row) => ['label' => $row->barangay, 'value' => (int) $row->total, 'unit' => 'farmers'])->all(),
            ], [
                'title' => 'Current registration outcomes',
                'rows' => collect(['verified' => $farmerSummary->verified, 'pending' => $farmerSummary->pending, 'rejected' => $farmerSummary->rejected, 'returned' => $farmerSummary->returned])
                    ->map(fn ($value, $key) => ['label' => ucfirst(str_replace('_', ' ', $key)), 'value' => (int) $value, 'unit' => 'farmers'])->values()->all(),
            ], [
                'title' => 'Registered farms by classification',
                'rows' => $farmClassifications->map(fn ($row) => [
                    'label' => filled($row->classification) ? $row->classification : 'Not recorded',
                    'value' => (int) $row->total,
                    'unit' => 'farms',
                ])->all(),
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function productionAnalytics(CarbonImmutable $from, CarbonImmutable $to, ?int $seasonId): array
    {
        $crops = DB::table('crop_production_records')->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($seasonId, fn (Builder|EloquentBuilder $query) => $query->where('season_id', $seasonId));
        $monthExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(harvested_on, '%Y-%m')",
            'pgsql' => "to_char(harvested_on, 'YYYY-MM')",
            'sqlsrv' => "FORMAT(harvested_on, 'yyyy-MM')",
            default => "strftime('%Y-%m', harvested_on)",
        };
        $monthlyYield = DB::table('crop_production_records')->select('yield_unit')
            ->selectRaw($monthExpression.' as month, SUM(yield_quantity) as quantity, COUNT(*) as records')
            ->whereNotNull('yield_quantity')->whereBetween('harvested_on', [$from->toDateString(), $to->toDateString()])
            ->when($seasonId, fn (Builder|EloquentBuilder $query) => $query->where('season_id', $seasonId))
            ->groupBy('month', 'yield_unit')->orderBy('month')->orderBy('yield_unit')->get();
        $yield = $monthlyYield->groupBy('yield_unit')->map(fn (Collection $rows, string $unit) => (object) [
            'yield_unit' => $unit,
            'quantity' => $rows->sum('quantity'),
            'records' => $rows->sum('records'),
        ])->values();
        $livestock = DB::table('livestock_records')->select('animal_type', DB::raw('SUM(quantity) as quantity'), DB::raw('COUNT(*) as records'))
            ->where('status', 'active')->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('animal_type')->orderByDesc('quantity')->limit(8)->get();
        $fisheries = DB::table('fishery_records')->select('production_unit', DB::raw('SUM(production_quantity) as quantity'), DB::raw('COUNT(*) as records'))
            ->whereNotNull('production_quantity')->whereBetween('recorded_on', [$from->toDateString(), $to->toDateString()])
            ->groupBy('production_unit')->orderBy('production_unit')->get();
        $incidents = PestDiseaseIncident::query()->whereIn('status', ['reported', 'investigating'])
            ->whereBetween('reported_on', [$from->toDateString(), $to->toDateString()])->count();

        return [
            'title' => 'Agricultural production and monitoring',
            'cards' => [
                $this->card('Crop records', $crops->count(), 'Records created during the selected dates and season.'),
                $this->card('Active livestock records', (int) $livestock->sum('records'), 'Active livestock rows by their recorded date during the selected period.'),
                $this->card('Fishery records', (int) $fisheries->sum('records'), 'Fishery rows by their recorded date during the selected period.'),
                $this->card('Open incidents', $incidents, 'Reported or investigating incidents reported during the selected dates.'),
                $this->card('Monitoring visits', DB::table('farm_monitoring_records')->whereBetween('visited_on', [$from->toDateString(), $to->toDateString()])->count(), 'Visits by their recorded visit date.'),
            ],
            'breakdowns' => [[
                'title' => 'Harvested crop yield by unit',
                'rows' => $yield->map(fn ($row) => ['label' => $row->yield_unit, 'value' => (float) $row->quantity, 'unit' => $row->yield_unit, 'detail' => (int) $row->records.' harvest records'])->all(),
            ], [
                'title' => 'Monthly harvested crop yield by unit',
                'rows' => $monthlyYield->map(fn ($row) => ['label' => CarbonImmutable::parse($row->month.'-01')->format('M Y').' · '.$row->yield_unit, 'value' => (float) $row->quantity, 'unit' => $row->yield_unit])->all(),
            ], [
                'title' => 'Active livestock reported by type',
                'rows' => $livestock->map(fn ($row) => ['label' => $row->animal_type, 'value' => (int) $row->quantity, 'unit' => 'head', 'detail' => (int) $row->records.' records'])->all(),
            ], [
                'title' => 'Fisheries production by unit',
                'rows' => $fisheries->map(fn ($row) => ['label' => $row->production_unit, 'value' => (float) $row->quantity, 'unit' => $row->production_unit, 'detail' => (int) $row->records.' records'])->all(),
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function inventoryAnalytics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $stockItems = $this->stockItems();
        $movements = DB::table('inventory_movements')->select('movement_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(quantity) as quantity'))
            ->where('status', 'posted')->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('movement_type')->orderBy('movement_type')->get();
        $stockByUnit = $stockItems->groupBy('unit')->map(fn (Collection $items, string $unit) => [
            'label' => $unit,
            'value' => round($items->sum(fn ($item) => (float) $item->usable_quantity), 2),
            'unit' => $unit,
        ])->values()->all();
        $expiredBatches = DB::table('supply_batches')->where('quantity_available', '>', 0)
            ->whereDate('expires_on', '<', today())->count();
        $lowStockItems = $stockItems->filter(fn ($item) => (float) $item->usable_quantity <= (float) $item->minimum_stock)->count();

        return [
            'title' => 'Inventory and supply',
            'cards' => [
                $this->card('Active supply items', SupplyItem::query()->where('status', 'active')->count(), 'Catalog items, not stock quantity.'),
                $this->card('Low-stock items', $lowStockItems, 'Usable, unexpired stock at or below each item minimum.'),
                $this->card('Expired batches', $expiredBatches, 'Batches with positive physical balance and past expiry; excluded from usable stock.'),
                $this->card('Receipts in period', (int) $movements->firstWhere('movement_type', 'receipt')?->total, 'Posted receipt movements in the selected dates.'),
                $this->card('Releases in period', (int) $movements->firstWhere('movement_type', 'release')?->total, 'Posted release movements in the selected dates.'),
            ],
            'breakdowns' => [[
                'title' => 'Current usable stock by unit',
                'rows' => $stockByUnit,
            ], [
                'title' => 'Posted movements in period',
                'rows' => $movements->map(fn ($row) => [
                    'label' => ucfirst($row->movement_type),
                    'value' => (int) $row->total,
                    'unit' => 'transactions',
                    'detail' => 'Recorded quantity: '.number_format((float) $row->quantity, 2),
                ])->all(),
            ]],
            'stock_items' => $stockItems->take(10)->map(fn ($item) => [
                'name' => $item->name,
                'code' => $item->code,
                'usable' => (float) $item->usable_quantity,
                'minimum' => (float) $item->minimum_stock,
                'unit' => $item->symbol,
                'low' => (float) $item->usable_quantity <= (float) $item->minimum_stock,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function assistanceAnalytics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $applications = DB::table('assistance_applications')->select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->groupBy('status')->orderBy('status')->get();
        $distributed = DB::table('assistance_distributions as d')
            ->join('supply_batches as b', 'b.id', '=', 'd.supply_batch_id')
            ->join('supply_items as i', 'i.id', '=', 'b.supply_item_id')
            ->join('units_of_measurement as u', 'u.id', '=', 'i.unit_id')
            ->select('u.symbol', DB::raw('SUM(d.quantity) as quantity'), DB::raw('COUNT(*) as total'))
            ->where('d.status', 'distributed')->whereBetween('d.distributed_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('u.symbol')->orderBy('u.symbol')->get();
        $distributedCount = (int) $distributed->sum('total');
        $approvedAllocation = DB::table('assistance_applications as a')
            ->join('supply_items as i', 'i.id', '=', 'a.approved_supply_item_id')
            ->join('units_of_measurement as u', 'u.id', '=', 'i.unit_id')
            ->select('u.symbol', DB::raw('SUM(a.approved_quantity) as quantity'), DB::raw('COUNT(*) as total'))
            ->whereIn('a.status', ['approved', 'completed'])->whereNotNull('a.approved_quantity')
            ->whereBetween('a.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('u.symbol')->orderBy('u.symbol')->get();
        $activePrograms = DB::table('assistance_programs')->where('status', 'open')
            ->where(fn (Builder|EloquentBuilder $query) => $query->whereNull('opens_on')->orWhereDate('opens_on', '<=', today()))
            ->where(fn (Builder|EloquentBuilder $query) => $query->whereNull('closes_on')->orWhereDate('closes_on', '>=', today()))->count();

        return [
            'title' => 'Agricultural assistance',
            'cards' => [
                $this->card('Available programs', $activePrograms, 'Open programs within their configured date window.'),
                $this->card('Pending applications', (int) $applications->firstWhere('status', 'submitted')?->total, 'Submitted applications created during the selected period.'),
                $this->card('Approved applications', (int) $applications->firstWhere('status', 'approved')?->total, 'Applications submitted in the selected period and currently approved.'),
                $this->card('Completed distributions', $distributedCount, 'Distribution transactions recorded in the selected period.'),
            ],
            'breakdowns' => [[
                'title' => 'Applications submitted in period by current status',
                'rows' => $applications->map(fn ($row) => ['label' => ucfirst(str_replace('_', ' ', $row->status)), 'value' => (int) $row->total, 'unit' => 'applications'])->all(),
            ], [
                'title' => 'Approved allocation by supply unit',
                'rows' => $approvedAllocation->map(fn ($row) => ['label' => $row->symbol, 'value' => (float) $row->quantity, 'unit' => $row->symbol, 'detail' => (int) $row->total.' approved or completed applications'])->all(),
            ], [
                'title' => 'Distributed assistance by supply unit',
                'rows' => $distributed->map(fn ($row) => ['label' => $row->symbol, 'value' => (float) $row->quantity, 'unit' => $row->symbol, 'detail' => (int) $row->total.' distributions'])->all(),
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function equipmentAnalytics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $statuses = DB::table('equipment_assets')->select('availability_status', DB::raw('COUNT(*) as total'))
            ->groupBy('availability_status')->orderBy('availability_status')->get();
        $registeredAssets = (int) $statuses->sum('total');
        $activeAssets = (int) $statuses->reject(fn ($row) => $row->availability_status === 'archived')->sum('total');
        $issued = (int) $statuses->firstWhere('availability_status', 'issued')?->total;
        $reservations = EquipmentRequest::query()->where('status', 'approved')
            ->where('schedule_start', '<=', $to->endOfDay())->where('schedule_end', '>=', $from->startOfDay())
            ->distinct('equipment_asset_id')->count('equipment_asset_id');
        $requests = DB::table('equipment_requests')->select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->groupBy('status')->orderBy('status')->get();

        return [
            'title' => 'Equipment and assets',
            'cards' => [
                $this->card('Registered assets', $registeredAssets, 'Includes archived assets for a complete asset register.'),
                $this->card('Available', (int) $statuses->firstWhere('availability_status', 'available')?->total, 'Current assets marked available by AEAMS.'),
                $this->card('Reserved', $reservations, 'Distinct assets with approved reservations overlapping the selected dates.'),
                $this->card('Issued', $issued, 'Current asset status; excludes archived assets.'),
                $this->card('Under maintenance', (int) $statuses->firstWhere('availability_status', 'maintenance')?->total, 'Assets unavailable while maintenance is active.'),
                $this->card('Utilization', $activeAssets > 0 ? number_format(($issued / $activeAssets) * 100, 1).'%' : '0.0%', 'Issued assets divided by non-archived assets; current snapshot.'),
            ],
            'breakdowns' => [[
                'title' => 'Equipment requests by current status',
                'rows' => $requests->map(fn ($row) => ['label' => ucfirst(str_replace('_', ' ', $row->status)), 'value' => (int) $row->total, 'unit' => 'requests'])->all(),
            ], [
                'title' => 'Assets by availability status',
                'rows' => $statuses->map(fn ($row) => ['label' => ucfirst(str_replace('_', ' ', $row->availability_status)), 'value' => (int) $row->total, 'unit' => 'assets'])->all(),
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function financeAnalytics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $allocations = BudgetAllocation::query()->with('period')
            ->where('status', 'active')
            ->whereHas('period', fn (Builder|EloquentBuilder $query) => $query->whereDate('starts_on', '<=', $to->toDateString())->whereDate('ends_on', '>=', $from->toDateString()))
            ->orderBy('reference')->get();
        $summaries = $this->budget->summaries($allocations);
        $authorized = $committed = $actual = $available = 0;

        foreach ($summaries as $summary) {
            $authorized += $summary['authorized_cents'];
            $committed += $this->budget->cents($summary['committed']);
            $actual += $this->budget->cents($summary['actual']);
            $available += $summary['available_cents'];
        }

        $requestStatuses = DB::table('procurement_requests')->select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->groupBy('status')->orderBy('status')->get();
        $orderStatuses = DB::table('purchase_orders')->select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->groupBy('status')->orderBy('status')->get();
        $outstandingDeliveries = DB::table('purchase_order_items as i')->join('purchase_orders as o', 'o.id', '=', 'i.purchase_order_id')
            ->whereColumn('i.received_quantity', '<', 'i.ordered_quantity')
            ->whereIn('o.status', ['approved', 'ordered', 'partially_received'])->count();
        $receiptCount = DB::table('procurement_receipts')->whereBetween('received_on', [$from->toDateString(), $to->toDateString()])->count();
        $acquisitionCount = DB::table('procurement_equipment_acquisitions')->whereBetween('acquired_on', [$from->toDateString(), $to->toDateString()])->count();

        return [
            'title' => 'Budget and procurement',
            'cards' => [
                $this->card('Authorized budget', $this->formatMoney($authorized), 'Active allocations whose budget period overlaps the selected dates.'),
                $this->card('Committed', $this->formatMoney($committed), 'Net commitments after recorded commitment releases.'),
                $this->card('Expenditure', $this->formatMoney($actual), 'Posted expenditure ledger debits; commitments are not counted as expenditure.'),
                $this->card('Available budget', $this->formatMoney($available), 'BudgetService ledger balance, including credits and all posted debits.'),
                $this->card('Pending requests', (int) $requestStatuses->whereIn('status', ['submitted', 'under_review'])->sum('total'), 'Submitted or under-review requests created in this period.'),
                $this->card('Outstanding deliveries', $outstandingDeliveries, 'Open purchase-order lines with received quantity below ordered quantity.'),
            ],
            'breakdowns' => [[
                'title' => 'Procurement requests by status',
                'rows' => $requestStatuses->map(fn ($row) => ['label' => ucfirst(str_replace('_', ' ', $row->status)), 'value' => (int) $row->total, 'unit' => 'requests'])->all(),
            ], [
                'title' => 'Purchase orders by status',
                'rows' => $orderStatuses->map(fn ($row) => ['label' => ucfirst(str_replace('_', ' ', $row->status)), 'value' => (int) $row->total, 'unit' => 'orders'])->all(),
            ], [
                'title' => 'Receiving and equipment acquisitions',
                'rows' => [
                    ['label' => 'Goods receipts', 'value' => $receiptCount, 'unit' => 'receipts'],
                    ['label' => 'Equipment acquisitions', 'value' => $acquisitionCount, 'unit' => 'acquisitions'],
                ],
            ]],
            'allocations' => $allocations->take(8)->map(function (BudgetAllocation $allocation) use ($summaries) {
                $summary = $summaries[$allocation->id];

                return [
                    'reference' => $allocation->reference,
                    'program' => $allocation->program_code,
                    'period' => $allocation->period?->name,
                    'authorized' => $summary['authorized'],
                    'committed' => $summary['committed'],
                    'actual' => $summary['actual'],
                    'available' => $summary['available'],
                ];
            })->all(),
        ];
    }

    /** @return Collection<int, object> */
    private function stockItems(): Collection
    {
        return DB::table('supply_items as i')
            ->join('units_of_measurement as u', 'u.id', '=', 'i.unit_id')
            ->leftJoin('supply_batches as b', 'b.supply_item_id', '=', 'i.id')
            ->where('i.status', 'active')
            ->select('i.id', 'i.name', 'i.code', 'i.minimum_stock', 'u.symbol')
            ->selectRaw("COALESCE(SUM(CASE WHEN b.status = 'available' AND b.quantity_available > 0 AND (b.expires_on IS NULL OR b.expires_on >= ?) THEN b.quantity_available ELSE 0 END), 0) as usable_quantity", [today()->toDateString()])
            ->groupBy('i.id', 'i.name', 'i.code', 'i.minimum_stock', 'u.symbol')
            ->orderBy('i.name')->get()
            ->map(function ($item) {
                $item->unit = $item->symbol;

                return $item;
            });
    }

    /** @return array<string, mixed> */
    private function farmerReport(CarbonImmutable $from, CarbonImmutable $to, ?string $status): array
    {
        $query = DB::table('farmers')->select('registration_status', 'barangay', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('registration_status', 'barangay')->orderBy('registration_status')->orderBy('barangay');
        if ($status !== null) {
            $query->where('registration_status', $status);
        }

        return $this->reportResult('Farmer and farm registrations', 'ffrms', ['Registration status', 'Barangay', 'Farmers'],
            $query->get()->map(fn ($row) => [$row->registration_status, $row->barangay ?: 'Not recorded', (int) $row->total])->all());
    }

    /** @return array<string, mixed> */
    private function productionReport(string $from, string $to, ?int $seasonId, ?string $status): array
    {
        $crops = DB::table('crop_production_records')->select('crop_type', 'yield_unit', DB::raw('SUM(yield_quantity) as quantity'), DB::raw('COUNT(*) as records'))
            ->whereNotNull('yield_quantity')->whereBetween('harvested_on', [$from, $to])
            ->when($seasonId, fn (Builder|EloquentBuilder $query) => $query->where('season_id', $seasonId))
            ->when($status, fn (Builder|EloquentBuilder $query) => $query->where('status', $status))
            ->groupBy('crop_type', 'yield_unit')->orderBy('crop_type')->get()
            ->map(fn ($row) => ['Crop: '.$row->crop_type, $row->yield_unit, (float) $row->quantity, (int) $row->records]);
        $livestock = DB::table('livestock_records')->select('animal_type', DB::raw('SUM(quantity) as quantity'), DB::raw('COUNT(*) as records'))
            ->whereBetween('recorded_on', [$from, $to])->when($status, fn (Builder|EloquentBuilder $query) => $query->where('status', $status))
            ->groupBy('animal_type')->orderBy('animal_type')->get()
            ->map(fn ($row) => ['Livestock: '.$row->animal_type, 'head', (int) $row->quantity, (int) $row->records]);
        $fishery = DB::table('fishery_records')->select('fishery_type', 'production_unit', DB::raw('SUM(production_quantity) as quantity'), DB::raw('COUNT(*) as records'))
            ->whereNotNull('production_quantity')->whereBetween('recorded_on', [$from, $to])
            ->groupBy('fishery_type', 'production_unit')->orderBy('fishery_type')->get()
            ->map(fn ($row) => ['Fishery: '.$row->fishery_type, $row->production_unit, (float) $row->quantity, (int) $row->records]);

        return $this->reportResult('Agricultural production and monitoring', 'apms', ['Category', 'Unit', 'Reported quantity', 'Records'],
            $crops->concat($livestock)->concat($fishery)->values()->all());
    }

    /** @return array<string, mixed> */
    private function inventoryReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = $this->stockItems();
        $expired = DB::table('supply_batches as b')->join('supply_items as i', 'i.id', '=', 'b.supply_item_id')
            ->join('units_of_measurement as u', 'u.id', '=', 'i.unit_id')
            ->select('i.name', 'i.code', 'u.symbol', DB::raw('SUM(b.quantity_available) as quantity'))
            ->where('b.quantity_available', '>', 0)->whereDate('b.expires_on', '<', today())
            ->groupBy('i.name', 'i.code', 'u.symbol')->get()->keyBy('code');
        $rows = $items->map(function ($item) use ($expired) {
            $expiredQuantity = (float) ($expired->get($item->code)?->quantity ?? 0);

            return [$item->code, $item->name, $item->symbol, (float) $item->usable_quantity, (float) $item->minimum_stock, $expiredQuantity];
        })->all();
        $periodMovements = DB::table('inventory_movements')->select('movement_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(quantity) as quantity'))
            ->where('status', 'posted')->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('movement_type')->get()
            ->map(fn ($row) => ['Movement', $row->movement_type, 'posted transactions', (float) $row->quantity, (int) $row->total, '—']);
        $rows = array_merge($rows, $periodMovements->all());

        return $this->reportResult('Inventory balance and stock movements', 'aisms', ['Code / record type', 'Supply item / movement', 'Unit', 'Usable / movement quantity', 'Minimum / movement count', 'Expired quantity'], $rows);
    }

    /** @return array<string, mixed> */
    private function assistanceReport(CarbonImmutable $from, CarbonImmutable $to, ?string $status): array
    {
        $applicationStatuses = ['submitted', 'approved', 'rejected', 'returned', 'completed'];
        $query = DB::table('assistance_applications as a')->join('assistance_programs as p', 'p.id', '=', 'a.program_id')
            ->select('p.name', 'a.status', DB::raw('COUNT(*) as total'))
            ->whereBetween('a.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when(in_array($status, $applicationStatuses, true), fn (Builder|EloquentBuilder $query) => $query->where('a.status', $status))
            ->groupBy('p.name', 'a.status')->orderBy('p.name')->orderBy('a.status');
        $applications = $status === 'distributed'
            ? collect()
            : $query->get()->map(fn ($row) => [$row->name, 'Application', $row->status, (int) $row->total, '—']);
        $distributions = collect();
        if ($status === null || $status === 'distributed') {
            $distributions = DB::table('assistance_distributions as d')
                ->join('assistance_applications as a', 'a.id', '=', 'd.application_id')
                ->join('assistance_programs as p', 'p.id', '=', 'a.program_id')
                ->join('supply_batches as b', 'b.id', '=', 'd.supply_batch_id')
                ->join('supply_items as i', 'i.id', '=', 'b.supply_item_id')
                ->join('units_of_measurement as u', 'u.id', '=', 'i.unit_id')
                ->select('p.name', 'u.symbol', DB::raw('COUNT(*) as total'), DB::raw('SUM(d.quantity) as quantity'))
                ->where('d.status', 'distributed')->whereBetween('d.distributed_at', [$from->startOfDay(), $to->endOfDay()])
                ->groupBy('p.name', 'u.symbol')->orderBy('p.name')->get()
                ->map(fn ($row) => [$row->name, 'Distribution', $row->symbol, (int) $row->total, (float) $row->quantity]);
        }

        return $this->reportResult('Assistance applications and distributions', 'aasms', ['Program', 'Record type', 'Status / unit', 'Records', 'Distributed quantity'],
            $applications->concat($distributions)->values()->all());
    }

    /** @return array<string, mixed> */
    private function equipmentReport(CarbonImmutable $from, CarbonImmutable $to, ?string $status): array
    {
        $query = DB::table('equipment_assets as a')->leftJoin('equipment_categories as c', 'c.id', '=', 'a.category_id')
            ->select('c.name as category', 'a.availability_status', DB::raw('COUNT(*) as total'))
            ->when($status, fn (Builder|EloquentBuilder $query) => $query->where('a.availability_status', $status))
            ->groupBy('c.name', 'a.availability_status')->orderBy('c.name')->orderBy('a.availability_status');
        $rows = $query->get()->map(fn ($row) => [$row->category ?: 'Uncategorized', $row->availability_status, (int) $row->total])->all();
        $requests = DB::table('equipment_requests')->select('status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($status, fn (Builder|EloquentBuilder $query) => $query->where('status', $status))
            ->groupBy('status')->get()
            ->map(fn ($row) => ['Equipment requests', $row->status, (int) $row->total]);

        return $this->reportResult('Equipment and utilization', 'aeams', ['Asset group', 'Status', 'Assets / requests'], array_merge($rows, $requests->all()));
    }

    /** @return array<string, mixed> */
    private function budgetReport(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $allocations = BudgetAllocation::query()->with('period')
            ->whereHas('period', fn (Builder|EloquentBuilder $query) => $query->whereDate('starts_on', '<=', $to->toDateString())->whereDate('ends_on', '>=', $from->toDateString()))
            ->orderBy('reference')->get();
        $summaries = $this->budget->summaries($allocations);
        $rows = $allocations->map(function (BudgetAllocation $allocation) use ($summaries) {
            $summary = $summaries[$allocation->id];

            return [$allocation->reference, $allocation->program_code, $allocation->period?->name ?? '', $summary['authorized'], $summary['committed'], $summary['actual'], $summary['available']];
        })->all();

        return $this->reportResult('Budget allocations and ledger balances', 'abpms', ['Allocation reference', 'Program', 'Budget period', 'Authorized (PHP)', 'Committed (PHP)', 'Actual expenditure (PHP)', 'Available (PHP)'], $rows);
    }

    /** @return array<string, mixed> */
    private function procurementReport(CarbonImmutable $from, CarbonImmutable $to, ?string $status): array
    {
        $requests = DB::table('procurement_requests')->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(estimated_total) as amount'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->when($status, fn (Builder|EloquentBuilder $query) => $query->where('status', $status))
            ->groupBy('status')->orderBy('status')->get()->map(fn ($row) => ['Procurement requests', $row->status, (int) $row->total, $this->formatMoney($this->budget->cents((string) $row->amount))]);
        $orders = DB::table('purchase_orders')->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(total_amount) as amount'))
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])->when($status, fn (Builder|EloquentBuilder $query) => $query->where('status', $status))
            ->groupBy('status')->orderBy('status')->get()->map(fn ($row) => ['Purchase orders', $row->status, (int) $row->total, $this->formatMoney($this->budget->cents((string) $row->amount))]);
        $operational = collect();
        if ($status === null) {
            $receipts = DB::table('procurement_receipts')->whereBetween('received_on', [$from->toDateString(), $to->toDateString()])->count();
            $outstanding = DB::table('purchase_order_items as i')->join('purchase_orders as o', 'o.id', '=', 'i.purchase_order_id')
                ->whereColumn('i.received_quantity', '<', 'i.ordered_quantity')
                ->whereIn('o.status', ['approved', 'ordered', 'partially_received'])->count();
            $acquisitions = DB::table('procurement_equipment_acquisitions')->whereBetween('acquired_on', [$from->toDateString(), $to->toDateString()])->count();
            $operational = collect([
                ['Goods receipts', 'Received in period', $receipts, 'Receipt records'],
                ['Purchase order lines', 'Outstanding delivery', $outstanding, 'Open lines'],
                ['Equipment acquisitions', 'Recorded in period', $acquisitions, 'Acquisition records'],
            ]);
        }

        return $this->reportResult('Procurement requests, orders, and receiving', 'abpms', ['Record type', 'Status / period', 'Records', 'Total value / measure'],
            $requests->concat($orders)->concat($operational)->values()->all());
    }

    /** @return array<string, mixed> */
    private function reportResult(string $title, string $module, array $columns, array $rows): array
    {
        return compact('title', 'module', 'columns', 'rows');
    }

    private function formatMoney(int $cents): string
    {
        $money = $this->budget->money($cents);
        [$whole, $fraction] = explode('.', $money, 2);
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?: $whole;

        return "\u{20B1}".$whole.'.'.$fraction;
    }

    /** @return array{label: string, value: int|string, note: string} */
    private function card(string $label, int|string $value, string $note): array
    {
        return compact('label', 'value', 'note');
    }
}
