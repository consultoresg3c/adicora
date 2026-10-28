<?php

namespace App\Services\Purchases;

use App\Models\ProcessDefinition;
use App\Models\ProcessStep;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Guarda y avanza etapas en una transacción; nunca acepta etapa, empresa ni estatus del cliente. */
class PurchaseWorkflowService
{
    public function __construct(private PurchaseAccessService $access, private RecordHistoryService $history, private PurchaseFieldService $fields) {}

    public function save(?PurchaseRequest $record, array $data, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($record, $data, $actor) {
            $creating = $record === null;
            $selectedId = ! empty($data['start_stage_id']) ? (int) $data['start_stage_id'] : null;
            $configuration = $creating ? $this->access->initialStage($actor, $selectedId) : $record->currentStage;
            abort_unless($configuration, 403);
            $definition = ProcessDefinition::lockForUpdate()->findOrFail($configuration->process_definition_id);
            if (isset($data['configuration_version']) && (int) $data['configuration_version'] !== $definition->version) {
                throw ValidationException::withMessages(['configuration_version' => 'La configuración de campos cambió. Recarga el formulario antes de guardar.']);
            }
            if ($creating) {
                $stage = $this->access->initialStage($actor, $selectedId);
                abort_unless($stage, 403);
                $draft = $stage->initialStatus;
                if (! $draft?->is_active || $draft->type?->code !== 'purchase_requests') {
                    throw ValidationException::withMessages(['workflow' => 'Configura un estatus Borrador activo para las compras.']);
                }
                $record = new PurchaseRequest(['company_id' => $actor->company_id, 'created_by_id' => $actor->id, 'purchase_stage_id' => $stage->id, 'status_id' => $draft->id, 'currency' => 'USD', 'version' => 1]);
            } else {
                $record = PurchaseRequest::lockForUpdate()->findOrFail($record->id);
                abort_unless($record->company_id === $actor->company_id, 404);
                if ($record->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['version' => 'La solicitud cambió. Recarga la página antes de continuar.']);
                }
                abort_unless($this->access->canWork($actor, $record), 403);
                $stage = PurchaseStage::lockForUpdate()->findOrFail($record->purchase_stage_id);
            }
            $stage->load('fieldRules.field');
            $values = $this->fields->validateValues($stage, $creating ? null : $record, $data);
            $profile = $this->fields->profile($stage);
            $before = $this->history->snapshot($record);
            $customChanges = [];
            foreach ($values as $fieldId => $value) {
                $field = $profile[$fieldId]['field'];
                if ($field->attribute !== null) {
                    if ($field->attribute === 'supplier_id' && $value !== null) {
                        $supplier = Supplier::where('company_id', $actor->company_id)->whereHas('status', fn ($query) => $query->where('is_active', true))->lockForUpdate()->find($value);
                        if (! $supplier) {
                            throw ValidationException::withMessages(['supplier_id' => 'Selecciona un proveedor activo de tu empresa.']);
                        }
                    }
                    $record->setAttribute($field->attribute, $value);
                } elseif ($this->fields->value($field, $creating ? null : $record) !== $value) {
                    $customChanges[$fieldId] = $value;
                }
            }
            if ($data['intent'] === 'advance') {
                $this->advance($record, $stage);
            }
            if (! $creating && ($record->isDirty() || $customChanges !== [])) {
                $record->version++;
            }
            $record->save();
            foreach ($customChanges as $fieldId => $value) {
                $custom = $record->customValues()->firstOrNew(['process_field_id' => $fieldId]);
                $custom->value = $value;
                $custom->save();
            }
            $this->history->record($record, $actor, $creating ? 'created' : 'updated', $before);

            return $record;
        });
    }

    private function advance(PurchaseRequest $record, PurchaseStage $stage): void
    {
        $result = $stage->completionStatus;
        if (! $result?->is_active || $result->type?->code !== 'purchase_requests') {
            throw ValidationException::withMessages(['workflow' => 'El estatus de resultado de la etapa no está disponible.']);
        }
        $record->status_id = $result->id;
        $next = PurchaseStage::with('stage.status', 'role')->where('process_definition_id', $stage->process_definition_id)->where('position', $stage->position + 1)->first();
        $edges = ProcessStep::where('process_definition_id', $stage->process_definition_id)->where('origin_stage_id', $stage->process_stage_id)->whereHas('status', fn ($query) => $query->where('is_active', true))->lockForUpdate()->get();
        if ($stage->is_end) {
            if ($edges->isNotEmpty()) {
                throw ValidationException::withMessages(['workflow' => 'Una etapa final no puede tener una transición de salida. Revisa la configuración.']);
            }
            $record->completed_at = now();

            return;
        }
        if (! $next) {
            throw ValidationException::withMessages(['workflow' => 'Esta etapa no tiene destino y no está marcada para finalizar el proceso.']);
        }
        if ($next->stage?->lifecycle_status !== 1 || $next->role?->company_id !== $record->company_id || $edges->count() !== 1 || $edges->first()->destination_stage_id !== $next->process_stage_id || $edges->first()->responsible_role_id !== $stage->responsible_role_id) {
            throw ValidationException::withMessages(['workflow' => 'La siguiente etapa o su transición no están disponibles. Revisa la configuración de Procesos.']);
        }
        $record->purchase_stage_id = $next->id;
    }
}
