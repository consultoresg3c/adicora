<?php

namespace App\Http\Requests\Management;

use Illuminate\Validation\Rule;

class RecordHistoryRequest extends ListingRequest
{
    public function rules(): array
    {
        return parent::rules() + ['action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])]];
    }
}
