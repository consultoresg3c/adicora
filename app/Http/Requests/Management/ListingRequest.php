<?php

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Parámetros comunes de los listados; la autorización y el ámbito siguen en cada ruta/consulta. */
class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
            'status_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->whereNull('deleted_at')],
            'company_id' => [Rule::excludeIf($this->user()?->company_id !== null), 'nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'status_type_id' => ['nullable', 'integer', Rule::exists('status_types', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
            'module_id' => ['nullable', 'integer', Rule::exists('modules', 'id')],
            'kind' => ['nullable', Rule::in(['parent', 'child'])],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
        ];
    }

    public function searchTerm(): string
    {
        return trim((string) $this->input('q', ''));
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : 15;
    }

    public function attributes(): array
    {
        return ['q' => 'búsqueda', 'per_page' => 'filas por página', 'page' => 'página', 'company_id' => 'empresa', 'status_id' => 'estado', 'status_type_id' => 'módulo', 'parent_id' => 'menú padre', 'module_id' => 'módulo', 'kind' => 'tipo de menú', 'is_active' => 'disponibilidad'];
    }
}
