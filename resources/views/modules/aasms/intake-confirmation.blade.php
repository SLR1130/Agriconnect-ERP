<x-layouts.app>
    <x-slot name="title">Intake recorded {{ $application->application_reference }}</x-slot>
    <x-slot name="header">Intake acknowledgment</x-slot>
    <x-slot name="description">The application was recorded in the shared AASMS system and is awaiting its existing review process.</x-slot>

    @if(session('status'))
        <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><p class="font-bold">The file could not be accepted:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="mx-auto max-w-4xl space-y-5">
        <section class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm" aria-labelledby="intake-received-heading">
            <div class="border-b border-emerald-100 bg-sage/70 p-5 sm:p-7">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-900"><x-ui.icon name="success" size="sm"/>Application received</span>
                <h2 id="intake-received-heading" class="mt-3 text-2xl font-bold text-forest-green">The application was recorded</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-700">This is the same AASMS application record used for farmer-portal submissions. It is now available to the existing authorized MAO review workflow.</p>
            </div>
            <dl class="grid gap-px bg-gray-200 sm:grid-cols-2">
                <div class="bg-white p-4 sm:p-5"><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Application reference</dt><dd class="mt-1 break-all text-lg font-extrabold tracking-wide text-forest-green">{{ $application->application_reference }}</dd></div>
                <div class="bg-white p-4 sm:p-5"><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Submitted</dt><dd class="mt-1 font-semibold text-gray-900">{{ $application->submitted_at?->timezone(config('agriculture.business_timezone'))->format('M j, Y g:i A') }} Philippine time</dd></div>
                <div class="bg-white p-4 sm:p-5"><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Farmer</dt><dd class="mt-1 font-semibold text-gray-900">{{ $application->farmer?->user?->name }} <span class="text-xs font-normal text-gray-600">({{ $application->farmer?->farmer_code }})</span></dd></div>
                <div class="bg-white p-4 sm:p-5"><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Program and status</dt><dd class="mt-1 font-semibold text-gray-900">{{ $application->program?->name }} <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-900">{{ str($application->status)->replace('_', ' ')->headline() }}</span></dd></div>
                <div class="bg-white p-4 sm:p-5 sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-gray-500">Intake recorded by</dt><dd class="mt-1 font-semibold text-gray-900">{{ data_get($application->acknowledgment?->snapshot, 'intake.actor_name', 'Staff identity recorded in the immutable application acknowledgment') }}</dd><p class="mt-1 text-xs text-gray-600">This staff member entered the information. The staff member is not the applicant or the MAO decision-maker by virtue of intake.</p></div>
            </dl>
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:p-5">
                <a href="{{ route('modules.aasms.intake.acknowledgment', $application) }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-forest-green px-5 py-3 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-green"><x-ui.icon name="reports" size="sm"/>Print acknowledgment</a>
                @if(auth()->user()->canModuleAction('aasms', 'view'))
                    <a href="{{ route('modules.aasms.applications.show', $application) }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-emerald-700 px-5 py-3 text-sm font-bold text-emerald-900 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"><x-ui.icon name="action" size="sm"/>Open AASMS review record</a>
                @endif
                <a href="{{ route('modules.aasms.intake') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-gray-300 px-5 py-3 text-sm font-bold text-gray-800 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600">Start another intake</a>
            </div>
            <p class="px-4 pb-5 text-xs leading-5 text-gray-600 sm:px-5">The acknowledgment confirms receipt only. It does not imply approval, entitlement, or distribution. Its reference and original snapshot are immutable.</p>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="requirements-heading">
            <h2 id="requirements-heading" class="flex items-center gap-2 text-lg font-bold text-forest-green"><x-ui.icon name="reports"/>Program requirements</h2>
            @php($requirements = $application->requirementVersion?->documentRequirements ?? collect())
            @if($requirements->isEmpty())
                <p class="mt-2 text-sm text-gray-600">No digitized document checklist was recorded for the applicable program version. Refer to the printed acknowledgment for the preserved submission summary.</p>
            @else
                <ul class="mt-3 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    @foreach($requirements as $requirement)
                        <li class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="font-semibold text-gray-900">{{ $requirement->display_name }} @if($requirement->is_required)<span class="text-xs font-bold text-amber-800">Required</span>@else<span class="text-xs text-gray-500">Optional</span>@endif</p>@if($requirement->description)<p class="mt-1 text-xs text-gray-600">{{ $requirement->description }}</p>@endif</div>
                            @if($uploadEnabled && in_array($application->status, ['submitted', 'returned'], true))
                                <form method="POST" action="{{ route('modules.aasms.intake.documents.store', [$application, $requirement->id]) }}" enctype="multipart/form-data" class="w-full sm:w-auto">
                                    @csrf
                                    <label for="intake-document-{{ $requirement->id }}" class="sr-only">Upload {{ $requirement->display_name }}</label>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <input id="intake-document-{{ $requirement->id }}" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required class="min-h-11 max-w-full rounded-lg border border-gray-300 p-2 text-xs text-gray-700 file:mr-2 file:rounded-md file:border-0 file:bg-sage file:px-3 file:py-1.5 file:font-semibold file:text-forest-green focus:outline-none focus:ring-2 focus:ring-emerald-300">
                                        <button type="submit" class="min-h-11 shrink-0 rounded-lg border border-emerald-700 px-3 py-2 text-xs font-bold text-emerald-900 hover:bg-emerald-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Send to secure scan</button>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">PDF, JPEG, or PNG · up to {{ number_format(min($maxUploadKilobytes, (int) ceil($requirement->max_size_bytes / 1024))) }} KB. Files remain private and quarantined until scanned.</p>
                                </form>
                            @else
                                <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600">{{ $application->status === 'submitted' ? 'No upload action available' : 'Application no longer accepts files' }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @unless($uploadEnabled)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-5 text-amber-950">Private uploads are disabled in this QA configuration. No document can be uploaded, opened, reviewed, or treated as received. The feature does not bypass the application-document feature flag or scanner.</p>
            @endunless
        </section>

        @if($canViewDocuments && $application->relationLoaded('documents') && $application->documents->isNotEmpty())
            <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6" aria-labelledby="document-status-heading">
                <h2 id="document-status-heading" class="text-lg font-bold text-forest-green">Private document processing status</h2>
                <ul class="mt-3 space-y-2">@foreach($application->documents as $document)<li class="flex flex-col gap-2 rounded-lg border border-gray-200 p-3 sm:flex-row sm:items-center sm:justify-between"><span class="text-sm font-semibold text-gray-900">{{ $document->original_filename }} <span class="text-xs font-normal text-gray-500">v{{ $document->version_number }}</span></span><span class="text-xs text-gray-700">Scan: {{ str($document->scan_status)->headline() }} · Review: {{ str($document->review_status)->headline() }}</span>@if($document->scan_status === 'clean')<a class="text-sm font-bold text-emerald-800 underline" href="{{ route('modules.aasms.applications.documents.file', [$application, $document->id]) }}">Open through authorized document access</a>@endif</li>@endforeach</ul>
            </section>
        @endif

        <aside class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950"><strong>QA attestation boundary.</strong> This synthetic workflow records the signed-in staff actor and test-only attestation in the immutable submission acknowledgment. It does not claim a real-world identity check, farmer consent, or farmer signature. Operational activation requires approved MAO procedures.</aside>
    </div>
</x-layouts.app>
