<?php

namespace App\Services\Navigation;

use Illuminate\Support\Facades\Route;

/** Catálogo explícito de destinos seguros. Nunca interpreta PHP ni URLs guardadas en la base. */
class NavigationRegistry
{
    public function icons(): array
    {
        return [
            'layout-dashboard' => 'Inicio',
            'building' => 'Empresa',
            'building-community' => 'Sucursales',
            'users' => 'Usuarios',
            'shield-lock' => 'Accesos',
            'settings' => 'Configuración',
            'list-check' => 'Estados',
            'layout-grid' => 'Módulos',
            'briefcase' => 'Organización',
            'folders' => 'Carpeta',
        ];
    }

    public function actions(): array
    {
        $definitions = [
            'dashboard' => ['label' => 'Inicio', 'permission' => null, 'module' => null],
            'companies.index' => ['label' => 'Empresas', 'permission' => 'system.companies.manage', 'module' => null],
            'branches.index' => ['label' => 'Sucursales', 'permission' => 'organization.branches.manage', 'module' => 'organization'],
            'users.index' => ['label' => 'Usuarios', 'permission' => 'access.users.manage', 'module' => 'access'],
            'roles.index' => ['label' => 'Roles y permisos', 'permission' => 'access.roles.manage', 'module' => 'access'],
            'statuses.index' => ['label' => 'Estados', 'permission' => 'system.statuses.manage', 'module' => null],
            'menus.index' => ['label' => 'Menús', 'permission' => 'system.menus.manage', 'module' => null],
        ];

        $actions = [];
        foreach ($definitions as $name => $definition) {
            $route = Route::getRoutes()->getByName($name);
            if (! $route || ! in_array('GET', $route->methods(), true) || $route->parameterNames() !== []) {
                continue;
            }

            $actions[$name] = $definition + ['controller' => class_basename($route->getActionName())];
        }

        return $actions;
    }
}
