<?php

namespace App\Services\Management;

use App\Models\ProcessStage;
use App\Models\ProcessStep;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessStepService
{
    public function __construct(private RecordHistoryService $history) {}

    public function save(ProcessStep $record, array $data, User $actor): ProcessStep
    {
        return DB::transaction(function () use ($record, $data, $actor) {
            if ($record->exists) {
                $record = ProcessStep::query()->lockForUpdate()->findOrFail($record->id);
            }
            $stages = ProcessStage::whereIn('id', [$data['origin_stage_id'], $data['destination_stage_id']])->orderBy('id')->lockForUpdate()->get();
            foreach (['origin_stage_id', 'destination_stage_id'] as $field) {
                if (! $stages->contains('id', (int) $data[$field])) {
                    throw ValidationException::withMessages([$field => 'Selecciona una etapa disponible.']);
                }
            }
            $role = Role::where(fn ($query) => $query->whereNull('company_id')->orWhereHas('company'))->lockForUpdate()->find($data['responsible_role_id']);
            if (! $role) {
                throw ValidationException::withMessages(['responsible_role_id' => 'Selecciona un rol disponible.']);
            }
            $action = $record->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($record);
            $record->fill($data)->save();
            $this->history->record($record, $actor, $action, $before);

            return $record;
        });
    }

    public function delete(ProcessStep $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $record = ProcessStep::query()->lockForUpdate()->findOrFail($record->id);
            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
