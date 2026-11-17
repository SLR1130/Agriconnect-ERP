@php
    $content = $announcement?->editorContent() ?? [];
    $revision = $announcement?->currentRevision;
    $editing = $announcement !== null;
    $formId = $editing ? 'announcement-'.$announcement->id : 'announcement-new';
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('modules.aasms.announcements.update', $announcement) : route('modules.aasms.announcements.store') }}" class="publication-form" aria-label="{{ $editing ? 'Edit announcement revision' : 'Create announcement draft' }}">
    @csrf @if($editing)@method('PATCH')@endif
    <input type="hidden" name="_announcement_id" value="{{ $editing ? $announcement->id : 'new' }}">
    <div class="publication-form-grid">
        <div class="farmer-field publication-form-wide"><label for="{{ $formId }}-title">Official title <span aria-hidden="true">*</span></label><input id="{{ $formId }}-title" name="title" maxlength="180" value="{{ old('title', $content['title'] ?? '') }}" required></div>
        <div class="farmer-field publication-form-wide"><label for="{{ $formId }}-body">Description <span aria-hidden="true">*</span></label><textarea id="{{ $formId }}-body" name="body" maxlength="10000" rows="8" required aria-describedby="{{ $formId }}-body-help">{{ old('body', $content['body'] ?? '') }}</textarea><small id="{{ $formId }}-body-help">Basic Markdown is supported for headings, lists, emphasis and links. Raw HTML is stripped before public display.</small></div>
        <div class="farmer-field"><label for="{{ $formId }}-office">Implementing office</label><input id="{{ $formId }}-office" name="office" maxlength="180" value="{{ old('office', $content['office'] ?? '') }}" placeholder="Municipal Agriculture Office"></div>
        <div class="farmer-field"><label for="{{ $formId }}-category">Agricultural category</label><input id="{{ $formId }}-category" name="category" maxlength="100" value="{{ old('category', $content['category'] ?? '') }}" placeholder="Crop support, livestock, fisheries"></div>
        <div class="farmer-field"><label for="{{ $formId }}-area">Target service area</label><input id="{{ $formId }}-area" name="service_area" maxlength="180" value="{{ old('service_area', $content['service_area'] ?? '') }}" placeholder="Barangay or municipality coverage"></div>
        <div class="farmer-field"><label for="{{ $formId }}-program">Published AASMS program</label><select id="{{ $formId }}-program" name="assistance_program_id"><option value="">No linked assistance program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('assistance_program_id', $content['assistance_program_id'] ?? '') == $program->id)>{{ $program->name }}</option>@endforeach</select><small>Only published programs can be linked. The AASMS program controls eligibility and application dates.</small></div>
        <div class="farmer-field"><label for="{{ $formId }}-expiry">Announcement visibility ends</label><input id="{{ $formId }}-expiry" name="expires_at" type="datetime-local" value="{{ old('expires_at', isset($content['expires_at']) && $content['expires_at'] ? \Illuminate\Support\Carbon::parse($content['expires_at'])->format('Y-m-d\\TH:i') : '') }}"><small>Use the notice expiry for publication visibility. Application deadlines come from the linked AASMS program.</small></div>
        <div class="farmer-field publication-form-wide"><label for="{{ $formId }}-requirements">Requirements summary</label><textarea id="{{ $formId }}-requirements" name="requirements_summary" maxlength="4000" rows="4">{{ old('requirements_summary', $content['requirements_summary'] ?? '') }}</textarea></div>
        <div class="farmer-field publication-form-wide"><label for="{{ $formId }}-instructions">Official instructions</label><textarea id="{{ $formId }}-instructions" name="instructions" maxlength="4000" rows="4">{{ old('instructions', $content['instructions'] ?? '') }}</textarea></div>
        <div class="farmer-field publication-form-wide"><label for="{{ $formId }}-contact">Official contact information</label><textarea id="{{ $formId }}-contact" name="contact_details" maxlength="2000" rows="3">{{ old('contact_details', $content['contact_details'] ?? '') }}</textarea></div>
        <fieldset class="publication-media-fieldset publication-form-wide"><legend>Cover photograph</legend><p>JPEG, PNG or WebP · up to 4 MB. Stored privately and served only while this revision is published.</p>
            @if($editing && $revision?->media->isNotEmpty())
                @php($existingCover = $revision->media->first(fn ($media) => $media->is_cover))
                @if($existingCover)<div class="publication-current-cover"><img src="{{ route('modules.aasms.announcements.media', [$announcement, $revision, $existingCover]) }}" alt="{{ $existingCover->alt_text }}"><span>Current cover: {{ $existingCover->alt_text }}</span></div>@endif
            @endif
            <label for="{{ $formId }}-cover-image">Choose a cover photograph</label><input id="{{ $formId }}-cover-image" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" aria-describedby="{{ $formId }}-cover-help">
            <label for="{{ $formId }}-cover-alt">Alternative text for new cover image</label><input id="{{ $formId }}-cover-alt" name="cover_alt" maxlength="180" value="{{ old('cover_alt') }}" placeholder="Describe what the photo shows">
            <small id="{{ $formId }}-cover-help">Do not include private farmer information in photographs.</small>
        </fieldset>
        <fieldset class="publication-media-fieldset publication-form-wide"><legend>Gallery photographs</legend><p>Up to six additional JPEG, PNG or WebP images. Provide descriptive alternative text for each selected file.</p>
            <label for="{{ $formId }}-gallery-images">Choose gallery photographs</label><input id="{{ $formId }}-gallery-images" type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp" multiple data-publication-gallery-input aria-describedby="{{ $formId }}-gallery-help">
            <div data-publication-gallery-alt-list></div><small id="{{ $formId }}-gallery-help">Each image is limited to 4 MB and kept outside public storage.</small>
        </fieldset>
        @if($editing && $revision?->media->isNotEmpty())
            <fieldset class="publication-media-fieldset publication-form-wide"><legend>Current revision images</legend><p>Uncheck an image to remove it from this revision. Move buttons change display order; choose one image as the cover.</p>
                <div class="publication-media-list" data-publication-media-list>
                    @foreach($revision->media as $media)
                        <div class="publication-media-item" data-publication-media-item>
                            <img src="{{ route('modules.aasms.announcements.media', [$announcement, $revision, $media]) }}" alt="">
                            <div class="publication-media-controls">
                                <input type="hidden" name="media_order[]" value="{{ $media->id }}" data-publication-media-order>
                                <label class="publication-keep"><input type="checkbox" name="keep_media_ids[]" value="{{ $media->id }}" checked> Keep image</label>
                                <label class="publication-cover"><input type="radio" name="existing_cover_id" value="{{ $media->id }}" @checked($media->is_cover)> Use as cover</label>
                                <label for="{{ $formId }}-alt-{{ $media->id }}">Alternative text</label><input id="{{ $formId }}-alt-{{ $media->id }}" name="media_alt[{{ $media->id }}]" maxlength="180" value="{{ old('media_alt.'.$media->id, $media->alt_text) }}">
                                <div class="publication-order-actions"><button type="button" data-publication-move="up" aria-label="Move image up">Move up</button><button type="button" data-publication-move="down" aria-label="Move image down">Move down</button></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endif
    </div>
    <button class="farmer-button farmer-button-gold" type="submit">{{ $editing ? 'Save new revision' : 'Save private draft' }}</button>
</form>
