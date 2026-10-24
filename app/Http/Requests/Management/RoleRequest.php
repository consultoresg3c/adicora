<?php

namespace App\Http\Requests\Management;

use App\Services\Access\AccessService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoleRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'access.roles.manage';
    }

    public function rules(): array
    {
        $companyId = app(AccessService::class)->companyId($this->user(), $this->input('company_id'));

        return [
            'company_id' => $this->companyRules(false),
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->where('company_id', $companyId)->ignore($this->route('role'))],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isEmpty()) {
                $this->validateGrants($validator);
            }
        }];
    }
}
