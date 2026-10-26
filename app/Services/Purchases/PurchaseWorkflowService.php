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
    public function __construct(private PurchaseAccessService $access, private RecordHistoryService $history) {}

    public function save(?PurchaseRequest $record, array $data, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($record, $data, $actor) {
            $creating = $record === null;
            $configuration = $creating ? $this->access->initialStage($actor) : $record->currentStage;
            abort_unless($configuration, 403);
            ProcessDefinition::lockForUpdate()->findOrFail($configuration->process_definition_id);
            if ($creating) {
                $stage = $this->access->initialStage($actor);
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
            abort_unless($stage->field_name === null || in_array($stage->field_name, PurchaseStage::FIELDS, true), 403);
            $before = $this->history->snapshot($record);
            $values = $stage->field_name ? [$stage->field_name => $data[$stage->field_name] ?? null] : [];
            if ($stage->field_name === 'purchase_date') {
                if (! empty($data['supplier_id'])) {
                    $supplier = Supplier::where('company_id', $actor->company_id)->whereHas('status', fn ($query) => $query->where('is_active', true))->lockForUpdate()->find($data['supplier_id']);
                    if (! $supplier) {
                        throw ValidationException::withMessages(['supplier_id' => 'Selecciona un proveedor activo de tu empresa.']);
                    }
                }
                $values += ['supplier_id' => $data['supplier_id'] ?? null, 'amount' => $data['amount'] ?? null];
            }
            $record->fill($values);
            if ($data['intent'] === 'advance') {
                $this->advance($record, $stage);
            }
            if (! $creating && $record->isDirty()) {
                $record->version++;
            }
            $record->save();
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
        if (! $next) {
            if ($edges->isNotEmpty() || PurchaseStage::where('process_definition_id', $stage->process_definition_id)->where('position', '>', $stage->position)->exists()) {
                throw ValidationException::withMessages(['workflow' => 'La secuencia del proceso está incompleta. Revisa su configuración.']);
            }
            $record->completed_at = now();

            return;
        }
        if ($next->stage?->lifecycle_status !== 1 || $next->role?->company_id !== $record->company_id || $edges->count() !== 1 || $edges->first()->destination_stage_id !== $next->process_stage_id || $edges->first()->responsible_role_id !== $stage->responsible_role_id) {
            throw ValidationException::withMessages(['workflow' => 'La siguiente etapa o su transición no están disponibles. Revisa la configuración de Procesos.']);
        }
        $record->purchase_stage_id = $next->id;
    }
}
