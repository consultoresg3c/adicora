<form class="dialog-form" method="post" action="{{ route('processes.stages.destroy', [$process, $record]) }}" data-async-form novalidate>
@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $process->version }}">
<p>¿Quitar <strong>{{ $record->name }}</strong> del proceso <strong>{{ $process->name }}</strong>?</p>
<p>Las etapas anterior y siguiente se conectarán automáticamente. Las solicitudes anteriores y el historial se conservarán.</p>
@include('modules.processes.partials.confirm-actions', ['label' => 'Quitar etapa'])
</form>
