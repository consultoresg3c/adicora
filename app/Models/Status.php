<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Estado dinámico. is_active determina si permite operar, independientemente de su nombre. */
class Status extends Model
{
    use SoftDeletes;

    protected $fillable = ['status_type_id', 'name', 'is_active'];

    /** Disponibilidad del catálogo: activo 1, inactivo 0 y eliminado -1. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) $this->is_active;
    }

    /** Acciones sobre este estado mediante status_id del historial. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'status_id');
    }

    /** Últimas cinco acciones del catálogo. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Contexto al que pertenece el estado, relacionado exclusivamente por ID. */
    public function type(): BelongsTo
    {
        return $this->belongsTo(StatusType::class, 'status_type_id');
    }

    /** Pasos que utilizan este estado por ID. */
    public function processSteps(): HasMany
    {
        return $this->hasMany(ProcessStep::class);
    }

    /** Etapas que utilizan este estado por ID. */
    public function processStages(): HasMany
    {
        return $this->hasMany(ProcessStage::class);
    }

    /** Procesos que utilizan este estado administrativo. */
    public function processDefinitions(): HasMany
    {
        return $this->hasMany(ProcessDefinition::class);
    }

    /** Proveedores que utilizan este estado. */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /** Solicitudes que utilizan este estatus de negocio. */
    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    /** Etapas configuradas con este resultado. */
    public function purchaseStageResults(): HasMany
    {
        return $this->hasMany(PurchaseStage::class, 'completion_status_id');
    }

    /** Etapas que utilizan este estatus inicial. */
    public function purchaseStageStarts(): HasMany
    {
        return $this->hasMany(PurchaseStage::class, 'initial_status_id');
    }

    /** Empresas que utilizan este estado. */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /** Sucursales que utilizan este estado. */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** Usuarios cuyo acceso depende de este estado. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Entradas de navegación que utilizan este estado por status_id. */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'status_id');
    }
}
