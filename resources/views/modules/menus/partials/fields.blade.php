<x-record-form-header>
    <x-slot:options>@include('modules.menus.partials.record-options')</x-slot:options>
    <x-field name="name" label="Nombre del menú" :value="$record->name" required maxlength="80" />
</x-record-form-header>
<div class="form-grid">
    <div class="field">
        <label for="icon">Icono <span class="required-mark" aria-hidden="true">*</span></label>
        <div class="icon-picker-control"><span class="menu-icon-preview" data-icon-preview><x-icon :name="old('icon', $record->icon ?? 'layout-grid')" /></span><select name="icon" id="icon" required aria-describedby="icon-error" data-icon-select>
            @foreach($icons as $icon => $label)<option value="{{ $icon }}" @selected(old('icon', $record->icon ?? 'layout-grid') === $icon)>{{ $label }}</option>@endforeach
        </select></div>
        <p class="field-error" data-error="icon" id="icon-error">@error('icon'){{ $message }}@enderror</p>
    </div>
    <x-field name="sort_order" label="Orden" type="number" :value="$record->sort_order ?? 0" min="0" max="9999" required />
    <div class="form-full">
        <input type="hidden" name="is_parent" value="0">
        <label class="checkbox-label switch-label"><input type="checkbox" role="switch" name="is_parent" value="1" aria-describedby="is_parent-error" data-parent-menu @checked(old('is_parent', $record->is_parent))><span><strong>Es menú padre</strong><small class="field-hint">Agrupa opciones y solo se despliega en el menú lateral.</small></span></label>
        <p class="field-error" id="is_parent-error" data-error="is_parent">@error('is_parent'){{ $message }}@enderror</p>
    </div>
    <div class="form-full menu-child-fields" data-child-menu-fields>
        <x-select name="parent_id" label="Pertenece al menú" :options="$parents" :value="$record->parent_id" />
        <div class="field">
            <label for="route_name">Controlador y acción inicial <span class="required-mark" aria-hidden="true">*</span></label>
            <select name="route_name" id="route_name" required aria-describedby="route_name-error route_name-hint">
                <option value="">Selecciona una acción</option>
                @foreach($actions as $routeName => $action)<option value="{{ $routeName }}" @selected(old('route_name', $record->route_name) === $routeName)>{{ $action['label'] }} · {{ $action['controller'] }}</option>@endforeach
            </select>
            <span class="field-hint" id="route_name-hint">El módulo y el permiso se vinculan automáticamente por sus IDs.</span>
            <p class="field-error" data-error="route_name" id="route_name-error">@error('route_name'){{ $message }}@enderror</p>
        </div>
    </div>
    <div class="form-full"><x-select name="status_id" label="Estado" :options="$statuses" :value="$record->status_id" /></div>
</div>
@foreach($icons as $icon => $label)
    <template data-icon-template="{{ $icon }}"><x-icon :name="$icon" /></template>
@endforeach
