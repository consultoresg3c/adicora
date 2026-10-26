<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Acción de administración inmutable; actor, empresa y registro se relacionan exclusivamente por ID. */
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'company_id', 'company_record_id', 'branch_id', 'user_id', 'role_id', 'status_id', 'menu_item_id', 'process_stage_id', 'process_step_id', 'process_definition_id', 'supplier_id', 'purchase_stage_id', 'purchase_request_id', 'action', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    /** Autor conservado en el historial aunque su cuenta tenga borrado lógico. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    /** Ámbito empresarial del evento, identificado mediante company_id. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id')->withTrashed();
    }

    /** Empresa modificada; diferente del company_id utilizado para delimitar el ámbito. */
    public function companyRecord(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_record_id')->withTrashed();
    }

    /** Sucursal afectada por esta acción. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    /** Usuario afectado, que puede ser diferente del actor. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** Rol afectado por esta acción. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id')->withTrashed();
    }

    /** Estado de catálogo afectado por esta acción. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id')->withTrashed();
    }

    /** Entrada de navegación afectada por esta acción. */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id')->withTrashed();
    }

    /** Etapa del catálogo afectada por esta acción. */
    public function processStage(): BelongsTo
    {
        return $this->belongsTo(ProcessStage::class)->withTrashed();
    }

    /** Paso de proceso afectado por esta acción. */
    public function processStep(): BelongsTo
    {
        return $this->belongsTo(ProcessStep::class)->withTrashed();
    }

    /** Proveedor afectado por esta acción. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** Configuración operativa de etapa afectada. */
    public function purchaseStage(): BelongsTo
    {
        return $this->belongsTo(PurchaseStage::class)->withTrashed();
    }

    /** Solicitud de compra afectada. */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class)->withTrashed();
    }

    /** Proceso nombrado afectado por esta acción. */
    public function processDefinition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class)->withTrashed();
    }
}
