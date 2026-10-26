<?php

namespace App\Services\Purchases;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\ProcessDefinition;
use App\Models\ProcessStage;
use App\Models\ProcessStep;
use App\Models\PurchaseStage;
use App\Models\Role;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\RecordHistoryService;
use App\Services\Management\CompanyService;
use App\Services\Management\ProcessConfigurationService;
use App\Services\Management\ProcessStageService;
use App\Services\Management\ProcessStepService;
use App\Services\Management\RoleService;
use App\Services\Management\SupplierService;
use App\Services\Management\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Preparación explícita de la empresa solicitada; repetible sin restablecer credenciales. */
class PurchaseSetupService
{
    public function prepare(Company $company, User $actor, string $password): array
    {
        abort_unless(app()->environment(['local', 'testing']) && $actor->is_super_admin, 403);

        return DB::transaction(function () use ($company, $actor, $password) {
            $company = Company::lockForUpdate()->findOrFail($company->id);
            if ($company->lifecycle_status !== 1) {
                throw ValidationException::withMessages(['company' => 'La empresa debe estar activa.']);
            }
            $module = Module::where('code', 'purchases')->firstOrFail();
            $ability = Permission::where('code', 'purchases.requests.work')->firstOrFail();
            $modules = $company->modules()->pluck('modules.id')->push($module->id)->unique()->all();
            app(CompanyService::class)->save($company, $company->only(['name', 'tax_id', 'email', 'phone', 'address', 'status_id']) + ['module_ids' => $modules], $actor);
            $supplier = Supplier::withTrashed()->where('company_id', $company->id)->where('name', 'Librería de Nacho')->first();
            if ($supplier?->trashed()) {
                throw ValidationException::withMessages(['supplier' => 'El proveedor inicial fue eliminado. No se restaura automáticamente.']);
            }
            if (! $supplier) {
                app(SupplierService::class)->save(new Supplier, ['company_id' => $company->id, 'name' => 'Librería de Nacho', 'status_id' => $this->status('suppliers', 'Activo')->id], $actor);
            }
            $process = ProcessDefinition::where('company_id', $company->id)->first() ?? app(ProcessConfigurationService::class)->save(new ProcessDefinition, ['name' => 'Compras', 'company_id' => $company->id], $actor);
            $definitions = [
                ['solicitud compra', 'comprador', 'comprador@yraida.test', 'compra solicitada', 'purchase_date'],
                ['Aprobación nivel1', 'aprobador N1', 'aprobador.n1@yraida.test', 'compra aprobada nivel 1', 'approval_date'],
                ['Recepción y verificación', 'receptor y verificador', 'receptor.verificador@yraida.test', 'Compra recibida y verificada', 'receipt_date'],
                ['Pago', 'pagador', 'pagador@yraida.test', 'Compra pagada', 'payment_date'],
            ];
            $stages = collect();
            $users = [];
            foreach ($definitions as $position => [$stageName, $roleName, $email, $result, $field]) {
                $role = Role::withTrashed()->with('permissions')->where('company_id', $company->id)->where('name', $roleName)->first();
                if ($role?->trashed() || ($role && $role->permissions->where('id', '!=', $ability->id)->isNotEmpty())) {
                    throw ValidationException::withMessages(['role' => 'El rol '.$roleName.' está eliminado o tiene otros permisos. Revísalo antes de preparar las cuentas.']);
                }
                $role = app(RoleService::class)->save($role ?? new Role, ['name' => $roleName, 'company_id' => $company->id, 'permission_ids' => [$ability->id]], $actor);
                $user = User::withTrashed()->with('roles', 'permissions')->where('email', $email)->first();
                if ($user && ($user->trashed() || $user->is_super_admin || $user->company_id !== $company->id || $user->roles->where('id', '!=', $role->id)->isNotEmpty() || $user->permissions->isNotEmpty())) {
                    throw ValidationException::withMessages(['user' => 'La cuenta '.$email.' ya existe con otro ámbito o acceso. No se modificó.']);
                }
                if (! $user) {
                    if ($password === '') {
                        throw ValidationException::withMessages(['password' => 'Indica una contraseña temporal para las cuentas nuevas.']);
                    }
                    $user = app(UserService::class)->save(new User, ['name' => $roleName, 'email' => $email, 'password' => $password, 'company_id' => $company->id, 'status_id' => $this->status('users', 'Activo')->id, 'role_ids' => [$role->id], 'permission_ids' => []], $actor);
                } elseif (! $user->roles->contains('id', $role->id)) {
                    throw ValidationException::withMessages(['user' => 'La cuenta '.$email.' no tiene su rol esperado. Revísala antes de continuar.']);
                }
                $users[] = ['name' => $user->name, 'email' => $user->email];
                $stage = ProcessStage::withTrashed()->where('name', $stageName)->first();
                if ($stage?->trashed()) {
                    throw ValidationException::withMessages(['stage' => 'La etapa '.$stageName.' fue eliminada.']);
                }
                $stage ??= app(ProcessStageService::class)->save(new ProcessStage, ['name' => $stageName, 'status_id' => $this->status('process_stages', 'Activo')->id], $actor);
                $configuration = PurchaseStage::withTrashed()->where('company_id', $company->id)->where('position', $position + 1)->first() ?? new PurchaseStage;
                $values = ['process_definition_id' => $process->id, 'company_id' => $company->id, 'process_stage_id' => $stage->id, 'responsible_role_id' => $role->id, 'completion_status_id' => $this->status('purchase_requests', $result)->id, 'initial_status_id' => $position === 0 ? $this->status('purchase_requests', 'Borrador')->id : null, 'position' => $position + 1, 'field_name' => $field];
                if ($configuration->trashed() || ($configuration->exists && collect($values)->except(['initial_status_id'])->contains(fn ($value, $key) => (string) $configuration->{$key} !== (string) $value))) {
                    throw ValidationException::withMessages(['workflow' => 'Ya existe otra configuración de compras para esta empresa. No se reemplazó.']);
                }
                $before = app(RecordHistoryService::class)->snapshot($configuration);
                $action = $configuration->exists ? 'updated' : 'created';
                $configuration->fill($values)->save();
                app(RecordHistoryService::class)->record($configuration, $actor, $action, $before);
                $stages->push($configuration);
            }
            foreach ($stages->slice(0, -1) as $index => $stage) {
                $next = $stages[$index + 1];
                $edges = ProcessStep::where('origin_stage_id', $stage->process_stage_id)->get();
                if ($edges->contains(fn ($edge) => $edge->destination_stage_id !== $next->process_stage_id || $edge->responsible_role_id !== $stage->responsible_role_id)) {
                    throw ValidationException::withMessages(['workflow' => 'Una etapa tiene otra transición. Resuélvela en Procesos antes de preparar el flujo.']);
                }
                if ($edges->isEmpty()) {
                    app(ProcessStepService::class)->save(new ProcessStep, ['process_definition_id' => $process->id, 'origin_stage_id' => $stage->process_stage_id, 'destination_stage_id' => $next->process_stage_id, 'responsible_role_id' => $stage->responsible_role_id, 'status_id' => $this->status('processes', 'Activo')->id], $actor);
                }
            }

            return $users;
        });
    }

    private function status(string $type, string $name): Status
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))->when($type === 'purchase_requests', fn ($query) => $query->where('name', $name))->where('is_active', true)->orderBy('id')->firstOrFail();
    }
}
