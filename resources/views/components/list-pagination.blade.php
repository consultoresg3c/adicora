@props(['records'])
<nav class="list-pagination" aria-label="Paginación del listado">
    <p class="list-result-count" role="status">
        @if($records->total() > 0)
            <strong>{{ $records->firstItem() }}–{{ $records->lastItem() }}</strong> de <strong>{{ $records->total() }}</strong> registros
        @else
            Sin registros
        @endif
    </p>
    <div class="list-page-controls">
        @if($records->onFirstPage())
            <button type="button" class="button button-secondary icon-button" disabled aria-label="Página anterior"><x-icon name="chevron-left" size="18" /></button>
        @else
            <a class="button button-secondary icon-button" href="{{ $records->previousPageUrl() }}" rel="prev" aria-label="Página anterior" title="Página anterior"><x-icon name="chevron-left" size="18" /></a>
        @endif
        <span class="list-page-position">Página <strong>{{ $records->currentPage() }}</strong> de <strong>{{ $records->lastPage() }}</strong></span>
        @if($records->hasMorePages())
            <a class="button button-secondary icon-button" href="{{ $records->nextPageUrl() }}" rel="next" aria-label="Página siguiente" title="Página siguiente"><x-icon name="chevron-right" size="18" /></a>
        @else
            <button type="button" class="button button-secondary icon-button" disabled aria-label="Página siguiente"><x-icon name="chevron-right" size="18" /></button>
        @endif
    </div>
</nav>
