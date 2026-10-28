@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'etapas-procesos', 'record' => $record->id])" :print-template="'print-processStage-'.$record->id" :delete-template="'delete-processStage-'.$record->id" delete-label="Eliminar etapa" label="Opciones del registro" />
    @include('modules.process-stages.partials.print-detail')
@endif
