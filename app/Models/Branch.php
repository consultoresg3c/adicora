<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Sucursal de una única empresa. El acceso se limita por company_id. */
class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'address', 'status_id'];

    /** Estado base derivado del catálogo y del borrado lógico. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Acciones que afectan exclusivamente a esta sucursal mediante branch_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'branch_id');
    }

    /** Últimas cinco acciones de esta sucursal. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Empresa propietaria de la sucursal. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Estado dinámico del contexto de sucursales. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
