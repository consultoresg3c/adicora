<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Vincula una etapa del catálogo con el rol de empresa, el resultado y el campo que completa. */
class PurchaseStage extends Model
{
    use SoftDeletes;

    /** Nombres técnicos de campos del formulario; no son estados de negocio. */
    public const FIELDS = ['purchase_date', 'approval_date', 'receipt_date', 'payment_date'];

    protected $fillable = ['process_definition_id', 'company_id', 'process_stage_id', 'responsible_role_id', 'completion_status_id', 'initial_status_id', 'field_name', 'position', 'is_start', 'is_end', 'review_only'];

    protected $attributes = ['is_start' => false, 'is_end' => false, 'review_only' => false];

    protected function casts(): array
    {
        return ['process_definition_id' => 'integer', 'company_id' => 'integer', 'process_stage_id' => 'integer', 'responsible_role_id' => 'integer', 'completion_status_id' => 'integer', 'position' => 'integer', 'initial_status_id' => 'integer', 'is_start' => 'boolean', 'is_end' => 'boolean', 'review_only' => 'boolean'];
    }

    /** Etiqueta del registro en confirmaciones e historial. */
    public function getNameAttribute(): string
    {
        return $this->stage?->name ?? 'Etapa';
    }

    /** Últimas cinco modificaciones de esta etapa. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Disponibilidad derivada de la etapa de catálogo. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : ($this->stage?->lifecycle_status ?? 0);
    }

    /** Empresa propietaria de esta configuración operativa. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Etapa administrada en Sistema → Procesos. */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProcessStage::class, 'process_stage_id');
    }

    /** Rol responsable de la misma empresa. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id');
    }

    /** Estatus inicial de una solicitud, configurado mediante ID. */
    public function initialStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'initial_status_id');
    }

    /** Resultado de negocio al completar esta etapa. */
    public function completionStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'completion_status_id');
    }

    /** Historial de configuración identificado por FK. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class);
    }

    /** Proceso propietario de esta etapa o transición. */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id');
    }

    /** Matriz de acceso a los campos de esta etapa. */
    public function fieldRules(): HasMany
    {
        return $this->hasMany(ProcessStageField::class);
    }
}
