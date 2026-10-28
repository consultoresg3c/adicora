<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Models\Branch;
use App\Models\Company;
use App\Models\MenuItem;
use App\Models\ProcessDefinition;
use App\Models\ProcessField;
use App\Models\ProcessStage;
use App\Models\ProcessStep;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Role;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Conserva únicamente campos administrativos permitidos; nunca serializa el modelo completo. */
class RecordHistoryService
{
    public function snapshot(Model $record): array
    {
        if (! $record->exists) {
            return [];
        }

        $fields = match (true) {
            $record instanceof ProcessField => ['company_id', 'process_definition_id', 'name', 'data_type', 'text_length', 'decimal_places', 'position'],
            $record instanceof ProcessDefinition => ['company_id', 'name', 'status_id'],
            $record instanceof Supplier => ['company_id', 'name', 'status_id'],
            $record instanceof PurchaseStage => ['process_definition_id', 'company_id', 'process_stage_id', 'responsible_role_id', 'completion_status_id', 'initial_status_id', 'field_name', 'position', 'is_start', 'is_end', 'review_only'],
            $record instanceof PurchaseRequest => ['company_id', 'created_by_id', 'supplier_id', 'purchase_stage_id', 'status_id', 'amount', 'currency', 'purchase_date', 'approval_date', 'receipt_date', 'payment_date', 'completed_at'],
            $record instanceof ProcessStage => ['name', 'status_id'],
            $record instanceof ProcessStep => ['process_definition_id', 'origin_stage_id', 'destination_stage_id', 'responsible_role_id', 'status_id'],
            $record instanceof Company => ['name', 'tax_id', 'email', 'phone', 'address', 'status_id'],
            $record instanceof Branch => ['company_id', 'name', 'address', 'status_id'],
            $record instanceof User => ['company_id', 'name', 'email', 'status_id'],
            $record instanceof Role => ['company_id', 'name'],
            $record instanceof Status => ['status_type_id', 'name', 'is_active'],
            $record instanceof MenuItem => ['parent_id', 'module_id', 'permission_id', 'status_id', 'name', 'is_parent', 'icon', 'route_name', 'sort_order'],
        };
        $snapshot = $record->only($fields);
        foreach ($snapshot as $field => $value) {
            if (str_ends_with($field, '_id') && $value !== null) {
                $snapshot[$field] = (int) $value;
            }
        }

        // Una edición puede cambiar status_id después de haber cargado la relación anterior.
        $record->unsetRelation('status');
        foreach (['purchase_date', 'approval_date', 'receipt_date', 'payment_date', 'completed_at'] as $field) {
            if (array_key_exists($field, $snapshot) && $snapshot[$field] instanceof \DateTimeInterface) {
                $snapshot[$field] = $snapshot[$field]->format($field === 'completed_at' ? 'Y-m-d H:i:s' : 'Y-m-d');
            }
        }
        $snapshot['lifecycle_status'] = $record->lifecycle_status;
        if ($record instanceof PurchaseStage) {
            $snapshot['field_rules'] = $record->fieldRules()->whereHas('field')->orderBy('process_field_id')->get(['process_field_id', 'mode', 'is_required'])->map(fn ($rule) => $rule->only(['process_field_id', 'mode', 'is_required']))->all();
        }
        if ($record instanceof PurchaseRequest) {
            $snapshot['field_values'] = $record->customValues()->orderBy('process_field_id')->pluck('value', 'process_field_id')->all();
        }
        if ($record instanceof ProcessDefinition) {
            $snapshot['field_ids'] = $record->fields()->pluck('id')->all();
            $snapshot['stage_ids'] = $record->stages()->pluck('id')->all();
        }
        if ($record instanceof Company) {
            $snapshot['module_ids'] = $record->modules()->orderBy('modules.id')->pluck('modules.id')->all();
        }
        if ($record instanceof User) {
            $snapshot['role_ids'] = $record->roles()->orderBy('roles.id')->pluck('roles.id')->all();
        }
        if ($record instanceof User || $record instanceof Role) {
            $snapshot['permission_ids'] = $record->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();
        }

        return $snapshot;
    }

    public function record(Model $record, User $actor, string $action, array $before = [], bool $credentialsUpdated = false): ?AuditEntry
    {
        $after = $this->snapshot($record);
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $previous = $before[$field] ?? null;
            $current = $after[$field] ?? null;
            if ($previous !== $current) {
                $changes[$field] = ['before' => $previous, 'after' => $current];
            }
        }
        if ($credentialsUpdated) {
            // Se registra el hecho, sin contraseña, hash ni longitud del secreto.
            $changes['password_changed'] = ['before' => null, 'after' => true];
        }
        if ($changes === []) {
            return null;
        }

        $subjectKey = match (true) {
            $record instanceof ProcessField => 'process_field_id',
            $record instanceof ProcessDefinition => 'process_definition_id',
            $record instanceof Supplier => 'supplier_id',
            $record instanceof PurchaseStage => 'purchase_stage_id',
            $record instanceof PurchaseRequest => 'purchase_request_id',
            $record instanceof ProcessStage => 'process_stage_id',
            $record instanceof ProcessStep => 'process_step_id',
            $record instanceof Company => 'company_record_id',
            $record instanceof Branch => 'branch_id',
            $record instanceof User => 'user_id',
            $record instanceof Role => 'role_id',
            $record instanceof Status => 'status_id',
            $record instanceof MenuItem => 'menu_item_id',
        };
        $companyId = $record instanceof Company ? $record->id : $record->getAttribute('company_id');

        return AuditEntry::create([
            'actor_id' => $actor->id,
            'company_id' => $companyId,
            $subjectKey => $record->id,
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
