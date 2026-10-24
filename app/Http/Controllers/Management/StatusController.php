<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Management\StatusRequest;
use App\Models\Status;
use App\Models\StatusType;
use App\Services\Management\StatusService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StatusController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $record = $request->filled('editar')
            ? Status::with('type', 'recentActivities.actor')->findOrFail($request->integer('editar'))
            : new Status;
        $initialTemplate = $request->has('nuevo') ? 'create-status' : null;
        if ($record->exists) {
            $initialTemplate = 'edit-status-'.$record->id;
        }
        $records = Status::with('type', 'recentActivities.actor')
            ->when($request->searchTerm() !== '', fn ($query) => $query->whereLike('name', '%'.$request->searchTerm().'%'))
            ->when($request->filled('status_type_id'), fn ($query) => $query->where('status_type_id', $request->integer('status_type_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('status_type_id')->orderBy('name')->orderBy('id')
            ->paginate($request->perPage())->withQueryString();

        return ListingResponse::view('modules.statuses.index', [
            'records' => $records,
            'groups' => $records->getCollection()->groupBy('status_type_id'),
            'statusTypes' => StatusType::orderBy('id')->get(),
            'record' => $record,
            'newRecord' => new Status,
            'initialTemplate' => $initialTemplate,
            'openModal' => $initialTemplate !== null,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('statuses.index', ['nuevo' => 1]);
    }

    public function store(StatusRequest $request, StatusService $service): JsonResponse|RedirectResponse
    {
        $status = $service->save(new Status, $request->validated(), $request->user());

        return $this->saved($request, 'statuses.index', route('records.history', ['resource' => 'estados', 'record' => $status->id]));
    }

    public function edit(Status $status): RedirectResponse
    {
        return redirect()->route('statuses.index', ['editar' => $status->id]);
    }

    public function update(StatusRequest $request, Status $status, StatusService $service): JsonResponse|RedirectResponse
    {
        $service->save($status, $request->validated(), $request->user());

        return $this->saved($request, 'statuses.index', route('records.history', ['resource' => 'estados', 'record' => $status->id]));
    }

    public function destroy(Request $request, Status $status, StatusService $service): JsonResponse|RedirectResponse
    {
        $service->delete($status, $request->user());

        return $this->saved($request, 'statuses.index', route('records.history', ['resource' => 'estados', 'record' => $status->id]), 'Estado eliminado de forma lógica.');
    }
}
