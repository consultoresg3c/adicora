@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'usuarios', 'record' => $record->id])" :print-template="'print-user-'.$record->id" :delete-template="! $record->is_super_admin && $record->id !== auth()->id() ? 'delete-user-'.$record->id : null" delete-label="Eliminar usuario" label="Opciones del registro" />
    @include('modules.users.partials.print-detail')
@endif
