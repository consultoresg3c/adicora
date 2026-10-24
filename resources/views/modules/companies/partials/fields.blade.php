<div class="form-grid">
    <x-field name="name" label="Nombre de la empresa" :value="$record->name" required maxlength="180" autocomplete="organization" />
    <x-field name="tax_id" label="Identificación fiscal" :value="$record->tax_id" required maxlength="40" />
    <x-field name="email" label="Correo de contacto" type="email" :value="$record->email" maxlength="255" />
    <x-field name="phone" label="Teléfono" type="tel" :value="$record->phone" maxlength="40" />
    <div class="form-full"><x-field name="address" label="Dirección" :value="$record->address" maxlength="1000" /></div>
    <x-select name="status_id" label="Estado" :options="$statuses" :value="$record->status_id" />
</div>
<section class="form-section"><h2>Módulos habilitados</h2><p>Habilita las funciones disponibles para esta empresa. Los roles y permisos de sus usuarios se definen por separado.</p><div class="check-grid">
@foreach($modules as $module)<label class="checkbox-label"><input type="checkbox" name="module_ids[]" value="{{ $module->id }}" @checked(in_array($module->id, old('module_ids', $record->exists ? $record->modules->modelKeys() : [])))>{{ $module->name }}</label>@endforeach
</div><p class="field-error" data-error="module_ids">@error('module_ids'){{ $message }}@enderror</p></section>
