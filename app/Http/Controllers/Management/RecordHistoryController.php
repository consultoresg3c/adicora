<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\RecordHistoryRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\MenuItem;
use App\Models\ProcessDefinition;
use App\Models\ProcessStage;
use App\Models\ProcessStep;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Role;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\PurchaseAccessService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RecordHistoryController extends ManagementController
{
    /** Catálogo cerrado: la URL nunca elige clases, tablas ni permisos arbitrarios. */
    private const RESOURCES = [
        'empresas' => [Company::class, 'system.companies.manage', 'companies.index'],
        'sucursales' => [Branch::class, 'organization.branches.manage', 'branches.index'],
        'usuarios' => [User::class, 'access.users.manage', 'users.index'],
        'roles' => [Role::class, 'access.roles.manage', 'roles.index'],
        'estados' => [Status::class, 'system.statuses.manage', 'statuses.index'],
        'flujos-procesos' => [ProcessDefinition::class, 'system.processes.manage', 'processes.index'],
        'etapas-compras' => [PurchaseStage::class, 'system.processes.manage', 'processes.index'],
        'procesos' => [ProcessStep::class, 'system.processes.manage', 'processes.index'],
        'etapas-procesos' => [ProcessStage::class, 'system.processes.manage', 'process-stages.index'],
        'proveedores' => [Supplier::class, 'purchases.suppliers.manage', 'suppliers.index'],
        'solicitudes-compra' => [PurchaseRequest::class, 'purchases.requests.work', 'purchases.index'],
        'menus' => [MenuItem::class, 'system.menus.manage', 'menus.index'],
    ];

    public function __invoke(RecordHistoryRequest $request, string $resource, int $record): View|JsonResponse
    {
        [$model, $ability, $indexRoute] = self::RESOURCES[$resource];
        Gate::authorize($ability);
        request()->attributes->set('navigation_route', $indexRoute);
        $subject = $model::withTrashed()->findOrFail($record);
        if ($subject instanceof PurchaseRequest) {
            app(PurchaseAccessService::class)->assertRead($request->user(), $subject);
        }
        if ($subject instanceof Supplier) {
            $this->assertTenant($request, $subject);
        }
        if ($subject instanceof Branch || $subject instanceof User || $subject instanceof Role) {
            $this->assertTenant($request, $subject);
        }
        if ($subject instanceof User || $subject instanceof Role) {
            abort_unless($this->access->canManageGrants($request->user(), $subject), 403);
        }
        $entries = $subject->activities()->with('actor')
            ->when($request->searchTerm() !== '', fn ($query) => $query->whereHas('actor', fn ($actor) => $actor->whereLike('name', '%'.$request->searchTerm().'%')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->orderByDesc('id')->paginate($request->perPage())->withQueryString();

        return ListingResponse::view('modules.history.index', [
            'record' => $subject,
            'resource' => $resource,
            'title' => 'Historial de '.$subject->name,
            'indexRoute' => $indexRoute,
            'entries' => $entries,
        ]);
    }
}
