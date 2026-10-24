<?php

namespace App\Http\Requests\Management;

use Illuminate\Validation\Rule;

class CompanyRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'system.companies.manage';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'tax_id' => ['required', 'string', 'max:40', Rule::unique('companies')->ignore($this->route('company'))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'status_id' => $this->statusRule('companies'),
            'module_ids' => ['sometimes', 'array'],
            'module_ids.*' => ['integer', 'distinct', Rule::exists('modules', 'id')],
        ];
    }
}
