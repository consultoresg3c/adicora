<?php

namespace App\Services\Management;

use App\Models\ProcessDefinition;
use App\Models\ProcessStage;
use App\Models\ProcessStep;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Edita la secuencia completa y sus conexiones sin dejar dos configuraciones divergentes. */
class ProcessConfigurationService
{
    public function __construct(private RecordHistoryService $history) {}

    public function save(ProcessDefinition $process, array $data, User $actor): ProcessDefinition
    {
        abort_unless($actor->can('system.processes.manage'), 403);

        return DB::transaction(function () use ($process, $data, $actor) {
            if ($process->exists) {
                $process = $this->lock($process, (int) $data['version']);
            }
            $before = $this->history->snapshot($process);
            $creating = ! $process->exists;
            $process->fill($creating ? $data : ['name' => $data['name']]);
            if ($creating) {
                $process->status_id = Status::whereHas('type', fn ($query) => $query->where('code', 'processes'))->where('is_active', true)->firstOrFail()->id;
                $process->version = 1;
            } elseif ($process->isDirty()) {
                $process->version++;
            }
            $process->save();
            if ($creating) {
                app(ProcessFieldService::class)->ensureBuiltins($process, $actor);
            }
            $this->history->record($process, $actor, $creating ? 'created' : 'updated', $before);

            return $process;
        });
    }

    public function saveStage(ProcessDefinition $process, ?PurchaseStage $record, array $data, User $actor): PurchaseStage
    {
        abort_unless($actor->can('system.processes.manage'), 403);

        return DB::transaction(function () use ($process, $record, $data, $actor): PurchaseStage {
            $process = $this->lock($process, (int) $data['version']);
            $processBefore = $this->history->snapshot($process);
            $rows = $process->stages()->lockForUpdate()->get();
            if ($record) {
                $record = $rows->firstWhere('id', $record->id);
                abort_unless($record, 404);
            }
            if (! $record) {
                $this->assertEditable($process);
            } else {
                $structural = ['process_stage_id', 'responsible_role_id', 'completion_status_id', 'initial_status_id', 'position', 'is_start', 'is_end'];
                $changed = ! empty($data['new_stage_name']);
                foreach ($structural as $field) {
                    if (array_key_exists($field, $data) && (in_array($field, ['is_start', 'is_end'], true) ? (bool) $record->{$field} !== (bool) $data[$field] : (string) $record->{$field} !== (string) $data[$field])) {
                        $changed = true;
                    }
                }
                if ($changed) {
                    $this->assertEditable($process);
                }
            }
            $record ??= new PurchaseStage(['company_id' => $process->company_id, 'process_definition_id' => $process->id]);
            $snapshots = $rows->mapWithKeys(fn ($row) => [$row->id => $this->history->snapshot($row)])->all();
            $role = Role::where('company_id', $process->company_id)->lockForUpdate()->find($data['responsible_role_id']);
            if (! $role) {
                throw ValidationException::withMessages(['responsible_role_id' => 'Selecciona un rol vigente de la empresa del proceso.']);
            }
            $stage = ! empty($data['new_stage_name'])
                ? app(ProcessStageService::class)->save(new ProcessStage, ['name' => $data['new_stage_name'], 'status_id' => Status::whereHas('type', fn ($query) => $query->where('code', 'process_stages'))->where('is_active', true)->firstOrFail()->id], $actor)
                : ProcessStage::lockForUpdate()->findOrFail($data['process_stage_id']);
            $record->fill(['process_stage_id' => $stage->id, 'responsible_role_id' => $role->id, 'completion_status_id' => $data['completion_status_id'], 'review_only' => (bool) ($data['review_only'] ?? $record->review_only), 'is_start' => (bool) ($data['is_start'] ?? $record->is_start), 'is_end' => (bool) ($data['is_end'] ?? $record->is_end)]);
            $record->initial_status_id = $record->is_start ? ($data['initial_status_id'] ?? $record->initial_status_id) : null;
            $record->unsetRelation('stage');
            $rows = $rows->reject(fn ($row) => $record->exists && $row->id === $record->id)->values();
            $position = (int) $data['position'];
            if ($position < 1 || $position > $rows->count() + 1 || $rows->count() >= 500) {
                throw ValidationException::withMessages(['position' => 'Selecciona una posición válida dentro de la secuencia.']);
            }
            $rows->splice($position - 1, 0, [$record]);
            $this->persistSequence($process, $rows, $snapshots, $actor, $processBefore, $record, $data);

            return $record;
        });
    }

