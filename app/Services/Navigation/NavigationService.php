<?php

namespace App\Services\Navigation;

use App\Models\MenuItem;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NavigationService
{
    public function __construct(private AccessService $access, private NavigationRegistry $registry) {}

    public function isInstalled(): bool
    {
        return Schema::hasTable('menu_items');
    }

    public function forUser(User $user, Request $request): Collection
    {
        if (! $this->access->isActive($user)) {
            return collect();
        }

        $actions = $this->registry->actions();
        $items = $this->isInstalled()
            ? MenuItem::query()->with('status', 'permission', 'module')->whereHas('status', fn ($query) => $query->where('is_active', true))->orderBy('sort_order')->orderBy('id')->get()
            : $this->fallbackItems();

        $leaves = $items->filter(function (MenuItem $item) use ($user, $actions) {
            if ($item->is_parent || ! isset($actions[$item->route_name])) {
                return false;
            }

            $action = $actions[$item->route_name];
            // También se verifica la acción registrada: alterar la configuración no concede acceso.
            if ($action['permission'] !== null && ! $this->access->allows($user, $action['permission'])) {
                return false;
            }

            if ($item->exists && ($item->permission?->code !== $action['permission'] || $item->module?->code !== $action['module'] || $item->permission?->module_id !== $item->module_id)) {
                return false;
            }

            return true;
        })->each(function (MenuItem $item) use ($request) {
            $item->setAttribute('url', route($item->route_name));
            $pattern = $item->route_name === 'dashboard' ? 'dashboard' : str($item->route_name)->beforeLast('.').'.*';
            $item->setAttribute('active', $request->routeIs($pattern) || $request->attributes->get('navigation_route') === $item->route_name);
        });

        return $items->where('is_parent', true)->filter(function (MenuItem $parent) use ($leaves) {
            $children = $leaves->where('parent_id', $parent->id)->values();
            $parent->setRelation('children', $children);
            $parent->setAttribute('active', $children->contains('active', true));

            return $children->isNotEmpty();
        })->values();
    }

    private function fallbackItems(): Collection
    {
        $groups = [
            ['General', 'layout-dashboard', [['Inicio', 'layout-dashboard', 'dashboard']]],
            ['Organización', 'briefcase', [['Empresas', 'building', 'companies.index'], ['Sucursales', 'building-community', 'branches.index']]],
            ['Accesos', 'shield-lock', [['Usuarios', 'users', 'users.index'], ['Roles y permisos', 'shield-lock', 'roles.index']]],
            ['Sistema', 'settings', [['Estados', 'list-check', 'statuses.index'], ['Menús', 'layout-grid', 'menus.index']]],
        ];

        $items = collect();
        foreach ($groups as $index => [$name, $icon, $children]) {
            $parent = new MenuItem(['name' => $name, 'icon' => $icon, 'is_parent' => true]);
            $parent->id = -($index + 1);
            $items->push($parent);
            foreach ($children as [$childName, $childIcon, $route]) {
                $items->push(new MenuItem(['parent_id' => $parent->id, 'name' => $childName, 'icon' => $childIcon, 'route_name' => $route]));
            }
        }

        return $items;
    }
}
