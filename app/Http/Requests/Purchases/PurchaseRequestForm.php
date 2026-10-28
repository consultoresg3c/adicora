<?php

namespace App\Http\Requests\Purchases;

use App\Models\ProcessField;
use App\Models\PurchaseStage;
use App\Services\Purchases\PurchaseAccessService;
use App\Services\Purchases\PurchaseFieldService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestForm extends FormRequest
{
    private function stage(): ?PurchaseStage
    {
        return $this->route('purchase')?->currentStage ?? app(PurchaseAccessService::class)->initialStage($this->user(), $this->filled('start_stage_id') ? $this->integer('start_stage_id') : null);
    }

    public function authorize(): bool
    {
        $access = app(PurchaseAccessService::class);
        $record = $this->route('purchase');
        if ($record && $record->company_id !== $this->user()?->company_id) {
            abort(404);
        }

        return $record ? $access->canWork($this->user(), $record) : $access->initialStages($this->user())->isNotEmpty();
    }

    public function rules(): array
    {
        $stage = $this->stage();
        $allowed = $this->route('purchase') ? collect() : app(PurchaseAccessService::class)->initialStages($this->user());
        $fields = app(PurchaseFieldService::class);
        if ($stage) {
            $this->merge($fields->effectiveInput($stage, $this->route('purchase'), $this->all()));
        }
        $rules = [
            'start_stage_id' => $this->route('purchase') ? ['prohibited'] : [$allowed->count() > 1 ? 'required' : 'nullable', 'integer', Rule::in($allowed->modelKeys())],
            'intent' => ['required', Rule::in(['save', 'advance'])],
            'version' => [$this->route('purchase') ? 'required' : 'nullable', 'integer', 'min:1'],
            'configuration_version' => ['nullable', 'integer', 'min:1'],
            'company_id' => ['prohibited'], 'created_by_id' => ['prohibited'], 'status_id' => ['prohibited'],
            'purchase_stage_id' => ['prohibited'], 'completed_at' => ['prohibited'], 'currency' => ['prohibited'],
        ];
        $rules += $stage ? $fields->rules($stage, $this->input('intent') === 'advance') : array_fill_keys([...array_keys(ProcessField::BUILTINS), 'custom_fields'], ['prohibited']);

        return $rules;
    }

    public function attributes(): array
    {
        return ($this->stage() ? app(PurchaseFieldService::class)->attributes($this->stage()) : []) + ['supplier_id' => 'proveedor', 'amount' => 'monto', 'purchase_date' => 'fecha de compra', 'approval_date' => 'fecha de aprobación', 'receipt_date' => 'fecha de recepción', 'payment_date' => 'fecha de pago', 'version' => 'versión de la solicitud', 'start_stage_id' => 'estación de inicio'];
    }

    public function messages(): array
    {
        return ['prohibited' => 'No puedes modificar :attribute en esta etapa.', 'required' => 'Completa :attribute para enviar la solicitud.', 'exists' => 'Selecciona un proveedor activo de tu empresa.', 'after_or_equal' => 'La fecha no puede ser anterior a la etapa previa.', 'date_format' => 'Escribe una fecha válida.', 'decimal' => 'El número de decimales de :attribute supera el configurado.'];
    }
}
