<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Permiso del sistema si module_id es nulo; de módulo en caso contrario. */
class Permission extends Model
{
    protected $fillable = ['module_id', 'code', 'name'];

    /** Módulo al que pertenece; nulo representa configuración global. */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** Roles que conceden este permiso mediante IDs en el pivote. */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** Usuarios con concesión directa, adicional a sus roles. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
