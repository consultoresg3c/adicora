<?php

namespace App\Http\Requests\Management;

use App\Models\Role;
use App\Models\StatusType;
use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ManagementRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        foreach ($this->route()->parameters() as $record) {
            if ($record instanceof Model && $this->user()?->company_id !== null) {
                abort_if($record->company_id !== $this->user()->company_id, 404);
            }
        }

        return $this->user()?->can($this->ability()) ?? false;
    }

    protected function statusRule(string $type): array
    {
        return ['required', 'integer', Rule::exists('statuses', 'id')->whereNull('deleted_at')->where('status_type_id', StatusType::query()->where('code', $type)->value('id'))];
    }

    protected function companyRules(bool $required = true): array
    {
        if ($this->user()->company_id !== null) {
            return ['sometimes', 'integer', Rule::in([$this->user()->company_id])];
        }

        return [$required ? 'required' : 'nullable', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')];
    }

    protected function validateGrants(Validator $validator): void
    {
        $access = app(AccessService::class);
        $companyId = $access->companyId($this->user(), $this->input('company_id'));
        $allowed = $access->delegablePermissions($this->user(), $companyId)->pluck('id');
        $permissionIds = (array) $this->input('permission_ids', []);
        if (collect($permissionIds)->diff($allowed)->isNotEmpty()) {
            $validator->errors()->add('permission_ids', 'Solo puedes conceder permisos de este ámbito que tú poseas.');
        }

        $roleIds = (array) $this->input('role_ids', []);
        $roles = Role::query()->with('permissions')->whereIn('id', $roleIds)->get();
        if ($roles->count() !== count(array_unique($roleIds))) {
            $validator->errors()->add('role_ids', 'Selecciona roles disponibles. Un rol eliminado no puede asignarse.');
        }
        foreach ($roles as $role) {
            if ($role->company_id !== $companyId || $role->permissions->pluck('id')->diff($allowed)->isNotEmpty()) {
                $validator->errors()->add('role_ids', 'El rol debe pertenecer a la empresa y solo conceder permisos que puedas delegar.');
            }
        }
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña', 'company_id' => 'empresa', 'status_id' => 'estado', 'status_type_id' => 'tipo de estado', 'tax_id' => 'identificación fiscal', 'role_ids' => 'roles', 'permission_ids' => 'permisos', 'module_ids' => 'módulos'];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'email' => 'Escribe un correo válido.', 'unique' => 'Este valor de :attribute ya está registrado.', 'exists' => 'Selecciona un valor válido para :attribute.', 'min' => 'El campo :attribute debe tener al menos :min caracteres.', 'max' => 'El campo :attribute supera el máximo permitido.', 'confirmed' => 'La confirmación de :attribute no coincide.', 'in' => 'No puedes asignar esa empresa.'];
    }
}
