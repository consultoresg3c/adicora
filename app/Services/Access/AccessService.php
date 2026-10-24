<?php

namespace App\Services\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccessService
{
    public function isActive(User $user): bool
    {
        if ($user->trashed()) {
            return false;
        }
        $user->loadMissing('status', 'company.status');
        if (! $user->status?->is_active) {
            return false;
        }

        if ($user->company_id === null) {
            $user->loadMissing('roles');

            return $user->is_super_admin || $user->roles->contains(fn (Role $role) => ! $role->trashed() && $role->company_id === null);
        }

        return $user->company !== null
            && ! $user->company->trashed()
            && (bool) $user->company->status?->is_active;
    }

    public function allows(User $user, string $ability): bool
    {
        if (! $this->isActive($user)) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        $user->loadMissing('permissions', 'roles.permissions', 'company.modules');
        $roles = $user->roles->where('company_id', $user->company_id)->reject(fn (Role $role) => $role->trashed());
        $permission = $user->permissions->firstWhere('code', $ability)
            ?? $roles->flatMap(fn (Role $role) => $role->permissions)->firstWhere('code', $ability);
        if (! $permission) {
            return false;
        }

        // El permiso nunca puede superar el ámbito del usuario ni el módulo contratado.
        if ($user->company_id !== null) {
            return $permission->module_id !== null && $user->company->modules->contains('id', $permission->module_id);
        }

        return $permission->module_id === null;
    }

    public function scope(Builder $query, User $actor): Builder
    {
        return $actor->is_super_admin || $actor->company_id === null
            ? $query
            : $query->where('company_id', $actor->company_id);
    }

    public function companyId(User $actor, mixed $requested): ?int
    {
        if ($actor->company_id !== null) {
            return $actor->company_id;
        }

        return $requested === null || $requested === '' ? null : (int) $requested;
    }

    public function delegablePermissions(User $actor, ?int $companyId): Collection
    {
        return Permission::query()->with('module')->orderBy('id')->get()
            ->filter(function (Permission $permission) use ($actor, $companyId) {
                $correctScope = $companyId === null ? $permission->module_id === null : $permission->module_id !== null;

                return $correctScope && ($actor->is_super_admin || $this->allows($actor, $permission->code));
            });
    }

    public function canManageGrants(User $actor, User|Role $record): bool
    {
        if ($record instanceof User && ($record->is_super_admin || $record->id === $actor->id)) {
            return false;
        }

        if ($actor->is_super_admin) {
            return true;
        }

        if ($record->company_id !== $actor->company_id) {
            return false;
        }

        $granted = $record->permissions->pluck('id');
        if ($record instanceof User) {
            $granted = $granted->merge($record->roles->flatMap(fn (Role $role) => $role->permissions->pluck('id')));
        }

        // Impide tomar control de una cuenta más privilegiada cambiando su contraseña.
        return $granted->diff($this->delegablePermissions($actor, $record->company_id)->pluck('id'))->isEmpty();
    }
}
