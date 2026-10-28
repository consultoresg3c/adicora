@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'menus', 'record' => $record->id])" :print-template="'print-menu-'.$record->id" :delete-template="'delete-menu-'.$record->id" delete-label="Eliminar menú" label="Opciones del registro" />
    @include('modules.menus.partials.print-detail')
@endif
