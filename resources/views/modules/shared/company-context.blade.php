@if(auth()->user()->is_super_admin && !$record->exists)
<form class="context-picker" method="get">
    <p>Selecciona primero la empresa para mostrar sus roles y permisos. Cambiarla reinicia los datos del formulario.</p>
    <x-select name="company_id" label="Empresa de trabajo" :options="$companies" :value="$record->company_id" :required="false" :placeholder="$allowSystem ? 'Sistema (configuración global)' : 'Selecciona una empresa'" />
    <button type="submit" class="button button-secondary">Usar este ámbito</button>
</form>
@endif
