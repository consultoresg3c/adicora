<?php

namespace App\Http\Controllers\Management;

use App\Http\Requests\Management\ListingRequest;
use App\Http\Requests\Management\MenuItemRequest;
use App\Models\MenuItem;
use App\Models\Module;
use App\Models\Status;
use App\Services\Management\MenuItemService;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Navigation\NavigationService;
use App\Support\ListingResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MenuItemController extends ManagementController
{
    public function index(ListingRequest $request, NavigationService $navigation, NavigationRegistry $registry): View|JsonResponse
    {
        $term = $request->searchTerm();
        $installed = $navigation->isInstalled();
        $record = $installed && $request->filled('editar')
            ? MenuItem::with('recentActivities.actor')->findOrFail($request->integer('editar'))
            : new MenuItem;
        $records = $installed
            ? MenuItem::query()->with('parent', 'status', 'permission.module', 'recentActivities.actor')->withCount('children')
                ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereLike('name', '%'.$term.'%')->orWhereLike('route_name', '%'.$term.'%')
                    ->orWhereHas('parent', fn ($query) => $query->whereLike('name', '%'.$term.'%'))))
                ->when($request->integer('status_id'), fn ($query, $id) => $query->where('status_id', $id))
                ->when($request->integer('parent_id'), fn ($query, $id) => $query->where('parent_id', $id))
                ->when($request->integer('module_id'), fn ($query, $id) => $query->where('module_id', $id))
                ->when($request->filled('kind'), fn ($query) => $query->where('is_parent', $request->input('kind') === 'parent'))
                ->orderByRaw('COALESCE(parent_id, id)')->orderByDesc('is_parent')->orderBy('sort_order')->orderBy('id')
                ->paginate($request->perPage())->withQueryString()
            : new LengthAwarePaginator([], 0, $request->perPage(), 1, ['path' => $request->url()]);

        return ListingResponse::view('modules.menus.index', [
            'records' => $records,
            'record' => $record,
            'formRecord' => new MenuItem,
            'openModal' => $installed && ($request->has('nuevo') || $record->exists),
            'pendingMigration' => ! $installed,
        ] + $this->menuOptions($registry, $installed));
    }

    public function create(Request $request, NavigationService $navigation): RedirectResponse
    {
        abort_unless($navigation->isInstalled(), 503, 'Ejecuta la migración pendiente de menús para habilitar esta función.');

        return redirect()->route('menus.index', ['nuevo' => 1]);
    }

    public function store(MenuItemRequest $request, MenuItemService $service): JsonResponse|RedirectResponse
    {
        $menuItem = $service->save(new MenuItem, $request->validated(), $request->user());

        return $this->saved($request, 'menus.index', route('records.history', ['resource' => 'menus', 'record' => $menuItem->id]));
    }

    public function edit(Request $request, MenuItem $menuItem): RedirectResponse
    {
        return redirect()->route('menus.index', ['editar' => $menuItem->id]);
    }

    public function update(MenuItemRequest $request, MenuItem $menuItem, MenuItemService $service): JsonResponse|RedirectResponse
    {
        $service->save($menuItem, $request->validated(), $request->user());

        return $this->saved($request, 'menus.index', route('records.history', ['resource' => 'menus', 'record' => $menuItem->id]));
    }

    public function destroy(Request $request, MenuItem $menuItem, MenuItemService $service): JsonResponse|RedirectResponse
    {
        $service->delete($menuItem, $request->user());

        return $this->saved($request, 'menus.index', route('records.history', ['resource' => 'menus', 'record' => $menuItem->id]), 'Menú eliminado de forma lógica.');
    }

    private function menuOptions(NavigationRegistry $registry, bool $installed = true): array
    {
        return [
            'parents' => $installed ? MenuItem::query()->where('is_parent', true)->whereNull('parent_id')->orderBy('sort_order')->orderBy('id')->get() : collect(),
            'statuses' => Status::query()->whereHas('type', fn ($query) => $query->where('code', 'menus'))->orderBy('id')->get(),
            'actions' => $registry->actions(),
            'icons' => $registry->icons(),
            'modules' => Module::orderBy('name')->get(),
        ];
    }
}
