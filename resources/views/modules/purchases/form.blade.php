@extends('layouts.app')
@section('title', $record->exists ? $record->name : 'Nueva solicitud de compra')
@section('description', $editable ? 'Completa los campos de tu etapa. Los datos de las otras etapas son de consulta.' : 'Consulta el avance y el historial de esta solicitud.')
@section('module', 'resources/js/modules/purchases/index.js')
@section('actions')
<a class="button button-secondary" href="{{ route('purchases.index') }}">Pendientes</a>
<a class="button button-secondary" href="{{ route('purchases.tracking') }}">Tracking de solicitudes</a>
@endsection
@section('content')
<section class="form-panel">
    @if($record->exists)
        <h2>{{ $record->name }}</h2>
        <x-record-print :id="'print-purchase-'.$record->id" :title="$record->name" :fields="['Etapa actual' => $record->currentStage?->stage?->name, 'Estado' => $record->status?->name, 'Situación' => $record->completed_at ? 'Finalizada' : 'En curso']">
            <dl>@foreach($fieldViews as $field)<dt>{{ $field['field']->name }}{{ $field['field']->attribute === 'amount' ? ' (USD)' : '' }}</dt><dd>{{ $field['display'] }}</dd>@endforeach</dl>
        </x-record-print>
    @endif
    <h2>{{ $record->currentStage?->stage?->name ?? 'Etapa no disponible' }}</h2>
    <p>{{ $record->completed_at ? 'Solicitud finalizada' : 'Solicitud en curso' }} @if($record->exists) · {{ $record->status?->name }} @endif</p>
</section>
@if($editable)
<form class="form-panel" method="post" action="{{ $record->exists ? route('purchases.update', $record) : route('purchases.store') }}" data-async-form data-purchase-form novalidate>
@csrf @if($record->exists) @method('PUT') <input type="hidden" name="version" value="{{ $record->version }}"> @endif
<input type="hidden" name="intent" value="save">
<input type="hidden" name="configuration_version" value="{{ $configurationVersion }}">
@if(! $record->exists)<input type="hidden" name="start_stage_id" value="{{ $record->currentStage->id }}">@endif
@else
<section class="form-panel" aria-label="Datos de la solicitud">
@endif

@if($editable && ! $fieldViews->contains('editable', true))<p class="field-hint">Esta etapa requiere revisar los datos y confirmar el envío al siguiente responsable.</p>@endif
<x-record-form-header>
<x-slot:options>@if($record->exists)<x-record-options :history-url="route('records.history', ['resource' => 'solicitudes-compra', 'record' => $record->id])" :print-template="'print-purchase-'.$record->id" label="Opciones de la solicitud" />@endif</x-slot:options>

<div class="form-grid">
@foreach($fieldViews->take(2) as $item)@include('modules.purchases.partials.field')@endforeach
</div>
</x-record-form-header>
@if($fieldViews->count() > 2)
<div class="form-grid">
@foreach($fieldViews->skip(2) as $item)@include('modules.purchases.partials.field')@endforeach
</div>
@endif
@if($editable)
<div class="form-feedback" data-form-feedback role="alert" tabindex="-1"></div>
<div class="form-actions">
    <a class="button button-secondary" href="{{ route('purchases.index') }}">Volver</a>
    <button class="button button-secondary" type="submit" data-submit data-intent="save"><span data-submit-label>Guardar</span></button>
    <button class="button button-primary" type="submit" data-intent="advance">{{ $isFinalStage ? 'Completar etapa y finalizar' : 'Completar etapa y enviar' }}</button>
</div>
</form>
@else
</section>
@endif
@if($record->exists)<x-record-history :record="$record" resource="solicitudes-compra" :field-labels="$historyLabels" />@endif
@endsection
