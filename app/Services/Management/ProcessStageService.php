<?php

namespace App\Services\Management;

use App\Models\ProcessStage;
use App\Models\ProcessStep;
use App\Models\PurchaseStage;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessStageService
{
    public function __construct(private RecordHistoryService $history) {}

    public function save(ProcessStage $record, array $data, User $actor): ProcessStage
    {
        return DB::transaction(function () use ($record, $data, $actor) {
            if ($record->exists) {
                $record = ProcessStage::query()->lockForUpdate()->findOrFail($record->id);
            }
            $action = $record->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($record);
            $record->fill($data)->save();
            $this->history->record($record, $actor, $action, $before);

            return $record;
        });
    }

    public function delete(ProcessStage $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $record = ProcessStage::query()->lockForUpdate()->findOrFail($record->id);
            if (ProcessStep::withTrashed()->where(fn ($query) => $query->where('origin_stage_id', $record->id)->orWhere('destination_stage_id', $record->id))->exists()) {
                throw ValidationException::withMessages(['stage' => 'Esta etapa está asociada a pasos. Desactívala para conservar sus relaciones.']);
            }
            if (PurchaseStage::withTrashed()->where('process_stage_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['stage' => 'Esta etapa está configurada para compras. Desactívala para conservar su historial.']);
            }
            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
