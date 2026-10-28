<?php

namespace App\Services\Purchases;

use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Acceso por empresa y etapa para pendientes, y por actor exacto para tracking. */
class PurchaseAccessService
{
    public function pending(User $actor): Builder
    {
        $roles = $actor->roles()->where('roles.company_id', $actor->company_id)->select('roles.id');

        return PurchaseRequest::where('company_id', $actor->company_id)->whereNotNull('company_id')
            ->whereNull('completed_at')->whereHas('status', fn ($query) => $query->where('is_active', true))
            ->whereHas('currentStage', fn ($query) => $query->whereNull('deleted_at')->where('company_id', $actor->company_id)->whereHas('process.status', fn ($status) => $status->where('is_active', true))
                ->whereIn('responsible_role_id', $roles)->whereHas('role', fn ($role) => $role->where('company_id', $actor->company_id))
                ->whereHas('stage.status', fn ($status) => $status->where('is_active', true)));
    }

    public function tracking(User $actor): Builder
    {
        return PurchaseRequest::where('company_id', $actor->company_id)->whereNotNull('company_id')
            ->whereHas('activities', fn ($query) => $query->where('actor_id', $actor->id));
    }

    public function canWork(User $actor, PurchaseRequest $record): bool
    {
        return $actor->can('purchases.requests.work') && $this->pending($actor)->whereKey($record->id)->exists();
    }

    public function assertRead(User $actor, PurchaseRequest $record): void
    {
        abort_unless($actor->can('purchases.requests.work'), 403);
        abort_unless($record->company_id === $actor->company_id && ($this->pending($actor)->whereKey($record->id)->exists() || $this->tracking($actor)->withTrashed()->whereKey($record->id)->exists()), 404);
    }

    /** Entradas activas que el usuario puede iniciar; no se deducen de la posición. */
    public function initialStages(User $actor): Collection
    {
        if (! $actor->company_id || ! $actor->can('purchases.requests.work')) {
            return new Collection;
        }
        $roles = $actor->roles()->where('roles.company_id', $actor->company_id)->select('roles.id');

        return PurchaseStage::with('stage.status', 'role', 'initialStatus', 'process')
            ->where('company_id', $actor->company_id)->where('is_start', true)
            ->whereIn('responsible_role_id', $roles)
            ->whereHas('role', fn ($query) => $query->where('company_id', $actor->company_id))
            ->whereHas('process.status', fn ($status) => $status->where('is_active', true))
            ->whereHas('stage.status', fn ($status) => $status->where('is_active', true))
            ->whereHas('initialStatus', fn ($status) => $status->where('is_active', true)->whereHas('type', fn ($type) => $type->where('code', 'purchase_requests')))
            ->orderBy('position')->get();
    }

    /** Selección explícita cuando hay varias entradas; nunca elige una ajena al usuario. */
    public function initialStage(User $actor, ?int $selectedId = null): ?PurchaseStage
    {
        $stages = $this->initialStages($actor);

        return $selectedId !== null ? $stages->firstWhere('id', $selectedId) : ($stages->count() === 1 ? $stages->first() : null);
    }
}
