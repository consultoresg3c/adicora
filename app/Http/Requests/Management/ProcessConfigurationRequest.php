<?php

namespace App\Http\Requests\Management;

use App\Models\PurchaseStage;
use App\Models\StatusType;
use Illuminate\Validation\Rule;

class ProcessConfigurationRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'system.processes.manage';
    }

    public function authorize(): bool
    {
        $row = $this->route('purchaseStage');
        if ($row && $row->process_definition_id !== $this->route('processDefinition')->id) {
            abort(404);
        }

        return parent::authorize();
    }

    public function rules(): array
    {
        $companyId = $this->route('processDefinition')->company_id;
        $statuses = ['integer', Rule::exists('statuses', 'id')->whereNull('deleted_at')->where('status_type_id', StatusType::where('code', 'purchase_requests')->value('id'))];

        return [
            'version' => ['required', 'integer', 'min:1'],
            'process_stage_id' => ['required_without:new_stage_name', 'nullable', 'integer', Rule::exists('process_stages', 'id')->whereNull('deleted_at')],
            'new_stage_name' => ['nullable', 'string', 'max:180', Rule::unique('process_stages', 'name')],
            'responsible_role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')->where('company_id', $companyId)],
            'completion_status_id' => ['required', ...$statuses],
            'initial_status_id' => ['nullable', ...$statuses],
            'position' => ['required', 'integer', 'min:1', 'max:500'],
            'field_name' => ['nullable', Rule::in(PurchaseStage::FIELDS)],
            'company_id' => ['prohibited'], 'process_definition_id' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['process_stage_id' => 'etapa origen', 'new_stage_name' => 'nombre de nueva etapa', 'responsible_role_id' => 'rol responsable', 'completion_status_id' => 'estatus de resultado', 'position' => 'posición', 'field_name' => 'datos de la etapa'];
    }
}
