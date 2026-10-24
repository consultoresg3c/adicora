<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Entrada global de navegación. Los padres agrupan; las hojas apuntan a acciones registradas. */
class MenuItem extends Model
{
    use SoftDeletes;

    protected $fillable = ['parent_id', 'module_id', 'permission_id', 'status_id', 'name', 'is_parent', 'icon', 'route_name', 'sort_order'];

    protected $attributes = ['is_parent' => false, 'sort_order' => 0];

    /** Estado base del menú: 1 activo, 0 inactivo y -1 eliminado. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Acciones de este menú mediante menu_item_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'menu_item_id');
    }

    /** Últimas cinco acciones sobre esta entrada de navegación. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    protected function casts(): array
    {
        return ['is_parent' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Agrupador raíz al que pertenece esta hoja, identificado mediante parent_id. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Hojas de este agrupador, ordenadas de forma estable. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /** Módulo requerido por la acción, relacionado exclusivamente mediante module_id. */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** Permiso requerido por la acción; ocultar el menú nunca sustituye autorizar la ruta. */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /** Estado dinámico de navegación, asociado mediante status_id. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
