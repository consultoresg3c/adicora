<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Management\ProcessStageRequest;
use App\Models\ProcessStage;
use App\Models\Status;
use App\Services\Management\ProcessStageService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcessStageController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $request->attributes->set('navigation_route', 'processes.index');
        $term = $request->searchTerm();
        $record = $request->filled('editar') ? ProcessStage::with('recentActivities.actor')->findOrFail($request->integer('editar')) : new ProcessStage;
        $records = ProcessStage::with('status', 'recentActivities.actor', 'purchaseConfigurations.role', 'purchaseConfigurations.completionStatus', 'purchaseConfigurations.company')
            ->when($term !== '', fn ($query) => $query->whereLike('name', '%'.$term.'%'))
            ->when($request->integer('status_id'), fn ($query, $id) => $query->where('status_id', $id))
            ->orderBy('id')->paginate($request->perPage())->withQueryString();
        $statuses = Status::whereHas('type', fn ($query) => $query->where('code', 'process_stages'))->orderBy('id')->get();

        return ListingResponse::view('modules.process-stages.index', compact('records', 'record', 'statuses') + [
            'formRecord' => new ProcessStage,
            'openModal' => $request->has('nuevo') || $record->exists,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('process-stages.index', ['nuevo' => 1]);
    }

    public function store(ProcessStageRequest $request, ProcessStageService $service): JsonResponse|RedirectResponse
    {
        $processStage = $service->save(new ProcessStage, $request->validated(), $request->user());

        return $this->saved($request, 'process-stages.index', route('records.history', ['resource' => 'etapas-procesos', 'record' => $processStage->id]));
    }

    public function edit(Request $request, ProcessStage $processStage): RedirectResponse
    {
        return redirect()->route('process-stages.index', ['editar' => $processStage->id]);
    }

    public function update(ProcessStageRequest $request, ProcessStage $processStage, ProcessStageService $service): JsonResponse|RedirectResponse
    {
        $service->save($processStage, $request->validated(), $request->user());

        return $this->saved($request, 'process-stages.index', route('records.history', ['resource' => 'etapas-procesos', 'record' => $processStage->id]));
    }

    public function destroy(Request $request, ProcessStage $processStage, ProcessStageService $service): JsonResponse|RedirectResponse
    {
        $service->delete($processStage, $request->user());

        return $this->saved($request, 'process-stages.index', route('records.history', ['resource' => 'etapas-procesos', 'record' => $processStage->id]), 'Registro eliminado de forma lógica.');
    }
}
