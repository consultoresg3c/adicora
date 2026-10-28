@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'sucursales', 'record' => $record->id])" :print-template="'print-branch-'.$record->id" :delete-template="'delete-branch-'.$record->id" delete-label="Eliminar sucursal" label="Opciones del registro" />
    @include('modules.branches.partials.print-detail')
@endif
