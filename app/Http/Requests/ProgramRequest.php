<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->canModuleAction('aasms', 'create')
            || $user?->canModuleAction('aasms', 'update');
    }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'], 'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'], 'supply_item_id' => ['nullable', 'exists:supply_items,id'],
            'allocation_limit' => ['nullable', 'numeric', 'min:0.01'], 'requires_farm' => ['nullable', 'boolean'],
            'barangays' => ['nullable', 'string', 'max:1000'], 'activities' => ['nullable', 'string', 'max:1000'],
            'farm_categories' => ['nullable', 'string', 'max:1000'], 'previous_beneficiary_restricted' => ['nullable', 'boolean'],
        ];
    }
}
