<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Management\ProcessConfigurationRequest;
use App\Http\Requests\Management\ProcessDefinitionRequest;
use App\Models\Company;
use App\Models\ProcessDefinition;
use App\Models\ProcessStage;
use App\Models\PurchaseStage;
use App\Models\Role;
use App\Models\Status;
use App\Services\Management\ProcessConfigurationService;
use App\Support\AsyncResponse;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcessDefinitionController extends ManagementController
{
    public function index(ListingRequest $request): View|JsonResponse
    {
        $processes = ProcessDefinition::with('company', 'recentActivities.actor')->orderBy('name')->orderBy('id')->get();
        $process = $request->filled('process_id') ? $processes->firstWhere('id', $request->integer('process_id')) : $processes->first();
        abort_if($request->filled('process_id') && ! $process, 404);
        $sequence = $process ? $process->stages()->with('stage', 'role', 'completionStatus', 'recentActivities.actor')->get() : collect();
        $destinations = $sequence->mapWithKeys(fn ($item, $index) => [$item->id => $sequence->get($index + 1)?->stage?->name])->all();
        $record = $request->filled('editar') ? $sequence->firstWhere('id', $request->integer('editar')) : new PurchaseStage;
        abort_if($request->filled('editar') && ! $record, 404);
        $query = PurchaseStage::with('stage', 'role', 'completionStatus', 'recentActivities.actor')->where('process_definition_id', $process?->id)->whereNotNull('process_definition_id');
        $records = $query->when($request->searchTerm() !== '', fn ($query) => $query->where(fn ($query) => $query->whereHas('stage', fn ($stage) => $stage->whereLike('name', '%'.$request->searchTerm().'%'))->orWhereHas('role', fn ($role) => $role->whereLike('name', '%'.$request->searchTerm().'%'))))
            ->when($request->integer('status_id'), fn ($query, $id) => $query->where('completion_status_id', $id))
            ->orderBy('position')->paginate($request->perPage())->withQueryString();
        $statuses = Status::whereHas('type', fn ($query) => $query->where('code', 'purchase_requests'))->orderBy('id')->get();

        return ListingResponse::view('modules.processes.index', [
            'processes' => $processes, 'process' => $process, 'records' => $records,
            'sequence' => $sequence, 'destinations' => $destinations, 'record' => $record,
            'formRecord' => new PurchaseStage(['position' => $sequence->count() + 1, 'field_name' => $sequence->isEmpty() ? 'purchase_date' : null]),
            'statuses' => $statuses,
            'stages' => ProcessStage::orderBy('name')->get(),
            'roles' => $process ? Role::where('company_id', $process->company_id)->orderBy('name')->get() : collect(),
            'companies' => Company::orderBy('name')->get(),
            'initialTemplate' => $request->has('nuevo_proceso') ? 'create-process' : ($request->has('nuevo') && $process ? 'create-process-stage' : ($record?->exists ? 'edit-process-stage-'.$record->id : null)),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('processes.index', ['nuevo_proceso' => 1]);
    }

    public function store(ProcessDefinitionRequest $request, ProcessConfigurationService $service): JsonResponse|RedirectResponse
    {
        $process = $service->save(new ProcessDefinition, $request->validated(), $request->user());

        return $this->respond($request, $process);
    }

    public function edit(ProcessDefinition $processDefinition): RedirectResponse
    {
        return redirect()->route('processes.index', ['process_id' => $processDefinition->id]);
    }

    public function update(ProcessDefinitionRequest $request, ProcessDefinition $processDefinition, ProcessConfigurationService $service): JsonResponse|RedirectResponse
    {
        $service->save($processDefinition, $request->validated(), $request->user());

        return $this->respond($request, $processDefinition);
    }

    public function destroy(Request $request, ProcessDefinition $processDefinition, ProcessConfigurationService $service): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $service->delete($processDefinition, $data['version'], $request->user());

        return $this->respond($request, null, 'Proceso eliminado de forma lógica.');
    }

    public function createStage(ProcessDefinition $processDefinition): RedirectResponse
    {
        return redirect()->route('processes.index', ['process_id' => $processDefinition->id, 'nuevo' => 1]);
    }

    public function editStage(ProcessDefinition $processDefinition, PurchaseStage $purchaseStage): RedirectResponse
    {
        abort_unless($purchaseStage->process_definition_id === $processDefinition->id, 404);

        return redirect()->route('processes.index', ['process_id' => $processDefinition->id, 'editar' => $purchaseStage->id]);
    }

    public function storeStage(ProcessConfigurationRequest $request, ProcessDefinition $processDefinition, ProcessConfigurationService $service): JsonResponse|RedirectResponse
    {
        $service->saveStage($processDefinition, null, $request->validated(), $request->user());

        return $this->respond($request, $processDefinition);
    }

    public function updateStage(ProcessConfigurationRequest $request, ProcessDefinition $processDefinition, PurchaseStage $purchaseStage, ProcessConfigurationService $service): JsonResponse|RedirectResponse
    {
        abort_unless($purchaseStage->process_definition_id === $processDefinition->id, 404);
        $service->saveStage($processDefinition, $purchaseStage, $request->validated(), $request->user());

        return $this->respond($request, $processDefinition);
    }

    public function destroyStage(Request $request, ProcessDefinition $processDefinition, PurchaseStage $purchaseStage, ProcessConfigurationService $service): JsonResponse|RedirectResponse
    {
        abort_unless($purchaseStage->process_definition_id === $processDefinition->id, 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $service->deleteStage($processDefinition, $purchaseStage, $data['version'], $request->user());

        return $this->respond($request, $processDefinition, 'Etapa quitada del proceso. Se conservaron su historial y las solicitudes anteriores.');
    }

    private function respond(Request $request, ?ProcessDefinition $process, string $message = 'Proceso actualizado.'): JsonResponse|RedirectResponse
    {
        $request->session()->flash('success', $message);
        $url = route('processes.index', $process ? ['process_id' => $process->id] : []);

        return $request->expectsJson() ? AsyncResponse::success($message, ['redirect' => $url]) : redirect()->to($url);
    }
}
