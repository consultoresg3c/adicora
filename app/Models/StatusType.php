<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de contextos de estado; evita aplicar un estado de usuario a una empresa. */
class StatusType extends Model
{
    protected $fillable = ['code', 'name'];

    /** Estados que pertenecen a este contexto mediante status_type_id. */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }
}
