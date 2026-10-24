<?php

namespace App\Http\Requests\Management;

use Illuminate\Validation\Rule;

class StatusRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'system.statuses.manage';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('statuses')->where('status_type_id', $this->input('status_type_id'))->ignore($this->route('status'))],
            'status_type_id' => ['required', 'integer', Rule::exists('status_types', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
