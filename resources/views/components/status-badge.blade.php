@props(['status'])
<span @class(['status-badge', 'status-inactive' => ! $status?->is_active]) title="{{ $status?->name ?? 'Sin estado' }}"><span class="status-badge-label">{{ $status?->name ?? 'Sin estado' }}</span></span>