    public function deleteStage(ProcessDefinition $process, PurchaseStage $record, int $version, User $actor): void
    {
        abort_unless($actor->can('system.processes.manage'), 403);
        DB::transaction(function () use ($process, $record, $version, $actor): void {
            $process = $this->lock($process, $version);
            $this->assertEditable($process);
            $processBefore = $this->history->snapshot($process);
            $rows = $process->stages()->lockForUpdate()->get();
            $record = $rows->firstWhere('id', $record->id);
            abort_unless($record, 404);
            $snapshots = $rows->mapWithKeys(fn ($row) => [$row->id => $this->history->snapshot($row)])->all();
            $rows = $rows->reject(fn ($row) => $row->id === $record->id)->values();
            $this->validateSequence($rows);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $snapshots[$record->id]);
            $this->persistSequence($process, $rows, $snapshots, $actor, $processBefore);
        });
    }

    public function delete(ProcessDefinition $process, int $version, User $actor): void
    {
        abort_unless($actor->can('system.processes.manage'), 403);
        DB::transaction(function () use ($process, $version, $actor): void {
            $process = $this->lock($process, $version);
            $this->assertEditable($process);
            if ($process->stages()->exists()) {
                throw ValidationException::withMessages(['process' => 'Quita las etapas del proceso antes de eliminarlo.']);
            }
            $before = $this->history->snapshot($process);
            $process->delete();
            $this->history->record($process, $actor, 'deleted', $before);
        });
    }

    private function lock(ProcessDefinition $process, int $version): ProcessDefinition
    {
        $process = ProcessDefinition::lockForUpdate()->findOrFail($process->id);
        if ($process->version !== $version) {
            throw ValidationException::withMessages(['version' => 'El proceso cambió. Recarga la página antes de continuar.']);
        }

        return $process;
    }

    private function assertEditable(ProcessDefinition $process): void
    {
        if (PurchaseRequest::whereIn('purchase_stage_id', PurchaseStage::withTrashed()->where('process_definition_id', $process->id)->select('id'))->whereNull('completed_at')->exists()) {
            throw ValidationException::withMessages(['process' => 'Hay compras en curso. Finalízalas antes de cambiar las etapas del proceso.']);
        }
    }

    private function validateSequence(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }
        if ($rows->contains(fn ($row) => $row->is_start && ! $row->initial_status_id)) {
            throw ValidationException::withMessages(['initial_status_id' => 'Selecciona el estatus inicial de cada etapa que inicia el proceso.']);
        }
        if ($rows->pluck('process_stage_id')->unique()->count() !== $rows->count()) {
            throw ValidationException::withMessages(['process_stage_id' => 'Una etapa no puede aparecer dos veces en el mismo proceso.']);
        }

    }

    private function persistSequence(ProcessDefinition $process, Collection $rows, array $snapshots, User $actor, array $processBefore, ?PurchaseStage $configured = null, array $data = []): void
    {
        $this->validateSequence($rows);
        // Posiciones temporales dentro de la misma transacción para respetar el índice único.
        $process->stages()->update(['position' => DB::raw('position + 10000')]);
        foreach ($rows as $index => $row) {
            if ($row->exists) {
                $row->position = $row->getOriginal('position') + 10000;
                $row->syncOriginalAttribute('position');
            }
            $row->position = $index + 1;
            $row->save();
            if ($configured === $row) {
                app(ProcessFieldService::class)->saveRules($row, $data, $actor);
            }
        }
        foreach ($rows as $row) {
            $before = $snapshots[$row->id] ?? [];
            $this->history->record($row, $actor, $before === [] ? 'created' : 'updated', $before);
        }
        $this->syncSteps($process, $rows, $actor);
        $process->version++;
        $process->save();
        // La asignación ordenada se registra en la definición y en cada fila modificada.
        $this->history->record($process, $actor, 'updated', $processBefore);
    }

    private function syncSteps(ProcessDefinition $process, Collection $rows, User $actor): void
    {
        $edges = $process->steps()->lockForUpdate()->get();
        $kept = [];
        $statusId = Status::whereHas('type', fn ($query) => $query->where('code', 'processes'))->where('is_active', true)->firstOrFail()->id;
        foreach ($rows->slice(0, max(0, $rows->count() - 1)) as $index => $row) {
            if ($row->is_end) {
                continue;
            }
            $next = $rows[$index + 1];
            $edge = $edges->firstWhere('origin_stage_id', $row->process_stage_id) ?? new ProcessStep;
            $creating = ! $edge->exists;
            $before = $this->history->snapshot($edge);
            $edge->fill(['process_definition_id' => $process->id, 'origin_stage_id' => $row->process_stage_id, 'destination_stage_id' => $next->process_stage_id, 'responsible_role_id' => $row->responsible_role_id, 'status_id' => $edge->status_id ?? $statusId])->save();
            $this->history->record($edge, $actor, $creating ? 'created' : 'updated', $before);
            $kept[] = $edge->id;
        }
        foreach ($edges->whereNotIn('id', $kept) as $edge) {
            $before = $this->history->snapshot($edge);
            $edge->delete();
            $this->history->record($edge, $actor, 'deleted', $before);
        }
    }
}
