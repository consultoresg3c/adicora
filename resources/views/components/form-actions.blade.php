@props(['cancel', 'modal' => false])
<div class="form-feedback" data-form-feedback role="alert" tabindex="-1">@if($errors->any())Revisa los campos indicados.@endif</div>
<div class="form-actions">
    @if($modal)
        <button type="button" class="button button-secondary" data-close-dialog>Cancelar</button>
    @else
        <a class="button button-secondary" href="{{ $cancel }}">Cancelar</a>
    @endif
    <button class="button button-primary" type="submit" data-submit>
        <x-icon name="check" size="18" />
        <span data-submit-label>Guardar cambios</span>
    </button>
</div>
