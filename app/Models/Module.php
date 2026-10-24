<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de funcionalidades disponibles, separado de los permisos individuales. */
class Module extends Model
{
    protected $fillable = ['code', 'name'];

    /** Empresas con este módulo habilitado. */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    /** Permisos operativos asociados por module_id. */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
