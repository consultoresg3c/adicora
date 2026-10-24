<?php

namespace App\Services\Management;

use App\Models\Branch;
use App\Models\User;
use App\Services\Access\AccessService;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function __construct(private AccessService $access, private RecordHistoryService $history) {}

    public function save(Branch $branch, array $data, User $actor): Branch
    {
        return DB::transaction(function () use ($branch, $data, $actor) {
            if ($branch->exists) {
                $branch = $this->access->scope(Branch::query(), $actor)->lockForUpdate()->findOrFail($branch->id);
            }
            $data['company_id'] = $this->access->companyId($actor, $data['company_id'] ?? null);
            if ($branch->exists && $branch->company_id !== $data['company_id']) {
                throw ValidationException::withMessages(['company_id' => 'No se puede trasladar una sucursal entre empresas.']);
            }
            $action = $branch->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($branch);
            $branch->fill($data)->save();
            $this->history->record($branch, $actor, $action, $before);

            return $branch;
        });
    }

    public function delete(Branch $branch, User $actor): void
    {
        DB::transaction(function () use ($branch, $actor): void {
            $record = $this->access->scope(Branch::query(), $actor)->lockForUpdate()->findOrFail($branch->id);
            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
