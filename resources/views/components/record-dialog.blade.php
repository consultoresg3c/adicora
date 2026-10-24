@props(['id' => 'record-dialog', 'title' => 'Formulario', 'autoOpen' => false, 'initialTemplate' => null])
<dialog id="{{ $id }}" class="record-dialog" aria-labelledby="{{ $id }}-title" @if($autoOpen) open data-auto-open @endif @if($initialTemplate) data-initial-template="{{ $initialTemplate }}" @endif>
    <div class="dialog-header">
        <div>
            <p class="dialog-eyebrow">BUKO</p>
            <h2 id="{{ $id }}-title" data-dialog-title>{{ $title }}</h2>
        </div>
        <button type="button" class="button button-quiet icon-button" data-close-dialog aria-label="Cerrar ventana" title="Cerrar">
            <x-icon name="x" />
        </button>
    </div>
    <div data-dialog-body>{{ $slot }}</div>
</dialog>
