<x-layouts.app>
    <x-slot name="title">AASMS Assistance</x-slot>
    <x-slot name="header">Assistance & Subsidy Management</x-slot>
    <x-slot name="description">Coordinate farmer assistance programs, application review, and authorized supply distribution.</x-slot>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        @if($canManagePrograms)
            <a class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-forest-green px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-green" href="{{ route('modules.aasms.dashboard', ['workspace' => 'programs']) }}"><x-ui.icon name="add" size="sm"/>Create assistance program</a>
        @endif
        @if($claimTicketsAvailable)
            <a class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-emerald-200 bg-white px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" href="{{ route('modules.aasms.claim-tickets.index') }}"><x-ui.icon name="ticket" size="sm"/>Assistance claim tickets</a>
        @endif
        @if(\Illuminate\Support\Facades\Schema::hasTable('agricultural_announcements') && auth()->user()->canModuleAction('aasms', 'view'))
            <a class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-emerald-200 bg-white px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" href="{{ route('modules.aasms.announcements') }}"><x-ui.icon name="notifications" size="sm"/>Manage official MAO notices</a>
        @endif
        @if($canIntakeAssist && $staffAssistedIntakeEnabled)
            <a class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-forest-green px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-green" href="{{ route('modules.aasms.intake') }}"><x-ui.icon name="users" size="sm"/>Start staff-assisted intake</a>
        @endif
    </div>

    <style>
        .aas-card{background:#fff;border:1px solid #dfe9e1;border-radius:16px;box-shadow:0 5px 18px #153d2b0a}
        .aas-module-intro{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;align-items:end;margin:1rem 0 1.1rem;padding:1.25rem 1.35rem;border:1px solid #d7e7dc;border-radius:1rem;background:#f1f8f3}
        .aas-module-intro-copy{min-width:0}.aas-kicker{margin:0;color:#23754e;font-size:.68rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.aas-module-intro h2{margin:.3rem 0 0;color:#153d2b;font-size:1.2rem;line-height:1.25}.aas-module-intro p{max-width:58rem;margin:.45rem 0 0;color:#5b6f63;font-size:.86rem;line-height:1.55}.aas-module-intro-note{display:flex;align-items:center;gap:.6rem;max-width:16rem;padding:.7rem .85rem;border:1px solid #cfe2d4;border-radius:.8rem;background:#fff;color:#365246;font-size:.75rem;font-weight:700}.aas-module-intro-note svg{color:#23754e;flex:0 0 auto}
        .aas-program-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem}.aas-program-head h2{margin:.2rem 0 0;color:#153d2b;font-size:1.25rem}.aas-program-head p{max-width:46rem;margin:.4rem 0 0;color:#63756b;font-size:.82rem;line-height:1.5}.aas-step-label{display:inline-flex;align-items:center;gap:.4rem;white-space:nowrap;border:1px solid #d8e7dc;border-radius:999px;padding:.4rem .65rem;background:#f8faf7;color:#436052;font-size:.68rem;font-weight:800}.aas-step-label strong{display:grid;width:1.4rem;height:1.4rem;place-items:center;border-radius:50%;background:#153d2b;color:#fff;font-size:.65rem}
        .aas-program-setup{border:1px solid #cfe2d4;border-radius:1rem;background:#f8faf7;overflow:hidden}.aas-program-setup-top{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid #dfe9e1;background:#fff}.aas-program-setup-title{display:flex;align-items:flex-start;gap:.75rem}.aas-program-setup-icon{display:grid;width:2.25rem;height:2.25rem;flex:0 0 auto;place-items:center;border-radius:.7rem;background:#e7f0e8;color:#153d2b}.aas-program-setup-title h3{margin:0;color:#153d2b;font-size:.95rem}.aas-program-setup-title p{margin:.25rem 0 0;color:#63756b;font-size:.75rem;line-height:1.45}.aas-program-setup-summary{display:inline-flex;min-height:2.75rem;align-items:center;gap:.45rem;cursor:pointer;border:1px solid #153d2b;border-radius:.65rem;padding:.55rem .8rem;background:#153d2b;color:#fff;font-size:.74rem;font-weight:800;white-space:nowrap}.aas-program-setup-summary:hover{background:#1c6a46}.aas-program-setup-summary:focus-visible{outline:3px solid #d5ad55;outline-offset:2px}.aas-program-setup details[open] .aas-program-setup-summary{background:#23754e}.aas-program-setup .aas-disclosure{border:0;border-radius:0;background:#f8faf7;padding:1rem 1.1rem}.aas-program-setup .aas-disclosure>summary{display:none}.aas-program-guide{display:flex;flex-wrap:wrap;gap:.45rem 1rem;align-items:center;margin:0 1.1rem;padding:.7rem .8rem;border-bottom:1px solid #dfe9e1;color:#52685b;font-size:.73rem;line-height:1.45}.aas-program-guide strong{color:#153d2b}.aas-program-guide span{display:inline-flex;align-items:center;gap:.35rem}.aas-program-guide span::before{content:'→';color:#d5ad55;font-weight:900}
        .aas-setup-steps{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.55rem;margin:1rem 1.1rem 0;padding:0;list-style:none}.aas-setup-steps li{display:flex;align-items:center;gap:.45rem;min-width:0;color:#557064;font-size:.68rem;font-weight:750}.aas-setup-steps li span{display:grid;width:1.45rem;height:1.45rem;flex:0 0 auto;place-items:center;border:1px solid #b9d1bf;border-radius:50%;background:#fff;color:#23754e;font-size:.65rem;font-weight:850}.aas-setup-steps li:not(:last-child)::after{content:'';height:1px;flex:1;background:#cfe2d4}
        .aas-form-section{margin:0 0 .9rem;border:1px solid #dfe9e1;border-radius:.85rem;padding:1rem;background:#fff}.aas-form-section legend{padding:0 .4rem;color:#153d2b;font-size:.78rem;font-weight:850}.aas-form-section legend span{color:#7b8e83;font-weight:500}.aas-program-form{max-width:74rem}.aas-program-form .aas-btn{margin-top:.3rem}
        .aas-program-table .aas-program-name{display:flex;align-items:flex-start;gap:.55rem}.aas-program-mark{display:grid;width:1.8rem;height:1.8rem;flex:0 0 auto;place-items:center;border-radius:.55rem;background:#e7f0e8;color:#23754e}.aas-program-table .aas-program-name strong{display:block;line-height:1.35}.aas-program-meta{color:#708277;font-size:.72rem;line-height:1.45}.aas-program-table .aas-actions{min-width:13rem}.aas-program-table .aas-actions .aas-btn{width:100%}
        .aas-input{width:100%;min-height:2.65rem;border:1px solid #cfdcd2;border-radius:9px;padding:.6rem .7rem;background:#fff;color:#26382f;font-size:.78rem}
        .aas-input:focus{outline:3px solid #26865c55;outline-offset:1px;border-color:#26865c}
        .aas-btn{display:inline-flex;min-height:2.4rem;align-items:center;justify-content:center;border-radius:9px;padding:.55rem .8rem;font-size:.74rem;font-weight:750;text-align:center}
        .aas-primary{background:#153d2b;color:#fff}.aas-gold{background:#d5ad55;color:#153d2b}.aas-sage{background:#e7f0e8;color:#153d2b}.aas-danger{background:#fee7e5;color:#9f3b36}
        .aas-table-wrap{width:100%;overflow-x:auto;border:1px solid #e7eee8;border-radius:12px;scrollbar-width:thin}
        .aas-table{width:100%;min-width:760px;border-collapse:collapse}
        .aas-table th{padding:.7rem .75rem;text-align:left;color:#718177;font-size:.63rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;border-bottom:1px solid #dfe9e1;white-space:nowrap}
        .aas-table td{padding:.75rem;color:#405048;font-size:.75rem;border-bottom:1px solid #edf2ee;vertical-align:top}
        .aas-status{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .55rem;font-size:.65rem;font-weight:800;white-space:nowrap}
        .aas-open,.aas-approved,.aas-completed{background:#e7f0e8;color:#1c6a46}.aas-pending{background:#fff5d8;color:#8a691e}.aas-closed,.aas-rejected{background:#fee7e5;color:#a33e36}.aas-returned{background:#e7f0fb;color:#28618e}
        .aas-stat{padding:1rem 1.1rem}.aas-stat p{margin:0;color:#718177;font-size:.65rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}.aas-stat strong{display:block;margin-top:.35rem;color:#153d2b;font-size:1.55rem;line-height:1.2}.aas-stat p[data-aasms-stat]{margin-top:.5rem;font-size:1.875rem;font-weight:700;letter-spacing:normal;line-height:1.2;text-transform:none}
        .aas-disclosure{padding:.7rem;background:#f8faf7;border:1px solid #dfe9e1;border-radius:.75rem}.aas-disclosure>summary{list-style:none;cursor:pointer;color:#1c6a46;font-size:.72rem;font-weight:800}.aas-disclosure>summary::-webkit-details-marker{display:none}.aas-disclosure>summary:focus-visible{outline:3px solid #26865c88;outline-offset:2px}
        .aas-inline-label{display:block;margin-bottom:.3rem;color:#53645a;font-size:.67rem;font-weight:750}
        .aas-requirement-row{border:1px solid #dfe9e1;border-radius:12px;background:#fff;padding:1rem}
        .aas-requirement-row:focus-within{border-color:#26865c;box-shadow:0 0 0 3px #26865c20}
        .aas-requirement-row legend{padding:0}
        @media(max-width:820px){.aas-module-intro{grid-template-columns:1fr}.aas-module-intro-note{max-width:none}.aas-setup-steps{grid-template-columns:1fr 1fr}.aas-setup-steps li:not(:last-child)::after{display:none}.aas-program-head{display:block}.aas-step-label{margin-top:.75rem}.aas-program-table{min-width:0}.aas-program-table thead{display:none}.aas-program-table tbody,.aas-program-table tr,.aas-program-table td{display:block;width:100%}.aas-program-table tr{margin-bottom:.75rem;border:1px solid #dfe9e1;border-radius:.85rem;background:#fff;overflow:hidden}.aas-program-table td{display:grid;grid-template-columns:8.5rem minmax(0,1fr);gap:.75rem;padding:.8rem;border-bottom:1px solid #edf2ee}.aas-program-table td:last-child{border-bottom:0}.aas-program-table td::before{content:attr(data-label);color:#718177;font-size:.62rem;font-weight:850;letter-spacing:.04em;text-transform:uppercase}.aas-program-table td:nth-child(2)::before{content:'Application window'}.aas-program-table td:nth-child(3)::before{content:'Supply and eligibility'}.aas-program-table td:nth-child(4)::before{content:'Status'}.aas-program-table td:nth-child(5){display:block}.aas-program-table td:nth-child(5)::before{content:'Actions';display:block;margin-bottom:.55rem}.aas-program-table .aas-actions{min-width:0}}
        @media(max-width:640px){.aas-stat{padding:.8rem}.aas-stat strong{font-size:1.3rem}.aas-table{min-width:720px}.aas-program-setup-top{display:block}.aas-program-setup-summary{margin-top:.85rem}.aas-setup-steps{grid-template-columns:1fr}.aas-form-section{padding:.8rem}}
    </style>

    @if(session('status'))<div class="aas-card mb-5 border-l-4 border-emerald-600 px-5 py-4 text-sm text-emerald-800" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="aas-card mb-5 border-l-4 border-red-500 px-5 py-4 text-sm text-red-700" role="alert" tabindex="-1"><b>Assistance action needs attention.</b><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(!$requirementsSchemaReady)<div class="aas-card mb-5 border-l-4 border-amber-500 px-5 py-4 text-sm text-amber-900" role="status"><strong>Requirements versioning is not active on this database.</strong> Existing programs and application records remain viewable; program editing and new online applications are paused until the reviewed additive migrations are activated.</div>@endif

    <div class="erp-workspace" data-erp-workspace data-workspace-param="workspace" data-workspace-default="overview" data-workspace-initial="{{ old('_erp_workspace', old('_aasms_workspace', request('workspace', 'overview'))) }}">
        <x-ui.workflow-strip
            label="Assistance delivery flow"
            description="Move an approved application through allocation and physical handover before it becomes a completed distribution."
            :steps="['Programs', 'Applications', 'Decision', 'Claim ticket', 'Handover']"
        />
        <section class="aas-module-intro" aria-labelledby="aasms-workspace-intro">
            <div class="aas-module-intro-copy">
                <p class="aas-kicker">AASMS · farmer assistance</p>
                <h2 id="aasms-workspace-intro">Run each assistance service from setup to handover</h2>
                <p>Start with a program, publish its requirements, review verified farmer applications, then complete an authorized distribution through AISMS. Each workspace below represents one real operating task.</p>
            </div>
            <div class="aas-module-intro-note"><x-ui.icon name="calendar" size="sm"/><span>Business date: {{ $businessDate->format('M d, Y') }}<br><small>Asia/Manila calendar</small></span></div>
        </section>

        <div class="erp-workspace-tabs" role="tablist" aria-label="Assistance management workspaces">
            <button id="aasms-tab-overview" class="erp-workspace-tab" type="button" role="tab" aria-selected="true" aria-controls="aasms-panel-overview" data-erp-workspace-tab="overview">Overview</button>
            <button id="aasms-tab-programs" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="aasms-panel-programs" data-erp-workspace-tab="programs">Programs</button>
            <button id="aasms-tab-applications" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="aasms-panel-applications" data-erp-workspace-tab="applications">Applications &amp; approvals <span class="aas-status aas-pending">{{ $pending }}</span></button>
            <button id="aasms-tab-distributions" class="erp-workspace-tab" type="button" role="tab" aria-selected="false" aria-controls="aasms-panel-distributions" data-erp-workspace-tab="distributions">Distributions &amp; reports</button>
        </div>

        <section id="aasms-panel-overview" class="erp-workspace-panel" role="tabpanel" aria-labelledby="aasms-tab-overview" tabindex="0" data-erp-workspace-panel="overview">
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <article class="aas-card aas-stat"><p>Programs</p><strong>{{ $programs->count() }}</strong><span class="mt-1 block text-xs text-gray-500">Configured assistance programs</span></article>
                <article class="aas-card aas-stat"><p>For review</p><p data-aasms-stat="pending-applications" class="mt-2 text-3xl font-bold text-agricultural-gold">{{ $pending }}</p><span class="mt-1 block text-xs text-gray-500">Submitted applications</span></article>
                <article class="aas-card aas-stat"><p>Approved</p><p data-aasms-stat="approved-applications" class="mt-2 text-3xl font-bold text-emerald-green">{{ $approved }}</p><span class="mt-1 block text-xs text-gray-500">Awaiting distribution or completion</span></article>
                <article class="aas-card aas-stat"><p>Completed</p><strong>{{ $completed }}</strong><span class="mt-1 block text-xs text-gray-500">Assistance distributed</span></article>
            </div>
            <section class="aas-card mt-4 p-5">
                <h2 class="text-base font-bold text-forest-green">Continue assistance work</h2><p class="mt-1 text-sm text-gray-500">Open the relevant workspace to review programs, act on applications, or inspect completed distributions.</p>
                <div class="mt-4 flex flex-wrap gap-2"><button class="aas-btn aas-sage" type="button" data-erp-workspace-select="programs">Manage programs</button><button class="aas-btn aas-primary" type="button" data-erp-workspace-select="applications">Review applications</button><button class="aas-btn aas-gold" type="button" data-erp-workspace-select="distributions">View distribution history</button></div>
            </section>
        </section>

        <section id="aasms-panel-programs" class="erp-workspace-panel space-y-4" role="tabpanel" aria-labelledby="aasms-tab-programs" tabindex="0" data-erp-workspace-panel="programs" hidden>
            <div class="aas-program-head">
                <div><p class="aas-kicker">Step 1 · Program setup</p><h2>Define a service farmers can apply for</h2><p>Set the offer, schedule, eligible farmers, supporting documents and linked supply before the program becomes public.</p></div>
                <span class="aas-step-label"><strong>1</strong> Program authoring</span>
            </div>
            @if($canManagePrograms)
                <section class="aas-card aas-program-setup">
                    <div class="aas-program-setup-top">
                        <div class="aas-program-setup-title"><span class="aas-program-setup-icon" aria-hidden="true"><x-ui.icon name="add" size="sm"/></span><div><h3>Create a program draft</h3><p>This is the starting point for a new assistance service. Saving a draft does not publish it to farmers.</p></div></div>
                        <div class="aas-program-guide" role="note"><strong>Who can create:</strong> staff with program-management access <span>Create draft</span><span>Review requirements</span><span>Publish when authorized</span></div>
                        <details @if(old('_aasms_workspace') === 'programs' && old('_aasms_form') === 'create-program') open @endif>
                            <summary class="aas-program-setup-summary"><x-ui.icon name="add" size="sm"/> <span>Create program draft</span></summary>
                        @include('modules.aasms.partials.program-create-form')
                        @if(false)
                        <form method="POST" action="{{ route('modules.aasms.programs.store') }}" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @csrf<input type="hidden" name="_aasms_workspace" value="programs"><input type="hidden" name="_aasms_form" value="create-program">
                            <div><label class="aas-inline-label" for="aasms-create-name">Program name</label><input id="aasms-create-name" class="aas-input" name="name" value="{{ old('_aasms_form') === 'create-program' ? old('name') : '' }}" required></div>
                            <div><label class="aas-inline-label" for="aasms-create-category">Category</label><input id="aasms-create-category" class="aas-input" name="category" value="{{ old('_aasms_form') === 'create-program' ? old('category') : '' }}"></div>
                            <div><label class="aas-inline-label" for="aasms-create-supply">Linked supply (optional)</label><select id="aasms-create-supply" class="aas-input" name="supply_item_id"><option value="">Assign during review</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(old('supply_item_id') == $item->id && old('_aasms_form') === 'create-program')>{{ $item->code }} · {{ $item->name }}</option>@endforeach</select></div>
                            <div class="sm:col-span-2 xl:col-span-3"><label class="aas-inline-label" for="aasms-create-description">Description</label><textarea id="aasms-create-description" class="aas-input" name="description" rows="2">{{ old('_aasms_form') === 'create-program' ? old('description') : '' }}</textarea></div>
                            <div><label class="aas-inline-label" for="aasms-create-opens">Opens on</label><input id="aasms-create-opens" class="aas-input" name="opens_on" type="date" value="{{ old('_aasms_form') === 'create-program' ? old('opens_on') : '' }}"></div>
                            <div><label class="aas-inline-label" for="aasms-create-closes">Closes on</label><input id="aasms-create-closes" class="aas-input" name="closes_on" type="date" value="{{ old('_aasms_form') === 'create-program' ? old('closes_on') : '' }}"></div>
                            <div><label class="aas-inline-label" for="aasms-create-limit">Allocation limit</label><input id="aasms-create-limit" class="aas-input" name="allocation_limit" type="number" min=".01" step=".01" value="{{ old('_aasms_form') === 'create-program' ? old('allocation_limit') : '' }}"></div>
                            <div><label class="aas-inline-label" for="aasms-create-barangays">Eligible barangays</label><input id="aasms-create-barangays" class="aas-input" name="barangays" value="{{ old('_aasms_form') === 'create-program' ? old('barangays') : '' }}" placeholder="Comma separated; blank means all"></div>
                            <div><label class="aas-inline-label" for="aasms-create-activities">Eligible activities</label><input id="aasms-create-activities" class="aas-input" name="activities" value="{{ old('_aasms_form') === 'create-program' ? old('activities') : '' }}" placeholder="Comma separated"></div>
                            <div><label class="aas-inline-label" for="aasms-create-categories">Farm categories</label><input id="aasms-create-categories" class="aas-input" name="farm_categories" value="{{ old('_aasms_form') === 'create-program' ? old('farm_categories') : '' }}" placeholder="Comma separated"></div>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_farm" value="1" @checked(old('_aasms_form') === 'create-program' && old('requires_farm'))> Require a registered farm</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="previous_beneficiary_restricted" value="1" @checked(old('_aasms_form') === 'create-program' && old('previous_beneficiary_restricted'))> Restrict previous beneficiaries</label>
                            @include('modules.aasms.partials.requirement-editor', ['formKey' => 'create-program', 'requirementItems' => []])
                            <button class="aas-btn aas-primary sm:col-span-2 xl:col-span-3" type="submit">Save private program</button>
                        </form>
                        @endif
                        </details>
                </section>
                <ol class="aas-setup-steps" aria-label="Program setup steps"><li><span>1</span>Define service</li><li><span>2</span>Set eligibility</li><li><span>3</span>Publish requirements</li><li><span>4</span>Open applications</li><li><span>5</span>Announce to farmers</li></ol>
            @endif

            <section class="aas-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="aas-kicker">Configured services</p><h2 class="text-lg font-bold text-forest-green">Assistance programs</h2><p class="mt-1 max-w-2xl text-sm text-gray-500">Review what is currently configured. Existing applications keep their original requirements version when a program is edited.</p></div><span class="aas-status aas-open">{{ $programs->count() }} configured</span></div>
                <div class="aas-table-wrap mt-4"><table class="aas-table aas-program-table"><thead><tr><th>Program</th><th>Window</th><th>Supply &amp; eligibility</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                    @forelse($programs as $program)
                        @php($programEditorOpen = old('_aasms_program_id') == $program->id)
                        <tr>
                            <td data-label="Program"><div class="aas-program-name"><span class="aas-program-mark" aria-hidden="true"><x-ui.icon name="assistance" size="xs"/></span><div><strong class="text-forest-green">{{ $program->name }}</strong><div class="aas-program-meta">{{ $program->category ?: 'General assistance' }}</div><div class="aas-program-meta mt-1 max-w-sm">{{ $program->description ?: 'No description' }}</div></div></div>
                                @if($program->currentRequirementVersion)<div class="mt-2 text-xs text-gray-600">Requirements v{{ $program->currentRequirementVersion->version_number }} · {{ $program->currentRequirementVersion->source === 'legacy_snapshot' ? 'legacy baseline; original publisher/date unknown' : ($program->currentRequirementVersion->publisher?->name ?: 'publisher not recorded') }}@if($program->currentRequirementVersion->published_at) · {{ $program->currentRequirementVersion->published_at->timezone(config('agriculture.business_timezone'))->format('M d, Y') }}@endif</div>@endif
                                @if($program->draftRequirementVersion)<span class="aas-status aas-pending mt-2">Draft v{{ $program->draftRequirementVersion->version_number }} · not effective</span>@endif
                                @if($program->status === 'open')<a class="mt-1 inline-flex text-xs font-bold text-emerald-700 underline" href="{{ route('public.agricultural-programs.show', $program) }}" target="_blank" rel="noopener">View public program</a>@endif</td>
                            <td data-label="Application window">{{ $program->opens_on?->format('M d, Y') ?: 'No start date' }}<div class="aas-program-meta">through {{ $program->closes_on?->format('M d, Y') ?: 'No closing date' }}</div></td>
                            <td>{{ $program->supplyItem?->name ?: 'Assigned during review' }}<div class="mt-1 text-xs text-gray-500">{{ data_get($program->eligibility_rules, 'requires_farm') ? 'Farm required' : 'Farm optional' }} · {{ implode(', ', data_get($program->eligibility_rules, 'barangays', [])) ?: 'All barangays' }}</div></td>
                            <td><span class="aas-status {{ $program->status === 'open' ? 'aas-open' : 'aas-closed' }}">{{ $program->status === 'open' ? 'Published' : 'Private' }}</span><div class="mt-1 text-xs text-gray-500">{{ ['available' => 'Accepting applications', 'upcoming' => 'Application period upcoming', 'ended' => 'Applications ended', 'unpublished' => 'Hidden from public'][$program->availabilityStateOn($businessDate)] }}</div></td>
                            <td data-label="Actions"><div class="aas-actions min-w-[12rem] space-y-2">
                                @if($canPublish)
                                    @if($program->draftRequirementVersion)
                                        <form method="POST" action="{{ route('modules.aasms.requirements.publish', [$program, $program->draftRequirementVersion]) }}" data-confirm-submit="Publish requirements version {{ $program->draftRequirementVersion->version_number }}? New applications will use it; existing applications will retain their version.">@csrf<input type="hidden" name="_aasms_workspace" value="programs"><button class="aas-btn aas-gold w-full" type="submit">Publish requirements v{{ $program->draftRequirementVersion->version_number }}</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('modules.aasms.programs.toggle', $program) }}" data-confirm-submit="{{ $program->status === 'open' ? 'Hide' : 'Publish' }} this assistance program?">@csrf<input type="hidden" name="_aasms_workspace" value="programs"><button class="aas-btn aas-sage w-full" type="submit">{{ $program->status === 'open' ? 'Hide from public' : 'Publish for public' }}</button></form>
                                @endif
                                @if($canEditPrograms)
                                    <details class="aas-disclosure" @if($programEditorOpen && old('_aasms_form') === 'edit-program') open @endif><summary>Edit program and eligibility</summary>
                                        <form method="POST" action="{{ route('modules.aasms.programs.update', $program) }}" class="mt-3 grid gap-2">@csrf @method('PATCH')<input type="hidden" name="_aasms_workspace" value="programs"><input type="hidden" name="_aasms_form" value="edit-program"><input type="hidden" name="_aasms_program_id" value="{{ $program->id }}">
                                            <label class="aas-inline-label">Program name<input class="aas-input mt-1" name="name" value="{{ $programEditorOpen ? old('name', $program->name) : $program->name }}" required></label>
                                            <label class="aas-inline-label">Category<input class="aas-input mt-1" name="category" value="{{ $programEditorOpen ? old('category', $program->category) : $program->category }}"></label>
                                            <label class="aas-inline-label">Linked supply<select class="aas-input mt-1" name="supply_item_id"><option value="">Assign during review</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((int)($programEditorOpen ? old('supply_item_id', $program->supply_item_id) : $program->supply_item_id) === (int)$item->id)>{{ $item->name }}</option>@endforeach</select></label>
                                            <label class="aas-inline-label">Description<textarea class="aas-input mt-1" name="description" rows="2">{{ $programEditorOpen ? old('description', $program->description) : $program->description }}</textarea></label>
                                            <label class="aas-inline-label">Opens on<input class="aas-input mt-1" name="opens_on" value="{{ $program->opens_on?->format('Y-m-d') }}" type="date"></label>
                                            <label class="aas-inline-label">Closes on<input class="aas-input mt-1" name="closes_on" value="{{ $program->closes_on?->format('Y-m-d') }}" type="date"></label>
                                            <label class="aas-inline-label">Allocation limit<input class="aas-input mt-1" name="allocation_limit" value="{{ $program->allocation_limit }}" type="number" min=".01" step=".01"></label>
                                            <label class="aas-inline-label">Eligible barangays<input class="aas-input mt-1" name="barangays" value="{{ implode(', ', data_get($program->eligibility_rules, 'barangays', [])) }}"></label>
                                            <label class="aas-inline-label">Eligible activities<input class="aas-input mt-1" name="activities" value="{{ implode(', ', data_get($program->eligibility_rules, 'activities', [])) }}"></label>
                                            <label class="aas-inline-label">Farm categories<input class="aas-input mt-1" name="farm_categories" value="{{ implode(', ', data_get($program->eligibility_rules, 'farm_categories', [])) }}"></label>
                                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="requires_farm" value="1" @checked(data_get($program->eligibility_rules, 'requires_farm'))> Require a farm</label>
                                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="previous_beneficiary_restricted" value="1" @checked(data_get($program->eligibility_rules, 'previous_beneficiary_restricted'))> Restrict previous beneficiaries</label>
                                            @php($requirementsVersionForEditor = $program->draftRequirementVersion ?: $program->currentRequirementVersion)
                                            @include('modules.aasms.partials.requirement-editor', ['formKey' => 'edit-program', 'programId' => $program->id, 'requirementItems' => $requirementsVersionForEditor?->documentRequirements ?? []])
                                            <button class="aas-btn aas-primary" type="submit">Save program</button>
                                        </form>
                                    </details>
                                @endif
                                @if(!$canEditPrograms && !$canPublish)<span class="text-xs text-gray-500">Read-only access</span>@endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">No assistance programs have been configured.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
        </section>

        <section id="aasms-panel-applications" class="erp-workspace-panel" role="tabpanel" aria-labelledby="aasms-tab-applications" tabindex="0" data-erp-workspace-panel="applications" hidden>
            <section class="aas-card p-5">
                <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold text-forest-green">Application review queue</h2><p class="mt-1 text-sm text-gray-500">Approval, rejection, return, and distribution controls are scoped to each application.</p></div>
                    <form method="GET" class="flex flex-wrap items-end gap-2"><input type="hidden" name="workspace" value="applications"><div><label class="aas-inline-label" for="aasms-application-status">Application status</label><select id="aasms-application-status" class="aas-input" name="status"><option value="">All statuses</option>@foreach(['submitted','approved','returned','rejected','completed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div><button class="aas-btn aas-primary" type="submit">Filter queue</button></form>
                </div>
                <div class="aas-table-wrap mt-4"><table class="aas-table"><thead><tr><th>Application</th><th>Farmer</th><th>Eligibility</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                    @forelse($applications as $application)
                        <tr>
                            <td><strong class="text-forest-green">{{ $application->application_reference ?: 'Legacy record #'.$application->id }}</strong><div class="mt-1 max-w-xs text-xs text-gray-700">{{ $application->program->name }}</div><div class="mt-1 max-w-xs text-xs text-gray-500">@if($application->submitted_at){{ $application->submitted_at->timezone(config('agriculture.business_timezone'))->format('M d, Y · h:i A') }} Manila time @else Historical submission time not separately recorded @endif</div><div class="mt-1 text-xs text-gray-500">{{ $application->requirementVersion ? 'Requirements v'.$application->requirementVersion->version_number : 'Legacy requirements evidence unavailable' }}</div></td>
                            <td>{{ $application->farmer->user->name }}<div class="text-xs text-gray-500">{{ $application->farmer->farmer_code }} · {{ $application->farmer->barangay ?: 'No barangay' }}</div></td>
                            <td><span class="aas-status {{ $application->eligibility_passed ? 'aas-open' : 'aas-pending' }}">{{ $application->eligibility_passed ? 'Passed' : 'Needs review' }}</span></td>
                            <td><span class="aas-status {{ match($application->status) { 'submitted' => 'aas-pending', 'rejected' => 'aas-rejected', 'returned' => 'aas-returned', 'completed' => 'aas-completed', default => 'aas-approved' } }}">{{ ucfirst($application->status) }}</span></td>
                            <td>
                                <div class="mb-2 flex flex-wrap gap-2"><a class="aas-btn aas-sage" href="{{ route('modules.aasms.applications.show', $application) }}">View details</a>@if($application->acknowledgment)<a class="aas-btn aas-sage" href="{{ route('modules.aasms.applications.acknowledgment', $application) }}" target="_blank" rel="noopener">Acknowledgment</a>@endif</div>
                                <details class="aas-disclosure" @if(old('_aasms_application_id') == $application->id) open @endif><summary>Review &amp; distribution</summary>
                                    <div class="mt-3 space-y-3">
                                        @if(in_array($application->status, ['submitted','returned'], true) && ($canReview || $canApprove || $canReject))
                                            <form method="POST" action="{{ route('modules.aasms.review', $application) }}" class="rounded-xl bg-gray-50 p-3">@csrf<input type="hidden" name="_aasms_workspace" value="applications"><input type="hidden" name="_aasms_application_id" value="{{ $application->id }}">
                                                <h3 class="font-bold text-forest-green">Application decision</h3>
                                                <label class="aas-inline-label mt-3" for="aasms-decision-{{ $application->id }}">Decision</label><select id="aasms-decision-{{ $application->id }}" class="aas-input" name="status" required>@if($canApprove)<option value="approved">Approve</option>@endif @if($canReject)<option value="rejected">Reject</option>@endif @if($application->status === 'submitted' && $canReview)<option value="returned">Return for correction</option>@endif</select>
                                                <label class="aas-inline-label mt-3" for="aasms-review-notes-{{ $application->id }}">Review note <span class="text-red-700">(required for rejection or return)</span></label><textarea id="aasms-review-notes-{{ $application->id }}" class="aas-input" name="review_notes" rows="3">{{ old('_aasms_application_id') == $application->id ? old('review_notes') : '' }}</textarea>
                                                @if($canApprove)
                                                    <label class="aas-inline-label mt-3" for="aasms-quantity-{{ $application->id }}">Approved quantity</label><input id="aasms-quantity-{{ $application->id }}" class="aas-input" name="approved_quantity" type="number" min=".01" step=".01" value="{{ $application->approved_quantity }}">
                                                    <label class="aas-inline-label mt-3" for="aasms-supply-{{ $application->id }}">Approved supply item</label><select id="aasms-supply-{{ $application->id }}" class="aas-input" name="approved_supply_item_id"><option value="">Choose supply</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((int)$application->approved_supply_item_id === (int)$item->id)>{{ $item->name }}</option>@endforeach</select>
                                                @endif
                                                <button class="aas-btn aas-primary mt-3 w-full" type="submit">Save decision</button>
                                            </form>
                                        @else
                                            <p class="rounded-xl bg-gray-50 p-3 text-sm text-gray-500">This application is not currently reviewable. Its decision history is preserved.</p>
                                        @endif

                                        @if($application->status === 'approved' && $canDistribute)
                                            @if($batches->isNotEmpty())
                                                <form method="POST" action="{{ route('modules.aasms.distribute', $application) }}" class="rounded-xl bg-[#e7f0e8] p-3">@csrf<input type="hidden" name="_aasms_workspace" value="applications"><input type="hidden" name="_aasms_application_id" value="{{ $application->id }}">
                                                    <h3 class="font-bold text-forest-green">Authorize physical distribution</h3><p class="mt-1 text-xs text-gray-600">AISMS stock is deducted only after the authorized distribution succeeds.</p>
                                                    <label class="aas-inline-label mt-3" for="aasms-batch-{{ $application->id }}">Available, unexpired batch</label><select id="aasms-batch-{{ $application->id }}" class="aas-input" name="supply_batch_id" required><option value="">Select batch</option>@foreach($batches as $batch)<option value="{{ $batch->id }}">{{ $batch->item->name }} · {{ $batch->batch_number }} · {{ number_format((float)$batch->quantity_available, 2) }} available</option>@endforeach</select>
                                                    <label class="aas-inline-label mt-3" for="aasms-distribution-quantity-{{ $application->id }}">Quantity to distribute</label><input id="aasms-distribution-quantity-{{ $application->id }}" class="aas-input" name="quantity" type="number" min=".01" step=".01" value="{{ $application->approved_quantity }}" required>
                                                    <label class="aas-inline-label mt-3" for="aasms-idempotency-{{ $application->id }}">Retry reference (optional)</label><input id="aasms-idempotency-{{ $application->id }}" class="aas-input" name="idempotency_key" maxlength="100">
                                                    <button class="aas-btn aas-gold mt-3 w-full" type="submit">Confirm distribution</button>
                                                </form>
                                            @else
                                                <p class="rounded-xl bg-yellow-50 p-3 text-sm text-yellow-900">No unexpired AISMS batch has a positive balance; distribution cannot be completed yet.</p>
                                            @endif
                                        @elseif($application->status !== 'completed')
                                            <p class="rounded-xl bg-gray-50 p-3 text-sm text-gray-500">Distribution is available after approval and only to authorized personnel.</p>
                                        @endif

                                        <div class="rounded-xl border border-gray-200 p-3"><p class="text-xs text-gray-600"><strong>Current review note:</strong> {{ $application->review_notes ?: 'None recorded' }}</p>
                                            <details class="mt-3"><summary class="cursor-pointer text-xs font-bold text-emerald-700">View status history</summary><div class="mt-2 space-y-2">@forelse($application->history as $history)<p class="border-t border-gray-100 pt-2 text-xs text-gray-600">{{ ucfirst($history->from_status ?: 'new') }} → {{ ucfirst($history->to_status) }} · {{ $history->created_at?->format('M d, Y H:i') }}<br>{{ $history->notes ?: 'No note' }}</p>@empty<p class="text-xs text-gray-500">No history recorded.</p>@endforelse</div></details>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-gray-500">No applications match this status filter.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="mt-5">{{ $applications->links() }}</div>
            </section>
        </section>

        <section id="aasms-panel-distributions" class="erp-workspace-panel" role="tabpanel" aria-labelledby="aasms-tab-distributions" tabindex="0" data-erp-workspace-panel="distributions" hidden>
            <section class="aas-card p-5">
                <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-bold text-forest-green">Distribution history</h2><p class="mt-1 text-sm text-gray-500">Completed assistance distributions linked to the corresponding AISMS movement.</p></div><span class="aas-status aas-open">{{ $distributions->total() }} matching records</span></div>
                <form method="GET" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Filter assistance distributions">
                    <input type="hidden" name="workspace" value="distributions">
                    <div><label class="aas-inline-label" for="aasms-filter-reference">Transaction reference</label><input id="aasms-filter-reference" class="aas-input" name="reference" value="{{ request('reference') }}"></div>
                    <div><label class="aas-inline-label" for="aasms-filter-farmer">Farmer</label><input id="aasms-filter-farmer" class="aas-input" name="farmer" value="{{ request('farmer') }}"></div>
                    <div><label class="aas-inline-label" for="aasms-filter-barangay">Farmer's current barangay</label><select id="aasms-filter-barangay" class="aas-input" name="barangay"><option value="">All current barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay }}" @selected(request('barangay') === $barangay)>{{ $barangay }}</option>@endforeach</select></div>
                    @if($distributionBarangayReady)<div><label class="aas-inline-label" for="aasms-filter-distribution-barangay">Farmer barangay at distribution</label><select id="aasms-filter-distribution-barangay" class="aas-input" name="distribution_barangay"><option value="">All distribution barangays</option>@foreach($barangays as $barangay)<option value="{{ $barangay }}" @selected(request('distribution_barangay') === $barangay)>{{ $barangay }}</option>@endforeach<option value="__not_recorded__" @selected(request('distribution_barangay') === '__not_recorded__')>Not recorded</option></select></div>@endif
                    <div><label class="aas-inline-label" for="aasms-filter-program">Program</label><select id="aasms-filter-program" class="aas-input" name="program_id"><option value="">All programs</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(request('program_id') == $program->id)>{{ $program->name }}</option>@endforeach</select></div>
                    <div><label class="aas-inline-label" for="aasms-filter-supply">Supply item</label><select id="aasms-filter-supply" class="aas-input" name="supply_item_id"><option value="">All supplies</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(request('supply_item_id') == $item->id)>{{ $item->name }}</option>@endforeach</select></div>
                    <div><label class="aas-inline-label" for="aasms-filter-from">From</label><input id="aasms-filter-from" class="aas-input" name="from" value="{{ request('from') }}" type="date"></div>
                    <div><label class="aas-inline-label" for="aasms-filter-to">To</label><input id="aasms-filter-to" class="aas-input" name="to" value="{{ request('to') }}" type="date"></div>
                    <div class="flex items-end gap-2 sm:col-span-2"><button class="aas-btn aas-primary" type="submit">Filter distributions</button><a class="aas-btn aas-sage" href="{{ route('modules.aasms.dashboard', ['workspace' => 'distributions']) }}">Clear filters</a>@if($canExport)<a class="aas-btn aas-gold" href="{{ route('modules.aasms.export', request()->except(['workspace','distribution_page'])) }}">Download CSV</a>@endif</div>
                </form>
                <p class="mt-3 text-sm text-gray-600">@if($distributionBarangayReady)Historical barangay is recorded for new distributions. Older records without a snapshot remain unknown.@else Historical barangay recording is not yet activated; the current barangay may change.@endif</p>
                <details class="mt-4 rounded-xl border border-sage bg-off-white p-4">
                    <summary class="cursor-pointer font-semibold text-forest-green">Distribution totals by barangay <span class="font-normal text-slate-500">({{ $distributionBarangaySummaryBasis }})</span></summary>
                    <div class="aas-table-wrap mt-3"><table class="aas-table"><thead><tr><th>Barangay</th><th>Completed distributions</th><th>Total quantity</th></tr></thead><tbody>
                        @forelse($distributionBarangaySummary as $summary)
                            <tr><td>{{ $summary->barangay }}</td><td>{{ number_format((int) $summary->distribution_count) }}</td><td>{{ number_format((float) $summary->quantity_total, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-5 text-center text-gray-500">No completed distributions to summarize.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </details>
                <div class="aas-table-wrap mt-5"><table class="aas-table"><thead><tr><th>Reference</th><th>Farmer</th><th>Program</th><th>Supply / batch</th><th>Quantity</th><th>Processed</th></tr></thead><tbody>
                    @forelse($distributions as $distribution)
                        <tr><td><strong>{{ $distribution->reference }}</strong><div class="text-xs text-gray-500"><a class="font-semibold text-emerald-green underline underline-offset-2" href="{{ route('modules.aasms.applications.show', $distribution->application_id) }}">Open application #{{ $distribution->application_id }}</a></div></td><td>{{ $distribution->application->farmer->user->name }}<div class="text-xs text-gray-500">Current barangay: {{ $distribution->application->farmer->barangay ?: 'Not recorded' }}</div><div class="text-xs text-gray-500">At distribution: {{ $distribution->farmer_barangay_at_distribution ?: 'Not recorded' }}</div></td><td>{{ $distribution->application->program->name }}</td><td>{{ $distribution->batch->item->name }}<div class="text-xs text-gray-500">{{ $distribution->batch->batch_number }}</div></td><td>{{ number_format((float)$distribution->quantity, 2) }}</td><td>{{ $distribution->distributed_at?->format('M d, Y H:i') }}<div class="text-xs text-gray-500">{{ $distribution->processor?->name }}</div></td></tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-500">No distributions match these filters.</td></tr>
                    @endforelse
                </tbody></table></div>
                <div class="mt-5">{{ $distributions->links() }}</div>
            </section>
        </section>
    </div>
</x-layouts.app>
