@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'proveedores', 'record' => $record->id])" :print-template="'print-supplier-'.$record->id" :delete-template="'delete-supplier-'.$record->id" delete-label="Eliminar proveedor" label="Opciones del registro" />
    @include('modules.suppliers.partials.print-detail')
@endif
