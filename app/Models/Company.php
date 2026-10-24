<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Empresa: límite de aislamiento de usuarios, sucursales, roles y módulos contratados. */
class Company extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'tax_id', 'email', 'phone', 'address', 'status_id'];

    /** Estado base: 1 activo, 0 inactivo y -1 eliminado, sin sustituir status_id. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Historial de esta empresa, relacionado mediante company_record_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'company_record_id');
    }

    /** Últimas cinco acciones para los formularios y listados. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Estado de operación de la empresa. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** Sucursales de esta empresa, mediante company_id. */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** Usuarios adscritos a esta empresa. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Roles definidos exclusivamente para esta empresa. */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /** La presencia en el pivote habilita el módulo; su ausencia lo deshabilita. */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class);
    }
}
