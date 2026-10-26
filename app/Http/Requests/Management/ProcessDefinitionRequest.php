<?php

namespace App\Http\Requests\Management;

use Illuminate\Validation\Rule;

class ProcessDefinitionRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'system.processes.manage';
    }

    public function rules(): array
    {
        $process = $this->route('processDefinition');

        return [
            'name' => ['required', 'string', 'max:180'],
            'company_id' => $process ? ['prohibited'] : ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at'), Rule::unique('process_definitions')->whereNull('deleted_at')],
            'version' => [$process ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
