@props(['action', 'name', 'label' => 'registro', 'description' => null])
<form class="dialog-form" method="post" action="{{ $action }}" data-async-form novalidate>
    @csrf
    @method('DELETE')
    <div class="confirmation-copy">
        <p class="confirmation-message">¿Eliminar {{ $label }} <strong>{{ $name }}</strong>?</p>
        <p class="confirmation-description">Dejará de aparecer en los listados. Su información y su historial se conservarán mediante borrado lógico.</p>
        @if($description)<p class="confirmation-description">{{ $description }}</p>@endif
    </div>
    <div class="form-feedback" data-form-feedback role="alert" tabindex="-1"></div>
    <div class="form-actions">
        <button type="button" class="button button-secondary" data-close-dialog>Cancelar</button>
        <button type="submit" class="button button-danger button-delete" data-submit>
            <x-icon name="trash" size="18" /><span data-submit-label>Eliminar {{ $label }}</span>
        </button>
    </div>
</form>
