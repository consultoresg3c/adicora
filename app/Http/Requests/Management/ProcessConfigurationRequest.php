<?php

namespace App\Http\Requests\Management;

use App\Models\ProcessField;
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
            'is_start' => ['sometimes', 'boolean'],
            'is_end' => ['sometimes', 'boolean'],
            'version' => ['required', 'integer', 'min:1'],
            'process_stage_id' => ['required_without:new_stage_name', 'nullable', 'integer', Rule::exists('process_stages', 'id')->whereNull('deleted_at')],
            'new_stage_name' => ['nullable', 'string', 'max:180', Rule::unique('process_stages', 'name')],
            'responsible_role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')->where('company_id', $companyId)],
            'completion_status_id' => ['required', ...$statuses],
            'initial_status_id' => ['nullable', ...$statuses],
            'position' => ['required', 'integer', 'min:1', 'max:500'],
            'review_only' => ['sometimes', 'boolean'],
            'field_rules' => ['sometimes', 'array'],
            'field_rules.*' => ['array:mode,is_required'],
            'field_rules.*.mode' => ['required', Rule::in(['read', 'edit', 'hidden'])],
            'field_rules.*.is_required' => ['sometimes', 'boolean'],
            'new_field' => ['sometimes', 'array:name,data_type,text_length,decimal_places,is_required'],
            'new_field.name' => ['nullable', 'string', 'max:180'],
            'new_field.data_type' => [Rule::excludeIf(! $this->filled('new_field.name')), 'required', Rule::in(array_keys(ProcessField::TYPES))],
            'new_field.text_length' => [Rule::excludeIf(! $this->filled('new_field.name') || $this->input('new_field.data_type') !== 'text'), 'required', 'integer', 'min:1', 'max:10000'],
            'new_field.decimal_places' => [Rule::excludeIf(! $this->filled('new_field.name') || $this->input('new_field.data_type') !== 'numeric'), 'required', 'integer', 'min:0', 'max:8'],
            'new_field.is_required' => ['sometimes', 'boolean'],
            'company_id' => ['prohibited'], 'process_definition_id' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['process_stage_id' => 'etapa origen', 'new_stage_name' => 'nombre de nueva etapa', 'responsible_role_id' => 'rol responsable', 'completion_status_id' => 'estatus de resultado', 'position' => 'posición', 'review_only' => 'solo revisión y envío', 'new_field.name' => 'nombre del campo', 'new_field.data_type' => 'tipo de dato', 'new_field.text_length' => 'longitud', 'new_field.decimal_places' => 'decimales', 'is_start' => 'inicia el proceso', 'is_end' => 'finaliza el proceso'];
    }
}
