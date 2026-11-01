@php
    $rowKey = "requirements.$index";
    $field = fn (string $key, mixed $fallback = null) => data_get($requirement, $key, $fallback);
    $acceptedTypes = $field('accepted_mime_types', []);
    if (is_string($acceptedTypes)) $acceptedTypes = json_decode($acceptedTypes, true) ?: [];
    $sizeBytes = $field('max_size_bytes');
    $sizeMiB = $field('max_size_mib', $sizeBytes ? round($sizeBytes / 1024 / 1024, 2) : '');
    $requiredValue = $field('required', $field('is_required') === null ? '' : ($field('is_required') ? 'required' : 'optional'));
@endphp
<fieldset class="aas-requirement-row" data-requirement-row>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <legend class="text-sm font-bold text-forest-green">Supporting document</legend>
        <button type="button" class="aas-btn aas-danger" data-requirement-remove>Remove requirement</button>
    </div>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <label class="aas-inline-label">Requirement identifier
            <input class="aas-input mt-1" name="requirements[{{ $index }}][key]" value="{{ $field('key', $field('requirement_key', '')) }}" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="example: proof-of-farm">
            <small class="mt-1 block font-normal text-gray-500">Lowercase letters, numbers, and hyphens; unique in this version.</small>
        </label>
        <label class="aas-inline-label">Display name
            <input class="aas-input mt-1" name="requirements[{{ $index }}][display_name]" value="{{ $field('display_name', '') }}" maxlength="150" placeholder="Enter the MAO-approved name">
        </label>
        <label class="aas-inline-label">Requirement type
            <select class="aas-input mt-1" name="requirements[{{ $index }}][required]">
                <option value="">Choose required or optional</option>
                <option value="required" @selected($requiredValue === 'required')>Required</option>
                <option value="optional" @selected($requiredValue === 'optional')>Optional</option>
            </select>
        </label>
        <label class="aas-inline-label sm:col-span-2">Description
            <textarea class="aas-input mt-1" name="requirements[{{ $index }}][description]" rows="2" maxlength="1500" placeholder="Explain what this document demonstrates">{{ $field('description', '') }}</textarea>
        </label>
        <label class="aas-inline-label sm:col-span-2">Instructions
            <textarea class="aas-input mt-1" name="requirements[{{ $index }}][instructions]" rows="2" maxlength="1500" placeholder="Add only approved submission instructions">{{ $field('instructions', '') }}</textarea>
        </label>
        <fieldset class="sm:col-span-2">
            <legend class="aas-inline-label">Accepted file types for a future upload workflow</legend>
            <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-700">
                @foreach(['application/pdf' => 'PDF', 'image/jpeg' => 'JPEG image', 'image/png' => 'PNG image'] as $mime => $label)
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="requirements[{{ $index }}][accepted_mime_types][]" value="{{ $mime }}" @checked(in_array($mime, $acceptedTypes, true))> {{ $label }}</label>
                @endforeach
            </div>
        </fieldset>
        <label class="aas-inline-label">Maximum size (MiB)
            <input class="aas-input mt-1" name="requirements[{{ $index }}][max_size_mib]" value="{{ $sizeMiB }}" type="number" min="0.1" max="64" step="0.1" inputmode="decimal" placeholder="For example, 5">
        </label>
    </div>
</fieldset>
