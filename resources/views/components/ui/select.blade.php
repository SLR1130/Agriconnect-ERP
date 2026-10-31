@props(['name', 'label', 'value' => null, 'hint' => null, 'required' => false])
@php($fieldId = $attributes->get('id', 'field-'.str_replace(['[', ']'], ['-', ''], $name)))
<div class="ui-field">
    <label for="{{ $fieldId }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span><span class="sr-only"> (required)</span>@endif</label>
    <select id="{{ $fieldId }}" name="{{ $name }}" @if($required) required @endif {{ $attributes->except('id')->class('ui-control') }} @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>{{ $slot }}</select>
    @if($hint)<small>{{ $hint }}</small>@endif
    @error($name)<p id="{{ $fieldId }}-error" class="ui-field-error">{{ $message }}</p>@enderror
</div>
