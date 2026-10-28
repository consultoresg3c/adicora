<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Solicitud de compra de una empresa; su versión evita sobrescribir trabajo concurrente. */
class PurchaseRequest extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'created_by_id', 'supplier_id', 'purchase_stage_id', 'status_id', 'amount', 'currency', 'purchase_date', 'approval_date', 'receipt_date', 'payment_date', 'version', 'completed_at'];

    protected function casts(): array
    {
        return ['company_id' => 'integer', 'created_by_id' => 'integer', 'supplier_id' => 'integer', 'purchase_stage_id' => 'integer', 'status_id' => 'integer', 'amount' => 'decimal:2', 'version' => 'integer', 'purchase_date' => 'date:Y-m-d', 'approval_date' => 'date:Y-m-d', 'receipt_date' => 'date:Y-m-d', 'payment_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];
    }

    /** Identificador visible independiente de nombres de terceros. */
    public function getNameAttribute(): string
    {
        return 'Solicitud #'.$this->id;
    }

    /** Estado administrativo derivado, separado de la finalización del proceso. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : (int) (bool) $this->status?->is_active;
    }

    /** Empresa que delimita todo acceso a la solicitud. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Usuario que inició la solicitud. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }

    /** Proveedor conservado aunque se retire del catálogo. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** Etapa actual por ID, conservada para el historial. */
    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(PurchaseStage::class, 'purchase_stage_id')->withTrashed();
    }

    /** Estatus de negocio del catálogo de compras. */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** Acciones personales que determinan el tracking, sin compartir por rol. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class);
    }

    /** Últimas cinco acciones autorizadas de la solicitud. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Valores de los campos personalizados, almacenados mediante FK de campo. */
    public function customValues(): HasMany
    {
        return $this->hasMany(PurchaseFieldValue::class);
    }
}
