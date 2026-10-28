<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Regla por ID de campo y etapa; se audita como asignación de la etapa. */
class ProcessStageField extends Model
{
    use SoftDeletes;

    protected $fillable = ['purchase_stage_id', 'process_field_id', 'mode', 'is_required'];

    protected $attributes = ['mode' => 'hidden', 'is_required' => false];

    protected function casts(): array
    {
        return ['purchase_stage_id' => 'integer', 'process_field_id' => 'integer', 'is_required' => 'boolean'];
    }

    /** Etapa a la que pertenece la regla. */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PurchaseStage::class, 'purchase_stage_id');
    }

    /** Campo de la misma definición de proceso. */
    public function field(): BelongsTo
    {
        return $this->belongsTo(ProcessField::class, 'process_field_id');
    }
}
