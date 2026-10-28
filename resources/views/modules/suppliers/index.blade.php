@extends('layouts.app')
@section('title', 'Proveedores')
@section('description', 'Administra los proveedores disponibles para tus compras.')
@section('actions')
    <a class="button button-primary" href="{{ route('suppliers.create') }}" data-dialog-template="create-supplier"><x-icon name="plus" size="18" /> Nuevo proveedor</a>
@endsection
@section('module', 'resources/js/modules/suppliers/index.js')
@section('content')
<x-list-filters :action="route('suppliers.index')" placeholder="Buscar por proveedor o empresa">
    @if(auth()->user()->is_super_admin)
        <label class="list-filter-field" for="filter-company"><span>Empresa</span><select id="filter-company" name="company_id"><option value="">Todas las empresas</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>@endforeach</select></label>
    @endif
    <label class="list-filter-field" for="filter-status"><span>Estado</span><select id="filter-status" name="status_id"><option value="">Todos los estados</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>@endforeach</select></label>
</x-list-filters>
@fragment('list-results')
<div data-list-results>
<div class="table-panel">
    @if($records->isEmpty())<x-empty-state title="No se encontraron proveedores" description="Revisa los filtros o registra una nuevo proveedor." />
    @else
    <div class="table-scroll"><table><caption class="sr-only">Proveedores registrados</caption><thead><tr><th>Proveedor</th><th>Empresa</th><th>Estado</th></tr></thead><tbody>
        @foreach($records as $item)
            <tr>
                <td><a class="row-action" href="{{ route('suppliers.edit', $item) }}" data-dialog-template="edit-supplier-{{ $item->id }}" aria-label="Editar {{ $item->name }}">{{ $item->name }}</a></td>
                <td>{{ $item->company->name }}</td>
                <td><x-status-badge :status="$item->status" /></td>
            </tr>
        @endforeach
    </tbody></table></div>
    @endif
    <x-list-pagination :records="$records" />
</div>
@foreach($records as $item)
    <template id="edit-supplier-{{ $item->id }}" data-dialog-title="Editar proveedor">
        @include('modules.suppliers.partials.form', ['record' => $item])
    </template>
@endforeach
@if($record->exists && ! $records->getCollection()->contains('id', $record->id))
    <template id="edit-supplier-{{ $record->id }}" data-dialog-title="Editar proveedor">
        @include('modules.suppliers.partials.form')
    </template>
@endif
</div>
@endfragment
<template id="create-supplier" data-dialog-title="Nuevo proveedor">
    @include('modules.suppliers.partials.form', ['record' => $formRecord])
</template>
<x-record-dialog :title="$record->exists ? 'Editar proveedor' : 'Nuevo proveedor'" :auto-open="$openModal" :initial-template="$record->exists ? 'edit-supplier-'.$record->id : 'create-supplier'" />
@endsection
