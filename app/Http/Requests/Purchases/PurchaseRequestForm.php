<?php

namespace App\Http\Requests\Purchases;

use App\Models\PurchaseStage;
use App\Models\Status;
use App\Services\Purchases\PurchaseAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestForm extends FormRequest
{
    private function stage(): PurchaseStage
    {
        return $this->route('purchase')?->currentStage ?? app(PurchaseAccessService::class)->initialStage($this->user());
    }

    public function authorize(): bool
    {
        $access = app(PurchaseAccessService::class);
        $record = $this->route('purchase');
        if ($record && $record->company_id !== $this->user()?->company_id) {
            abort(404);
        }

        return $record ? $access->canWork($this->user(), $record) : $access->initialStage($this->user()) !== null;
    }

    public function rules(): array
    {
        $stage = $this->stage();
        $complete = $this->input('intent') === 'advance';
        $rules = [
            'intent' => ['required', Rule::in(['save', 'advance'])],
            'version' => [$this->route('purchase') ? 'required' : 'nullable', 'integer', 'min:1'],
            'company_id' => ['prohibited'], 'created_by_id' => ['prohibited'], 'status_id' => ['prohibited'],
            'purchase_stage_id' => ['prohibited'], 'completed_at' => ['prohibited'], 'currency' => ['prohibited'],
        ];
        foreach (PurchaseStage::FIELDS as $field) {
            $rules[$field] = $field === $stage->field_name
                ? [$complete ? 'required' : 'nullable', 'date_format:Y-m-d'] : ['prohibited'];
        }
        $isBuyer = $stage->field_name === 'purchase_date';
        $rules['supplier_id'] = $isBuyer ? [$complete ? 'required' : 'nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $this->user()->company_id)->whereNull('deleted_at')->whereIn('status_id', Status::where('is_active', true)->select('id'))] : ['prohibited'];
        $rules['amount'] = $isBuyer ? [$complete ? 'required' : 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999999999.99'] : ['prohibited'];
        if ($stage->field_name && $this->route('purchase')) {
            $previousFields = PurchaseStage::where('process_definition_id', $stage->process_definition_id)->where('position', '<', $stage->position)->whereNotNull('field_name')->orderByDesc('position')->pluck('field_name');
            foreach ($previousFields as $previous) {
                if ($date = $this->route('purchase')->{$previous}) {
                    $rules[$stage->field_name][] = 'after_or_equal:'.$date->format('Y-m-d');
                    break;
                }
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['supplier_id' => 'proveedor', 'amount' => 'monto', 'purchase_date' => 'fecha de compra', 'approval_date' => 'fecha de aprobación', 'receipt_date' => 'fecha de recepción', 'payment_date' => 'fecha de pago', 'version' => 'versión de la solicitud'];
    }

    public function messages(): array
    {
        return ['prohibited' => 'No puedes modificar :attribute en esta etapa.', 'required' => 'Completa :attribute para enviar la solicitud.', 'exists' => 'Selecciona un proveedor activo de tu empresa.', 'after_or_equal' => 'La fecha no puede ser anterior a la etapa previa.', 'date_format' => 'Escribe una fecha válida.', 'decimal' => 'El monto admite hasta dos decimales.'];
    }
}
