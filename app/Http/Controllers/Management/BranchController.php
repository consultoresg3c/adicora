<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\BranchRequest;
use App\Http\Requests\Management\ListingRequest;
use App\Models\Branch;
use App\Services\Management\BranchService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $term = $request->searchTerm();
        $record = $request->filled('editar')
            ? $this->access->scope(Branch::with('recentActivities.actor'), $request->user())->findOrFail($request->integer('editar'))
            : new Branch;

        $records = $this->access->scope(Branch::with('company', 'status', 'recentActivities.actor'), $request->user())
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', '%'.$term.'%')
                ->orWhereLike('address', '%'.$term.'%')
                ->orWhereHas('company', fn ($query) => $query->whereLike('name', '%'.$term.'%'))))
            ->when($request->integer('status_id'), fn ($query, $id) => $query->where('status_id', $id))
            ->when($request->user()->is_super_admin && $request->integer('company_id'), fn ($query) => $query->where('company_id', $request->integer('company_id')))
            ->orderBy('name')->orderBy('id')->paginate($request->perPage())->withQueryString();

        return ListingResponse::view('modules.branches.index', [
            'records' => $records,
            'record' => $record,
            'formRecord' => new Branch,
            'openModal' => $request->has('nuevo') || $record->exists,
        ] + $this->options($request, 'branches'));
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('branches.index', ['nuevo' => 1]);
    }

    public function store(BranchRequest $request, BranchService $service): JsonResponse|RedirectResponse
    {
        $branch = $service->save(new Branch, $request->validated(), $request->user());

        return $this->saved($request, 'branches.index', route('records.history', ['resource' => 'sucursales', 'record' => $branch->id]));
    }

    public function edit(Request $request, Branch $branch): RedirectResponse
    {
        $this->assertTenant($request, $branch);

        return redirect()->route('branches.index', ['editar' => $branch->id]);
    }

    public function update(BranchRequest $request, Branch $branch, BranchService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $branch);
        $service->save($branch, $request->validated(), $request->user());

        return $this->saved($request, 'branches.index', route('records.history', ['resource' => 'sucursales', 'record' => $branch->id]));
    }

    public function destroy(Request $request, Branch $branch, BranchService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $branch);
        $service->delete($branch, $request->user());

        return $this->saved($request, 'branches.index', route('records.history', ['resource' => 'sucursales', 'record' => $branch->id]), 'Sucursal eliminada de forma lógica.');
    }
}
