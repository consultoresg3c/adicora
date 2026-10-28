<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Valor escalar validado por tipo y relacionado con campo y solicitud mediante FKs. */
class PurchaseFieldValue extends Model
{
    use SoftDeletes;

    protected $fillable = ['purchase_request_id', 'process_field_id', 'value'];

    protected function casts(): array
    {
        return ['purchase_request_id' => 'integer', 'process_field_id' => 'integer'];
    }

    /** Solicitud propietaria del valor. */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    /** Definición conservada para el historial de valores. */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ProcessField::class, 'process_field_id')->withTrashed();
    }
}
