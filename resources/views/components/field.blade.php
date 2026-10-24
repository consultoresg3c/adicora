@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'hint' => null])
<div class="field">
    <label for="{{ $name }}">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" @required($required) aria-describedby="{{ $name }}-error{{ $hint ? ' '.$name.'-hint' : '' }}" @error($name) aria-invalid="true" @enderror {{ $attributes }}>
    @if($hint)<span id="{{ $name }}-hint" class="field-hint">{{ $hint }}</span>@endif
    <p class="field-error" id="{{ $name }}-error" data-error="{{ $name }}">@error($name){{ $message }}@enderror</p>
</div>
