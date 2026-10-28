<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Management\UserRequest;
use App\Models\Company;
use App\Models\Status;
use App\Models\User;
use App\Services\Management\UserService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $search = $request->searchTerm();
        $records = $this->access->scope(User::with('company', 'status', 'roles.permissions', 'permissions'), $request->user())
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $searchQuery) => $searchQuery
                ->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))
            ->when($request->filled('status_id'), fn (Builder $query) => $query->where('status_id', $request->integer('status_id')))
            ->when($request->user()->company_id === null && $request->filled('company_id'), fn (Builder $query) => $query->where('company_id', $request->integer('company_id')))
            ->orderBy('name')->orderBy('id')->paginate($request->perPage())->withQueryString();
        $records->getCollection()->each(fn (User $user) => $user->setAttribute('can_manage', $this->access->canManageGrants($request->user(), $user)));

        return ListingResponse::view('modules.users.index', [
            'records' => $records,
            'statuses' => Status::query()->whereHas('type', fn (Builder $query) => $query->where('code', 'users'))->orderBy('id')->get(['id', 'name']),
            'companies' => $request->user()->company_id === null ? Company::query()->orderBy('name')->orderBy('id')->get(['id', 'name']) : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->access->companyId($request->user(), $request->query('company_id'));

        return view('modules.users.form', ['record' => new User(['company_id' => $companyId])] + $this->options($request, 'users', $companyId));
    }

    public function store(UserRequest $request, UserService $service): JsonResponse|RedirectResponse
    {
        $user = $service->save(new User, $request->validated(), $request->user());

        return $this->saved($request, 'users.index', route('records.history', ['resource' => 'usuarios', 'record' => $user->id]));
    }

    public function edit(Request $request, User $user): View
    {
        $this->assertTenant($request, $user);
        abort_unless($this->access->canManageGrants($request->user(), $user), 403);

        return view('modules.users.form', ['record' => $user->load('company', 'status', 'roles', 'permissions', 'recentActivities.actor')] + $this->options($request, 'users', $user->company_id));
    }

    public function update(UserRequest $request, User $user, UserService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $user);
        $service->save($user, $request->validated(), $request->user());

        return $this->saved($request, 'users.index', route('records.history', ['resource' => 'usuarios', 'record' => $user->id]));
    }

    public function destroy(Request $request, User $user, UserService $service): JsonResponse|RedirectResponse
    {
        $this->assertTenant($request, $user);
        $service->delete($user, $request->user());

        return $this->saved($request, 'users.index', route('records.history', ['resource' => 'usuarios', 'record' => $user->id]), 'Usuario eliminado de forma lógica.');
    }
}
