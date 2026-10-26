<x-field name="name" label="Nombre de la etapa" :value="$record->name" required maxlength="180" />
<x-select name="status_id" label="Estatus" :options="$statuses" :value="$record->status_id" />
