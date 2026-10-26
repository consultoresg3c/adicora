@extends('layouts.app')
@section('title', 'Procesos')
@section('description', 'Organiza las etapas, sus responsables y el resultado de cada paso.')
@section('module', 'resources/js/modules/processes/index.js')
@section('actions')
    <a class="button button-secondary" href="{{ route('process-stages.index') }}">Catálogo de etapas</a>
    <a class="button button-secondary" href="{{ route('processes.create') }}" data-dialog-template="create-process">Nuevo proceso</a>
@endsection
@section('content')
<x-list-filters :action="route('processes.index')" placeholder="Buscar por etapa o rol">
    <label class="list-filter-field" for="filter-process"><span>Proceso</span><select id="filter-process" name="process_id"><option value="">Selecciona un proceso</option>@foreach($processes as $option)<option value="{{ $option->id }}" @selected($process?->id === $option->id)>{{ $option->name }} · {{ $option->company?->name }}</option>@endforeach</select></label>
    <label class="list-filter-field" for="filter-status"><span>Estatus de resultado</span><select id="filter-status" name="status_id"><option value="">Todos los estatus</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>@endforeach</select></label>
</x-list-filters>
@fragment('list-results')
<div data-list-results>
@if($process)
<section class="process-heading" aria-label="Datos del proceso">
    <form class="process-name-form" method="post" action="{{ route('processes.update', $process) }}" data-async-form novalidate>
        @csrf @method('PUT')<input type="hidden" name="version" value="{{ $process->version }}">
        <div class="process-name-control"><x-field name="name" label="Nombre proceso" :value="$process->name" required maxlength="180" /></div>
        <button class="button button-secondary" type="submit" data-submit><span data-submit-label>Guardar nombre</span></button>
        <div class="form-feedback" data-form-feedback role="alert" tabindex="-1"></div>
    </form>
    <div class="process-heading-meta"><a class="button button-primary" href="{{ route('processes.stages.create', $process) }}" data-dialog-template="create-process-stage"><x-icon name="plus" size="18" /> Agregar etapa</a><span>{{ $process->company?->name }}</span><a class="button button-quiet" href="{{ route('records.history', ['resource' => 'flujos-procesos', 'record' => $process->id]) }}">Historial del proceso</a><button class="button button-quiet button-danger" type="button" data-dialog-template="delete-process">Eliminar proceso</button></div>
</section>
@endif
<div class="table-panel">
@if($records->isEmpty())
    <x-empty-state :title="$process ? 'No hay etapas para estos filtros' : 'No hay procesos configurados'" :description="$process ? 'Agrega una etapa o revisa los filtros del proceso.' : 'Crea un proceso y agrega sus etapas para comenzar.'" />
@else
<div class="table-scroll"><table class="process-sequence-table"><caption class="sr-only">Etapas del proceso {{ $process->name }}</caption>
<thead><tr><th>Estación origen</th><th>Estación destino</th><th>Rol</th><th>Estatus</th><th class="actions-column"><span class="sr-only">Acciones</span></th></tr></thead>
<tbody>@foreach($records as $item)<tr>
    <td><span class="table-secondary">Etapa {{ $item->position }}</span><strong>{{ $item->stage?->name }}</strong></td>
    <td>{{ $destinations[$item->id] ?? 'Fin del proceso' }}</td>
    <td>{{ $item->role?->name ?? 'Rol no disponible' }}</td>
    <td><span class="process-result-status">{{ $item->completionStatus?->name ?? 'Estatus no disponible' }}</span></td>
    <td class="actions-column"><div class="row-actions">
        <a class="button button-quiet icon-button" href="{{ route('records.history', ['resource' => 'etapas-compras', 'record' => $item->id]) }}" aria-label="Historial de {{ $item->name }}" title="Ver historial"><x-icon name="list-check" size="18" /></a>
        <a class="button button-quiet icon-button" href="{{ route('processes.stages.edit', [$process, $item]) }}" data-dialog-template="edit-process-stage-{{ $item->id }}" aria-label="Modificar {{ $item->name }}" title="Modificar etapa"><x-icon name="pencil" size="18" /></a>
        <button class="button button-quiet icon-button button-danger" type="button" data-dialog-template="delete-process-stage-{{ $item->id }}" aria-label="Quitar {{ $item->name }}" title="Quitar etapa"><x-icon name="trash" size="18" /></button>
    </div></td>
</tr>@endforeach</tbody></table></div>
@endif
<x-list-pagination :records="$records" />
</div>
@if($process)
@foreach($records as $item)
<template id="edit-process-stage-{{ $item->id }}" data-dialog-title="Modificar etapa">@include('modules.processes.partials.stage-form', ['record' => $item])</template>
<template id="delete-process-stage-{{ $item->id }}" data-dialog-title="Quitar etapa">@include('modules.processes.partials.remove-stage', ['record' => $item])</template>
@endforeach
@if($record?->exists && ! $records->getCollection()->contains('id', $record->id))<template id="edit-process-stage-{{ $record->id }}" data-dialog-title="Modificar etapa">@include('modules.processes.partials.stage-form')</template>@endif
<template id="create-process-stage" data-dialog-title="Agregar etapa">@include('modules.processes.partials.stage-form', ['record' => $formRecord])</template>
<details class="record-history"><summary>Historial reciente del proceso</summary><x-record-history :record="$process" resource="flujos-procesos" /></details>
<template id="delete-process" data-dialog-title="Eliminar proceso">
<form class="dialog-form" method="post" action="{{ route('processes.destroy', $process) }}" data-async-form novalidate>@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $process->version }}"><p>¿Eliminar el proceso <strong>{{ $process->name }}</strong>? Primero debes quitar sus etapas. Su historial se conservará.</p>@include('modules.processes.partials.confirm-actions', ['label' => 'Eliminar proceso'])</form>
</template>
@endif
</div>
@endfragment
<template id="create-process" data-dialog-title="Nuevo proceso">
<form class="dialog-form" method="post" action="{{ route('processes.store') }}" data-async-form novalidate>@csrf
<x-field name="name" label="Nombre proceso" required maxlength="180" />
<x-select name="company_id" label="Empresa" :options="$companies" />
<p class="field-hint">Cada empresa dispone de un proceso de compras. Configura aquí su secuencia de etapas.</p>
<x-form-actions :cancel="route('processes.index')" modal />
</form>
</template>
<x-record-dialog title="Gestionar proceso" :auto-open="$initialTemplate !== null" :initial-template="$initialTemplate" />
@endsection
