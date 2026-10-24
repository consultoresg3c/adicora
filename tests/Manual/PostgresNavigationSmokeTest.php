<?php

namespace Tests\Manual;

use App\Models\Company;
use App\Models\MenuItem;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\Management\MenuItemService;
use App\Services\Navigation\NavigationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verificación manual de la conexión local existente. Nunca migra ni ejecuta seeders.
 * DatabaseTransactions revierte todas las filas de prueba incluso si falla una aserción.
 * Ejecutar explícitamente sin phpunit.xml para conservar la conexión PostgreSQL de .env:
 * php vendor/phpunit/phpunit/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Manual/PostgresNavigationSmokeTest.php
 */
class PostgresNavigationSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::connection()->getDriverName(), 'Esta comprobación solo admite PostgreSQL.');
        $this->assertTrue(Schema::hasTable('menu_items'), 'La migración de menús debe estar aplicada previamente.');
        $this->assertTrue(Schema::hasTable('audit_entries') && Schema::hasColumn('menu_items', 'deleted_at'), 'La migración incremental de borrado lógico e historial debe estar aplicada previamente.');
        config(['session.driver' => 'array', 'cache.default' => 'array', 'app.url' => 'http://localhost']);
        // La prueba llama directamente al kernel, sin el prefijo físico de la instalación Wamp.
        URL::forceRootUrl('http://localhost');
        $this->withoutVite();
        // Se comprueban HTTP y persistencia; el token del navegador se verifica por separado.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function admin(): User
    {
        return User::query()->whereNull('company_id')->where('is_super_admin', true)->firstOrFail();
    }

    private function statusId(string $type, bool $active = true): int
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))->where('is_active', $active)->firstOrFail()->id;
    }

    private function parentData(): array
    {
        return ['name' => 'Verificación temporal '.Str::lower(Str::random(10)), 'is_parent' => true, 'icon' => 'briefcase', 'status_id' => $this->statusId('menus'), 'sort_order' => 9990];
    }

    private function leafData(MenuItem $parent, string $route = 'branches.index', int $order = 0): array
    {
        return ['name' => 'Acción temporal '.Str::lower(Str::random(10)), 'is_parent' => false, 'icon' => 'building-community', 'status_id' => $this->statusId('menus'), 'sort_order' => $order, 'parent_id' => $parent->id, 'route_name' => $route];
    }

    private function companyUser(array $permissions = []): User
    {
        $company = Company::create(['name' => 'Verificación temporal de navegación', 'tax_id' => 'NAV-'.Str::upper(Str::random(12)), 'status_id' => $this->statusId('companies')]);
        $company->modules()->sync(Module::whereIn('code', ['organization', 'access'])->pluck('id'));
        $user = User::factory()->create(['name' => 'Cuenta temporal de verificación', 'email' => 'navigation-'.Str::lower(Str::random(12)).'@example.test', 'company_id' => $company->id, 'status_id' => $this->statusId('users')]);
        $user->permissions()->sync(Permission::whereIn('code', $permissions)->pluck('id'));

        return $user;
    }

    private function fixtureRoutes(User $user, MenuItem $parent): array
    {
        return app(NavigationService::class)->forUser($user, Request::create('/inicio'))
            ->firstWhere('id', $parent->id)?->children->pluck('route_name')->all() ?? [];
    }

    public function test_postgres_menu_create_read_update_delete_and_modal_contract(): void
    {
        $data = $this->parentData();
        $this->actingAs($this->admin())->postJson('/menus', $data)->assertOk()->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['redirect'], 'errors', 'meta']);
        $parent = MenuItem::where('name', $data['name'])->firstOrFail();
        $leafData = $this->leafData($parent);
        $this->postJson('/menus', $leafData)->assertOk()->assertJsonPath('success', true);
        $leaf = MenuItem::where('name', $leafData['name'])->firstOrFail();

        $this->get('/menus')->assertOk()->assertViewHas('pendingMigration', false);
        $this->getJson('/menus/'.$leaf->id.'/edit')->assertRedirect(route('menus.index', ['editar' => $leaf->id]));
        $this->get('/menus?editar='.$leaf->id)->assertViewHas('openModal', true)
            ->assertSee('id="edit-menu-'.$leaf->id.'"', false)->assertSee('action="'.route('menus.update', $leaf).'"', false);
        $this->assertSame($parent->id, $leaf->parent->id);
        $this->assertSame('organization', $leaf->module->code);
        $this->assertSame('organization.branches.manage', $leaf->permission->code);
        $this->assertSame($leaf->module_id, $leaf->permission->module_id);

        $leafData['name'] .= ' editada';
        $leafData['route_name'] = 'users.index';
        $this->putJson('/menus/'.$leaf->id, $leafData)->assertOk()->assertJsonPath('success', true);
        $leaf->refresh();
        $this->assertSame($leafData['name'], $leaf->name);
        $this->assertSame('access.users.manage', $leaf->permission->code);
        $this->assertSame('access', $leaf->module->code);

        $this->deleteJson('/menus/'.$leaf->id)->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted($leaf);
        $this->deleteJson('/menus/'.$parent->id)->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted($parent);
    }

    public function test_postgres_rejects_invalid_actions_foreign_status_context_and_company_configuration_access(): void
    {
        $this->actingAs($this->admin());
        $service = app(MenuItemService::class);
        $parent = $service->save(new MenuItem, $this->parentData(), $this->admin());
        $data = $this->leafData($parent);
        $data['route_name'] = 'https://example.test/execute';
        $data['icon'] = '<svg onload=alert(1)>';
        $data['status_id'] = $this->statusId('users');
        $data['permission_id'] = Permission::where('code', 'system.menus.manage')->value('id');

        $this->postJson('/menus', $data)->assertUnprocessable()->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['route_name', 'icon', 'status_id', 'permission_id']);
        $this->assertDatabaseMissing('menu_items', ['name' => $data['name']]);

        $leaf = $service->save(new MenuItem, $this->leafData($parent), $this->admin());
        $this->deleteJson('/menus/'.$parent->id)->assertUnprocessable()->assertJsonValidationErrors('menu');
        $this->assertModelExists($parent);
        $this->assertModelExists($leaf);

        $user = $this->companyUser(['access.users.manage']);
        $this->actingAs($user)->getJson('/menus')->assertForbidden()->assertJsonPath('success', false);
        $this->postJson('/menus', $this->parentData())->assertForbidden();
        $this->putJson('/menus/'.$leaf->id, $this->leafData($parent))->assertForbidden();
        $this->deleteJson('/menus/'.$leaf->id)->assertForbidden();
        $this->assertModelExists($leaf);
    }

    public function test_postgres_sidebar_and_routes_require_permission_plus_enabled_company_module(): void
    {
        $service = app(MenuItemService::class);
        $parent = $service->save(new MenuItem, $this->parentData(), $this->admin());
        $service->save(new MenuItem, $this->leafData($parent, 'users.index', 0), $this->admin());
        $service->save(new MenuItem, $this->leafData($parent, 'branches.index', 1), $this->admin());
        $user = $this->companyUser(['access.users.manage']);
        $role = Role::create(['name' => 'Verificación temporal de sucursales', 'company_id' => $user->company_id]);
        $role->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));
        $user->roles()->attach($role);

        $this->assertSame(['users.index', 'branches.index'], $this->fixtureRoutes($user->fresh(), $parent));
        $this->actingAs($user->fresh())->get('/usuarios')->assertOk();
        $this->get('/sucursales')->assertOk();

        $user->company->modules()->detach(Module::where('code', 'access')->value('id'));
        $this->assertSame(['branches.index'], $this->fixtureRoutes($user->fresh(), $parent));
        $this->actingAs($user->fresh())->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);

        $user->company->modules()->detach();
        $this->assertSame([], $this->fixtureRoutes($user->fresh(), $parent));
        $this->actingAs($user->fresh())->getJson('/sucursales')->assertForbidden();
        $this->assertDatabaseHas('permission_user', ['user_id' => $user->id, 'permission_id' => Permission::where('code', 'access.users.manage')->value('id')]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => Permission::where('code', 'organization.branches.manage')->value('id')]);
    }

    public function test_postgres_menu_relations_have_id_foreign_key_constraints(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('menu_items'))
            ->mapWithKeys(fn (array $key) => [$key['columns'][0] => $key['foreign_table'].'.'.$key['foreign_columns'][0]])->sortKeys()->all();

        $this->assertSame(['module_id' => 'modules.id', 'parent_id' => 'menu_items.id', 'permission_id' => 'permissions.id', 'status_id' => 'statuses.id'], $foreignKeys);
    }
}
