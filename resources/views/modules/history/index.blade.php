@extends('layouts.app')
@section('title', $title)
@section('description', 'Consulta las acciones registradas, sus responsables y los cambios de este elemento.')
@section('module', 'resources/js/modules/history/index.js')
@section('actions')
    <a class="button button-secondary" href="{{ route($indexRoute) }}"><x-icon name="arrow-left" size="18" /> Volver al listado</a>
@endsection
@section('content')
<x-list-filters :action="route('records.history', ['resource' => $resource, 'record' => $record->id])" placeholder="Buscar por responsable">
    <label class="list-filter-field">
        <span>Acción</span>
        <select name="action">
            <option value="">Todas las acciones</option>
            <option value="created" @selected(request('action') === 'created')>Creación</option>
            <option value="updated" @selected(request('action') === 'updated')>Modificación</option>
            <option value="deleted" @selected(request('action') === 'deleted')>Eliminación</option>
        </select>
    </label>
</x-list-filters>
@fragment('list-results')
<div data-list-results class="history-panel">
    <x-record-history :record="$record" :resource="$resource" :entries="$entries" full />
    <x-list-pagination :records="$entries" />
</div>
@endfragment
@endsection
