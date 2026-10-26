<?php

namespace App\Services\Purchases;

use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

    public function initialStage(User $actor): ?PurchaseStage
    {
        if (! $actor->company_id || ! $actor->can('purchases.requests.work')) {
            return null;
        }
        $stage = PurchaseStage::with('stage.status', 'role')->where('company_id', $actor->company_id)->whereHas('process.status', fn ($status) => $status->where('is_active', true))->where('position', 1)->first();
        if (! $stage || $stage->field_name !== 'purchase_date' || $stage->stage?->lifecycle_status !== 1 || $stage->role?->company_id !== $actor->company_id) {
            return null;
        }

        return $actor->roles()->whereKey($stage->responsible_role_id)->where('roles.company_id', $actor->company_id)->exists() ? $stage : null;
    }
}
