<x-layouts.app>
    <x-slot name="title">Staff-assisted application intake</x-slot>
    <x-slot name="header">Staff-assisted intake</x-slot>
    <x-slot name="description">Help a verified farmer submit through the existing AASMS process. Identity and consent procedures remain restricted to synthetic isolated QA.</x-slot>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('modules.aasms.dashboard') }}" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-forest-green hover:bg-sage focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-green">
            <x-ui.icon name="back" size="sm"/>Back to AASMS
        </a>
        <span class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-900">
            <x-ui.icon name="security" size="sm"/>Isolated SQLite QA only
        </span>
    </div>

    @if(session('status'))
        <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            <p class="font-bold">Review the following before continuing:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <ol aria-label="Application intake steps" class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach([['1', 'Find farmer', 'users'], ['2', 'Check identity and eligibility', 'security'], ['3', 'Choose program', 'assistance'], ['4', 'Review and submit', 'action']] as [$number, $label, $icon])
            <li class="flex min-h-14 items-center gap-2 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-xs font-semibold text-forest-green sm:text-sm">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-sage text-emerald-green"><x-ui.icon name="{{ $icon }}" size="sm"/></span>
                <span><span class="mr-1 text-emerald-green">{{ $number }}.</span>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,.82fr)]">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="farmer-search-heading">
            <div class="mb-4 flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sage text-forest-green"><x-ui.icon name="users" size="lg"/></span>
                <div><h2 id="farmer-search-heading" class="text-lg font-bold text-forest-green">1. Find the FFRMS farmer record</h2><p class="mt-1 text-sm text-gray-600">Search by farmer reference, registered name, or barangay. Contact details are not used as search terms.</p></div>
            </div>
            <form method="GET" action="{{ route('modules.aasms.intake') }}" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                <label class="sr-only" for="intake-farmer-search">Search FFRMS farmer records</label>
                <input id="intake-farmer-search" name="search" value="{{ $search }}" maxlength="100" placeholder="Farmer reference, name, or barangay" class="min-h-12 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-900 placeholder:text-gray-500 focus:border-emerald-green focus:outline-none focus:ring-2 focus:ring-emerald-200">
                <button type="submit" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-forest-green px-5 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-green"><x-ui.icon name="search" size="sm"/>Search farmers</button>
            </form>

            @if($search !== '')
                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                    @forelse($farmers as $farmer)
                        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="break-words font-bold text-gray-900">{{ $farmer->user?->name ?: 'Name not recorded' }}</p>
                                <p class="mt-1 text-xs text-gray-600">{{ $farmer->farmer_code ?: 'No farmer reference' }} <span aria-hidden="true">·</span> {{ $farmer->barangay ?: 'Barangay not recorded' }}</p>
                                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $farmer->isVerified() ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900' }}">{{ $farmer->isVerified() ? 'FFRMS verified' : 'Verification required' }}</span>
                            </div>
                            <a class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-emerald-700 px-4 py-2 text-sm font-bold text-emerald-900 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" href="{{ route('modules.aasms.intake', array_filter(['search' => $search, 'farmer_id' => $farmer->id, 'program_search' => $programSearch ?: null])) }}">Select farmer <x-ui.icon name="next" size="sm"/></a>
                        </div>
                    @empty
                        <div class="p-5 text-sm text-gray-600">No FFRMS records match this search. Check the spelling or farmer reference.</div>
                    @endforelse
                </div>
                @if($farmers->hasPages())<div class="mt-3">{{ $farmers->links() }}</div>@endif
            @endif
        </section>

        <aside class="rounded-2xl border border-emerald-100 bg-sage/60 p-4 sm:p-6" aria-labelledby="intake-boundary-heading">
            <h2 id="intake-boundary-heading" class="flex items-center gap-2 text-base font-bold text-forest-green"><x-ui.icon name="security"/>Intake safeguards</h2>
            <ul class="mt-3 space-y-2 text-sm leading-6 text-gray-700">
                <li>Only verified FFRMS farmers may proceed.</li>
                <li>Eligibility and open application dates are checked by the same AASMS service used for online applications.</li>
                <li>Duplicate farmer/program applications remain blocked by the existing submission workflow.</li>
                <li>Intake access does not grant application review, approval, distribution, or private-document viewing.</li>
            </ul>
            <p class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs leading-5 text-amber-950"><strong>Policy pending:</strong> This flow is available only on a designated disposable SQLite QA database. The checkbox below records a synthetic test attestation, not farmer consent, identity proof, or a signature.</p>
        </aside>
    </div>

    @if($selectedFarmer)
        <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="selected-farmer-heading">
            <div class="flex flex-col gap-3 border-b border-gray-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-green">Selected FFRMS record</p>
                    <h2 id="selected-farmer-heading" class="mt-1 text-xl font-bold text-forest-green">{{ $selectedFarmer->user?->name ?: 'Name not recorded' }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $selectedFarmer->farmer_code ?: 'No farmer reference' }} <span aria-hidden="true">·</span> {{ $selectedFarmer->barangay ?: 'Barangay not recorded' }}</p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-bold {{ $selectedFarmer->isVerified() ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900' }}">{{ str($selectedFarmer->registration_status)->replace('_', ' ')->headline() }}</span>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_1fr]">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Registered farms ({{ $selectedFarmer->farms->count() }})</h3>
                    @forelse($selectedFarmer->farms as $farm)
                        <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
                            <p class="font-semibold text-gray-900">{{ $farm->name ?: 'Unnamed farm' }}</p>
                            <p class="mt-1 text-xs text-gray-600">{{ $farm->barangay ?: $selectedFarmer->barangay ?: 'Barangay not recorded' }}@if($farm->area) <span aria-hidden="true">·</span> {{ $farm->area }} {{ $farm->area_unit }}@endif</p>
                            @if($farm->agricultural_activities)<p class="mt-1 text-xs text-gray-600">Activities: {{ implode(', ', $farm->agricultural_activities) }}</p>@endif
                        </div>
                    @empty
                        <p class="mt-2 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">No farm records are linked to this FFRMS farmer.</p>
                    @endforelse
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Recent FFRMS verification history</h3>
                    @forelse($selectedFarmer->registrationHistory as $history)
                        <div class="mt-2 border-l-2 border-emerald-300 pl-3 text-sm">
                            <p class="font-semibold text-gray-800">{{ str($history->to_status)->replace('_', ' ')->headline() }} <span class="font-normal text-gray-500">{{ $history->created_at?->format('M j, Y g:i A') }}</span></p>
                            <p class="mt-1 text-xs text-gray-600">Recorded by {{ $history->processor?->name ?: 'staff identity not recorded' }}</p>
                            @if($history->reason)<p class="mt-1 text-xs text-gray-600">{{ $history->reason }}</p>@endif
                        </div>
                    @empty
                        <p class="mt-2 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">No verification decision history is recorded.</p>
                    @endforelse
                    @if(!$selectedFarmer->isVerified())
                        <div role="status" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                            <strong>Do not submit yet.</strong> This registration must be verified through FFRMS first.
                            @if($canReviewFarmer)<a class="mt-2 inline-flex font-bold underline" href="{{ route('modules.ffrms.dashboard', ['search' => $selectedFarmer->farmer_code]) }}">Open the authorized FFRMS workflow</a>@endif
                        </div>
                    @endif
                </div>
            </div>
        </section>

        @if($selectedFarmer->isVerified())
            <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="program-selection-heading">
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sage text-forest-green"><x-ui.icon name="assistance" size="lg"/></span>
                    <div><h2 id="program-selection-heading" class="text-lg font-bold text-forest-green">3. Select a published assistance program</h2><p class="mt-1 text-sm text-gray-600">Only published AASMS programs are listed. Dates use the Asia/Manila business calendar; upcoming and ended programs cannot be submitted.</p></div>
                </div>
                <form method="GET" action="{{ route('modules.aasms.intake') }}" class="mb-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                    <input type="hidden" name="search" value="{{ $search }}"><input type="hidden" name="farmer_id" value="{{ $selectedFarmer->id }}">
                    <label class="sr-only" for="intake-program-search">Search published assistance programs</label>
                    <input id="intake-program-search" name="program_search" value="{{ $programSearch }}" maxlength="100" placeholder="Search program name or category" class="min-h-11 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-emerald-green focus:outline-none focus:ring-2 focus:ring-emerald-200">
                    <button class="min-h-11 rounded-lg border border-emerald-700 px-4 text-sm font-bold text-emerald-900 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="submit">Find programs</button>
                </form>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($programs as $program)
                        @php($availability = $program->availabilityStateOn($businessDate))
                        <article class="flex flex-col rounded-xl border {{ $selectedProgram?->id === $program->id ? 'border-emerald-600 ring-2 ring-emerald-100' : 'border-gray-200' }} p-4">
                            <div class="flex items-start justify-between gap-2"><h3 class="font-bold leading-5 text-forest-green">{{ $program->name }}</h3><span class="shrink-0 rounded-full px-2 py-1 text-[.68rem] font-bold {{ $availability === 'available' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900' }}">{{ ['available' => 'Open now', 'upcoming' => 'Upcoming', 'ended' => 'Ended', 'unpublished' => 'Hidden'][$availability] }}</span></div>
                            <p class="mt-2 text-xs font-semibold text-emerald-800">{{ $program->category ?: 'General assistance' }}</p>
                            <p class="mt-2 line-clamp-3 text-sm leading-5 text-gray-600">{{ $program->description ?: 'No additional program description is available.' }}</p>
                            <p class="mt-3 text-xs text-gray-600">{{ $program->opens_on?->format('M j, Y') ?: 'No opening date' }} – {{ $program->closes_on?->format('M j, Y') ?: 'No closing date' }}</p>
                            <a class="mt-4 inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-emerald-700 px-3 py-2 text-sm font-bold text-emerald-900 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" href="{{ route('modules.aasms.intake', array_filter(['search' => $search ?: null, 'farmer_id' => $selectedFarmer->id, 'program_search' => $programSearch ?: null, 'program_id' => $program->id])) }}">Review eligibility <x-ui.icon name="next" size="sm"/></a>
                        </article>
                    @empty
                        <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600 md:col-span-2 xl:col-span-3">No published programs match this search.</p>
                    @endforelse
                </div>
                @if($programs->hasPages())<div class="mt-4">{{ $programs->links() }}</div>@endif
            </section>

            @if($selectedProgram)
                <section class="mt-5 rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="program-review-heading">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-green">4. Review before submission</p>
                    <h2 id="program-review-heading" class="mt-1 text-xl font-bold text-forest-green">{{ $selectedProgram->name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-700">{{ $selectedProgram->description ?: 'No additional program description is available.' }}</p>

                    @if($existingApplication)
                        <div role="status" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                            <p class="font-bold">An application already exists for this farmer and program.</p>
                            <p class="mt-1">The one-application-per-farmer/program rule prevents a duplicate submission.</p>
                            @if($canViewApplication)<a class="mt-2 inline-flex font-bold underline" href="{{ route('modules.aasms.applications.show', $existingApplication) }}">Open existing application {{ $existingApplication->application_reference ?: '#'.$existingApplication->id }}</a>@else<p class="mt-2">Ask an AASMS reviewer with application-view access to open its record.</p>@endif
                        </div>
                    @elseif(!$eligibilityResult['passed'])
                        <div role="status" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"><p class="font-bold">This application cannot be submitted.</p><p class="mt-1">{{ $eligibilityResult['notes'] }}</p></div>
                    @else
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-sm font-bold text-forest-green">Eligibility checked</h3>
                                <p class="mt-1 text-sm text-gray-700">The existing AASMS eligibility service returned eligible for the selected verified farmer and current Philippine business date ({{ $businessDate->format('F j, Y') }}).</p>
                                <p class="mt-2 text-xs text-gray-600">{{ $eligibilityResult['notes'] ?: 'No additional eligibility notes.' }}</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 p-4">
                                <h3 class="text-sm font-bold text-forest-green">Requirements in the current version</h3>
                                @if($selectedProgram->currentRequirementVersion?->documentRequirements?->isNotEmpty())
                                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700">@foreach($selectedProgram->currentRequirementVersion->documentRequirements as $requirement)<li><strong>{{ $requirement->display_name }}</strong> — {{ $requirement->is_required ? 'Required' : 'Optional' }}@if($requirement->description) · {{ $requirement->description }}@endif</li>@endforeach</ul>
                                @else
                                    <p class="mt-1 text-sm text-gray-600">No digitized document checklist is recorded for this program version.</p>
                                @endif
                                <p class="mt-2 text-xs text-gray-600">If private uploads are activated in QA, attach files after submission on the confirmation page. Uploads use the existing private storage and scanner service.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('modules.aasms.intake.store') }}" class="mt-5 rounded-xl border border-emerald-200 bg-sage/40 p-4 sm:p-5">
                            @csrf
                            <input type="hidden" name="farmer_id" value="{{ $selectedFarmer->id }}">
                            <input type="hidden" name="program_id" value="{{ $selectedProgram->id }}">
                            <input type="hidden" name="submission_key" value="{{ old('submission_key', $submissionKey) }}">
                            <p class="text-sm font-bold text-forest-green">5. Record synthetic QA intake and submit</p>
                            <p class="mt-1 text-xs leading-5 text-gray-700">Staff member: <strong>{{ auth()->user()->name }}</strong>. The shared service records this user as the intake actor and does not impersonate the farmer.</p>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="identity-confirmation-method" class="mb-1 block text-sm font-semibold text-gray-800">Identity procedure (QA only)</label>
                                    <select id="identity-confirmation-method" name="identity_confirmation_method" required class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm focus:border-emerald-green focus:outline-none focus:ring-2 focus:ring-emerald-200">
                                        <option value="">Choose the configured QA method</option>
                                        @foreach(app(\App\Services\StaffAssistedIntakePolicy::class)->identityConfirmationMethods() as $method)
                                            <option value="{{ $method }}" @selected(old('identity_confirmation_method') === $method)>Synthetic QA attestation</option>
                                        @endforeach
                                    </select>
                                    @error('identity_confirmation_method')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                </div>
                                <label class="flex min-h-11 items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm leading-5 text-amber-950">
                                    <input type="checkbox" name="synthetic_qa_attestation" value="1" required @checked(old('synthetic_qa_attestation')) class="mt-1 h-4 w-4 rounded border-gray-400 text-emerald-700 focus:ring-emerald-600">
                                    <span>I am recording a <strong>synthetic QA attestation only</strong>. It is not a farmer signature, proof of identity, or legal consent.</span>
                                </label>
                            </div>
                            <label for="intake-purpose" class="mt-4 block text-sm font-semibold text-gray-800">Farmer's stated purpose (optional)</label>
                            <textarea id="intake-purpose" name="purpose" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-emerald-green focus:outline-none focus:ring-2 focus:ring-emerald-200">{{ old('purpose') }}</textarea>
                            @error('purpose')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                            <p class="mt-2 text-xs leading-5 text-gray-600">Submission records an application for MAO review. It does not approve the farmer or guarantee assistance. The application reference and immutable acknowledgment are generated by AASMS.</p>
                            <button type="submit" class="mt-4 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-forest-green px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-green sm:w-auto"><x-ui.icon name="success" size="sm"/>Submit through AASMS</button>
                        </form>
                    @endif
                </section>
            @endif
        @endif
    @endif
</x-layouts.app>
