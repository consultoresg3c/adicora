@props(['name', 'label', 'options', 'value' => null, 'required' => true, 'placeholder' => 'Selecciona una opción'])
<div class="field">
    <label for="{{ $name }}">{{ $label }} @if($required)<span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required) aria-describedby="{{ $name }}-error" @error($name) aria-invalid="true" @enderror {{ $attributes }}>
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $option)<option value="{{ $option->id }}" @selected((string) old($name, $value) === (string) $option->id)>{{ $option->name }}</option>@endforeach
    </select>
    <p class="field-error" id="{{ $name }}-error" data-error="{{ $name }}">@error($name){{ $message }}@enderror</p>
</div>
