<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MenuItem;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use App\Services\Navigation\NavigationService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function foundation(): User
    {
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Test.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('email', 'admin@buko.local')->firstOrFail();
    }

    private function statusFor(string $type, bool $active = true): int
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))->where('is_active', $active)->firstOrFail()->id;
    }

    private function member(array $abilities = []): User
    {
        $company = Company::create(['name' => 'Administración Salto Ángel', 'tax_id' => 'J-45781203-6', 'status_id' => $this->statusFor('companies')]);
        $company->modules()->sync(Module::pluck('id'));
        $user = User::factory()->create(['company_id' => $company->id, 'status_id' => $this->statusFor('users')]);
        $user->permissions()->sync(Permission::whereIn('code', $abilities)->pluck('id'));

        return $user;
    }

    private function parentData(string $name = 'Operaciones'): array
    {
        return ['name' => $name, 'is_parent' => true, 'icon' => 'briefcase', 'status_id' => $this->statusFor('menus'), 'sort_order' => 12];
    }

    private function leafData(string $route = 'branches.index'): array
    {
        return ['name' => 'Sedes', 'is_parent' => false, 'icon' => 'building-community', 'status_id' => $this->statusFor('menus'), 'sort_order' => 2, 'parent_id' => MenuItem::where('name', 'Organización')->firstOrFail()->id, 'route_name' => $route];
    }

    private function visibleRoutes(User $user): array
    {
        return app(NavigationService::class)->forUser($user, Request::create('/inicio'))
            ->flatMap(fn (MenuItem $parent) => $parent->children->pluck('route_name'))->values()->all();
    }

    public function test_guests_and_company_users_cannot_manage_global_menus(): void
    {
        $this->foundation();
        $this->getJson('/menus')->assertUnauthorized()->assertJsonPath('success', false);
        $user = $this->member(['access.users.manage']);

        $this->actingAs($user)->postJson('/menus', $this->parentData())->assertForbidden()->assertJsonPath('success', false);

        $this->assertDatabaseMissing('menu_items', ['name' => 'Operaciones']);
    }

    public function test_super_admin_creates_parents_and_leaves_with_derived_permission_and_module_ids(): void
    {
        $admin = $this->foundation();
        $this->actingAs($admin)->postJson('/menus', $this->parentData())->assertOk()
            ->assertJsonPath('success', true)->assertJsonStructure(['success', 'message', 'data' => ['redirect'], 'errors', 'meta']);
        $parent = MenuItem::where('name', 'Operaciones')->firstOrFail();
        $data = $this->leafData();
        $data['parent_id'] = $parent->id;

        $this->postJson('/menus', $data)->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('menu_items', ['name' => 'Operaciones', 'is_parent' => true, 'parent_id' => null, 'route_name' => null, 'permission_id' => null, 'module_id' => null]);
        $this->assertDatabaseHas('menu_items', ['name' => 'Sedes', 'parent_id' => $parent->id, 'route_name' => 'branches.index', 'permission_id' => Permission::where('code', 'organization.branches.manage')->value('id'), 'module_id' => Module::where('code', 'organization')->value('id')]);
    }

    public function test_global_role_with_menu_permission_can_manage_menus(): void
    {
        $this->foundation();
        $user = User::factory()->create(['company_id' => null, 'status_id' => $this->statusFor('users')]);
        $role = Role::create(['name' => 'Configuración de navegación', 'company_id' => null]);
        $role->permissions()->attach(Permission::where('code', 'system.menus.manage')->value('id'));
        $user->roles()->attach($role);

        $this->actingAs($user)->postJson('/menus', $this->parentData())->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('menu_items', ['name' => 'Operaciones']);
    }

    public function test_unregistered_actions_and_icon_markup_are_rejected_with_the_shared_contract(): void
    {
        $admin = $this->foundation();
        $data = $this->leafData();
        $data['route_name'] = 'https://example.test/execute';
        $data['icon'] = '<svg onload=alert(1)>';

        $this->actingAs($admin)->postJson('/menus', $data)->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.route_name.0', 'Selecciona una acción registrada del sistema.')
            ->assertJsonPath('errors.icon.0', 'Selecciona uno de los iconos disponibles.');

        $this->assertDatabaseMissing('menu_items', ['name' => 'Sedes']);
    }

    public function test_client_cannot_override_permission_or_module_ids(): void
    {
        $admin = $this->foundation();
        $data = $this->leafData();
        $data['permission_id'] = Permission::where('code', 'system.menus.manage')->value('id');
        $data['module_id'] = Module::where('code', 'access')->value('id');

        $this->actingAs($admin)->postJson('/menus', $data)->assertUnprocessable()->assertJsonValidationErrors(['permission_id', 'module_id']);

        $this->assertDatabaseMissing('menu_items', ['name' => 'Sedes']);
    }

    public function test_leaf_requires_a_root_parent_and_a_status_from_the_menu_context(): void
    {
        $admin = $this->foundation();
        $data = $this->leafData();
        $data['parent_id'] = MenuItem::where('route_name', 'users.index')->firstOrFail()->id;
        $data['status_id'] = $this->statusFor('users');

        $this->actingAs($admin)->postJson('/menus', $data)->assertUnprocessable()->assertJsonValidationErrors(['parent_id', 'status_id']);

        $this->assertDatabaseMissing('menu_items', ['name' => 'Sedes']);
    }

    #[TestWith([-1, 'El orden no puede ser negativo.'])]
    #[TestWith([10000, 'El orden no puede superar 9999.'])]
    #[TestWith(['orden', 'Escribe un número entero para el orden.'])]
    public function test_invalid_sort_order_has_a_numeric_validation_message(int|string $order, string $message): void
    {
        $admin = $this->foundation();
        $data = $this->parentData();
        $data['sort_order'] = $order;

        $this->actingAs($admin)->postJson('/menus', $data)->assertUnprocessable()->assertJsonPath('success', false)
            ->assertJsonPath('errors.sort_order.0', $message);

        $this->assertDatabaseMissing('menu_items', ['name' => $data['name']]);
    }

    public function test_parent_with_children_cannot_be_converted_or_deleted(): void
    {
        $admin = $this->foundation();
        $parent = MenuItem::where('name', 'Accesos')->firstOrFail();

        $this->actingAs($admin)->putJson('/menus/'.$parent->id, $this->leafData())->assertUnprocessable()->assertJsonValidationErrors('is_parent');
        $this->deleteJson('/menus/'.$parent->id)->assertUnprocessable()->assertJsonValidationErrors('menu');

        $this->assertModelExists($parent);
        $this->assertTrue($parent->fresh()->is_parent);
    }

    public function test_leaf_can_be_updated_and_deleted_without_changing_route_authorization(): void
    {
        $admin = $this->foundation();
        $leaf = MenuItem::where('route_name', 'branches.index')->firstOrFail();
        $data = $this->leafData();
        $data['name'] = 'Sucursales de la organización';

        $this->actingAs($admin)->putJson('/menus/'.$leaf->id, $data)->assertOk();
        $this->assertDatabaseHas('menu_items', ['id' => $leaf->id, 'name' => 'Sucursales de la organización']);
        $this->deleteJson('/menus/'.$leaf->id)->assertOk();
        $this->assertSoftDeleted($leaf);
        $member = $this->member();
        $this->actingAs($member)->getJson('/sucursales')->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_sidebar_requires_both_permission_and_enabled_company_module_and_hides_empty_parents(): void
    {
        $this->foundation();
        $user = $this->member(['organization.branches.manage']);
        $this->assertSame(['dashboard', 'branches.index'], $this->visibleRoutes($user));

        $user->company->modules()->detach();

        $this->assertSame(['dashboard'], $this->visibleRoutes($user->fresh()));
        $parents = app(NavigationService::class)->forUser($user->fresh(), Request::create('/inicio'));
        $this->assertSame(['General'], $parents->pluck('name')->all());
    }

    public function test_inactive_parent_and_inactive_leaf_are_not_in_the_sidebar(): void
    {
        $admin = $this->foundation();
        MenuItem::where('name', 'Accesos')->update(['status_id' => $this->statusFor('menus', false)]);
        MenuItem::where('route_name', 'branches.index')->update(['status_id' => $this->statusFor('menus', false)]);

        $routes = $this->visibleRoutes($admin);

        $this->assertNotContains('users.index', $routes);
        $this->assertNotContains('roles.index', $routes);
        $this->assertNotContains('branches.index', $routes);
        $this->assertContains('companies.index', $routes);
    }

    public function test_corrupt_menu_metadata_cannot_display_an_action_with_an_unrelated_permission(): void
    {
        $admin = $this->foundation();
        MenuItem::where('route_name', 'branches.index')->update(['permission_id' => Permission::where('code', 'system.menus.manage')->value('id')]);

        $this->assertNotContains('branches.index', $this->visibleRoutes($admin));
    }

    public function test_sidebar_has_a_permission_filtered_fallback_before_the_incremental_migration(): void
    {
        $this->withoutVite();
        $admin = $this->foundation();
        $user = $this->member(['access.users.manage']);
        Schema::drop('audit_entries');
        MenuItem::whereNotNull('parent_id')->forceDelete();
        MenuItem::query()->forceDelete();
        Schema::drop('menu_items');

        $this->assertSame(['dashboard', 'users.index'], $this->visibleRoutes($user));
        $this->actingAs($admin)->get('/menus')->assertOk()->assertViewHas('pendingMigration', true);
        $this->postJson('/menus', $this->parentData())->assertStatus(503)->assertJsonPath('success', false);
    }

    public function test_menu_forms_are_embedded_in_the_listing_and_routes_redirect_to_the_local_modal(): void
    {
        $this->withoutVite();
        $admin = $this->foundation();
        $record = MenuItem::where('route_name', 'branches.index')->firstOrFail();
        $record->update(['name' => '<script>alert("menu")</script>']);

        $this->actingAs($admin)->getJson('/menus/create')->assertRedirect(route('menus.index', ['nuevo' => 1]));
        $this->getJson('/menus/'.$record->id.'/edit')->assertRedirect(route('menus.index', ['editar' => $record->id]));
        $this->get('/menus/'.$record->id.'/edit')->assertRedirect(route('menus.index', ['editar' => $record->id]));

        $this->get('/menus')->assertViewHas('pendingMigration', false)
            ->assertSee('id="create-menu"', false)->assertSee('data-dialog-template="edit-menu-'.$record->id.'"', false)
            ->assertSee('id="delete-menu-'.$record->id.'"', false)->assertSee('name="route_name"', false)
            ->assertSee('action="'.route('menus.update', $record).'"', false)
            ->assertSee('action="'.route('menus.destroy', $record).'"', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)
            ->assertDontSee('data-remote-dialog', false)->assertDontSee('data-record=', false);
        $this->get('/menus?editar='.$record->id)->assertViewHas('openModal', true)
            ->assertSee('id="edit-menu-'.$record->id.'"', false)->assertDontSee('<script>alert', false);
    }
}
