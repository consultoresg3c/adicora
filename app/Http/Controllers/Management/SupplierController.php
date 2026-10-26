<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Management\SupplierRequest;
use App\Models\Supplier;
use App\Services\Management\SupplierService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $term = $request->searchTerm();
        $record = $request->filled('editar')
            ? $this->access->scope(Supplier::with('recentActivities.actor'), $request->user())->findOrFail($request->integer('editar'))
            : new Supplier;

        $records = $this->access->scope(Supplier::with('company', 'status', 'recentActivities.actor'), $request->user())
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereLike('name', '%'.$term.'%')
                ->orWhereHas('company', fn ($query) => $query->whereLike('name', '%'.$term.'%'))))
            ->when($request->integer('status_id'), fn ($query, $id) => $query->where('status_id', $id))
            ->when($request->user()->is_super_admin && $request->integer('company_id'), fn ($query) => $query->where('company_id', $request->integer('company_id')))
            ->orderBy('name')->orderBy('id')->paginate($request->perPage())->withQueryString();

        return ListingResponse::view('modules.suppliers.index', [
            'records' => $records,
            'record' => $record,
            'formRecord' => new Supplier,
            'openModal' => $request->has('nuevo') || $record->exists,
        ] + $this->options($request, 'suppliers'));
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('suppliers.index', ['nuevo' => 1]);
    }

    public function store(SupplierRequest $request, SupplierService $service): JsonResponse|RedirectResponse
    {
        $supplier = $service->save(new Supplier, $request->validated(), $request->user());

        return $this->saved($request, 'suppliers.index', route('records.history', ['resource' => 'proveedores', 'record' => $supplier->id]));
    }

    public function edit(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->assertTenant($request, $supplier);

        return redirect()->route('suppliers.index', ['editar' => $supplier->id]);
    }

    public function update(SupplierRequest $request, Supplier $supplier, SupplierService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $supplier);
        $service->save($supplier, $request->validated(), $request->user());

        return $this->saved($request, 'suppliers.index', route('records.history', ['resource' => 'proveedores', 'record' => $supplier->id]));
    }

    public function destroy(Request $request, Supplier $supplier, SupplierService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $supplier);
        $service->delete($supplier, $request->user());

        return $this->saved($request, 'suppliers.index', route('records.history', ['resource' => 'proveedores', 'record' => $supplier->id]), 'Proveedor eliminado de forma lógica.');
    }
}
