<form class="dialog-form" method="post" action="{{ $record->exists ? route('processes.stages.update', [$process, $record]) : route('processes.stages.store', $process) }}" data-async-form novalidate>
@csrf @if($record->exists) @method('PUT') @endif
<input type="hidden" name="version" value="{{ $process->version }}">
<x-select name="process_stage_id" label="Estación origen" :options="$stages" :value="$record->process_stage_id" :required="false" placeholder="Selecciona una etapa del catálogo" />
<x-field name="new_stage_name" label="Nombre de nueva etapa (opcional)" maxlength="180" hint="Escríbelo si deseas crear una etapa en vez de seleccionar una del catálogo." />
<x-field name="position" label="Posición en la secuencia" type="number" :value="$record->position" required min="1" :max="$sequence->count() + ($record->exists ? 0 : 1)" hint="El destino y las conexiones se ajustan automáticamente a esta posición." />
<x-select name="responsible_role_id" label="Rol" :options="$roles" :value="$record->responsible_role_id" />
<x-select name="completion_status_id" label="Estatus al completar la etapa" :options="$statuses" :value="$record->completion_status_id" />
<div class="field"><label for="field_name">Datos que completa esta etapa</label><select id="field_name" name="field_name" aria-describedby="field_name-error"><option value="">Solo revisión y envío</option>@foreach(['purchase_date' => 'Proveedor, monto y fecha de compra', 'approval_date' => 'Fecha de aprobación', 'receipt_date' => 'Fecha de recepción', 'payment_date' => 'Fecha de pago'] as $value => $label)<option value="{{ $value }}" @selected(old('field_name', $record->field_name) === $value)>{{ $label }}</option>@endforeach</select><p class="field-error" id="field_name-error" data-error="field_name">@error('field_name'){{ $message }}@enderror</p></div>
@if(! $record->exists && $sequence->isEmpty())<x-select name="initial_status_id" label="Estatus inicial de las solicitudes" :options="$statuses" />@endif
<p class="field-hint">No se pueden cambiar las etapas mientras existan compras en curso.</p>
<x-form-actions :cancel="route('processes.index', ['process_id' => $process->id])" modal />
</form>
@if($record->exists)<x-record-history :record="$record" resource="etapas-compras" />@endif
