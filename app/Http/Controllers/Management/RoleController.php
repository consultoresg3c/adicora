<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Management\RoleRequest;
use App\Models\Company;
use App\Models\Module;
use App\Models\Role;
use App\Services\Management\RoleService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $search = $request->searchTerm();
        $records = $this->access->scope(Role::with('company', 'permissions')->withCount('permissions', 'users'), $request->user())
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', '%'.$search.'%'))
            ->when($request->user()->company_id === null && $request->filled('company_id'), fn (Builder $query) => $query->where('company_id', $request->integer('company_id')))
            ->when($request->filled('module_id'), fn (Builder $query) => $query->whereHas('permissions', fn (Builder $permissions) => $permissions->where('module_id', $request->integer('module_id'))))
            ->orderBy('name')->orderBy('id')->paginate($request->perPage())->withQueryString();
        $records->getCollection()->each(fn (Role $role) => $role->setAttribute('can_manage', $this->access->canManageGrants($request->user(), $role)));

        return ListingResponse::view('modules.roles.index', [
            'records' => $records,
            'companies' => $request->user()->company_id === null ? Company::query()->orderBy('name')->orderBy('id')->get(['id', 'name']) : collect(),
            'modules' => Module::query()->when($request->user()->company_id !== null, fn (Builder $query) => $query->whereHas('companies', fn (Builder $companies) => $companies->whereKey($request->user()->company_id)))->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->access->companyId($request->user(), $request->query('company_id'));

        return view('modules.roles.form', ['record' => new Role(['company_id' => $companyId])] + $this->options($request, 'users', $companyId));
    }

    public function store(RoleRequest $request, RoleService $service): JsonResponse|RedirectResponse
    {
        $role = $service->save(new Role, $request->validated(), $request->user());

        return $this->saved($request, 'roles.index', route('records.history', ['resource' => 'roles', 'record' => $role->id]));
    }

    public function edit(Request $request, Role $role): View
    {
        $this->assertTenant($request, $role);
        abort_unless($this->access->canManageGrants($request->user(), $role), 403);

        return view('modules.roles.form', ['record' => $role->load('permissions', 'recentActivities.actor')] + $this->options($request, 'users', $role->company_id));
    }

    public function update(RoleRequest $request, Role $role, RoleService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $role);
        $service->save($role, $request->validated(), $request->user());

        return $this->saved($request, 'roles.index', route('records.history', ['resource' => 'roles', 'record' => $role->id]));
    }

    public function destroy(Request $request, Role $role, RoleService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $role);
        $service->delete($role, $request->user());

        return $this->saved($request, 'roles.index', route('records.history', ['resource' => 'roles', 'record' => $role->id]), 'Rol eliminado de forma lógica.');
    }
}
