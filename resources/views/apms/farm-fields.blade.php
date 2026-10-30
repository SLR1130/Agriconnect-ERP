<div class="erp-field">
    <label for="{{ $formKey }}-farmer_id">Farmer <span aria-hidden="true">*</span></label>
    <select id="{{ $formKey }}-farmer_id" class="erp-input" name="farmer_id" data-apms-farmer-select required>
        <option value="">Select a verified farmer</option>
        @foreach($farms->unique('farmer_id')->sortBy(fn ($farm) => $farm->farmer?->user?->name) as $farm)
            <option value="{{ $farm->farmer_id }}" @selected((string) old('farmer_id') === (string) $farm->farmer_id)>{{ $farm->farmer?->user?->name ?: 'Farmer #'.$farm->farmer_id }}</option>
        @endforeach
    </select>
    @error('farmer_id')<span class="apms-field-error">{{ $message }}</span>@enderror
</div>
<div class="erp-field">
    <label for="{{ $formKey }}-farm_id">Farm <span aria-hidden="true">*</span></label>
    <select id="{{ $formKey }}-farm_id" class="erp-input" name="farm_id" data-apms-farm-select required>
        <option value="">Select a farm</option>
        @foreach($farms as $farm)
            <option value="{{ $farm->id }}" data-farmer-id="{{ $farm->farmer_id }}" @selected((string) old('farm_id') === (string) $farm->id)>{{ $farm->name }} — {{ $farm->barangay }}</option>
        @endforeach
    </select>
    @error('farm_id')<span class="apms-field-error">{{ $message }}</span>@enderror
</div>
