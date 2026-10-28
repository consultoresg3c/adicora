@extends('layouts.app')
@section('title', 'Usuarios')
@section('module', 'resources/js/modules/users/index.js')
@section('description', 'Cada cuenta pertenece a una empresa y recibe acceso por roles o permisos individuales.')
@section('actions')<a class="button button-primary" href="{{ route('users.create') }}"><x-icon name="plus" size="18" /> Nuevo usuario</a>@endsection
@section('content')
    <x-list-filters :action="route('users.index')" placeholder="Buscar por nombre o correo">
        <label class="list-filter-field">
            <span>Estado</span>
            <select name="status_id">
                <option value="">Todos los estados</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>
                @endforeach
            </select>
        </label>
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
    </x-list-filters>
    @fragment('list-results')
    <div data-list-results>
    <div class="table-panel">
    @if($records->isEmpty())<x-empty-state title="No hay usuarios para mostrar" description="Crea una cuenta o ajusta la búsqueda y los filtros para encontrar otros resultados." />
    @else
    <div class="table-scroll"><table><caption class="sr-only">Usuarios registrados</caption><thead><tr><th>Usuario</th><th>Empresa</th><th>Roles</th><th>Estado</th></tr></thead><tbody>
        @foreach($records as $item)
            <tr>
                <td>@if($item->can_manage)<a class="row-action" href="{{ route('users.edit', $item) }}" aria-label="Editar {{ $item->name }}">{{ $item->name }}</a>@else<strong>{{ $item->name }}</strong><span class="table-secondary">Protegido</span>@endif<span class="table-secondary">{{ $item->email }}</span></td>
                <td>{{ $item->company?->name ?? 'Sistema' }}</td>
                <td>{{ $item->is_super_admin ? 'Superadministrador' : ($item->roles->pluck('name')->join(', ') ?: 'Sin rol') }}</td>
                <td><x-status-badge :status="$item->status" /></td>
            </tr>
        @endforeach
    </tbody></table></div>
    @endif
    <x-list-pagination :records="$records" />
    </div>
    </div>
    @endfragment
@endsection
