<x-record-form-header>
    <x-slot:options>@include('modules.statuses.partials.record-options')</x-slot:options>
    <x-field name="name" label="Nombre del estado" :value="$record->name" required maxlength="100" />
</x-record-form-header>
@if($record->exists)
    <input type="hidden" name="status_type_id" value="{{ $record->status_type_id }}">
    <p class="field-hint">Módulo: <strong>{{ $record->type->name }}</strong>. El módulo de un estado existente permanece fijo.</p>
@else
    <x-select name="status_type_id" label="Módulo" :options="$statusTypes" />
@endif
<input type="hidden" name="is_active" value="0">
<label class="checkbox-label switch-label">
    <input type="checkbox" name="is_active" value="1" role="switch" aria-describedby="is_active-error" @checked(old('is_active', !$record->exists || $record->is_active))>
    <span>Permite operar con este estado</span>
</label>
<p class="field-error" id="is_active-error" data-error="is_active">@error('is_active'){{ $message }}@enderror</p>
