<?php

namespace App\Http\Requests\Management;

use App\Services\Access\AccessService;
use Illuminate\Validation\Rule;

class SupplierRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'purchases.suppliers.manage';
    }

    public function rules(): array
    {
        $companyId = app(AccessService::class)->companyId($this->user(), $this->input('company_id'));

        return [
            'company_id' => $this->companyRules(),
            'name' => ['required', 'string', 'max:180', Rule::unique('suppliers')->where('company_id', $companyId)->ignore($this->route('supplier'))],
            'status_id' => $this->statusRule('suppliers'),
        ];
    }
}
