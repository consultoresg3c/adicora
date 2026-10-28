@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'hint' => null])
@php($errorKey = preg_replace('/\[([^\]]+)\]/', '.$1', $name))
<div class="field">
    <label for="{{ $name }}">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($errorKey, $value) }}" @required($required) aria-describedby="{{ $name }}-error{{ $hint ? ' '.$name.'-hint' : '' }}" @error($errorKey) aria-invalid="true" @enderror {{ $attributes }}>
    @if($hint)<span id="{{ $name }}-hint" class="field-hint">{{ $hint }}</span>@endif
    <p class="field-error" id="{{ $name }}-error" data-error="{{ $errorKey }}">@error($errorKey){{ $message }}@enderror</p>
</div>
