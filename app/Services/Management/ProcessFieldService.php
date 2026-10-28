<?php

namespace App\Services\Management;

use App\Models\ProcessDefinition;
use App\Models\ProcessField;
use App\Models\PurchaseStage;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Define campos y su matriz por etapa; un campo nuevo queda oculto en las demás etapas. */
class ProcessFieldService
{
    public function __construct(private RecordHistoryService $history) {}

    public function ensureBuiltins(ProcessDefinition $process, User $actor): void
    {
        foreach (ProcessField::BUILTINS as $attribute => [$name, $type, $decimals]) {
            if ($process->fields()->where('attribute', $attribute)->exists()) {
                continue;
            }
            $field = $process->fields()->create(['company_id' => $process->company_id, 'name' => $name, 'attribute' => $attribute, 'data_type' => $type, 'decimal_places' => $decimals, 'position' => $process->fields()->count() + 1]);
            $this->history->record($field, $actor, 'created');
        }
    }

    /** Compatibilidad de la preparación inicial: convierte su configuración original una única vez. */
    public function seedStage(PurchaseStage $stage, ?string $ownedField): void
    {
        if ($stage->fieldRules()->exists()) {
            return;
        }
        $owned = $ownedField === 'purchase_date' ? ['supplier_id', 'amount', 'purchase_date'] : array_filter([$ownedField]);
        $stage->review_only = $ownedField === null;
        $stage->save();
        foreach ($stage->process->fields()->get() as $field) {
            $editable = in_array($field->attribute, $owned, true);
            $stage->fieldRules()->create(['process_field_id' => $field->id, 'mode' => $editable ? 'edit' : 'read', 'is_required' => $editable]);
        }
    }

    public function saveRules(PurchaseStage $stage, array $data, User $actor): void
    {
        $fields = $stage->process->fields()->get();
        $submitted = $data['field_rules'] ?? [];
        if (collect(array_keys($submitted))->diff($fields->modelKeys())->isNotEmpty()) {
            throw ValidationException::withMessages(['field_rules' => 'La matriz contiene campos que no pertenecen a este proceso.']);
        }
        foreach ($fields as $field) {
            $rule = $stage->fieldRules()->firstOrNew(['process_field_id' => $field->id]);
            $values = $submitted[$field->id] ?? ['mode' => $rule->mode, 'is_required' => $rule->is_required];
            if (! in_array($values['mode'], ['read', 'edit', 'hidden'], true)) {
                throw ValidationException::withMessages(['field_rules.'.$field->id.'.mode' => 'Selecciona lectura, editable u oculto.']);
            }
            $mode = $stage->review_only && $values['mode'] !== 'hidden' ? 'read' : $values['mode'];
            $required = (bool) ($values['is_required'] ?? false);
            if (! $stage->review_only && $required && $mode !== 'edit') {
                throw ValidationException::withMessages(['field_rules.'.$field->id.'.is_required' => 'Solo un campo editable puede ser obligatorio.']);
            }
            $rule->fill(['mode' => $mode, 'is_required' => $mode === 'edit' && $required])->save();
        }
        if (! empty($data['new_field']['name'])) {
            $this->createField($stage, $data['new_field'], $actor);
        }
        $stage->unsetRelation('fieldRules');
    }

    private function createField(PurchaseStage $stage, array $data, User $actor): void
    {
        Validator::make(['new_field' => $data], [
            'new_field.name' => ['required', 'string', 'max:180', Rule::unique('process_fields', 'name')->where('process_definition_id', $stage->process_definition_id)->whereNull('deleted_at')],
            'new_field.data_type' => ['required', Rule::in(array_keys(ProcessField::TYPES))],
            'new_field.text_length' => [Rule::excludeIf(($data['data_type'] ?? null) !== 'text'), 'required', 'integer', 'min:1', 'max:10000'],
            'new_field.decimal_places' => [Rule::excludeIf(($data['data_type'] ?? null) !== 'numeric'), 'required', 'integer', 'min:0', 'max:8'],
            'new_field.is_required' => ['sometimes', 'boolean'],
        ])->validate();
        $process = $stage->process;
        if ($process->fields()->count() >= 100) {
            throw ValidationException::withMessages(['new_field.name' => 'El proceso admite hasta 100 campos.']);
        }
        $otherStages = $process->stages()->where('id', '!=', $stage->id)->get();
        $field = $process->fields()->create(['company_id' => $process->company_id, 'name' => $data['name'], 'data_type' => $data['data_type'], 'text_length' => $data['data_type'] === 'text' ? $data['text_length'] : null, 'decimal_places' => $data['data_type'] === 'numeric' ? $data['decimal_places'] : null, 'position' => $process->fields()->max('position') + 1]);
        $this->history->record($field, $actor, 'created');
        $stage->fieldRules()->create(['process_field_id' => $field->id, 'mode' => $stage->review_only ? 'read' : 'edit', 'is_required' => ! $stage->review_only && (bool) ($data['is_required'] ?? false)]);
        foreach ($otherStages as $other) {
            $other->fieldRules()->create(['process_field_id' => $field->id, 'mode' => 'hidden', 'is_required' => false]);
        }
    }

    public function delete(ProcessDefinition $process, ProcessField $field, int $version, User $actor): void
    {
        abort_unless($actor->can('system.processes.manage'), 403);
        DB::transaction(function () use ($process, $field, $version, $actor): void {
            $process = ProcessDefinition::lockForUpdate()->findOrFail($process->id);
            abort_unless($field->process_definition_id === $process->id, 404);
            if ($process->version !== $version) {
                throw ValidationException::withMessages(['version' => 'El proceso cambió. Recarga la página.']);
            }
            if ($field->attribute !== null) {
                throw ValidationException::withMessages(['field' => 'Los campos base se conservan. Puedes ocultarlos en la matriz de cada etapa.']);
            }
            $processBefore = $this->history->snapshot($process);
            $before = $this->history->snapshot($field);
            $field->delete();
            $this->history->record($field, $actor, 'deleted', $before);
            $process->version++;
            $process->save();
            $this->history->record($process, $actor, 'updated', $processBefore);
        });
    }
}
