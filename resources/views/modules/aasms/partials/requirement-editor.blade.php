@php
    $matchesEditedProgram = ($programId ?? null) === null || (string) old('_aasms_program_id') === (string) ($programId ?? '');
    $requirementRows = old('_aasms_form') === ($formKey ?? null) && $matchesEditedProgram
        ? old('requirements', [])
        : ($requirementItems ?? []);
    $requirementRows = is_array($requirementRows) ? $requirementRows : [];
@endphp
<section class="aas-requirements-editor sm:col-span-2 xl:col-span-3" data-requirement-editor>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="font-bold text-forest-green">Program document requirements</h3>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-600">Configure only documents approved for this program. Saving creates a draft version; a separately authorized publisher makes it effective. Online document upload is not active in Phase 6C.1.</p>
        </div>
        <button class="aas-btn aas-sage" type="button" data-requirement-add>Add requirement</button>
    </div>

    <div class="mt-3 space-y-3" data-requirement-list>
        @foreach($requirementRows as $index => $requirement)
            @include('modules.aasms.partials.requirement-row', ['index' => $index, 'requirement' => $requirement])
        @endforeach
    </div>

    @if(count($requirementRows) === 0)
        <p class="mt-3 rounded-lg bg-[#f8faf7] p-3 text-xs text-gray-600" data-requirement-empty>No document checklist is configured in this version.</p>
    @endif

    <template data-requirement-template>
        @include('modules.aasms.partials.requirement-row', ['index' => '__INDEX__', 'requirement' => []])
    </template>
</section>
