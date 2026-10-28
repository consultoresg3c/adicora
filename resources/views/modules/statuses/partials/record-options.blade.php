@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'estados', 'record' => $record->id])" :print-template="'print-status-'.$record->id" :delete-template="'delete-status-'.$record->id" delete-label="Eliminar estado" label="Opciones del registro" />
    @include('modules.statuses.partials.print-detail')
@endif
