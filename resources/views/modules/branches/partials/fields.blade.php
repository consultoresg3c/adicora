<x-record-form-header>
    <x-slot:options>@include('modules.branches.partials.record-options')</x-slot:options>
    @if(auth()->user()->is_super_admin)
        <x-select name="company_id" label="Empresa" :options="$companies" :value="$record->company_id" />
    @else
        <x-field name="name" label="Nombre de la sucursal" :value="$record->name" required maxlength="180" />
    @endif
</x-record-form-header>
@if(auth()->user()->is_super_admin)
    <x-field name="name" label="Nombre de la sucursal" :value="$record->name" required maxlength="180" />
@endif
<x-field name="address" label="Dirección" :value="$record->address" maxlength="1000" />
<x-select name="status_id" label="Estado" :options="$statuses" :value="$record->status_id" />
