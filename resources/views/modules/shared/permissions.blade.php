<section class="form-section"><h2>Permisos{{ isset($direct) ? ' individuales' : '' }}</h2><p>Solo se muestran permisos que puedes delegar. Los permisos de módulos requieren que la empresa tenga el módulo habilitado.</p>
<div class="check-grid">
@forelse($permissions->groupBy(fn($permission) => $permission->module?->name ?? 'Sistema') as $group => $items)
    <fieldset class="check-group"><legend>{{ $group }}</legend>
    @foreach($items as $permission)<label class="checkbox-label"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" aria-describedby="permission_ids-error" @checked(in_array($permission->id, old('permission_ids', $record->exists ? $record->permissions->modelKeys() : [])))>{{ $permission->name }}</label>@endforeach
    </fieldset>
@empty<p class="field-hint">No hay permisos disponibles en este ámbito.</p>@endforelse
</div><p class="field-error" id="permission_ids-error" data-error="permission_ids">@error('permission_ids'){{ $message }}@enderror</p></section>
