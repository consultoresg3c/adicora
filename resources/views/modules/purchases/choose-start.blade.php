@extends('layouts.app')
@section('title', 'Seleccionar estación de inicio')
@section('description', 'Elige desde cuál de tus estaciones de inicio deseas crear la solicitud.')
@section('actions')<a class="button button-secondary" href="{{ route('purchases.index') }}">Volver a pendientes</a>@endsection
@section('content')
<section class="workspace-links" aria-label="Estaciones de inicio disponibles">
@foreach($stages as $stage)
<a class="workspace-link" href="{{ route('purchases.create', ['start_stage_id' => $stage->id]) }}"><div><h3>{{ $stage->stage?->name }}</h3><p>{{ $stage->process?->name }} · {{ $stage->role?->name }}</p></div><span aria-hidden="true">↗</span></a>
@endforeach
</section>
@endsection
