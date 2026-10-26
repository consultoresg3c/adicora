@extends('layouts.app')
@section('title', 'Inicio')
@section('description', 'Organiza tu espacio de trabajo y administra los accesos de tu equipo.')
@section('content')
<section class="overview-intro"><h2>{{ $company?->name ?? 'Administración de BUKO' }}</h2><p>{{ auth()->user()->is_super_admin ? 'Comienza creando una empresa, habilita sus módulos y asigna un administrador. Cada empresa mantiene sus propios usuarios, sucursales y roles.' : 'Aquí encontrarás las funciones habilitadas para tu empresa. Los accesos disponibles dependen de los permisos de tu cuenta.' }}</p></section>
<div class="overview-stats">
    @if($companyCount !== null)<div class="overview-stat"><strong>{{ $companyCount }}</strong><span>Empresas registradas</span></div>@endif
    @if($branchCount !== null)<div class="overview-stat"><strong>{{ $branchCount }}</strong><span>Sucursales registradas</span></div>@endif
    @if($userCount !== null)<div class="overview-stat"><strong>{{ $userCount }}</strong><span>Usuarios registrados</span></div>@endif
</div>
<section class="workspace-links" aria-label="Accesos disponibles">
    @can('system.companies.manage')<a class="workspace-link" href="{{ route('companies.index') }}"><div><h3>Empresas</h3><p>Información general y módulos habilitados.</p></div><span aria-hidden="true">↗</span></a>@endcan
    @can('organization.branches.manage')<a class="workspace-link" href="{{ route('branches.index') }}"><div><h3>Sucursales</h3><p>Ubicaciones que forman parte de cada empresa.</p></div><span aria-hidden="true">↗</span></a>@endcan
    @can('access.users.manage')<a class="workspace-link" href="{{ route('users.index') }}"><div><h3>Usuarios</h3><p>Cuentas, roles y permisos individuales.</p></div><span aria-hidden="true">↗</span></a>@endcan
    @can('access.roles.manage')<a class="workspace-link" href="{{ route('roles.index') }}"><div><h3>Roles y permisos</h3><p>Accesos compartidos por los miembros del equipo.</p></div><span aria-hidden="true">↗</span></a>@endcan
    @can('purchases.requests.work')<a class="workspace-link" href="{{ route('purchases.index') }}"><div><h3>Pendientes de compras</h3><p>Solicitudes que esperan tu intervención.</p></div><span aria-hidden="true">↗</span></a>
    <a class="workspace-link" href="{{ route('purchases.tracking') }}"><div><h3>Tracking de solicitudes</h3><p>Avance de las solicitudes en las que has trabajado.</p></div><span aria-hidden="true">↗</span></a>@endcan
    @can('purchases.suppliers.manage')<a class="workspace-link" href="{{ route('suppliers.index') }}"><div><h3>Proveedores</h3><p>Catálogo de proveedores de la empresa.</p></div><span aria-hidden="true">↗</span></a>@endcan
    @can('system.statuses.manage')<a class="workspace-link" href="{{ route('statuses.index') }}"><div><h3>Estados</h3><p>Catálogo dinámico para empresas, sucursales y usuarios.</p></div><span aria-hidden="true">↗</span></a>@endcan
</section>
@endsection
