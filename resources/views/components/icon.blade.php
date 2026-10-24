@props(['name', 'size' => 20])
@php
    // Solo se insertan assets locales del catálogo, nunca SVG enviado por formularios.
    $safeName = preg_match('/^[a-z0-9-]+$/', $name) ? $name : 'layout-grid';
    $iconPath = public_path('icons/tabler/'.$safeName.'.svg');
    $svg = is_file($iconPath) ? file_get_contents($iconPath) : '';
@endphp
<span {{ $attributes->class('icon')->merge(['aria-hidden' => 'true', 'style' => '--icon-size: '.(int) $size.'px']) }}>{!! $svg !!}</span>
