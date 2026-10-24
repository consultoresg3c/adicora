<?php

namespace App\Services\Management;

use App\Models\Company;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    public function __construct(private RecordHistoryService $history) {}

    public function save(Company $company, array $data, User $actor): Company
    {
        return DB::transaction(function () use ($company, $data, $actor) {
            if ($company->exists) {
                $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            }
            $action = $company->exists ? 'updated' : 'created';
            $before = $this->history->snapshot($company);
            $company->fill(Arr::except($data, 'module_ids'))->save();
            $company->modules()->sync($data['module_ids'] ?? []);
            $this->history->record($company, $actor, $action, $before);

            return $company;
        });
    }

    public function delete(Company $company, User $actor): void
    {
        DB::transaction(function () use ($company, $actor): void {
            $record = Company::query()->lockForUpdate()->findOrFail($company->id);
            $before = $this->history->snapshot($record);
            // Los hijos y sus claves permanecen; el control de acceso impide operar sin empresa disponible.
            $record->delete();
            $this->history->record($record, $actor, 'deleted', $before);
        });
    }
}
