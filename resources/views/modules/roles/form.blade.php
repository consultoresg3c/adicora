@extends('layouts.app')
@section('title', $record->exists ? 'Editar rol' : 'Nuevo rol')
@section('description', 'Los roles pertenecen a una sola empresa. Los roles de sistema se reservan para configuración global.')
@section('module', 'resources/js/modules/roles/index.js')
@section('content')
@include('modules.shared.company-context', ['allowSystem' => true])
<form class="form-panel" method="post" action="{{ $record->exists ? route('roles.update', $record) : route('roles.store') }}" data-async-form novalidate>

@csrf @if($record->exists)@method('put')@endif
<input type="hidden" name="company_id" value="{{ $record->company_id }}">
<x-record-form-header>
<x-slot:options>@if($record->exists)
    <x-record-options :history-url="route('records.history', ['resource' => 'roles', 'record' => $record->id])" :print-template="'print-role-'.$record->id" :delete-template="'delete-role-'.$record->id" delete-label="Eliminar rol" label="Opciones del rol" />
@endif</x-slot:options>
<x-field name="name" label="Nombre del rol" :value="$record->name" required maxlength="100" />
</x-record-form-header>
@include('modules.shared.permissions')<x-form-actions :cancel="route('roles.index')" />
</form>
@if($record->exists)
    <template id="delete-role-{{ $record->id }}" data-dialog-title="Eliminar rol">
        @include('modules.roles.partials.delete-form')
    </template>
    @include('modules.roles.partials.print-detail')
    <x-record-history :record="$record" resource="roles" />
@endif
@endsection
