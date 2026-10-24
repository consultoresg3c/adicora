@extends('layouts.app')
@section('title', $record->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('description', 'Crea una cuenta de empresa y asigna únicamente los accesos necesarios.')
@section('module', 'resources/js/modules/users/index.js')
@section('content')
@include('modules.shared.company-context', ['allowSystem' => false])
@if($record->company_id)
<form class="form-panel" method="post" action="{{ $record->exists ? route('users.update', $record) : route('users.store') }}" data-async-form novalidate>@csrf @if($record->exists)@method('put')@endif
<input type="hidden" name="company_id" value="{{ $record->company_id }}">
<div class="form-grid">
    <x-field name="name" label="Nombre completo" :value="$record->name" required maxlength="180" autocomplete="name" />
    <x-field name="email" label="Correo electrónico" type="email" :value="$record->email" required maxlength="255" autocomplete="off" />
    <x-field name="password" label="Contraseña" type="password" :required="!$record->exists" minlength="12" maxlength="128" autocomplete="new-password" :hint="$record->exists ? 'Déjala vacía para conservar la actual.' : 'Usa al menos 12 caracteres.'" />
    <x-field name="password_confirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" />
    <x-select name="status_id" label="Estado" :options="$statuses" :value="$record->status_id" />
</div>
<section class="form-section"><h2>Roles de la empresa</h2><p>Un rol concede los permisos que tiene configurados.</p><div class="check-grid">@forelse($roles as $role)<label class="checkbox-label"><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" aria-describedby="role_ids-error" @checked(in_array($role->id, old('role_ids', $record->exists ? $record->roles->modelKeys() : [])))>{{ $role->name }}</label>@empty<p class="field-hint">Todavía no hay roles en esta empresa. Puedes asignar permisos individuales.</p>@endforelse</div><p class="field-error" id="role_ids-error" data-error="role_ids">@error('role_ids'){{ $message }}@enderror</p></section>
@include('modules.shared.permissions', ['direct' => true])<x-form-actions :cancel="route('users.index')" />
</form>
@else<x-empty-state title="Selecciona una empresa para continuar" description="La cuenta quedará vinculada a esa empresa. Luego podrás asignarle roles y permisos." />@endif
@if($record->exists)
    <x-record-history :record="$record" resource="usuarios" />
@endif
@endsection
