@extends('layouts.app')
@section('title', 'Estados')
@section('description', 'Estados agrupados por módulo. Define cuáles permiten operar sin mezclar sus relaciones.')
@section('actions')
    <a class="button button-primary" href="{{ route('statuses.create') }}" data-dialog-template="create-status">
        <x-icon name="plus" size="18" /> Nuevo estado
    </a>
@endsection
@section('module', 'resources/js/modules/statuses/index.js')
@section('content')
<x-list-filters :action="route('statuses.index')" placeholder="Buscar estado por nombre">
    <label class="list-filter-field">
        <span>Módulo</span>
        <select name="status_type_id">
            <option value="">Todos los módulos</option>
            @foreach($statusTypes as $type)
                <option value="{{ $type->id }}" @selected((string) request('status_type_id') === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="list-filter-field">
        <span>Permite operar</span>
        <select name="is_active">
            <option value="">Todos</option>
            <option value="1" @selected(request('is_active') === '1')>Sí</option>
            <option value="0" @selected(request('is_active') === '0')>No</option>
        </select>
    </label>
</x-list-filters>
@fragment('list-results')
<div data-list-results>
<div class="table-panel">
    @if($records->isEmpty())
        <x-empty-state title="Sin estados para estos filtros" description="Ajusta la búsqueda o los filtros para consultar otros estados." />
    @else
    <div class="table-scroll">
        <table>
            <caption class="sr-only">Estados disponibles</caption>
            <thead><tr><th>Nombre</th><th>Permite operar</th><th class="actions-column"><span class="sr-only">Acciones</span></th></tr></thead>
            @foreach($groups as $typeId => $items)
            <tbody aria-labelledby="status-group-{{ $typeId }}">
                <tr class="table-group-row">
                    <th id="status-group-{{ $typeId }}" scope="rowgroup" colspan="3">{{ $items->first()->type->name }}</th>
                </tr>
                @foreach($items as $item)
                    <tr>
                        <td><strong>{{ $item->name }}</strong></td>
                        <td><x-boolean-badge :value="$item->is_active" /></td>
                        <td class="actions-column">
                            <div class="row-actions">
                                <a class="button button-quiet icon-button" href="{{ route('records.history', ['resource' => 'estados', 'record' => $item->id]) }}" aria-label="Historial del estado {{ $item->name }} de {{ $item->type->name }}" title="Ver historial"><x-icon name="list-check" size="18" /></a>
                                <a class="button button-quiet icon-button" href="{{ route('statuses.edit', $item) }}" data-dialog-template="edit-status-{{ $item->id }}" aria-label="Editar estado {{ $item->name }} de {{ $item->type->name }}" title="Editar estado"><x-icon name="pencil" size="18" /></a>
                                <button class="button button-quiet icon-button button-danger" type="button" data-dialog-template="delete-status-{{ $item->id }}" aria-label="Eliminar estado {{ $item->name }} de {{ $item->type->name }}" title="Eliminar estado"><x-icon name="trash" size="18" /></button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            @endforeach
        </table>
    </div>
    @endif
    <x-list-pagination :records="$records" />
</div>
@foreach($records as $item)
    <template id="edit-status-{{ $item->id }}" data-dialog-title="Editar estado">
        @include('modules.statuses.partials.form', ['record' => $item])
    </template>
    <template id="delete-status-{{ $item->id }}" data-dialog-title="Eliminar estado">
        @include('modules.statuses.partials.delete-form', ['record' => $item])
    </template>
@endforeach
@if($record->exists && !$records->getCollection()->contains('id', $record->id))
    <template id="edit-status-{{ $record->id }}" data-dialog-title="Editar estado">
        @include('modules.statuses.partials.form', ['record' => $record])
    </template>
@endif
</div>
@endfragment
<template id="create-status" data-dialog-title="Nuevo estado">
    @include('modules.statuses.partials.form', ['record' => $newRecord])
</template>
<x-record-dialog :initial-template="$initialTemplate" />
@endsection
