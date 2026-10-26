@props(['record', 'resource', 'entries' => null, 'showLink' => true, 'full' => false])
@php
    // El controlador carga esta relación: la vista nunca consulta la base de datos.
    $history = $entries ?? ($record->relationLoaded('recentActivities') ? $record->getRelation('recentActivities') : collect());
    $actions = ['created' => 'Registro creado', 'updated' => 'Registro actualizado', 'deleted' => 'Registro eliminado'];
    $labels = [
        'name' => 'Nombre', 'tax_id' => 'Identificación fiscal', 'email' => 'Correo electrónico',
        'phone' => 'Teléfono', 'address' => 'Dirección', 'is_active' => 'Permite operar',
        'is_parent' => 'Menú padre', 'sort_order' => 'Orden', 'icon' => 'Icono',
        'route_name' => 'Acción inicial', 'lifecycle_status' => 'Estado del registro',
        'status_id' => 'Estado', 'status_type_id' => 'Módulo del estado', 'company_id' => 'Empresa',
        'parent_id' => 'Menú padre', 'module_id' => 'Módulo', 'permission_id' => 'Permiso',
        'module_ids' => 'Módulos habilitados', 'permission_ids' => 'Permisos', 'role_ids' => 'Roles',
        'origin_stage_id' => 'Etapa origen', 'destination_stage_id' => 'Etapa destino', 'responsible_role_id' => 'Rol responsable',
        'stage_ids' => 'Secuencia de etapas', 'process_definition_id' => 'Proceso', 'process_stage_id' => 'Etapa del catálogo',
        'completion_status_id' => 'Estatus de resultado', 'initial_status_id' => 'Estatus inicial', 'position' => 'Posición', 'field_name' => 'Datos de la etapa',
        'supplier_id' => 'Proveedor', 'purchase_stage_id' => 'Etapa actual', 'created_by_id' => 'Solicitante',
        'purchase_date' => 'Fecha de compra', 'approval_date' => 'Fecha de aprobación', 'receipt_date' => 'Fecha de recepción', 'payment_date' => 'Fecha de pago',
        'amount' => 'Monto', 'currency' => 'Moneda', 'completed_at' => 'Finalización',
        'password_changed' => 'Contraseña',
    ];
    $formatValue = static function ($value, string $field): string {
        if ($value === null || $value === '') return 'Sin valor';
        if (in_array($field, ['is_active', 'is_parent'], true)) return $value ? 'Sí' : 'No';
        if ($field === 'field_name') return ['purchase_date' => 'Proveedor, monto y fecha de compra', 'approval_date' => 'Fecha de aprobación', 'receipt_date' => 'Fecha de recepción', 'payment_date' => 'Fecha de pago'][$value] ?? 'Solo revisión y envío';
        if ($field === 'lifecycle_status') return [1 => 'Activo', 0 => 'Inactivo', -1 => 'Eliminado'][$value] ?? (string) $value;
        return is_scalar($value) ? (string) $value : 'Asignación actualizada';
    };
@endphp
<section {{ $attributes->class('record-history') }} aria-label="Historial de acciones">
    <div class="record-history-header">
        <div><h2>Historial de acciones</h2><p>Actividad registrada en este elemento.</p></div>
        @if($showLink && ! $full)
            <a class="button button-quiet history-link" href="{{ route('records.history', ['resource' => $resource, 'record' => $record->id]) }}"><x-icon name="list-check" size="18" /> Ver historial</a>
        @endif
    </div>
    @if($history->isEmpty())
        <p class="history-empty">{{ $full ? 'No hay acciones para estos filtros. Los cambios que se registren aparecerán con su fecha y responsable.' : 'Los próximos cambios aparecerán aquí con su fecha y responsable.' }}</p>
    @else
        <ol class="history-timeline">
            @foreach($history as $entry)
                <li class="history-entry">
                    <span class="history-marker" aria-hidden="true"><x-icon :name="$entry->action === 'deleted' ? 'trash' : ($entry->action === 'created' ? 'plus' : 'pencil')" size="14" /></span>
                    <div class="history-entry-content">
                        <div class="history-entry-heading"><strong>{{ $actions[$entry->action] ?? 'Acción registrada' }}</strong><time datetime="{{ $entry->created_at->toIso8601String() }}">{{ $entry->created_at->format('d/m/Y H:i') }}</time></div>
                        <p class="history-actor">{{ $entry->actor?->name ?? 'Sistema' }}</p>
                        @if($entry->changes)
                            <dl class="history-changes">
                                @foreach($entry->changes as $field => $change)
                                    @continue(! isset($labels[$field]) || ! is_array($change))
                                    <div>
                                        <dt>{{ $labels[$field] }}</dt>
                                        <dd>
                                            @if($field === 'password_changed')
                                                Contraseña actualizada
                                            @elseif(str_ends_with($field, '_id') || str_ends_with($field, '_ids'))
                                                Asignación actualizada
                                            @else
                                                <span class="history-before">{{ $formatValue($change['before'] ?? null, $field) }}</span>
                                                <span class="history-change-arrow" aria-label="cambió a">→</span>
                                                <span>{{ $formatValue($change['after'] ?? null, $field) }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
