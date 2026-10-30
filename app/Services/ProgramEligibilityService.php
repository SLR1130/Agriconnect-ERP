<?php

namespace App\Services;

use App\Models\{AssistanceApplication, AssistanceProgram, Farmer};
use Illuminate\Validation\ValidationException;

class ProgramEligibilityService
{
    public function check(AssistanceProgram $program, Farmer $farmer): array
    {
        $rules = $program->eligibility_rules ?: [];
        $reasons = [];
        if (($rules['requires_verified_farmer'] ?? true) && ! $farmer->isVerified()) $reasons[] = 'Farmer registration must be verified.';
        if (! $program->isAvailable()) $reasons[] = 'This program is not accepting applications.';
        $farms = $farmer->farms;
        if (($rules['requires_farm'] ?? false) && $farms->isEmpty()) $reasons[] = 'A registered farm is required.';
        if ($barangays = ($rules['barangays'] ?? [])) if (! in_array($farmer->barangay, $barangays, true)) $reasons[] = 'Farmer barangay is not eligible.';
        if ($activities = ($rules['activities'] ?? [])) if (! $farms->contains(fn ($farm) => count(array_intersect($activities, $farm->agricultural_activities ?: [])) > 0)) $reasons[] = 'No registered farm has an eligible agricultural activity.';
        if ($categories = ($rules['farm_categories'] ?? [])) if (! $farms->contains(fn ($farm) => in_array($farm->classification, $categories, true) || count(array_intersect($categories, $farm->categories ?: [])) > 0)) $reasons[] = 'No registered farm has an eligible category.';
        if (($rules['previous_beneficiary_restricted'] ?? false) && AssistanceApplication::where('program_id', $program->id)->where('farmer_id', $farmer->id)->where('status', 'completed')->exists()) $reasons[] = 'Previous beneficiaries cannot reapply to this program.';
        return ['passed' => $reasons === [], 'notes' => implode(' ', $reasons)];
    }
    public function requireEligible(AssistanceProgram $program, Farmer $farmer): array
    {
        $result = $this->check($program, $farmer);
        if (! $result['passed']) throw ValidationException::withMessages(['program' => $result['notes']]);
        return $result;
    }
}
