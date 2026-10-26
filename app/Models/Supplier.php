<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Proveedor de una empresa, seleccionado por ID en las solicitudes. */
class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'status_id'];

    /** Estado base derivado del catálogo y del borrado lógico. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Acciones que afectan exclusivamente a este proveedor mediante supplier_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'supplier_id');
    }

    /** Últimas cinco acciones de este proveedor. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Empresa propietaria de el proveedor. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Estado dinámico del contexto de proveedores. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
