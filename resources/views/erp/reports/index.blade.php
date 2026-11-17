<x-layouts.app>
    <x-slot name="header">Consolidated Agricultural Reports</x-slot>
    <x-slot name="description">Period-filtered summaries from authorized AgriConnect records, with aggregate CSV exports.</x-slot>

    <section class="mb-5 rounded-2xl bg-gradient-to-r from-[#153d2b] to-[#26865c] p-5 text-white md:p-6">
        <p class="text-xs font-black uppercase tracking-[.16em] text-[#e3c878]">Shared ERP reporting</p>
        <h2 class="mt-2 text-xl font-extrabold">{{ $report['title'] }}</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-white/85">This report contains grouped totals and omits direct farmer identifiers. Source access and export availability follow your effective module permissions.</p>
    </section>

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><p class="font-bold">Please correct the report filters.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="erp-surface mb-5 p-4 md:p-5" aria-labelledby="report-filter-heading">
        <h2 id="report-filter-heading" class="mb-3 text-sm font-extrabold text-[#153d2b]">Choose a report and filter</h2>
        <form method="GET" action="{{ route('erp.reports.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2"><label for="report-type" class="erp-field-label">Report</label><select id="report-type" name="report" class="erp-input" required>@foreach($reports as $key => $label)<option value="{{ $key }}" @selected($reportKey === $key)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="report-from" class="erp-field-label">From</label><input id="report-from" name="from" type="date" class="erp-input" value="{{ $filters['from'] }}" required></div>
            <div><label for="report-to" class="erp-field-label">Through</label><input id="report-to" name="to" type="date" class="erp-input" value="{{ $filters['to'] }}" min="{{ $filters['from'] }}" required></div>
            @if($reportKey === 'apms' && $seasons->isNotEmpty())
                <div><label for="report-season" class="erp-field-label">Season</label><select id="report-season" name="season" class="erp-input"><option value="">All seasons</option>@foreach($seasons as $season)<option value="{{ $season->id }}" @selected((int) $filters['season'] === (int) $season->id)>{{ $season->name }}</option>@endforeach</select></div>
            @endif
            @if(count($statusOptions))
                <div><label for="report-status" class="erp-field-label">Status</label><select id="report-status" name="status" class="erp-input"><option value="">All statuses</option>@foreach($statusOptions as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select></div>
            @endif
            <div class="flex items-end gap-2"><button type="submit" class="erp-button erp-button-primary">Generate</button><a href="{{ route('erp.reports.index', ['report' => $reportKey]) }}" class="erp-button erp-button-secondary">Reset</a></div>
        </form>
    </section>

    <section class="erp-surface overflow-hidden" aria-labelledby="report-data-heading">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 md:flex-row md:items-center md:justify-between md:px-5">
            <div><h2 id="report-data-heading" class="text-base font-extrabold text-[#243324]">{{ $report['title'] }}</h2><p class="mt-1 text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($filters['from'])->format('M j, Y') }} through {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('M j, Y') }} · Generated {{ $generatedAt->format('M j, Y g:i A') }}</p></div>
            <div class="flex flex-wrap items-center gap-2"><span class="erp-pill">{{ count($report['rows']) }} aggregate rows</span>@if($canExport)<a href="{{ route('erp.reports.index', array_merge(request()->query(), ['report' => $reportKey, 'format' => 'csv'])) }}" class="erp-button erp-button-secondary">Download CSV</a>@else<span class="text-xs text-gray-500">Export permission not assigned</span>@endif</div>
        </div>
        @if(count($report['rows']))
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-100 text-left text-sm"><thead class="bg-[#f8faf7] text-[.68rem] uppercase tracking-wide text-gray-500"><tr>@foreach($report['columns'] as $column)<th class="whitespace-nowrap px-4 py-3 font-bold">{{ $column }}</th>@endforeach</tr></thead><tbody class="divide-y divide-gray-100">@foreach($report['rows'] as $row)<tr class="hover:bg-[#fbfcfa]">@foreach($row as $cell)<td class="max-w-[24rem] break-words px-4 py-3 text-gray-700">{{ is_numeric($cell) && is_float($cell) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        @else
            <div class="px-5 py-12 text-center"><span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-[#e7f0e8] text-lg text-[#26865c]" aria-hidden="true">—</span><p class="mt-3 text-sm font-bold text-[#243324]">No records for this selection</p><p class="mt-1 text-xs text-gray-500">Try a wider date range or clear the status filter.</p></div>
        @endif
        <div class="border-t border-gray-100 px-4 py-3 text-xs leading-5 text-gray-500 md:px-5">
            <p><strong>Filters:</strong> {{ $filters['from'] }} through {{ $filters['to'] }} · status {{ $filters['status'] ?: 'all' }}@if($reportKey === 'apms') · season {{ $seasons->firstWhere('id', $filters['season'])?->name ?? 'all' }}@endif</p>
            <p class="mt-1"><strong>Data scope:</strong> {{ strtoupper($report['module']) }} ·
                @switch($reportKey)
                    @case('ffrms') Farmer registrations are grouped by current registration status and barangay; the selected period uses farmer creation dates. No direct farmer identifiers are included. @break
                    @case('apms') Crop records use harvest dates, while livestock and fisheries use recorded dates. Quantities remain separated by unit; the selected season applies to crop records. @break
                    @case('aisms') Usable balances are current as of today; positive expired stock is shown separately. Posted movements use the selected period and retain their supply units. @break
                    @case('aasms') Application rows use submission dates and current status; distribution rows use distribution timestamps and preserve each supply unit. @break
                    @case('aeams') Asset availability is a current snapshot; equipment request counts use creation dates. Status categories are shown separately. @break
                    @case('abpms') Allocations overlap the selected dates; committed, actual, and available amounts are current shared-ledger balances as of report generation. Values are in Philippine pesos. @break
                    @case('procurement') Requests and orders use creation dates; receipts and equipment acquisitions use their recorded operation dates. Financial values are in Philippine pesos. @break
                @endswitch
            </p>
        </div>
    </section>
</x-layouts.app>
