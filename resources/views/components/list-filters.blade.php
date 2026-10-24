@props(['action', 'placeholder' => 'Buscar por nombre…'])
<form method="GET" action="{{ $action }}" class="list-filters" data-list-filters role="search" aria-label="Buscar y filtrar listado">
    <label class="list-filter-field list-search">
        <span>Buscar</span>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" maxlength="100" autocomplete="off">
    </label>
    {{ $slot }}
    <label class="list-filter-field list-page-size">
        <span>Filas</span>
        <select name="per_page" aria-label="Filas por página">
            @foreach([10, 15, 25, 50] as $size)
                <option value="{{ $size }}" @selected((int) request('per_page', 15) === $size)>{{ $size }}</option>
            @endforeach
        </select>
    </label>
    <div class="list-filter-actions">
        <button class="button button-primary" type="submit">Filtrar</button>
        <a class="button button-secondary" href="{{ $action }}">Limpiar</a>
    </div>
</form>
<p class="list-feedback" data-list-feedback role="status" aria-live="polite" hidden></p>
