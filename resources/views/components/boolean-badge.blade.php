@props(['value'])
<span @class(['status-badge', 'boolean-badge', 'status-inactive' => ! $value])>{{ $value ? 'Sí' : 'No' }}</span>
