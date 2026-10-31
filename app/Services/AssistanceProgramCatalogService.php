<?php

namespace App\Services;

use App\Models\AssistanceProgram;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AssistanceProgramCatalogService
{
    /**
     * Return the same published, date-aware program catalog to both farmer
     * entry points. Date comparisons intentionally retain the application's
     * current `today()` policy; changing its timezone is a separate decision.
     *
     * @return array{search:string,category:string,availability:string,categories:Collection,programs:\Illuminate\Contracts\Pagination\LengthAwarePaginator,eligibilities:Collection}
     */
    public function forFarmer(Request $request, Farmer $farmer, ProgramEligibilityService $eligibility): array
    {
        $search = trim($request->string('search')->toString());
        $category = trim($request->string('category')->toString());
        $availability = $request->string('availability')->toString() ?: 'available';

        abort_unless(in_array($availability, ['available', 'upcoming', 'ended', 'all'], true), 404);

        $published = AssistanceProgram::query()->where('status', 'open');
        $programQuery = (clone $published)
            ->when($availability === 'available', fn ($query) => $query
                ->where(fn ($dates) => $dates->whereNull('opens_on')->orWhereDate('opens_on', '<=', today()))
                ->where(fn ($dates) => $dates->whereNull('closes_on')->orWhereDate('closes_on', '>=', today())))
            ->when($availability === 'upcoming', fn ($query) => $query->whereDate('opens_on', '>', today()))
            ->when($availability === 'ended', fn ($query) => $query->whereNotNull('closes_on')->whereDate('closes_on', '<', today()))
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
            'categories' => (clone $published)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'programs' => $programs,
            'eligibilities' => $programs->getCollection()->mapWithKeys(fn (AssistanceProgram $program) => [
                $program->id => $eligibility->check($program, $farmer),
            ]),
        ];
    }
}
