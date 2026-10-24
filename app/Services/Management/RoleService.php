<?php

namespace App\Services\Management;

use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessService;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function __construct(private AccessService $access, private RecordHistoryService $history) {}

    public function save(Role $role, array $data, User $actor): Role
    {
        $companyId = $this->access->companyId($actor, $data['company_id'] ?? null);
        abort_if($role->exists && ! $this->access->canManageGrants($actor, $role), 403);
        if ($role->exists && $role->company_id !== $companyId) {
            throw ValidationException::withMessages(['company_id' => 'No se puede trasladar un rol entre empresas.']);
        }

        return DB::transaction(function () use ($role, $data, $companyId, $actor) {
            if ($role->exists) {
                $role = $this->access->scope(Role::query(), $actor)->lockForUpdate()->findOrFail($role->id);
                abort_unless($this->access->canManageGrants($actor, $role), 403);
            }
            $action = $role->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($role);
            $role->fill(['name' => $data['name'], 'company_id' => $companyId])->save();
            $role->permissions()->sync($data['permission_ids'] ?? []);
            $this->history->record($role, $actor, $action, $before);

            return $role;
        });
    }

    public function delete(Role $role, User $actor): void
    {
        DB::transaction(function () use ($role, $actor): void {
            $record = $this->access->scope(Role::query(), $actor)->lockForUpdate()->findOrFail($role->id);
            abort_unless($this->access->canManageGrants($actor, $record), 403);
            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
