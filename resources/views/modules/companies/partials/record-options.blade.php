@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'empresas', 'record' => $record->id])" :print-template="'print-company-'.$record->id" :delete-template="'delete-company-'.$record->id" delete-label="Eliminar empresa" label="Opciones del registro" />
    @include('modules.companies.partials.print-detail')
@endif
