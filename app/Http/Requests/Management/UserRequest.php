<?php

namespace App\Http\Requests\Management;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'access.users.manage';
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'password' => [$this->route('user') ? 'nullable' : 'required', 'string', 'min:12', 'max:128', 'confirmed'],
            'company_id' => $this->companyRules(),
            'status_id' => $this->statusRule('users'),
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->whereNull('deleted_at')],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')],
            'is_super_admin' => ['prohibited'],
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
