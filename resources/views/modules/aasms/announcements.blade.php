<x-layouts.app>
    <x-slot name="header">Official agricultural publications</x-slot>
    <x-slot name="description">Prepare accessible, photo-rich government notices and program announcements. Public changes require an explicit publisher permission.</x-slot>
    <a class="mb-5 inline-flex items-center gap-2 text-sm font-bold text-emerald-700 underline" href="{{ route('modules.aasms.dashboard') }}"><x-ui.icon name="back" size="sm"/>Assistance workspace</a>
    @if(session('status'))<div class="farmer-message farmer-message-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="farmer-message farmer-message-error" role="alert"><strong>Please correct the publication form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="publication-policy-note" aria-label="Publication review policy">
        <x-ui.icon name="security" size="lg"/>
        <div><strong>Authoring and publishing are separate actions.</strong><p>Editors can save and preview private revisions. Only Super Administrators or an MAO role with the exact <code>modules.aasms.publish</code> grant can publish or archive. Broad legacy grants do not imply publication authority.</p></div>
        <a href="{{ route('public.agricultural-services.index') }}" target="_blank" rel="noopener">View public directory <x-ui.icon name="next" size="sm"/></a>
    </section>

    @if($canCreate)
        <section class="publication-create-panel">
            <div><p class="public-agri-eyebrow">New official notice</p><h2>Create a publication draft</h2><p>Use verified MAO information and approved photographs only.</p></div>
            <details class="farmer-disclosure" @if($errors->any() && old('_announcement_id') === 'new') open @endif>
                <summary><x-ui.icon name="add" size="sm"/>Start a new draft</summary>
                <div class="publication-form-shell">@include('modules.aasms.partials.announcement-form', ['announcement' => null, 'programs' => $programs])</div>
            </details>
        </section>
    @endif

    <section class="publication-list-section" aria-labelledby="publication-list-heading">
        <div class="public-agri-section-heading"><div><p class="public-agri-eyebrow">AASMS publication desk</p><h2 id="publication-list-heading">Announcements and revisions</h2><p>Drafts and revision previews remain private until an authorized publisher approves them.</p></div><span class="farmer-pill">{{ $announcements->total() }} records</span></div>
        <div class="publication-list">
            @forelse($announcements as $announcement)
                @php($content = $announcement->editorContent())
                @php($revision = $announcement->currentRevision)
                @php($cover = $revision?->media?->first(fn ($media) => $media->is_cover) ?? $revision?->media?->first())
                <article class="publication-record">
                    <div class="publication-record-head">
                        @if($cover)<img src="{{ route('modules.aasms.announcements.media', [$announcement, $revision, $cover]) }}" alt="" class="publication-record-thumb">@else<span class="publication-record-thumb-empty"><x-ui.icon name="office" size="lg"/></span>@endif
                        <div class="publication-record-title"><p class="public-agri-eyebrow">{{ $content['category'] ?: 'Agricultural notice' }} · {{ $content['office'] ?: 'Office not specified' }}</p><h3>{{ $content['title'] }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string) \Illuminate\Support\Str::markdown($content['body'], ['html_input' => 'strip', 'allow_unsafe_links' => false])), 180) }}</p></div>
                        <span class="farmer-pill {{ $announcement->status === 'published' ? '' : 'farmer-pill-warn' }}">{{ ucfirst($announcement->status) }}</span>
                    </div>
                    <div class="publication-record-meta"><span>Author: {{ $announcement->creator?->name ?: 'Unknown' }}</span><span>Current revision: {{ $revision?->revision_number ?: 'Legacy' }}</span><span>{{ $announcement->program?->name ?: 'No linked program' }}</span><span>{{ $announcement->published_at ? 'Published '.$announcement->published_at->timezone('Asia/Manila')->format('M j, Y g:i A') : 'Not published' }}</span></div>
                    <div class="publication-actions">
                        @if($revision)<a class="farmer-button farmer-button-light" href="{{ route('modules.aasms.announcements.preview', [$announcement, $revision]) }}" target="_blank" rel="noopener">Preview revision <x-ui.icon name="next" size="sm"/></a>@endif
                        @if($announcement->status === 'published')<a class="farmer-text-link" href="{{ route('public.announcements.show', $announcement) }}" target="_blank" rel="noopener">View public version <x-ui.icon name="next" size="sm"/></a>@endif
                        @if($canUpdate && in_array($announcement->status, ['draft', 'published'], true))
                            <details class="farmer-disclosure publication-edit-disclosure" @if($errors->any() && (string) old('_announcement_id') === (string) $announcement->id) open @endif>
                                <summary>Edit content and media</summary>
                                <p class="publication-edit-note">@if($announcement->status === 'published')Saving creates a private pending revision. The current public version stays visible until a publisher approves the revision.@elseEach save preserves the prior draft revision for review history.@endif</p>
                                <div class="publication-form-shell">@include('modules.aasms.partials.announcement-form', ['announcement' => $announcement, 'programs' => $programs])</div>
                            </details>
                        @endif
                        @if($canPublish && $revision?->state === 'draft')<form method="POST" action="{{ route('modules.aasms.announcements.publish', $announcement) }}" data-confirm-submit="Publish this official announcement revision?">@csrf<button class="farmer-button farmer-button-gold" type="submit">Publish revision</button></form>@elseif($revision?->state === 'draft')<span class="publication-permission-note">Awaiting a user with explicit publication permission.</span>@endif
                        @if($canPublish && $announcement->status === 'published')<form method="POST" action="{{ route('modules.aasms.announcements.archive', $announcement) }}" data-confirm-submit="Archive this public announcement? Its history will be retained.">@csrf<button class="farmer-button farmer-button-light" type="submit">Archive</button></form>@endif
                    </div>
                    <details class="publication-history"><summary>Publication history ({{ $announcement->revisions->count() }} revisions)</summary><ol>@forelse($announcement->revisions as $history)<li><span class="farmer-pill {{ $history->state === 'published' ? '' : 'farmer-pill-warn' }}">{{ ucfirst($history->state) }}</span><strong>Revision {{ $history->revision_number }}</strong><span>Prepared by {{ $history->creator?->name ?: 'Unknown' }}</span><span>{{ $history->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</span>@if($history->publisher)<span>Published by {{ $history->publisher->name }}{{ $history->published_at ? ' · '.$history->published_at->timezone('Asia/Manila')->format('M j, Y g:i A') : '' }}</span>@endif<a href="{{ route('modules.aasms.announcements.preview', [$announcement, $history]) }}" target="_blank" rel="noopener">Preview</a></li>@empty<li>No revision history is available for this legacy notice.</li>@endforelse</ol></details>
                </article>
            @empty
                <div class="farmer-empty"><span class="ui-service-icon"><x-ui.icon name="office" size="lg"/></span><strong>No announcements have been created.</strong><p>Create a private draft to begin the official publication workflow. Nothing appears in public discovery until it is published.</p></div>
            @endforelse
        </div>
        @if($announcements->hasPages())<div class="public-agri-pagination">{{ $announcements->links() }}</div>@endif
    </section>
</x-layouts.app>
