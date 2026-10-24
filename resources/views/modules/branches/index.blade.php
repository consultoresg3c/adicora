@extends('layouts.app')
@section('title', 'Sucursales')
@section('description', 'Mantén organizadas las ubicaciones de tu empresa.')
@section('actions')
    <a class="button button-primary" href="{{ route('branches.create') }}" data-dialog-template="create-branch"><x-icon name="plus" size="18" /> Nueva sucursal</a>
@endsection
@section('module', 'resources/js/modules/branches/index.js')
@section('content')
<x-list-filters :action="route('branches.index')" placeholder="Buscar por sucursal, dirección o empresa">
    @if(auth()->user()->is_super_admin)
        <label class="list-filter-field" for="filter-company"><span>Empresa</span><select id="filter-company" name="company_id"><option value="">Todas las empresas</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>@endforeach</select></label>
    @endif
    <label class="list-filter-field" for="filter-status"><span>Estado</span><select id="filter-status" name="status_id"><option value="">Todos los estados</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>@endforeach</select></label>
</x-list-filters>
@fragment('list-results')
<div data-list-results>
<div class="table-panel">
    @if($records->isEmpty())<x-empty-state title="No se encontraron sucursales" description="Revisa los filtros o registra una nueva ubicación de tu empresa." />
    @else
    <div class="table-scroll"><table><caption class="sr-only">Sucursales registradas</caption><thead><tr><th>Sucursal</th><th>Empresa</th><th>Estado</th><th class="actions-column"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
        @foreach($records as $item)
            <tr>
                <td><strong>{{ $item->name }}</strong><span class="table-secondary">{{ $item->address }}</span></td>
                <td>{{ $item->company->name }}</td>
                <td><x-status-badge :status="$item->status" /></td>
                <td class="actions-column">
                    <div class="row-actions">
                        <a class="button button-quiet icon-button" href="{{ route('records.history', ['resource' => 'sucursales', 'record' => $item->id]) }}" aria-label="Historial de {{ $item->name }}" title="Ver historial"><x-icon name="list-check" size="18" /></a>
                        <a class="button button-quiet icon-button" href="{{ route('branches.edit', $item) }}" data-dialog-template="edit-branch-{{ $item->id }}" aria-label="Editar {{ $item->name }}" title="Editar sucursal"><x-icon name="pencil" size="18" /></a>
                        <button class="button button-quiet icon-button button-danger" type="button" data-dialog-template="delete-branch-{{ $item->id }}" aria-label="Eliminar {{ $item->name }}" title="Eliminar sucursal"><x-icon name="trash" size="18" /></button>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody></table></div>
    @endif
    <x-list-pagination :records="$records" />
</div>
@foreach($records as $item)
    <template id="edit-branch-{{ $item->id }}" data-dialog-title="Editar sucursal">
        @include('modules.branches.partials.form', ['record' => $item])
    </template>
    <template id="delete-branch-{{ $item->id }}" data-dialog-title="Eliminar sucursal">
        @include('modules.branches.partials.delete-form', ['record' => $item])
    </template>
@endforeach
@if($record->exists && ! $records->getCollection()->contains('id', $record->id))
    <template id="edit-branch-{{ $record->id }}" data-dialog-title="Editar sucursal">
        @include('modules.branches.partials.form')
    </template>
@endif
</div>
@endfragment
<template id="create-branch" data-dialog-title="Nueva sucursal">
    @include('modules.branches.partials.form', ['record' => $formRecord])
</template>
<x-record-dialog :title="$record->exists ? 'Editar sucursal' : 'Nueva sucursal'" :auto-open="$openModal" :initial-template="$record->exists ? 'edit-branch-'.$record->id : 'create-branch'" />
@endsection
