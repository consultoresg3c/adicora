<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Configuración global de procesos, relacionada exclusivamente por ID. */
class ProcessStage extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'status_id'];

    protected function casts(): array
    {
        return ['status_id' => 'integer'];
    }

    /** Estado base derivado del catálogo y del borrado lógico. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Acciones que afectan exclusivamente a este registro mediante process_stage_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'process_stage_id');
    }

    /** Últimas cinco acciones de este registro. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Estado dinámico del contexto de etapas de procesos. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** Configuraciones de compras con rol y resultado por empresa. */
    public function purchaseConfigurations(): HasMany
    {
        return $this->hasMany(PurchaseStage::class);
    }
}
