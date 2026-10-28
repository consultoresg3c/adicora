<form class="dialog-form" method="post" action="{{ $record->exists ? route('process-stages.update', $record) : route('process-stages.store') }}" data-async-form novalidate>
    @csrf
    @if($record->exists) @method('PUT') @endif
    @include('modules.process-stages.partials.fields')
    <x-form-actions :cancel="route('process-stages.index')" modal />
</form>
@if($record->exists)
    <x-record-history :record="$record" resource="etapas-procesos" />
@endif
@if($record->exists)
    <template id="delete-processStage-{{ $record->id }}" data-dialog-title="Eliminar etapa">
        @include('modules.process-stages.partials.delete-form')
    </template>
@endif
