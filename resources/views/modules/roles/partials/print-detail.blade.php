<x-record-print :id="'print-role-'.$record->id" :title="$record->name" :fields="['Empresa' => $record->company?->name ?? 'Sistema', 'Registro' => $record->id]">
    <h2>Permisos asignados</h2>
    @forelse($record->permissions->groupBy(fn ($permission) => $permission->module?->name ?? 'Sistema') as $group => $items)
        <h3>{{ $group }}</h3>
        <ul>@foreach($items as $permission)<li>{{ $permission->name }}</li>@endforeach</ul>
    @empty
        <p>Sin permisos asignados.</p>
    @endforelse
</x-record-print>
