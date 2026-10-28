<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Purchases\PurchaseRequestForm;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\Supplier;
use App\Services\Purchases\PurchaseAccessService;
use App\Services\Purchases\PurchaseFieldService;
use App\Services\Purchases\PurchaseWorkflowService;
use App\Support\AsyncResponse;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseAccessService $access, private PurchaseFieldService $fields) {}

    public function index(ListingRequest $request): View|JsonResponse
    {
        $tracking = $request->routeIs('purchases.tracking');
        $query = $tracking ? $this->access->tracking($request->user()) : $this->access->pending($request->user());
        $records = $query->with('supplier', 'currentStage.stage', 'currentStage.fieldRules.field', 'status')
            ->when($request->searchTerm() !== '', fn ($query) => $query->where(fn ($query) => $query->where(fn ($visible) => $visible->whereHas('currentStage.fieldRules', fn ($rule) => $rule->where('mode', '!=', 'hidden')->whereHas('field', fn ($field) => $field->where('attribute', 'supplier_id')))->whereHas('supplier', fn ($supplier) => $supplier->whereLike('name', '%'.$request->searchTerm().'%')))->when(ctype_digit($request->searchTerm()), fn ($query) => $query->orWhere('id', (int) $request->searchTerm()))))
            ->when($request->integer('status_id'), fn ($query, $id) => $query->where('status_id', $id))
            ->latest('id')->paginate($request->perPage())->withQueryString();

        foreach ($records as $item) {
            $item->visible_supplier = $this->fields->visibleAttribute($item->currentStage, 'supplier_id');
            $item->visible_amount = $this->fields->visibleAttribute($item->currentStage, 'amount');
        }

        return ListingResponse::view('modules.purchases.index', [
            'records' => $records, 'tracking' => $tracking,
            'canCreate' => $this->access->initialStages($request->user())->isNotEmpty(),
            'statuses' => Status::whereHas('type', fn ($query) => $query->where('code', 'purchase_requests'))->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $request->validate(['start_stage_id' => ['nullable', 'integer', 'min:1']]);
        $stages = $this->access->initialStages($request->user());
        abort_if($stages->isEmpty(), 403);
        $stage = $this->access->initialStage($request->user(), $request->filled('start_stage_id') ? $request->integer('start_stage_id') : null);
        if (! $stage) {
            abort_if($request->filled('start_stage_id'), 404);

            return view('modules.purchases.choose-start', ['stages' => $stages]);
        }
        $record = new PurchaseRequest(['company_id' => $request->user()->company_id, 'currency' => 'USD']);
        $record->setRelation('currentStage', $stage);

        return $this->form($request, $record, true);
    }

    public function show(Request $request, PurchaseRequest $purchase): View
    {
        $this->access->assertRead($request->user(), $purchase);
        $purchase->load('supplier', 'status', 'currentStage.stage', 'recentActivities.actor');

        return $this->form($request, $purchase, $this->access->canWork($request->user(), $purchase));
    }

    private function form(Request $request, PurchaseRequest $record, bool $editable): View
    {
        $record->loadMissing('customValues', 'currentStage.fieldRules.field', 'currentStage.process');
        $stage = $record->currentStage;
        if ($record->relationLoaded('recentActivities')) {
            $record->setRelation('recentActivities', $this->fields->redactHistory($record->recentActivities, $stage));
        }

        return view('modules.purchases.form', [
            'record' => $record, 'editable' => $editable,
            'isFinalStage' => $stage->is_end,
            'fieldViews' => $this->fields->viewFields($stage, $record, $editable),
            'configurationVersion' => $stage->process->version,
            'historyLabels' => $this->fields->historyLabels($stage),
            'suppliers' => $editable && $this->fields->profile($stage)->contains(fn ($item) => $item['field']->attribute === 'supplier_id' && $item['mode'] === 'edit')
                ? Supplier::where('company_id', $request->user()->company_id)->whereHas('status', fn ($query) => $query->where('is_active', true))->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(PurchaseRequestForm $request, PurchaseWorkflowService $service): JsonResponse|RedirectResponse
    {
        $record = $service->save(null, $request->validated(), $request->user());

        return $this->saved($request, $record);
    }

    public function update(PurchaseRequestForm $request, PurchaseRequest $purchase, PurchaseWorkflowService $service): JsonResponse|RedirectResponse
    {
        $record = $service->save($purchase, $request->validated(), $request->user());

        return $this->saved($request, $record);
    }

    private function saved(Request $request, PurchaseRequest $record): JsonResponse|RedirectResponse
    {
        $message = $request->input('intent') === 'advance' ? ($record->completed_at ? 'Etapa completada. Solicitud finalizada.' : 'Etapa completada. Solicitud enviada al siguiente responsable.') : 'Solicitud guardada.';
        $request->session()->flash('success', $message);

        return $request->expectsJson() ? AsyncResponse::success($message, ['redirect' => route('purchases.index')]) : redirect()->route('purchases.index');
    }
}
