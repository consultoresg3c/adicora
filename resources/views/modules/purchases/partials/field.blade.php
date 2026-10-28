@php
    $definition = $item['field'];
    $label = $definition->name.($definition->attribute === 'amount' ? ' (USD)' : '');
    $key = $item['error_key'];
    $id = 'purchase-field-'.$definition->id;
    $type = match ($definition->data_type) { 'integer', 'numeric' => 'number', 'datetime' => 'datetime-local', 'date' => 'date', 'time' => 'time', default => 'text' };
    $value = $definition->data_type === 'datetime' && $item['value'] !== null ? str_replace(' ', 'T', $item['value']) : $item['value'];
@endphp
<div class="field">
@if($item['editable'])
<label for="{{ $id }}">{{ $label }} @if($item['required'])<span class="required-mark" aria-hidden="true">*</span>@endif</label>
@if($definition->data_type === 'supplier')
<select id="{{ $id }}" name="{{ $item['input_name'] }}" aria-describedby="{{ $id }}-error{{ $item['required'] ? ' '.$id.'-hint' : '' }}" @error($key) aria-invalid="true" @enderror><option value="">Selecciona un proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old($key, $value) === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>
@else
<input id="{{ $id }}" name="{{ $item['input_name'] }}" type="{{ $type }}" value="{{ old($key, $value) }}" aria-describedby="{{ $id }}-error{{ $item['required'] ? ' '.$id.'-hint' : '' }}" @error($key) aria-invalid="true" @enderror
    @if($definition->data_type === 'text') maxlength="{{ $definition->text_length }}" @endif
    @if($definition->data_type === 'integer') step="1" @endif
    @if($definition->data_type === 'numeric') step="{{ $definition->decimal_places === 0 ? '1' : '0.'.str_repeat('0', $definition->decimal_places - 1).'1' }}" @endif
    @if(in_array($definition->data_type, ['time', 'datetime'], true)) step="1" @endif
    @if($definition->attribute === 'amount') min="0.01" max="99999999999999.99" @endif>
@endif
@if($item['required'])<span class="field-hint" id="{{ $id }}-hint">Obligatorio al enviar. Puedes guardar sin completarlo.</span>@endif
<p class="field-error" id="{{ $id }}-error" data-error="{{ $key }}">@error($key){{ $message }}@enderror</p>
@else
<span id="{{ $id }}-label">{{ $label }}</span><p aria-labelledby="{{ $id }}-label">{{ $item['display'] }}</p>
@endif
</div>
