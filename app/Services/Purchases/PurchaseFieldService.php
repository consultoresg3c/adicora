<?php

namespace App\Services\Purchases;

use App\Models\ProcessField;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Status;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Aplica la matriz en validación, persistencia, presentación e historial, siempre en servidor. */
class PurchaseFieldService
{
    public function profile(PurchaseStage $stage): Collection
    {
        $stage->loadMissing('fieldRules.field');

        return $stage->fieldRules->filter(fn ($rule) => $rule->field !== null && $rule->field->process_definition_id === $stage->process_definition_id && $rule->field->company_id === $stage->company_id && ($rule->field->attribute === null || array_key_exists($rule->field->attribute, ProcessField::BUILTINS)))
            ->sortBy(fn ($rule) => $rule->field->position)->mapWithKeys(function ($rule) use ($stage) {
                $mode = $stage->review_only && $rule->mode !== 'hidden' ? 'read' : $rule->mode;

                return [$rule->process_field_id => ['field' => $rule->field, 'mode' => $mode, 'required' => $mode === 'edit' && $rule->is_required]];
            });
    }

    public function key(ProcessField $field): string
    {
        return $field->attribute ?? 'custom_fields.'.$field->id;
    }

    public function inputName(ProcessField $field): string
    {
        return $field->attribute ?? 'custom_fields['.$field->id.']';
    }

    public function value(ProcessField $field, ?PurchaseRequest $record): ?string
    {
        if (! $record) {
            return null;
        }
        if ($field->attribute !== null) {
            if (! array_key_exists($field->attribute, ProcessField::BUILTINS)) {
                throw ValidationException::withMessages(['workflow' => 'La definición de un campo base no es válida.']);
            }
            $value = $record->{$field->attribute};
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            return $value === null ? null : (string) $value;
        }
        $record->loadMissing('customValues');

        return $record->customValues->firstWhere('process_field_id', $field->id)?->value;
    }

    public function effectiveInput(PurchaseStage $stage, ?PurchaseRequest $record, array $data): array
    {
        foreach ($this->profile($stage) as $item) {
            $key = $this->key($item['field']);
            if ($item['mode'] === 'edit' && ! Arr::has($data, $key)) {
                $value = $this->value($item['field'], $record);
                Arr::set($data, $key, $item['field']->data_type === 'datetime' && $value !== null ? str_replace(' ', 'T', $value) : $value);
            }
        }

        return $data;
    }

