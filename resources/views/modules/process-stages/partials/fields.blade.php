<x-record-form-header>
    <x-slot:options>@include('modules.process-stages.partials.record-options')</x-slot:options>
    <x-field name="name" label="Nombre de la etapa" :value="$record->name" required maxlength="180" />
</x-record-form-header>
<x-select name="status_id" label="Estatus" :options="$statuses" :value="$record->status_id" />
