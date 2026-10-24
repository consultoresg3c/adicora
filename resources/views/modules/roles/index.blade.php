@extends('layouts.app')
@section('title', 'Roles y permisos')
@section('module', 'resources/js/modules/roles/index.js')
@section('description', 'Agrupa permisos por responsabilidad. Los permisos directos complementan los roles de cada usuario.')
@section('actions')<a class="button button-primary" href="{{ route('roles.create') }}"><x-icon name="plus" size="18" /> Nuevo rol</a>@endsection
@section('content')
    <x-list-filters :action="route('roles.index')" placeholder="Buscar por nombre de rol">
        @if(auth()->user()->company_id === null)
            <label class="list-filter-field">
                <span>Empresa</span>
                <select name="company_id">
                    <option value="">Todas las empresas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="list-filter-field">
            <span>Permisos del módulo</span>
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
    @if($records->isEmpty())<x-empty-state title="No hay roles para mostrar" description="Crea un rol o ajusta la búsqueda y los filtros para encontrar otros resultados." />
    @else
    <div class="table-scroll"><table><caption class="sr-only">Roles registrados</caption><thead><tr><th>Rol</th><th>Ámbito</th><th class="numeric">Permisos</th><th class="numeric">Usuarios</th><th class="actions-column"><span class="sr-only">Acciones</span></th></tr></thead><tbody>
        @foreach($records as $item)
            <tr>
                <td><strong>{{ $item->name }}</strong></td>
                <td>{{ $item->company?->name ?? 'Sistema' }}</td>
                <td class="numeric">{{ $item->permissions_count }}</td>
                <td class="numeric">{{ $item->users_count }}</td>
                <td class="actions-column">
                    @if($item->can_manage)
                        <div class="row-actions">
                            <a class="button button-quiet icon-button" href="{{ route('records.history', ['resource' => 'roles', 'record' => $item->id]) }}" aria-label="Historial de {{ $item->name }}" title="Ver historial"><x-icon name="list-check" size="18" /></a>
                            <a class="button button-quiet icon-button" href="{{ route('roles.edit', $item) }}" aria-label="Editar {{ $item->name }}" title="Editar rol"><x-icon name="pencil" size="18" /></a>
                            <button class="button button-quiet icon-button button-danger" type="button" data-dialog-template="delete-role-{{ $item->id }}" aria-label="Eliminar {{ $item->name }}" title="Eliminar rol"><x-icon name="trash" size="18" /></button>
                        </div>
                    @else<span class="table-secondary">Protegido</span>@endif
                </td>
            </tr>
        @endforeach
    </tbody></table></div>
    @endif
    <x-list-pagination :records="$records" />
    </div>
    @foreach($records as $item)
        @if($item->can_manage)
            <template id="delete-role-{{ $item->id }}" data-dialog-title="Eliminar rol">
                @include('modules.roles.partials.delete-form', ['record' => $item])
            </template>
        @endif
    @endforeach
    </div>
    @endfragment
<x-record-dialog title="Eliminar rol" />
@endsection
