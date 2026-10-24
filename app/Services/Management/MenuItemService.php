<?php

namespace App\Services\Management;

use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MenuItemService
{
    public function __construct(private NavigationRegistry $registry, private RecordHistoryService $history) {}

    public function save(MenuItem $item, array $data, User $actor): MenuItem
    {
        return DB::transaction(function () use ($item, $data, $actor) {
            if ($item->exists) {
                $item = MenuItem::query()->lockForUpdate()->findOrFail($item->id);
            }

            if ($data['is_parent']) {
                $data = array_replace($data, ['parent_id' => null, 'route_name' => null, 'permission_id' => null, 'module_id' => null]);
            } else {
                if ($item->exists && $item->children()->exists()) {
                    throw ValidationException::withMessages(['is_parent' => 'Mueve o elimina los hijos antes de convertir este menú en una acción.']);
                }

                $parent = MenuItem::query()->lockForUpdate()->find($data['parent_id']);
                if (! $parent?->is_parent || $parent->parent_id !== null || $parent->id === $item->id) {
                    throw ValidationException::withMessages(['parent_id' => 'Selecciona un menú padre de primer nivel.']);
                }

                $action = $this->registry->actions()[$data['route_name']] ?? null;
                if ($action === null) {
                    throw ValidationException::withMessages(['route_name' => 'Selecciona una acción registrada del sistema.']);
                }

                $permission = $action['permission'] === null ? null : Permission::with('module')->where('code', $action['permission'])->first();
                if ($action['permission'] !== null && (! $permission || $permission->module?->code !== $action['module'])) {
                    throw ValidationException::withMessages(['route_name' => 'El permiso de esta acción no está configurado correctamente.']);
                }

                $data['permission_id'] = $permission?->id;
                $data['module_id'] = $permission?->module_id;
            }

            $action = $item->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($item);
            $item->fill($data)->save();
            $this->history->record($item, $actor, $action, $before);

            return $item;
        });
    }

    public function delete(MenuItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $record = MenuItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($record->children()->exists()) {
                throw ValidationException::withMessages(['menu' => 'Mueve o elimina los hijos antes de eliminar este menú padre.']);
            }

            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
