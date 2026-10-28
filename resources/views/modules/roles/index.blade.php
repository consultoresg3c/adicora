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
    <div class="table-scroll"><table><caption class="sr-only">Roles registrados</caption><thead><tr><th>Rol</th><th>Ámbito</th><th class="numeric">Permisos</th><th class="numeric">Usuarios</th></tr></thead><tbody>
        @foreach($records as $item)
            <tr>
                <td>@if($item->can_manage)<a class="row-action" href="{{ route('roles.edit', $item) }}" aria-label="Editar {{ $item->name }}">{{ $item->name }}</a>@else<strong>{{ $item->name }}</strong><span class="table-secondary">Protegido</span>@endif</td>
                <td>{{ $item->company?->name ?? 'Sistema' }}</td>
                <td class="numeric">{{ $item->permissions_count }}</td>
                <td class="numeric">{{ $item->users_count }}</td>
            </tr>
        @endforeach
    </tbody></table></div>
    @endif
    <x-list-pagination :records="$records" />
    </div>
    </div>
    @endfragment
@endsection
