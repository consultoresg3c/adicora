<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->restrictOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->boolean('is_parent')->default(false);
            $table->string('icon', 40);
            $table->string('route_name', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['parent_id', 'sort_order', 'id']);
        });

        $timestamp = now();
        // Datos pequeños y deterministas: sirven para actualizar y para una instalación nueva.
        $typeId = DB::table('status_types')->where('code', 'menus')->value('id')
            ?? DB::table('status_types')->insertGetId(['code' => 'menus', 'name' => 'Menús', 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        $activeId = null;
        foreach (['Activo' => true, 'Inactivo' => false] as $name => $active) {
            $id = DB::table('statuses')->where('status_type_id', $typeId)->where('name', $name)->value('id')
                ?? DB::table('statuses')->insertGetId(['status_type_id' => $typeId, 'name' => $name, 'is_active' => $active, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
            if ($active) {
                $activeId = $id;
            }
        }

        $moduleIds = [];
        foreach (['organization' => 'Organización', 'access' => 'Usuarios y acceso'] as $code => $name) {
            $moduleIds[$code] = DB::table('modules')->where('code', $code)->value('id')
                ?? DB::table('modules')->insertGetId(['code' => $code, 'name' => $name, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        }

        $permissionIds = [];
        foreach ([
            ['system.companies.manage', 'Gestionar empresas y sus módulos', null],
            ['system.statuses.manage', 'Gestionar estados del sistema', null],
            ['system.menus.manage', 'Gestionar menús del sistema', null],
            ['organization.branches.manage', 'Gestionar sucursales', 'organization'],
            ['access.users.manage', 'Gestionar usuarios', 'access'],
            ['access.roles.manage', 'Gestionar roles y permisos', 'access'],
        ] as [$code, $name, $module]) {
            $permissionIds[$code] = DB::table('permissions')->where('code', $code)->value('id')
                ?? DB::table('permissions')->insertGetId(['code' => $code, 'name' => $name, 'module_id' => $module === null ? null : $moduleIds[$module], 'created_at' => $timestamp, 'updated_at' => $timestamp]);
        }

        $groups = [
            ['General', 'layout-dashboard', [['Inicio', 'layout-dashboard', 'dashboard', null, null]]],
            ['Organización', 'briefcase', [['Empresas', 'building', 'companies.index', 'system.companies.manage', null], ['Sucursales', 'building-community', 'branches.index', 'organization.branches.manage', 'organization']]],
            ['Accesos', 'shield-lock', [['Usuarios', 'users', 'users.index', 'access.users.manage', 'access'], ['Roles y permisos', 'shield-lock', 'roles.index', 'access.roles.manage', 'access']]],
            ['Sistema', 'settings', [['Estados', 'list-check', 'statuses.index', 'system.statuses.manage', null], ['Menús', 'layout-grid', 'menus.index', 'system.menus.manage', null]]],
        ];

        foreach ($groups as $order => [$name, $icon, $children]) {
            $parentId = DB::table('menu_items')->insertGetId(['name' => $name, 'icon' => $icon, 'is_parent' => true, 'status_id' => $activeId, 'sort_order' => $order, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
            foreach ($children as $childOrder => [$childName, $childIcon, $route, $permission, $module]) {
                DB::table('menu_items')->insert([
                    'parent_id' => $parentId,
                    'name' => $childName,
                    'icon' => $childIcon,
                    'is_parent' => false,
                    'route_name' => $route,
                    'permission_id' => $permission === null ? null : $permissionIds[$permission],
                    'module_id' => $module === null ? null : $moduleIds[$module],
                    'status_id' => $activeId,
                    'sort_order' => $childOrder,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('menu_items')->whereNotNull('parent_id')->delete();
        Schema::dropIfExists('menu_items');
        // Conserva catálogos y permisos: pueden estar asignados a roles, usuarios u otras entidades.
    }
};
