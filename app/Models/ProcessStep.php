<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Configuración global de procesos, relacionada exclusivamente por ID. */
class ProcessStep extends Model
{
    use SoftDeletes;

    protected $fillable = ['process_definition_id', 'origin_stage_id', 'destination_stage_id', 'status_id', 'responsible_role_id'];

    protected function casts(): array
    {
        return ['process_definition_id' => 'integer', 'origin_stage_id' => 'integer', 'destination_stage_id' => 'integer', 'responsible_role_id' => 'integer', 'status_id' => 'integer'];
    }

    /** Estado base derivado del catálogo y del borrado lógico. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Acciones que afectan exclusivamente a este registro mediante process_step_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'process_step_id');
    }

    /** Últimas cinco acciones de este registro. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Estado dinámico del contexto de procesos. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** Etapa inicial conservada aunque se elimine del catálogo. */
    public function originStage(): BelongsTo
    {
        return $this->belongsTo(ProcessStage::class, 'origin_stage_id')->withTrashed();
    }

    /** Etapa de llegada conservada por ID. */
    public function destinationStage(): BelongsTo
    {
        return $this->belongsTo(ProcessStage::class, 'destination_stage_id')->withTrashed();
    }

    /** Rol existente responsable de ejecutar el paso; conserva su referencia histórica. */
    public function responsibleRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id')->withTrashed();
    }

    /** Nombre descriptivo para confirmaciones e historial. */
    public function getNameAttribute(): string
    {
        return ($this->originStage?->name ?? 'Etapa origen').' → '.($this->destinationStage?->name ?? 'Etapa destino');
    }

    /** Proceso propietario de esta etapa o transición. */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id');
    }
}
