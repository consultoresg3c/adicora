@extends('layouts.app')
@section('title', $record->exists ? 'Editar empresa' : 'Nueva empresa')
@section('description', 'Información general y funciones disponibles para la empresa.')
@section('module', 'resources/js/modules/companies/index.js')
@section('content')
<form class="form-panel" method="post" action="{{ $record->exists ? route('companies.update', $record) : route('companies.store') }}" data-async-form novalidate>@csrf @if($record->exists)@method('put')@endif
    @include('modules.companies.partials.fields')<x-form-actions :cancel="route('companies.index')" />
</form>
@if($record->exists)
    <x-record-history :record="$record" resource="empresas" />
@endif
@endsection
