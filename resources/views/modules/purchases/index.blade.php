@extends('layouts.app')
@section('title', $tracking ? 'Tracking de solicitudes' : 'Pendientes de compras')
@section('description', $tracking ? 'Consulta las solicitudes en las que tú has trabajado.' : 'Solicitudes pendientes que corresponden a tus roles en esta empresa.')
@section('module', 'resources/js/modules/purchases/index.js')
@section('actions')
    <a class="button button-secondary" href="{{ route($tracking ? 'purchases.index' : 'purchases.tracking') }}">{{ $tracking ? 'Ver pendientes' : 'Tracking de solicitudes' }}</a>
    @if($canCreate)<a class="button button-primary" href="{{ route('purchases.create') }}"><x-icon name="plus" size="18" /> Nueva solicitud</a>@endif
@endsection
@section('content')
<x-list-filters :action="route($tracking ? 'purchases.tracking' : 'purchases.index')" placeholder="Buscar por número o proveedor">
    <label class="list-filter-field" for="filter-status"><span>Estatus</span><select id="filter-status" name="status_id"><option value="">Todos los estatus</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>@endforeach</select></label>
</x-list-filters>
@fragment('list-results')
<div data-list-results>
<div class="table-panel">
@if($records->isEmpty())
    <x-empty-state :title="$tracking ? 'Todavía no hay solicitudes en tu tracking' : 'No tienes solicitudes pendientes'" :description="$tracking ? 'Aquí aparecerán las solicitudes que hayas creado o actualizado.' : 'Las solicitudes aparecerán cuando lleguen a una etapa asignada a tu rol.'" />
@else
<div class="table-scroll"><table><caption class="sr-only">{{ $tracking ? 'Solicitudes en las que has trabajado' : 'Solicitudes pendientes' }}</caption>
<thead><tr><th>Solicitud</th><th>Proveedor</th><th>Etapa actual</th><th>Estatus</th><th class="numeric">Monto USD</th><th>Situación</th><th class="actions-column"><span class="sr-only">Acciones</span></th></tr></thead>
<tbody>@foreach($records as $item)<tr>
<td><strong>#{{ $item->id }}</strong></td><td>{{ $item->supplier?->name ?? 'Sin proveedor' }}</td><td>{{ $item->currentStage?->stage?->name ?? 'Etapa no disponible' }}</td>
<td><x-status-badge :status="$item->status" /></td><td class="numeric">{{ $item->amount === null ? 'Sin monto' : number_format((float) $item->amount, 2, '.', ',') }}</td><td>{{ $item->completed_at ? 'Finalizada' : 'En curso' }}</td>
<td class="actions-column"><a class="button button-quiet" href="{{ route('purchases.show', $item) }}">Abrir solicitud</a></td>
</tr>@endforeach</tbody></table></div>
@endif
<x-list-pagination :records="$records" />
</div>
</div>
@endfragment
@endsection
