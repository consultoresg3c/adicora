@props(['id', 'title', 'fields' => []])
<template id="{{ $id }}">
    <article class="record-print-detail" aria-label="Detalle guardado del registro">
        <h1>{{ $title }}</h1>
        <dl>@foreach($fields as $label => $value)<dt>{{ $label }}</dt><dd>{{ $value === null || $value === '' ? 'Sin información' : $value }}</dd>@endforeach</dl>
        {{ $slot }}
    </article>
</template>
