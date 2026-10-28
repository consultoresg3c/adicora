<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Campo de un proceso; attribute identifica exclusivamente columnas base registradas por el servidor. */
class ProcessField extends Model
{
    use SoftDeletes;

    public const TYPES = ['text' => 'Texto', 'integer' => 'Entero', 'numeric' => 'Numérico', 'date' => 'Fecha', 'time' => 'Hora', 'datetime' => 'Fecha y hora'];

    public const BUILTINS = ['supplier_id' => ['Proveedor', 'supplier', null], 'amount' => ['Monto', 'numeric', 2], 'purchase_date' => ['Fecha de compra', 'date', null], 'receipt_date' => ['Fecha de recepción', 'date', null], 'approval_date' => ['Fecha de aprobación', 'date', null], 'payment_date' => ['Fecha de pago', 'date', null]];

    protected $fillable = ['company_id', 'process_definition_id', 'name', 'attribute', 'data_type', 'text_length', 'decimal_places', 'position'];

    protected function casts(): array
    {
        return ['company_id' => 'integer', 'process_definition_id' => 'integer', 'text_length' => 'integer', 'decimal_places' => 'integer', 'position' => 'integer'];
    }

    /** Disponibilidad derivada del borrado lógico. */
    public function getLifecycleStatusAttribute(): int
    {
        return $this->trashed() ? -1 : 1;
    }

    /** Proceso que define este campo mediante ID. */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id');
    }

    /** Empresa propietaria del campo. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Historial de definición del campo, sin valores de solicitudes. */
    public function activities(): HasMany
    {
        return $this->hasMany(AuditEntry::class);
    }

    /** Últimas modificaciones de definición. */
    public function recentActivities(): HasMany
    {
        return $this->activities()->latest('id')->limit(5);
    }

    /** Valores que conservan su FK aunque el campo se retire. */
    public function values(): HasMany
    {
        return $this->hasMany(PurchaseFieldValue::class);
    }
}
