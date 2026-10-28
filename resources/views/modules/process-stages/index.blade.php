@extends('layouts.app')
@section('title', 'Etapas de procesos')
@section('description', 'Administra las etapas disponibles para los pasos del proceso.')
@section('actions')
    <a class="button button-secondary" href="{{ route('processes.index') }}">Volver a Procesos</a>
    <a class="button button-primary" href="{{ route('process-stages.create') }}" data-dialog-template="create-processStage"><x-icon name="plus" size="18" /> Nueva etapa</a>
@endsection
@section('module', 'resources/js/modules/process-stages/index.js')
@section('content')
<x-list-filters :action="route('process-stages.index')" placeholder="Buscar por etapa">
    <label class="list-filter-field" for="filter-status"><span>Estatus</span><select id="filter-status" name="status_id"><option value="">Todos los estados</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>@endforeach</select></label>
</x-list-filters>
@fragment('list-results')
<div data-list-results>
<div class="table-panel">
    @if($records->isEmpty())<x-empty-state title="No se encontraron etapas" description="Revisa los filtros o registra un nuevo registro." />
    @else
    <div class="table-scroll"><table><caption class="sr-only">Etapas de procesos registradas</caption><thead><tr><th>Etapa</th><th>Estatus administrativo</th><th>Resultado y responsable de compras</th></tr></thead><tbody>
        @foreach($records as $item)
            <tr>
                <td><a class="row-action" href="{{ route('process-stages.edit', $item) }}" data-dialog-template="edit-processStage-{{ $item->id }}" aria-label="Editar {{ $item->name }}">{{ $item->name }}</a></td>
                <td><x-status-badge :status="$item->status" /></td>
                <td>@forelse($item->purchaseConfigurations as $configuration)<strong>{{ $configuration->completionStatus?->name }}</strong><span class="table-secondary">{{ $configuration->role?->name ?? 'Rol no disponible' }} · {{ $configuration->company?->name }}</span>@empty<span>Sin configuración de compras</span>@endforelse</td>
            </tr>
        @endforeach
    </tbody></table></div>
    @endif
    <x-list-pagination :records="$records" />
</div>
@foreach($records as $item)
    <template id="edit-processStage-{{ $item->id }}" data-dialog-title="Editar etapa">
        @include('modules.process-stages.partials.form', ['record' => $item])
    </template>
@endforeach
@if($record->exists && ! $records->getCollection()->contains('id', $record->id))
    <template id="edit-processStage-{{ $record->id }}" data-dialog-title="Editar etapa">
        @include('modules.process-stages.partials.form')
    </template>
@endif
</div>
@endfragment
<template id="create-processStage" data-dialog-title="Nueva etapa">
    @include('modules.process-stages.partials.form', ['record' => $formRecord])
</template>
<x-record-dialog :title="$record->exists ? 'Editar etapa' : 'Nueva etapa'" :auto-open="$openModal" :initial-template="$record->exists ? 'edit-processStage-'.$record->id : 'create-processStage'" />
@endsection
