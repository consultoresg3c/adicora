<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Module;
use App\Models\Role;
use App\Models\Status;
use App\Models\StatusType;
use App\Services\Access\AccessService;
use App\Support\AsyncResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class ManagementController extends Controller
{
    public function __construct(protected AccessService $access) {}

    protected function options(Request $request, string $statusType, ?int $companyId = null): array
    {
        return [
            'companies' => Company::query()->when($request->user()->company_id, fn ($query, $id) => $query->whereKey($id))->orderBy('name')->get(),
            'statuses' => Status::query()->whereHas('type', fn ($query) => $query->where('code', $statusType))->orderBy('id')->get(),
            'statusTypes' => StatusType::orderBy('id')->get(),
            'modules' => Module::orderBy('id')->get(),
            'permissions' => $this->access->delegablePermissions($request->user(), $companyId),
            'roles' => Role::query()->with('permissions')->where('company_id', $companyId)->orderBy('name')->get(),
        ];
    }

    protected function assertTenant(Request $request, Model $record): void
    {
        abort_if($request->user()->company_id !== null && $record->company_id !== $request->user()->company_id, 404);
    }

    protected function saved(Request $request, string $route, ?string $historyUrl = null, string $message = 'Cambios guardados correctamente.'): JsonResponse|RedirectResponse
    {
        $request->session()->flash('success', $message);
        if ($historyUrl !== null) {
            $request->session()->flash('history_url', $historyUrl);
        }

        return $request->expectsJson()
            ? AsyncResponse::success($message, ['redirect' => route($route)])
            : redirect()->route($route);
    }
}
