<?php

namespace App\Services\Management;

use App\Models\Supplier;
use App\Models\User;
use App\Services\Access\AccessService;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(private AccessService $access, private RecordHistoryService $history) {}

    public function save(Supplier $supplier, array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $actor) {
            if ($supplier->exists) {
                $supplier = $this->access->scope(Supplier::query(), $actor)->lockForUpdate()->findOrFail($supplier->id);
            }
            $data['company_id'] = $this->access->companyId($actor, $data['company_id'] ?? null);
            if ($supplier->exists && $supplier->company_id !== $data['company_id']) {
                throw ValidationException::withMessages(['company_id' => 'No se puede trasladar un proveedor entre empresas.']);
            }
            $action = $supplier->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($supplier);
            $supplier->fill($data)->save();
            $this->history->record($supplier, $actor, $action, $before);

            return $supplier;
        });
    }

    public function delete(Supplier $supplier, User $actor): void
    {
        DB::transaction(function () use ($supplier, $actor): void {
            $record = $this->access->scope(Supplier::query(), $actor)->lockForUpdate()->findOrFail($supplier->id);
            $before = $this->history->snapshot($record);
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
