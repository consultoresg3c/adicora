<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Proceso identificado por nombre y empresa, con secuencia de etapas propia. */
class ProcessDefinition extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'status_id', 'version'];

    /** Estado base derivado del catálogo y del borrado lógico. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Acciones que afectan exclusivamente a este proceso mediante process_definition_id. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class, 'process_definition_id');
    }

    /** Últimas cinco acciones de este proceso. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Empresa propietaria del proceso. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Estado dinámico del contexto de procesos. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** Etapas vigentes, ordenadas en la secuencia del proceso. */
    public function stages(): HasMany
    {
        return $this->hasMany(PurchaseStage::class)->orderBy('position');
    }

    /** Transiciones que conectan las etapas de este proceso. */
    public function steps(): HasMany
    {
        return $this->hasMany(ProcessStep::class);
    }

    protected function casts(): array
    {
        return ['company_id' => 'integer', 'status_id' => 'integer', 'version' => 'integer'];
    }

    /** Campos vigentes del proceso en el orden de presentación. */
    public function fields(): HasMany
    {
        return $this->hasMany(ProcessField::class)->orderBy('position')->orderBy('id');
    }
}
