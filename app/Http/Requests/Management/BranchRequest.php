<?php

namespace App\Http\Requests\Management;

use App\Services\Access\AccessService;
use Illuminate\Validation\Rule;

class BranchRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'organization.branches.manage';
    }

    public function rules(): array
    {
        $companyId = app(AccessService::class)->companyId($this->user(), $this->input('company_id'));

        return [
            'company_id' => $this->companyRules(),
            'name' => ['required', 'string', 'max:180', Rule::unique('branches')->where('company_id', $companyId)->ignore($this->route('branch'))],
            'address' => ['nullable', 'string', 'max:1000'],
            'status_id' => $this->statusRule('branches'),
        ];
    }
}
