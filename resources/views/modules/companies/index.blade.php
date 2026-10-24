@extends('layouts.app')
@section('title', 'Empresas')
@section('module', 'resources/js/modules/companies/index.js')
@section('description', 'Administra la información de cada empresa y las funciones que puede utilizar.')
@section('actions')<a class="button button-primary" href="{{ route('companies.create') }}"><x-icon name="plus" size="18" /> Nueva empresa</a>@endsection
@section('content')
    <x-list-filters :action="route('companies.index')" placeholder="Buscar empresa, identificación o correo">
        <label class="list-filter-field">
            <span>Estado</span>
            <select name="status_id">
                <option value="">Todos los estados</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="list-filter-field">
            <span>Módulo habilitado</span>
            <select name="module_id">
                <option value="">Todos los módulos</option>
                @foreach($modules as $module)
                    <option value="{{ $module->id }}" @selected((string) request('module_id') === (string) $module->id)>{{ $module->name }}</option>
                @endforeach
            </select>
        </label>
    </x-list-filters>
    @fragment('list-results')
    <div data-list-results>
    <div class="table-panel">
    @if($records->isEmpty())<x-empty-state title="No hay empresas para mostrar" description="Crea una empresa o ajusta la búsqueda y los filtros para encontrar otros resultados." />
    @else
    <div class="table-scroll"><table><caption class="sr-only">Empresas registradas</caption><thead><tr><th>Empresa</th><th>Identificación fiscal</th><th>Estado</th><th class="numeric">Sucursales</th><th class="numeric">Usuarios</th><th class="actions-column"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
        @foreach($records as $item)
            <tr>
                <td><strong>{{ $item->name }}</strong><span class="table-secondary">{{ $item->email }}</span></td>
                <td>{{ $item->tax_id }}</td>
                <td><x-status-badge :status="$item->status" /></td>
                <td class="numeric">{{ $item->branches_count }}</td>
                <td class="numeric">{{ $item->users_count }}</td>
                <td class="actions-column">
                    <div class="row-actions">
                        <a class="button button-quiet icon-button" href="{{ route('records.history', ['resource' => 'empresas', 'record' => $item->id]) }}" aria-label="Historial de {{ $item->name }}" title="Ver historial"><x-icon name="list-check" size="18" /></a>
                        <a class="button button-quiet icon-button" href="{{ route('companies.edit', $item) }}" aria-label="Editar {{ $item->name }}" title="Editar empresa"><x-icon name="pencil" size="18" /></a>
                        <button class="button button-quiet icon-button button-danger" type="button" data-dialog-template="delete-company-{{ $item->id }}" aria-label="Eliminar {{ $item->name }}" title="Eliminar empresa"><x-icon name="trash" size="18" /></button>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody></table></div>
    @endif
    <x-list-pagination :records="$records" />
    </div>
    @foreach($records as $item)
        <template id="delete-company-{{ $item->id }}" data-dialog-title="Eliminar empresa">
            @include('modules.companies.partials.delete-form', ['record' => $item])
        </template>
    @endforeach
    </div>
    @endfragment
<x-record-dialog title="Eliminar empresa" />
@endsection
