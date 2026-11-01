<?php

namespace App\Services;

use App\Models\AssistanceProgram;
use App\Models\Farmer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AssistanceProgramCatalogService
{
    public function __construct(private readonly AgriculturalBusinessCalendar $calendar) {}

    /**
     * Return the same published, date-aware program catalog to both farmer
     * entry points using one Philippine business date for filters and eligibility.
     *
     * @return array{search:string,category:string,availability:string,businessDate:CarbonImmutable,categories:Collection,programs:LengthAwarePaginator,eligibilities:Collection}
     */
    public function forFarmer(Request $request, Farmer $farmer, ProgramEligibilityService $eligibility): array
    {
        $search = trim($request->string('search')->toString());
        $category = trim($request->string('category')->toString());
        $availability = $request->string('availability')->toString() ?: 'available';
        $businessDate = $this->calendar->today();
        $date = $businessDate->toDateString();

        abort_unless(in_array($availability, ['available', 'upcoming', 'ended', 'all'], true), 404);

        $published = AssistanceProgram::query()->where('status', 'open');
        $programQuery = (clone $published)
            ->when($availability === 'available', fn ($query) => $query
                ->where(fn ($dates) => $dates->whereNull('opens_on')->orWhereDate('opens_on', '<=', $date))
                ->where(fn ($dates) => $dates->whereNull('closes_on')->orWhereDate('closes_on', '>=', $date)))
            ->when($availability === 'upcoming', fn ($query) => $query->whereDate('opens_on', '>', $date))
            ->when($availability === 'ended', fn ($query) => $query->whereNotNull('closes_on')->whereDate('closes_on', '<', $date))
            ->when($search !== '', fn ($query) => $query->where(fn ($results) => $results
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')))
            ->when($category !== '', fn ($query) => $query->where('category', $category));

        $programs = $programQuery->with('supplyItem')
            ->orderBy('closes_on')
            ->orderBy('name')
            ->paginate(9)
            ->withQueryString();

        return [
            'search' => $search,
            'category' => $category,
            'availability' => $availability,
            'businessDate' => $businessDate,
            'categories' => (clone $published)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'programs' => $programs,
            'eligibilities' => $programs->getCollection()->mapWithKeys(fn (AssistanceProgram $program) => [
                $program->id => $eligibility->check($program, $farmer, $businessDate),
            ]),
        ];
    }
}
