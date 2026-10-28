@props(['printTemplate', 'historyUrl', 'deleteTemplate' => null, 'deleteLabel' => 'Eliminar registro', 'label' => 'Opciones del registro', 'inline' => false])
<details @class(['record-options', 'record-options-inline' => $inline]) data-record-options>
    <summary class="button button-secondary icon-button" aria-label="{{ $label }}" title="{{ $label }}"><x-icon name="dots-vertical" /></summary>
    <div class="record-options-panel">
        <a class="button button-quiet" href="{{ $historyUrl }}">Ver historial</a>
        <button class="button button-quiet" type="button" data-record-print="{{ $printTemplate }}">Imprimir detalle</button>
        @if($deleteTemplate)
            <button class="button button-quiet button-danger" type="button" data-dialog-template="{{ $deleteTemplate }}" data-dialog-target="record-confirm-dialog">{{ $deleteLabel }}</button>
        @endif
    </div>
</details>
