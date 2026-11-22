@php
    $editingFarm = isset($farm);
    $useSubmittedValues = !$editingFarm || (string) old('_farm_id') === (string) $farm->id;
    $showFarmErrors = !$editingFarm || (string) old('_farm_id') === (string) $farm->id;
    $fieldValue = fn (string $key, mixed $fallback = null) => $useSubmittedValues ? old($key, $fallback) : $fallback;
    $prefix = $prefix ?? ($editingFarm ? 'farm-'.$farm->id : 'new-farm');
@endphp
@if($editingFarm)<input type="hidden" name="_farm_id" value="{{ $farm->id }}">@endif
@php
    $barangayValue = (string) $fieldValue('barangay', $editingFarm ? $farm->barangay : $farmer->barangay);
    $barangayChoices = collect($barangays ?? [])->values();
    if ($editingFarm && $barangayValue !== '' && ! $barangayChoices->contains($barangayValue)) {
        $barangayChoices->prepend($barangayValue);
    }
    $savedOutline = $editingFarm && $farm->boundary_coordinates ? json_encode($farm->boundary_coordinates) : '';
@endphp
@if($wizard ?? false)
<div class="grid gap-3 sm:grid-cols-2" data-farm-step-panel="1">
    <div class="sm:col-span-2"><h3 class="wizard-step-title" tabindex="-1" data-farm-step-heading>Farm basics</h3><p class="wizard-step-copy">Start with the farm name and barangay where it is located.</p></div>
@endif
<div class="farmer-field sm:col-span-2"><label for="{{ $prefix }}-name">Farm name <span aria-hidden="true">*</span></label><input id="{{ $prefix }}-name" name="name" maxlength="255" value="{{ $fieldValue('name', $editingFarm ? $farm->name : '') }}" required autocomplete="organization" @if($showFarmErrors && $errors->has('name')) aria-invalid="true" aria-describedby="{{ $prefix }}-name-error" @endif>@if($showFarmErrors)@error('name')<small id="{{ $prefix }}-name-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
<div class="farmer-field"><label for="{{ $prefix }}-barangay">Barangay in Bongabong <span aria-hidden="true">*</span></label><select id="{{ $prefix }}-barangay" name="barangay" data-farm-location-barangay required autocomplete="address-level3" @if($showFarmErrors && $errors->has('barangay')) aria-invalid="true" aria-describedby="{{ $prefix }}-barangay-error" @endif><option value="">Choose the farm's barangay</option>@foreach($barangayChoices as $barangay)<option value="{{ $barangay }}" @selected($barangayValue === $barangay)>{{ $barangay }}{{ $barangay === $barangayValue && ! collect($barangays ?? [])->contains($barangay) ? ' (current record; confirm or change)' : '' }}</option>@endforeach</select>@if($showFarmErrors)@error('barangay')<small id="{{ $prefix }}-barangay-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
@if($wizard ?? false)</div>
<div class="grid gap-3 sm:grid-cols-2" data-farm-step-panel="2">
    <div class="sm:col-span-2"><h3 class="wizard-step-title" tabindex="-1" data-farm-step-heading>Pin your farm location</h3><p class="wizard-step-copy">Choose the farm on the map or let your device find where you are. This pin is a location reference, not a surveyed property boundary.</p></div>
@endif
<div class="farmer-field sm:col-span-2"><label for="{{ $prefix }}-location">Farm sitio or nearby landmark <span class="ui-optional-label">Optional</span></label><input id="{{ $prefix }}-location" name="location_details" data-farm-location-details maxlength="255" value="{{ $fieldValue('location_details', $editingFarm ? $farm->location_details : '') }}" placeholder="Public sitio, purok, road, or landmark" @if($showFarmErrors && $errors->has('location_details')) aria-invalid="true" aria-describedby="{{ $prefix }}-location-error" @endif>@if($showFarmErrors)@error('location_details')<small id="{{ $prefix }}-location-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
@if($showFarmErrors)@error('location')<p class="ui-field-error sm:col-span-2" role="alert">{{ $message }}</p>@enderror @endif
@include('farmer.partials.farm-location-map', [
    'farm' => $editingFarm ? $farm : null,
    'prefix' => $prefix,
    'fieldsetId' => $prefix.'-location',
    'mapId' => $prefix.'-map',
    'fieldValue' => $fieldValue,
    'showFarmErrors' => $showFarmErrors,
])
@if($wizard ?? false)
</div>
<div class="grid gap-3 sm:grid-cols-2" data-farm-step-panel="3">
    <div class="sm:col-span-2"><h3 class="wizard-step-title" tabindex="-1" data-farm-step-heading>Farm details</h3><p class="wizard-step-copy">Add the farm area, land arrangement, and what you grow or raise.</p></div>
