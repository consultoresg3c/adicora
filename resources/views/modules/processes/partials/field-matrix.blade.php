<input type="hidden" name="review_only" value="0">
<label class="checkbox-label switch-label"><input type="checkbox" name="review_only" value="1" role="switch" data-review-only aria-describedby="review_only-error" @checked(old('review_only', $record->review_only))><span>Solo revisión y envío</span></label>
<p class="field-error" id="review_only-error" data-error="review_only">@error('review_only'){{ $message }}@enderror</p>
<div class="table-scroll field-matrix-scroll">
<table class="field-matrix" data-field-matrix><caption class="sr-only">Visualización y obligatoriedad de campos en esta etapa</caption>
<thead><tr><th scope="col">Campos</th><th scope="col">Lectura</th><th scope="col">Editable</th><th scope="col">Oculto</th><th scope="col">Obligatorio</th></tr></thead>
<tbody>@foreach($fields as $field)
@php
    $rule = $record->fieldRules->firstWhere('process_field_id', $field->id);
    $mode = old('field_rules.'.$field->id.'.mode', $rule?->mode ?? 'hidden');
@endphp
<tr data-field-rule>
    <th scope="row">
        @if($field->attribute === null)
            <x-record-options :history-url="route('records.history', ['resource' => 'campos-procesos', 'record' => $field->id])" :print-template="'print-process-field-'.$field->id" :delete-template="'delete-process-field-'.$field->id" delete-label="Eliminar campo" label="Opciones del campo" inline />
            <x-record-print :id="'print-process-field-'.$field->id" :title="$field->name" :fields="['Proceso' => $process->name, 'Tipo de dato' => $fieldTypes[$field->data_type] ?? $field->data_type, 'Longitud máxima' => $field->text_length, 'Decimales' => $field->decimal_places]" />
        @endif
        <span>{{ $field->name }}</span><span class="table-secondary">{{ $field->data_type === 'supplier' ? 'Catálogo de proveedores' : ($fieldTypes[$field->data_type] ?? '') }}@if($field->data_type === 'text') · {{ $field->text_length }} caracteres @elseif($field->data_type === 'numeric') · {{ $field->decimal_places }} decimales @endif</span>

        <p class="field-error" id="field-rule-{{ $field->id }}-error" data-error="field_rules.{{ $field->id }}.mode">@error('field_rules.'.$field->id.'.mode'){{ $message }}@enderror</p>
    </th>
    @foreach(['read' => 'Lectura', 'edit' => 'Editable', 'hidden' => 'Oculto'] as $value => $label)
    <td><label class="field-mode-label"><span class="sr-only">{{ $label }}: {{ $field->name }}</span><input class="field-mode-toggle" type="radio" name="field_rules[{{ $field->id }}][mode]" value="{{ $value }}" aria-describedby="field-rule-{{ $field->id }}-error" @checked($mode === $value)></label></td>
    @endforeach
    <td><input type="hidden" name="field_rules[{{ $field->id }}][is_required]" value="0"><label class="field-mode-label"><span class="sr-only">Obligatorio: {{ $field->name }}</span><input class="field-mode-toggle" type="checkbox" role="switch" name="field_rules[{{ $field->id }}][is_required]" value="1" data-field-required aria-describedby="field-required-{{ $field->id }}-error" @checked(old('field_rules.'.$field->id.'.is_required', $rule?->is_required ?? false))></label><p class="field-error" id="field-required-{{ $field->id }}-error" data-error="field_rules.{{ $field->id }}.is_required">@error('field_rules.'.$field->id.'.is_required'){{ $message }}@enderror</p></td>
</tr>
@endforeach</tbody></table>
</div>
<p class="field-error" data-error="field_rules">@error('field_rules'){{ $message }}@enderror</p>
<p class="field-hint">Los campos obligatorios pueden quedar vacíos al guardar; deben completarse antes de enviar a la siguiente etapa.</p>
<fieldset class="new-process-field" data-new-field><legend>Nuevo campo</legend>
<x-field name="new_field[name]" label="Nombre del campo" maxlength="180" :value="old('new_field.name')" />
<div class="field"><label for="new_field_type">Tipo de dato</label><select id="new_field_type" name="new_field[data_type]" data-new-field-type aria-describedby="new-field-type-error">@foreach($fieldTypes as $value => $label)<option value="{{ $value }}" @selected(old('new_field.data_type', 'text') === $value)>{{ $label }}</option>@endforeach</select><p class="field-error" id="new-field-type-error" data-error="new_field.data_type">@error('new_field.data_type'){{ $message }}@enderror</p></div>
<div data-text-length><x-field name="new_field[text_length]" label="Longitud máxima" type="number" min="1" max="10000" :value="old('new_field.text_length', 180)" /></div>
<div data-decimal-places hidden><x-field name="new_field[decimal_places]" label="Cantidad de decimales" type="number" min="0" max="8" :value="old('new_field.decimal_places', 2)" /></div>
<input type="hidden" name="new_field[is_required]" value="0"><label class="checkbox-label"><input type="checkbox" name="new_field[is_required]" value="1" @checked(old('new_field.is_required', false))>Obligatorio al enviar en esta etapa</label>
<p class="field-hint">Se incorporará al guardar. En las demás etapas existentes quedará oculto.</p>
</fieldset>