    public function rules(PurchaseStage $stage, bool $advance): array
    {
        $profile = $this->profile($stage);
        $customIds = $profile->filter(fn ($item) => $item['field']->attribute === null)->keys()->map(fn ($id) => (string) $id)->all();
        $rules = ['custom_fields' => ['nullable', 'array:'.implode(',', $customIds)]];
        foreach ($profile as $item) {
            $field = $item['field'];
            $key = $this->key($field);
            if ($item['mode'] !== 'edit') {
                $rules[$key] = ['prohibited'];

                continue;
            }
            $typeRules = match ($field->data_type) {
                'text' => ['string', 'max:'.$field->text_length],
                'integer' => ['integer'],
                'numeric' => ['numeric', 'decimal:0,'.$field->decimal_places, 'regex:/^-?\d+(?:\.\d+)?$/'],
                'date' => ['date_format:Y-m-d'],
                'time' => ['date_format:H:i,H:i:s'],
                'datetime' => ['date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
                'supplier' => ['integer', Rule::exists('suppliers', 'id')->where('company_id', $stage->company_id)->whereNull('deleted_at')->whereIn('status_id', Status::where('is_active', true)->select('id'))],
                default => ['prohibited'],
            };
            if ($field->attribute === 'amount') {
                $typeRules = [...$typeRules, 'gt:0', 'max:99999999999999.99'];
            }
            $rules[$key] = [$advance && $item['required'] ? 'required' : 'nullable', ...$typeRules];
        }
        // Los campos base ausentes de la matriz nunca son editables por omisión.
        foreach (array_keys(ProcessField::BUILTINS) as $attribute) {
            $rules[$attribute] ??= ['prohibited'];
        }

        return $rules;
    }

    public function attributes(PurchaseStage $stage): array
    {
        return $this->profile($stage)->mapWithKeys(fn ($item) => [$this->key($item['field']) => $item['field']->name])->all();
    }

    /** Devuelve únicamente valores editables, normalizados por ID de campo. */
    public function validateValues(PurchaseStage $stage, ?PurchaseRequest $record, array $data): array
    {
        $input = $this->effectiveInput($stage, $record, $data);
        $validated = Validator::make($input, $this->rules($stage, ($data['intent'] ?? null) === 'advance'), [
            'required' => 'Completa :attribute antes de enviar a la siguiente etapa.',
            'prohibited' => 'No puedes modificar :attribute en esta etapa.',
            'exists' => 'Selecciona un proveedor activo de tu empresa.',
            'decimal' => 'El número de decimales de :attribute supera el configurado.',
        ], $this->attributes($stage))->validate();
        $values = [];
        $dates = [];
        foreach (['purchase_date', 'approval_date', 'receipt_date', 'payment_date'] as $attribute) {
            $dates[$attribute] = $record?->{$attribute}?->format('Y-m-d');
        }
        $editableDates = [];
        foreach ($this->profile($stage) as $id => $item) {
            if ($item['mode'] !== 'edit') {
                continue;
            }
            $field = $item['field'];
            $value = $this->normalize($field, Arr::get($validated, $this->key($field)));
            $values[$id] = $value;
            if ($field->attribute !== null && array_key_exists($field->attribute, $dates)) {
                $dates[$field->attribute] = $value;
                $editableDates[$field->attribute] = true;
            }
        }
        $previous = null;
        $previousKey = null;
        foreach ($dates as $attribute => $date) {
            if ($date === null) {
                continue;
            }
            if ($previous !== null && $date < $previous) {
                $key = isset($editableDates[$attribute]) ? $attribute : $previousKey;
                throw ValidationException::withMessages([$key => 'La fecha no puede ser anterior a la etapa previa ni posterior a una etapa ya registrada.']);
            }
            $previous = $date;
            $previousKey = $attribute;
        }

        return $values;
    }

    private function normalize(ProcessField $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if ($field->data_type === 'numeric') {
            $negative = str_starts_with($value, '-');
            [$integer, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
            $integer = ltrim($integer, '0') ?: '0';
            $fraction = str_pad($fraction, $field->decimal_places, '0');
            $sign = $negative && trim($integer.$fraction, '0') !== '' ? '-' : '';

            return $sign.$integer.($field->decimal_places > 0 ? '.'.$fraction : '');
        }
        if ($field->data_type === 'integer' || $field->data_type === 'supplier') {
            return (string) (int) $value;
        }
        if ($field->data_type === 'time') {
            return strlen($value) === 5 ? $value.':00' : $value;
        }
        if ($field->data_type === 'datetime') {
            $value = str_replace('T', ' ', $value);

            return strlen($value) === 16 ? $value.':00' : $value;
        }

        return $value;
    }

    public function viewFields(PurchaseStage $stage, PurchaseRequest $record, bool $editable): Collection
    {
        return $this->profile($stage)->filter(fn ($item) => $item['mode'] !== 'hidden')->map(function ($item) use ($record, $editable) {
            $field = $item['field'];
            $value = $this->value($field, $record);
            $display = $value ?? 'Sin valor';
            if ($field->data_type === 'supplier') {
                $display = $record->supplier?->name ?? 'Sin proveedor';
            } elseif ($value !== null && in_array($field->data_type, ['date', 'datetime'], true)) {
                $date = new \DateTimeImmutable($value);
                $display = $date->format($field->data_type === 'date' ? 'd/m/Y' : 'd/m/Y H:i:s');
            }

            return $item + ['value' => $value, 'display' => $display, 'input_name' => $this->inputName($field), 'error_key' => $this->key($field), 'editable' => $editable && $item['mode'] === 'edit'];
        });
    }

    public function visibleAttribute(PurchaseStage $stage, string $attribute): bool
    {
        return $this->profile($stage)->contains(fn ($item) => $item['field']->attribute === $attribute && $item['mode'] !== 'hidden');
    }

    /** Evita que un campo oculto reaparezca a través de diferencias históricas. */
    public function redactHistory(Collection $entries, PurchaseStage $stage): Collection
    {
        $visible = $this->profile($stage)->filter(fn ($item) => $item['mode'] !== 'hidden');
        $attributes = $visible->pluck('field.attribute')->filter()->all();
        $customIds = $visible->filter(fn ($item) => $item['field']->attribute === null)->keys()->all();
        $allowed = [...$attributes, 'company_id', 'created_by_id', 'purchase_stage_id', 'status_id', 'currency', 'completed_at', 'lifecycle_status'];

        return $entries->map(function ($entry) use ($allowed, $customIds) {
            $copy = clone $entry;
            $changes = Arr::only($entry->changes, $allowed);
            $custom = $entry->changes['field_values'] ?? [];
            foreach ($customIds as $id) {
                $before = $custom['before'][$id] ?? null;
                $after = $custom['after'][$id] ?? null;
                if ($before !== $after) {
                    $changes['custom_value_'.$id] = ['before' => $before, 'after' => $after];
                }
            }
            $copy->changes = $changes;

            return $copy;
        });
    }

    public function historyLabels(PurchaseStage $stage): array
    {
        return $this->profile($stage)->filter(fn ($item) => $item['mode'] !== 'hidden' && $item['field']->attribute === null)
            ->mapWithKeys(fn ($item) => ['custom_value_'.$item['field']->id => $item['field']->name])->all();
    }
}
