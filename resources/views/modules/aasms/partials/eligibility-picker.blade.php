@php
    $pickerValues = is_array($selected ?? null)
        ? collect($selected)
        : collect(preg_split('/\s*,\s*/', trim((string) ($selected ?? '')), -1, PREG_SPLIT_NO_EMPTY));
    $pickerValues = $pickerValues->map(fn ($value) => trim((string) $value))->filter()->unique()->values();
    $pickerOptions = collect($options ?? []);
    $missingValues = $pickerValues->reject(fn ($value) => $pickerOptions->has($value));
    foreach ($missingValues as $missingValue) {
        $pickerOptions->put($missingValue, str($missingValue)->replace('_', ' ')->title()->toString().' (existing value)');
    }
@endphp

<div class="aas-eligibility-picker" data-aasms-picker>
    <span class="aas-inline-label" id="{{ $id }}-label">{{ $label }}</span>
    <details class="aas-picker-details">
        <summary class="aas-picker-summary" aria-labelledby="{{ $id }}-label">
            <span data-aasms-picker-summary data-empty-label="{{ $placeholder }}">{{ $pickerValues->isEmpty() ? $placeholder : $pickerValues->count().' selected' }}</span>
            <x-ui.icon name="chevron" size="sm" />
        </summary>
        <div class="aas-picker-panel" role="group" aria-labelledby="{{ $id }}-label">
            <p class="aas-picker-hint">{{ $hint }}</p>
            @foreach($pickerOptions as $value => $optionLabel)
                <label class="aas-picker-option">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" @checked($pickerValues->contains($value)) data-aasms-picker-choice>
                    <span>{{ $optionLabel ?: $value }}</span>
                </label>
            @endforeach
        </div>
    </details>
</div>
