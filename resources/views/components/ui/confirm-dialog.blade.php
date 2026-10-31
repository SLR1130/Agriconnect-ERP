@props(['id', 'title', 'description' => null, 'confirmLabel' => 'Continue', 'cancelLabel' => 'Cancel', 'variant' => 'primary'])
<dialog id="{{ $id }}" class="ui-dialog" aria-labelledby="{{ $id }}-title" @if($description) aria-describedby="{{ $id }}-description" @endif>
    <div class="ui-dialog-panel"><div class="ui-dialog-heading"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="ui-icon-button" data-ui-dialog-close aria-label="Close"><x-ui.icon name="close"/></button></div>
        @if($description)<p id="{{ $id }}-description">{{ $description }}</p>@endif<div class="ui-dialog-content">{{ $slot }}</div>
        <div class="ui-dialog-actions"><button type="button" class="ui-button ui-button-secondary" data-ui-dialog-close>{{ $cancelLabel }}</button><button type="button" class="ui-button ui-button-{{ $variant }}" data-ui-dialog-confirm>{{ $confirmLabel }}</button></div>
    </div>
</dialog>
