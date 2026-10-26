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
    <h2>{{ $record->currentStage?->stage?->name ?? 'Etapa no disponible' }}</h2>
    <p>{{ $record->completed_at ? 'Solicitud finalizada' : 'Solicitud en curso' }} @if($record->exists) · {{ $record->status?->name }} @endif</p>
</section>
@if($editable)
<form class="form-panel" method="post" action="{{ $record->exists ? route('purchases.update', $record) : route('purchases.store') }}" data-async-form data-purchase-form novalidate>
@csrf @if($record->exists) @method('PUT') <input type="hidden" name="version" value="{{ $record->version }}"> @endif
<input type="hidden" name="intent" value="save">
@else
<section class="form-panel" aria-label="Datos de la solicitud">
@endif
@if($editable && $field === null)<p class="field-hint">Esta etapa requiere revisar los datos y confirmar el envío al siguiente responsable.</p>@endif
<div class="form-grid">
@if($field === 'purchase_date')
    <x-select name="supplier_id" label="Proveedor" :options="$suppliers" :value="$record->supplier_id" :required="false" />
    <x-field name="amount" label="Monto (USD)" type="number" :value="$record->amount" step="0.01" min="0.01" max="99999999999999.99" />
@else
    <div class="field"><span>Proveedor</span><p>{{ $record->supplier?->name ?? 'Sin proveedor' }}</p></div>
    <div class="field"><span>Monto (USD)</span><p>{{ $record->amount === null ? 'Sin monto' : number_format((float) $record->amount, 2, '.', ',') }}</p></div>
@endif
@foreach(['purchase_date' => 'Fecha de compra', 'approval_date' => 'Fecha de aprobación', 'receipt_date' => 'Fecha de recepción', 'payment_date' => 'Fecha de pago'] as $dateField => $label)
    @if($field === $dateField)
        <x-field :name="$dateField" :label="$label" type="date" :value="$record->{$dateField}?->format('Y-m-d')" />
    @else
        <div class="field"><span>{{ $label }}</span><p>{{ $record->{$dateField}?->format('d/m/Y') ?? 'Pendiente' }}</p></div>
    @endif
@endforeach
</div>
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
@if($record->exists)<x-record-history :record="$record" resource="solicitudes-compra" />@endif
@endsection
