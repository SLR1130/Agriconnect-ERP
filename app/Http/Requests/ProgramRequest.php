<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'requirements' => ['sometimes', 'array', 'max:12'],
            'requirements.*.key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'requirements.*.display_name' => ['nullable', 'string', 'max:150'],
            'requirements.*.description' => ['nullable', 'string', 'max:1500'],
            'requirements.*.instructions' => ['nullable', 'string', 'max:1500'],
            'requirements.*.required' => ['nullable', Rule::in(['required', 'optional'])],
            'requirements.*.accepted_mime_types' => ['nullable', 'array', 'max:3'],
            'requirements.*.accepted_mime_types.*' => ['string', Rule::in(['application/pdf', 'image/jpeg', 'image/png'])],
            'requirements.*.max_size_mib' => ['nullable', 'numeric', 'min:0.1', 'max:64'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $keys = [];
            foreach ($this->input('requirements', []) as $index => $requirement) {
                if (! is_array($requirement)) {
                    $validator->errors()->add("requirements.$index", 'Requirement details are invalid.');
                    continue;
                }

                $hasContent = collect($requirement)->contains(fn ($value) => is_array($value)
                    ? $value !== []
                    : trim((string) $value) !== '');
                if (! $hasContent) {
                    continue;
                }

                foreach (['key' => 'identifier', 'display_name' => 'display name'] as $field => $label) {
                    if (blank($requirement[$field] ?? null)) {
                        $validator->errors()->add("requirements.$index.$field", "Each document requirement needs a {$label}.");
                    }
                }
                if (empty($requirement['accepted_mime_types'])) {
                    $validator->errors()->add("requirements.$index.accepted_mime_types", 'Choose at least one accepted document type.');
                }
                if (! is_numeric($requirement['max_size_mib'] ?? null)) {
                    $validator->errors()->add("requirements.$index.max_size_mib", 'Set a maximum file size for each document requirement.');
                }

                $key = (string) ($requirement['key'] ?? '');
                if ($key !== '' && in_array($key, $keys, true)) {
                    $validator->errors()->add("requirements.$index.key", 'Requirement identifiers must be unique within this version.');
                }
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }];
    }
}
