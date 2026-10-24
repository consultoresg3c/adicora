<?php

namespace App\Services\Management;

use App\Models\User;
use App\Services\Access\AccessService;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private AccessService $access, private RecordHistoryService $history) {}

    public function save(User $user, array $data, User $actor): User
    {
        abort_if($user->exists && ! $this->access->canManageGrants($actor, $user), 403);
        $companyId = $this->access->companyId($actor, $data['company_id'] ?? null);
        if ($user->exists && $user->company_id !== $companyId) {
            throw ValidationException::withMessages(['company_id' => 'No se puede trasladar un usuario entre empresas.']);
        }

        return DB::transaction(function () use ($user, $data, $companyId, $actor) {
            if ($user->exists) {
                $user = $this->access->scope(User::query(), $actor)->lockForUpdate()->findOrFail($user->id);
                abort_unless($this->access->canManageGrants($actor, $user), 403);
            }
            $action = $user->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($user);
            $attributes = Arr::except($data, ['role_ids', 'permission_ids']);
            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }
            $attributes['company_id'] = $companyId;
            $user->fill($attributes);
            $credentialsUpdated = $action === 'updated' && $user->isDirty('password');
            $user->save();
            $user->roles()->sync($data['role_ids'] ?? []);
            $user->permissions()->sync($data['permission_ids'] ?? []);
            $this->history->record($user, $actor, $action, $before, $credentialsUpdated);

            return $user;
        });
    }

    public function delete(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $record = $this->access->scope(User::query(), $actor)->lockForUpdate()->findOrFail($user->id);
            abort_unless($this->access->canManageGrants($actor, $record), 403);
            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