@endif
<div class="grid gap-3 sm:grid-cols-2">
    @if($wizard ?? false)<div class="sm:col-span-2"><h4 class="wizard-step-title">Farm area and ownership</h4><p class="wizard-step-copy">Area is optional. Tell us the land arrangement so farm records are clear.</p></div>@endif
<div class="farmer-field"><label for="{{ $prefix }}-area">Farm area</label><input id="{{ $prefix }}-area" name="area" type="number" min="0.01" step="0.01" inputmode="decimal" value="{{ $fieldValue('area', $editingFarm ? $farm->area : '') }}" aria-describedby="{{ $prefix }}-area-help @if($showFarmErrors && $errors->has('area')) {{ $prefix }}-area-error @endif" @if($showFarmErrors && $errors->has('area')) aria-invalid="true" @endif><small id="{{ $prefix }}-area-help" data-farm-area-feedback role="status" aria-live="polite">Saving an outline fills this field with an approximate area. You can also enter it manually.</small>@if($showFarmErrors)@error('area')<small id="{{ $prefix }}-area-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
<div class="farmer-field"><label for="{{ $prefix }}-unit">Area unit <span aria-hidden="true">*</span></label><select id="{{ $prefix }}-unit" name="area_unit" required @if($showFarmErrors && $errors->has('area_unit')) aria-invalid="true" aria-describedby="{{ $prefix }}-unit-error" @endif>@foreach(['hectare'=>'Hectares','square_meter'=>'Square meters'] as $value=>$label)<option value="{{ $value }}" @selected($fieldValue('area_unit', $editingFarm ? ($farm->area_unit ?: 'hectare') : 'hectare') === $value)>{{ $label }}</option>@endforeach</select>@if($showFarmErrors)@error('area_unit')<small id="{{ $prefix }}-unit-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
<div class="farmer-field"><label for="{{ $prefix }}-ownership">Land arrangement <span aria-hidden="true">*</span></label><select id="{{ $prefix }}-ownership" name="ownership_type" required @if($showFarmErrors && $errors->has('ownership_type')) aria-invalid="true" aria-describedby="{{ $prefix }}-ownership-error" @endif>@foreach(['owner'=>'I own this farm','tenant'=>'I am a tenant','leaseholder'=>'I am a leaseholder','other'=>'Other arrangement'] as $value=>$label)<option value="{{ $value }}" @selected($fieldValue('ownership_type', $editingFarm ? $farm->ownership_type : 'owner') === $value)>{{ $label }}</option>@endforeach</select>@if($showFarmErrors)@error('ownership_type')<small id="{{ $prefix }}-ownership-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
</div>
<div class="grid gap-3 sm:grid-cols-2">
    @if($wizard ?? false)<div class="sm:col-span-2"><h4 class="wizard-step-title">Farm activities and usual products</h4><p class="wizard-step-copy">Tell the MAO what this farm normally grows or raises.</p></div>@endif
<div class="farmer-field sm:col-span-2"><label for="{{ $prefix }}-activities">Farm activities</label><input id="{{ $prefix }}-activities" name="agricultural_activities" maxlength="1000" value="{{ $fieldValue('agricultural_activities', $editingFarm ? implode(', ', $farm->agricultural_activities ?? []) : '') }}" placeholder="For example: rice farming, vegetable growing, poultry raising" @if($showFarmErrors && $errors->has('agricultural_activities')) aria-invalid="true" aria-describedby="{{ $prefix }}-activities-error" @endif><small>Optional. Describe the work carried out on this farm.</small>@if($showFarmErrors)@error('agricultural_activities')<small id="{{ $prefix }}-activities-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
<div class="farmer-field sm:col-span-2"><label for="{{ $prefix }}-categories">Usual farm products</label><input id="{{ $prefix }}-categories" name="categories" maxlength="1000" value="{{ $fieldValue('categories', $editingFarm ? implode(', ', $farm->categories ?? []) : '') }}" placeholder="For example: palay, corn, mango, chicken, tilapia" @if($showFarmErrors && $errors->has('categories')) aria-invalid="true" aria-describedby="{{ $prefix }}-categories-error" @endif><small>Optional. List the crops, animals, or fish products for this farm, separated by commas.</small>@if($showFarmErrors)@error('categories')<small id="{{ $prefix }}-categories-error" class="ui-field-error">{{ $message }}</small>@enderror @endif</div>
<p class="sm:col-span-2 text-sm leading-6 text-slate-600">This is your farm profile, not a report for a specific season. After saving, use the seasonal report buttons on the farm card to send actual crop, livestock, or fishery details to the MAO for review.</p>
</div>
@if($wizard ?? false)</div>@endif
