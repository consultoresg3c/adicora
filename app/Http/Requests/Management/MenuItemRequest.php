<?php

namespace App\Http\Requests\Management;

use App\Models\MenuItem;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MenuItemRequest extends ManagementRequest
{
    protected function ability(): string
    {
        return 'system.menus.manage';
    }

    public function authorize(): bool
    {
        if ($this->user()?->company_id !== null || ! ($this->user()?->can($this->ability()) ?? false)) {
            return false;
        }

        abort_unless(Schema::hasTable('menu_items'), 503, 'Ejecuta la migración pendiente de menús para habilitar esta función.');

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_parent' => $this->input('is_parent', false)]);
    }

    public function rules(NavigationRegistry $registry): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'is_parent' => ['required', 'boolean'],
            'icon' => ['required', 'string', Rule::in(array_keys($registry->icons()))],
            'status_id' => $this->statusRule('menus'),
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'parent_id' => [Rule::excludeIf($this->boolean('is_parent')), 'required', 'integer', Rule::exists('menu_items', 'id')->whereNull('deleted_at')->where('is_parent', true)->whereNull('parent_id')],
            'route_name' => [Rule::excludeIf($this->boolean('is_parent')), 'required', 'string', Rule::in(array_keys($registry->actions()))],
            'module_id' => ['prohibited'],
            'permission_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $record = $this->route('menuItem');
            if (! $record instanceof MenuItem) {
                return;
            }

            if (! $this->boolean('is_parent') && $this->integer('parent_id') === $record->id) {
                $validator->errors()->add('parent_id', 'Un menú no puede pertenecer a sí mismo.');
            }

            if ($record->is_parent && ! $this->boolean('is_parent') && $record->children()->exists()) {
                $validator->errors()->add('is_parent', 'Mueve o elimina los hijos antes de convertir este menú en una acción.');
            }
        }];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['is_parent' => 'menú padre', 'icon' => 'icono', 'sort_order' => 'orden', 'parent_id' => 'menú padre', 'route_name' => 'acción'];
    }

    public function messages(): array
    {
        return array_replace(parent::messages(), [
            'parent_id.required' => 'Selecciona el menú padre de esta acción.',
            'parent_id.exists' => 'Selecciona un menú padre de primer nivel.',
            'route_name.in' => 'Selecciona una acción registrada del sistema.',
            'icon.in' => 'Selecciona uno de los iconos disponibles.',
            'sort_order.integer' => 'Escribe un número entero para el orden.',
            'sort_order.min' => 'El orden no puede ser negativo.',
            'sort_order.max' => 'El orden no puede superar 9999.',
            'prohibited' => 'El campo :attribute se determina automáticamente a partir de la acción.',
        ]);
    }
}
