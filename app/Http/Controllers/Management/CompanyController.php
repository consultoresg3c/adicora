<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\CompanyRequest;
use App\Http\Requests\Management\ListingRequest;
use App\Models\Company;
use App\Models\Module;
use App\Models\Status;
use App\Services\Management\CompanyService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $search = $request->searchTerm();
        $records = Company::query()->with('status')->withCount('branches', 'users')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $searchQuery) => $searchQuery
                ->whereLike('name', '%'.$search.'%')
                ->orWhereLike('tax_id', '%'.$search.'%')
                ->orWhereLike('email', '%'.$search.'%')))
            ->when($request->filled('status_id'), fn (Builder $query) => $query->where('status_id', $request->integer('status_id')))
            ->when($request->filled('module_id'), fn (Builder $query) => $query->whereHas('modules', fn (Builder $modules) => $modules->whereKey($request->integer('module_id'))))
            ->orderBy('name')->orderBy('id')->paginate($request->perPage())->withQueryString();

        return ListingResponse::view('modules.companies.index', [
            'records' => $records,
            'statuses' => Status::query()->whereHas('type', fn (Builder $query) => $query->where('code', 'companies'))->orderBy('id')->get(['id', 'name']),
            'modules' => Module::query()->orderBy('id')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('modules.companies.form', ['record' => new Company] + $this->options($request, 'companies'));
    }

    public function store(CompanyRequest $request, CompanyService $service): JsonResponse|RedirectResponse
    {
        $company = $service->save(new Company, $request->validated(), $request->user());

        return $this->saved($request, 'companies.index', route('records.history', ['resource' => 'empresas', 'record' => $company->id]));
    }

    public function edit(Request $request, Company $company): View
    {
        return view('modules.companies.form', ['record' => $company->load('modules', 'recentActivities.actor')] + $this->options($request, 'companies'));
    }

    public function update(CompanyRequest $request, Company $company, CompanyService $service): JsonResponse|RedirectResponse
    {
        $service->save($company, $request->validated(), $request->user());

        return $this->saved($request, 'companies.index', route('records.history', ['resource' => 'empresas', 'record' => $company->id]));
    }

    public function destroy(Request $request, Company $company, CompanyService $service): JsonResponse|RedirectResponse
    {
        $service->delete($company, $request->user());

        return $this->saved($request, 'companies.index', route('records.history', ['resource' => 'empresas', 'record' => $company->id]), 'Empresa eliminada de forma lógica.');
    }
}
