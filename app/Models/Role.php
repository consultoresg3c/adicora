<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Rol global con company_id nulo o rol propio de una empresa; no hay acceso implícito por nombre. */
class Role extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'name'];

    /** Los roles existentes están disponibles; -1 representa el borrado lógico. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : 1;
    }

    /** Historial de cambios y permisos de este rol mediante role_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'role_id');
    }

    /** Últimas cinco acciones sobre este rol. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Empresa propietaria; nulo para roles de sistema. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Permisos concedidos por este rol. */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /** Usuarios que reciben este rol, con pertenencia validada por el servicio. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
