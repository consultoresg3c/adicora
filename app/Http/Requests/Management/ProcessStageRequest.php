<?php

namespace App\Http\Requests\Management;

use Illuminate\Validation\Rule;

class ProcessStageRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'system.processes.manage';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180', Rule::unique('process_stages')->ignore($this->route('processStage'))],
            'status_id' => $this->statusRule('process_stages'),
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['origin_stage_id' => 'etapa origen', 'destination_stage_id' => 'etapa destino', 'responsible_role_id' => 'rol responsable'];
    }
}
