@if(auth()->user()->is_super_admin)<x-select name="company_id" label="Empresa" :options="$companies" :value="$record->company_id" />@endif
<x-field name="name" label="Nombre del proveedor" :value="$record->name" required maxlength="180" />
<x-select name="status_id" label="Estado" :options="$statuses" :value="$record->status_id" />
