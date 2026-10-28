<form class="dialog-form" method="post" action="{{ $record->exists ? route('processes.stages.update', [$process, $record]) : route('processes.stages.store', $process) }}" data-async-form data-stage-form novalidate>

@csrf @if($record->exists) @method('PUT') @endif
<input type="hidden" name="version" value="{{ $process->version }}">
<x-record-form-header>
<x-slot:options>@if($record->exists)<x-record-options :history-url="route('records.history', ['resource' => 'etapas-compras', 'record' => $record->id])" :print-template="'print-process-stage-'.$record->id" :delete-template="'delete-process-stage-'.$record->id" delete-label="Quitar etapa" label="Opciones de la etapa" />@endif</x-slot:options>
<x-select name="process_stage_id" label="Estación origen" :options="$stages" :value="$record->process_stage_id" :required="false" placeholder="Selecciona una etapa del catálogo" />
</x-record-form-header>
<x-field name="new_stage_name" label="Nombre de nueva etapa (opcional)" maxlength="180" hint="Escríbelo si deseas crear una etapa en vez de seleccionar una del catálogo." />
<x-field name="position" label="Posición en la secuencia" type="number" :value="$record->position" required min="1" :max="$sequence->count() + ($record->exists ? 0 : 1)" hint="El destino y las conexiones se ajustan automáticamente a esta posición." />
<x-select name="responsible_role_id" label="Rol" :options="$roles" :value="$record->responsible_role_id" />
<x-select name="completion_status_id" label="Estatus al completar la etapa" :options="$statuses" :value="$record->completion_status_id" />
@include('modules.processes.partials.field-matrix')
<input type="hidden" name="is_start" value="0">
<label class="checkbox-label switch-label"><input type="checkbox" name="is_start" value="1" role="switch" data-process-start aria-describedby="is_start-error" @error('is_start') aria-invalid="true" @enderror @checked(old('is_start', $record->is_start))><span>Inicia el proceso</span></label>
<p class="field-error" id="is_start-error" data-error="is_start">@error('is_start'){{ $message }}@enderror</p>
<p class="field-hint">Puedes marcar más de una estación de inicio en el mismo proceso.</p>
<div data-start-status @if(! old('is_start', $record->is_start)) hidden @endif>
    <x-select name="initial_status_id" label="Estatus inicial de las solicitudes" :options="$statuses" :value="$record->initial_status_id" :required="false" />
</div>
<input type="hidden" name="is_end" value="0">
<label class="checkbox-label switch-label"><input type="checkbox" name="is_end" value="1" role="switch" aria-describedby="is_end-error" @error('is_end') aria-invalid="true" @enderror @checked(old('is_end', $record->is_end))><span>Finaliza el proceso</span></label>
<p class="field-error" id="is_end-error" data-error="is_end">@error('is_end'){{ $message }}@enderror</p>
<p class="field-hint">Con compras en curso solo puedes ajustar la visualización de campos o agregar campos. Los cambios de secuencia y responsables requieren finalizar las compras.</p>
<x-form-actions :cancel="route('processes.index', ['process_id' => $process->id])" modal />
</form>
@if($record->exists)<x-record-history :record="$record" resource="etapas-compras" />@endif
@if($record->exists)
    <template id="delete-process-stage-{{ $record->id }}" data-dialog-title="Quitar etapa">@include('modules.processes.partials.remove-stage')</template>
    <x-record-print :id="'print-process-stage-'.$record->id" :title="$record->stage?->name" :fields="['Proceso' => $process->name, 'Posición' => $record->position, 'Rol' => $record->role?->name, 'Estatus al completar' => $record->completionStatus?->name, 'Inicia el proceso' => $record->is_start ? 'Sí' : 'No', 'Finaliza el proceso' => $record->is_end ? 'Sí' : 'No', 'Solo revisión' => $record->review_only ? 'Sí' : 'No']">
        <h2>Permisos de campos</h2>
        <ul>@foreach($record->fieldRules as $rule)<li>{{ $rule->field?->name }} · {{ ['read' => 'Lectura', 'edit' => 'Editable', 'hidden' => 'Oculto'][$rule->mode] ?? $rule->mode }}{{ $rule->is_required ? ' · Obligatorio' : '' }}</li>@endforeach</ul>
    </x-record-print>
@endif
