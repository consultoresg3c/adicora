<?php

namespace App\Services\Management;

use App\Models\Status;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StatusService
{
    public function __construct(private RecordHistoryService $history) {}

    public function save(Status $status, array $data, User $actor): Status
    {
        return DB::transaction(function () use ($status, $data, $actor) {
            if ($status->exists) {
                $status = Status::query()->lockForUpdate()->findOrFail($status->id);
            }
            // Cambiar el tipo invalidaría las relaciones que ya utilizan este estado.
            if ($status->exists && $status->status_type_id !== (int) $data['status_type_id']) {
                throw ValidationException::withMessages(['status_type_id' => 'El tipo de un estado existente no puede cambiar.']);
            }
            $action = $status->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($status);
            $status->fill($data)->save();
            $this->history->record($status, $actor, $action, $before);

            return $status;
        });
    }

    public function delete(Status $status, User $actor): void
    {
        DB::transaction(function () use ($status, $actor): void {
            $record = Status::query()->lockForUpdate()->findOrFail($status->id);
            foreach (['companies', 'branches', 'users', 'menuItems', 'processSteps', 'processStages', 'suppliers', 'purchaseRequests', 'purchaseStageResults', 'purchaseStageStarts', 'processDefinitions'] as $relation) {
                if ($record->{$relation}()->withTrashed()->exists()) {
                    throw ValidationException::withMessages(['status' => 'Este estado está asociado a registros. Desactívalo para conservar sus relaciones.']);
                }
            }
            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
