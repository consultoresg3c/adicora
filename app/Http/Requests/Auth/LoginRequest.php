<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string'], 'remember' => ['sometimes', 'boolean']];
    }

    public function messages(): array
    {
        return ['email.required' => 'Escribe tu correo electrónico.', 'email.email' => 'Escribe un correo válido.', 'password.required' => 'Escribe tu contraseña.'];
    }
}
