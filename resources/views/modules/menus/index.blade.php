@extends('layouts.app')
@section('title', 'Menús')
@section('description', 'Organiza los módulos del menú lateral, sus iconos, orden y estado.')

@section('actions')
    @unless($pendingMigration)
        <a class="button button-primary" href="{{ route('menus.create') }}" data-dialog-template="create-menu"><x-icon name="plus" size="18" /> Nuevo menú</a>
    @endunless
@endsection

@section('module', 'resources/js/modules/menus/index.js')

@section('content')
    @if($pendingMigration)
        <div class="notice" role="status">La administración del menú estará disponible al aplicar la nueva migración de menús. Tu navegación actual sigue funcionando.</div>
    @else
        <x-list-filters :action="route('menus.index')" placeholder="Buscar por menú, acción o menú padre">
            <label class="list-filter-field" for="filter-kind"><span>Tipo de menú</span><select id="filter-kind" name="kind"><option value="">Todos los tipos</option><option value="parent" @selected(request('kind') === 'parent')>Menús padre</option><option value="child" @selected(request('kind') === 'child')>Módulos</option></select></label>
            <label class="list-filter-field" for="filter-parent"><span>Menú padre</span><select id="filter-parent" name="parent_id"><option value="">Todos los menús padre</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string) request('parent_id') === (string) $parent->id)>{{ $parent->name }}</option>@endforeach</select></label>
            <label class="list-filter-field" for="filter-module"><span>Módulo</span><select id="filter-module" name="module_id"><option value="">Todos los módulos</option>@foreach($modules as $module)<option value="{{ $module->id }}" @selected((string) request('module_id') === (string) $module->id)>{{ $module->name }}</option>@endforeach</select></label>
            <label class="list-filter-field" for="filter-status"><span>Estado</span><select id="filter-status" name="status_id"><option value="">Todos los estados</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected((string) request('status_id') === (string) $status->id)>{{ $status->name }}</option>@endforeach</select></label>
        </x-list-filters>
        @fragment('list-results')
        <div data-list-results>
        <div class="table-panel">
            @if($records->isEmpty())
                <x-empty-state title="No se encontraron menús" description="Revisa los filtros o crea un menú padre y agrega sus módulos." />
            @else
                <div class="table-scroll">
                    <table>
                        <caption class="sr-only">Menús y módulos del sistema</caption>
                        <thead><tr><th>Menú</th><th>Pertenece a</th><th>Acción inicial</th><th>Estado</th><th class="numeric">Orden</th></tr></thead>
                        <tbody>
                            @foreach($records as $item)
                                <tr>
                                    <td><span class="menu-cell"><span class="menu-icon-preview"><x-icon :name="$item->icon" /></span><span><a class="row-action" href="{{ route('menus.edit', $item) }}" data-dialog-template="edit-menu-{{ $item->id }}" aria-label="Editar menú {{ $item->name }}">{{ $item->name }}</a><small class="table-secondary">{{ $item->is_parent ? 'Menú padre' : 'Módulo' }}</small></span></span></td>
                                    <td>{{ $item->parent?->name ?? 'Menú principal' }}</td>
                                    <td><span class="table-secondary">{{ $item->is_parent ? 'Desplegar opciones' : ($actions[$item->route_name]['controller'] ?? 'Acción no disponible') }}</span></td>
                                    <td><x-status-badge :status="$item->status" /></td>
                                    <td class="numeric">{{ $item->sort_order }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <x-list-pagination :records="$records" />
        </div>
        @foreach($records as $item)
            <template id="edit-menu-{{ $item->id }}" data-dialog-title="Editar menú">
                @include('modules.menus.partials.form', ['record' => $item])
            </template>
        @endforeach
        @if($record->exists && ! $records->getCollection()->contains('id', $record->id))
            <template id="edit-menu-{{ $record->id }}" data-dialog-title="Editar menú">
                @include('modules.menus.partials.form')
            </template>
        @endif
        </div>
        @endfragment
        <template id="create-menu" data-dialog-title="Nuevo menú">
            @include('modules.menus.partials.form', ['record' => $formRecord])
        </template>
        <x-record-dialog :title="$record->exists ? 'Editar menú' : 'Nuevo menú'" :auto-open="$openModal" :initial-template="$record->exists ? 'edit-menu-'.$record->id : 'create-menu'" />
    @endif
@endsection
