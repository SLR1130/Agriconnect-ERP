<form method="POST" action="{{ route('modules.aasms.programs.store') }}" class="aas-program-form mt-4">
    @csrf
    <input type="hidden" name="_aasms_workspace" value="programs">
    <input type="hidden" name="_aasms_form" value="create-program">

    <fieldset class="aas-form-section">
        <legend>1. Service identity <span>what farmers will receive</span></legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div><label class="aas-inline-label" for="aasms-create-name">Program name</label><input id="aasms-create-name" class="aas-input" name="name" value="{{ old('_aasms_form') === 'create-program' ? old('name') : '' }}" required></div>
            <div><label class="aas-inline-label" for="aasms-create-category">Category</label><input id="aasms-create-category" class="aas-input" name="category" value="{{ old('_aasms_form') === 'create-program' ? old('category') : '' }}"></div>
            <div class="sm:col-span-2"><label class="aas-inline-label" for="aasms-create-description">Description</label><textarea id="aasms-create-description" class="aas-input" name="description" rows="3">{{ old('_aasms_form') === 'create-program' ? old('description') : '' }}</textarea><p class="mt-1 text-xs text-slate-500">Explain the support, intended farmer group and what the office will provide.</p></div>
        </div>
    </fieldset>

    <fieldset class="aas-form-section">
        <legend>2. Schedule and resource <span>when and what can be allocated</span></legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div><label class="aas-inline-label" for="aasms-create-opens">Opens on</label><input id="aasms-create-opens" class="aas-input" name="opens_on" type="date" value="{{ old('_aasms_form') === 'create-program' ? old('opens_on') : '' }}"></div>
            <div><label class="aas-inline-label" for="aasms-create-closes">Closes on</label><input id="aasms-create-closes" class="aas-input" name="closes_on" type="date" value="{{ old('_aasms_form') === 'create-program' ? old('closes_on') : '' }}"></div>
            <div><label class="aas-inline-label" for="aasms-create-limit">Allocation limit</label><input id="aasms-create-limit" class="aas-input" name="allocation_limit" type="number" min=".01" step=".01" value="{{ old('_aasms_form') === 'create-program' ? old('allocation_limit') : '' }}"></div>
            <div><label class="aas-inline-label" for="aasms-create-supply">Linked supply (optional)</label><select id="aasms-create-supply" class="aas-input" name="supply_item_id"><option value="">Assign during review</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old('supply_item_id') == $item->id && old('_aasms_form') === 'create-program')>{{ $item->code }} · {{ $item->name }}</option>@endforeach</select></div>
        </div>
        <p class="mt-2 text-xs text-slate-500">Dates use the Asia/Manila business calendar. The program stays private until an authorized publisher opens it.</p>
    </fieldset>

    <fieldset class="aas-form-section">
        <legend>3. Eligibility <span>who may apply</span></legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div><label class="aas-inline-label" for="aasms-create-barangays">Eligible barangays</label><input id="aasms-create-barangays" class="aas-input" name="barangays" value="{{ old('_aasms_form') === 'create-program' ? old('barangays') : '' }}" placeholder="Blank means all barangays"></div>
            <div><label class="aas-inline-label" for="aasms-create-activities">Eligible activities</label><input id="aasms-create-activities" class="aas-input" name="activities" value="{{ old('_aasms_form') === 'create-program' ? old('activities') : '' }}" placeholder="e.g. rice, fishing"></div>
            <div><label class="aas-inline-label" for="aasms-create-categories">Farm categories</label><input id="aasms-create-categories" class="aas-input" name="farm_categories" value="{{ old('_aasms_form') === 'create-program' ? old('farm_categories') : '' }}" placeholder="Comma separated"></div>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-sm"><label class="flex items-center gap-2"><input type="checkbox" name="requires_farm" value="1" @checked(old('_aasms_form') === 'create-program' && old('requires_farm'))> Require a registered farm</label><label class="flex items-center gap-2"><input type="checkbox" name="previous_beneficiary_restricted" value="1" @checked(old('_aasms_form') === 'create-program' && old('previous_beneficiary_restricted'))> Restrict previous beneficiaries</label></div>
    </fieldset>

    <fieldset class="aas-form-section">
        <legend>4. Supporting documents <span>evidence required from applicants</span></legend>
        @include('modules.aasms.partials.requirement-editor', ['formKey' => 'create-program', 'requirementItems' => []])
    </fieldset>

    <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-xs text-slate-500">This saves a private draft. It will not appear to farmers until requirements and publication are approved.</p><button class="aas-btn aas-primary" type="submit"><x-ui.icon name="success" size="sm"/>Save private program</button></div>
</form>
