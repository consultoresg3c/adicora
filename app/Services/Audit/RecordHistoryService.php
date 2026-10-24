<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Models\Branch;
use App\Models\Company;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Conserva únicamente campos administrativos permitidos; nunca serializa el modelo completo. */
class RecordHistoryService
{
    public function snapshot(Model $record): array
    {
        if (! $record->exists) {
            return [];
        }

        $fields = match (true) {
            $record instanceof Company => ['name', 'tax_id', 'email', 'phone', 'address', 'status_id'],
            $record instanceof Branch => ['company_id', 'name', 'address', 'status_id'],
            $record instanceof User => ['company_id', 'name', 'email', 'status_id'],
            $record instanceof Role => ['company_id', 'name'],
            $record instanceof Status => ['status_type_id', 'name', 'is_active'],
            $record instanceof MenuItem => ['parent_id', 'module_id', 'permission_id', 'status_id', 'name', 'is_parent', 'icon', 'route_name', 'sort_order'],
        };
        $snapshot = $record->only($fields);
        foreach ($snapshot as $field => $value) {
            if (str_ends_with($field, '_id') && $value !== null) {
                $snapshot[$field] = (int) $value;
            }
        }

        // Una edición puede cambiar status_id después de haber cargado la relación anterior.
        $record->unsetRelation('status');
        $snapshot['lifecycle_status'] = $record->lifecycle_status;
        if ($record instanceof Company) {
            $snapshot['module_ids'] = $record->modules()->orderBy('modules.id')->pluck('modules.id')->all();
        }
        if ($record instanceof User) {
            $snapshot['role_ids'] = $record->roles()->orderBy('roles.id')->pluck('roles.id')->all();
        }
        if ($record instanceof User || $record instanceof Role) {
            $snapshot['permission_ids'] = $record->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();
        }

        return $snapshot;
    }

    public function record(Model $record, User $actor, string $action, array $before = [], bool $credentialsUpdated = false): ?AuditEntry
    {
        $after = $this->snapshot($record);
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $previous = $before[$field] ?? null;
            $current = $after[$field] ?? null;
            if ($previous !== $current) {
                $changes[$field] = ['before' => $previous, 'after' => $current];
            }
        }
        if ($credentialsUpdated) {
            // Se registra el hecho, sin contraseña, hash ni longitud del secreto.
            $changes['password_changed'] = ['before' => null, 'after' => true];
        }
        if ($changes === []) {
            return null;
        }

        $subjectKey = match (true) {
            $record instanceof Company => 'company_record_id',
            $record instanceof Branch => 'branch_id',
            $record instanceof User => 'user_id',
            $record instanceof Role => 'role_id',
            $record instanceof Status => 'status_id',
            $record instanceof MenuItem => 'menu_item_id',
        };
        $companyId = $record instanceof Company ? $record->id : $record->getAttribute('company_id');

        return AuditEntry::create([
            'actor_id' => $actor->id,
            'company_id' => $companyId,
            $subjectKey => $record->id,
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
