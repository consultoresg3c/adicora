<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function catalogs(): void
    {
        config(['buko.initial_admin.password' => null]);
        $this->seed(FoundationSeeder::class);
    }

    private function statusFor(string $type, bool $active = true): Status
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))
            ->where('is_active', $active)->firstOrFail();
    }

    private function company(string $taxId = 'J-40571623-8'): Company
    {
        $company = Company::create([
            'name' => 'Servicios de Occidente',
            'tax_id' => $taxId,
            'status_id' => $this->statusFor('companies')->id,
        ]);
        $company->modules()->sync(Module::pluck('id'));

        return $company;
    }

    private function member(Company $company, array $abilities = []): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'status_id' => $this->statusFor('users')->id,
            'password' => 'Sesion.Buko.2026!',
        ]);
        $user->permissions()->sync(Permission::whereIn('code', $abilities)->pluck('id'));

        return $user;
    }

    public function test_login_rotates_session_and_logout_invalidates_its_data(): void
    {
        $this->catalogs();
        $user = $this->member($this->company());
        $this->withSession(['private_marker' => 'session-value']);
        $oldSessionId = session()->getId();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'Sesion.Buko.2026!'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $authenticatedSessionId = session()->getId();

        $this->postJson('/logout')->assertOk()->assertSessionMissing('private_marker');

        $this->assertGuest();
        $this->assertNotSame($authenticatedSessionId, session()->getId());
        $this->getJson('/inicio')->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_role_permissions_allow_own_company_but_never_show_another_company_users(): void
    {
        $this->catalogs();
        $company = $this->company();
        $manager = $this->member($company);
        $colleague = $this->member($company);
        $outsider = $this->member($this->company('J-40571624-6'));
        $role = Role::create(['name' => 'Gestión de usuarios', 'company_id' => $company->id]);
        $role->permissions()->attach(Permission::where('code', 'access.users.manage')->value('id'));
        $manager->roles()->attach($role);

        $this->actingAs($manager)->get('/usuarios')
            ->assertOk()->assertSee($colleague->email)->assertDontSee($outsider->email);
    }

    public function test_company_user_cannot_write_foreign_users_or_roles_and_receives_404(): void
    {
        $this->catalogs();
        $manager = $this->member($this->company(), ['access.users.manage', 'access.roles.manage']);
        $otherCompany = $this->company('J-40571624-6');
        $target = $this->member($otherCompany);
        $role = Role::create(['name' => 'Administración de otra empresa', 'company_id' => $otherCompany->id]);

        $this->actingAs($manager)->putJson('/usuarios/'.$target->id, [
            'name' => 'Cambio indebido',
            'email' => $target->email,
            'status_id' => $target->status_id,
        ])->assertNotFound()->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);

        $this->putJson('/roles/'.$role->id, ['name' => 'Cambio indebido'])
            ->assertNotFound()->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => $target->name]);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Administración de otra empresa']);
    }

    public function test_company_manager_cannot_assign_a_more_privileged_role_and_receives_422(): void
    {
        $this->catalogs();
        $company = $this->company();
        $manager = $this->member($company, ['access.users.manage']);
        $target = $this->member($company);
        $role = Role::create(['name' => 'Administrar permisos', 'company_id' => $company->id]);
        $role->permissions()->attach(Permission::where('code', 'access.roles.manage')->value('id'));

        $this->actingAs($manager)->putJson('/usuarios/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'status_id' => $target->status_id,
            'role_ids' => [$role->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('role_ids')
            ->assertJsonPath('errors.role_ids.0', 'El rol debe pertenecer a la empresa y solo conceder permisos que puedas delegar.');

        $this->assertDatabaseMissing('role_user', ['user_id' => $target->id, 'role_id' => $role->id]);
    }

    public function test_company_manager_cannot_reset_password_of_a_role_privileged_account_and_receives_403(): void
    {
        $this->catalogs();
        $company = $this->company();
        $manager = $this->member($company, ['access.users.manage']);
        $target = $this->member($company);
        $originalPassword = $target->password;
        $role = Role::create(['name' => 'Responsable de acceso', 'company_id' => $company->id]);
        $role->permissions()->attach(Permission::where('code', 'access.roles.manage')->value('id'));
        $target->roles()->attach($role);

        $this->actingAs($manager)->putJson('/usuarios/'.$target->id, [
            'name' => $target->name,
            'email' => $target->email,
            'status_id' => $target->status_id,
            'password' => 'No.Puede.Cambiar.2026!',
            'password_confirmation' => 'No.Puede.Cambiar.2026!',
        ])->assertForbidden()->assertJsonPath('success', false);

        $this->assertSame($originalPassword, $target->fresh()->password);
        $this->assertDatabaseHas('role_user', ['user_id' => $target->id, 'role_id' => $role->id]);
    }

    public function test_role_manager_cannot_change_a_role_with_permissions_they_do_not_have_and_receives_403(): void
    {
        $this->catalogs();
        $company = $this->company();
        $manager = $this->member($company, ['access.roles.manage']);
        $role = Role::create(['name' => 'Gestión de cuentas', 'company_id' => $company->id]);
        $permissionId = Permission::where('code', 'access.users.manage')->value('id');
        $role->permissions()->attach($permissionId);

        $this->actingAs($manager)->putJson('/roles/'.$role->id, [
            'name' => 'Intento de reemplazo',
            'permission_ids' => [],
        ])->assertForbidden()->assertJsonPath('success', false);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Gestión de cuentas']);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $permissionId]);
    }

    public function test_disabled_user_loses_session_and_private_session_data_and_receives_403(): void
    {
        $this->catalogs();
        $user = $this->member($this->company());
        $user->update(['status_id' => $this->statusFor('users', false)->id]);

        $this->actingAs($user)->withSession(['private_marker' => 'sensitive-value'])
            ->getJson('/inicio')->assertForbidden()->assertSessionMissing('private_marker')
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);

        $this->assertGuest();
    }

    public function test_a_disabled_module_only_removes_access_for_its_company_and_receives_403(): void
    {
        $this->catalogs();
        $disabledCompany = $this->company();
        $enabledCompany = $this->company('J-40571624-6');
        $disabledUser = $this->member($disabledCompany, ['access.users.manage']);
        $enabledUser = $this->member($enabledCompany, ['access.users.manage']);
        $disabledCompany->modules()->detach(Module::where('code', 'access')->value('id'));

        $this->actingAs($disabledUser)->getJson('/usuarios')
            ->assertForbidden()->assertJsonPath('success', false);

        $this->actingAs($enabledUser)->get('/usuarios')->assertOk()->assertSee($enabledUser->email);
    }

    public function test_enabled_company_modules_never_grant_permissions_to_another_user(): void
    {
        $this->catalogs();
        $company = $this->company();
        $manager = $this->member($company, ['access.users.manage', 'organization.branches.manage']);
        $unprivileged = $this->member($company);

        $this->actingAs($manager)->get('/usuarios')->assertOk();
        $this->get('/sucursales')->assertOk();

        $this->actingAs($unprivileged)->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);
        $this->getJson('/sucursales')->assertForbidden()->assertJsonPath('success', false);
        $this->get('/inicio')->assertOk()
            ->assertDontSee('href="'.route('users.index').'"', false)
            ->assertDontSee('href="'.route('branches.index').'"', false);
    }

    public function test_revoked_direct_permission_is_rejected_on_the_next_authenticated_request(): void
    {
        $this->catalogs();
        $user = $this->member($this->company(), ['access.users.manage']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Sesion.Buko.2026!'])->assertOk();
        $this->get('/usuarios')->assertOk();

        $user->permissions()->detach();
        Auth::forgetGuards();

        $this->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);
        $this->assertAuthenticatedAs($user);
        $this->get('/inicio')->assertOk()->assertDontSee('href="'.route('users.index').'"', false);
    }

    public function test_revoked_role_permission_is_rejected_on_the_next_authenticated_request(): void
    {
        $this->catalogs();
        $company = $this->company();
        $user = $this->member($company);
        $role = Role::create(['name' => 'Gestión de cuentas', 'company_id' => $company->id]);
        $role->permissions()->attach(Permission::where('code', 'access.users.manage')->value('id'));
        $user->roles()->attach($role);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Sesion.Buko.2026!'])->assertOk();
        $this->get('/usuarios')->assertOk();

        $role->permissions()->detach();
        Auth::forgetGuards();

        $this->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);
        $this->assertAuthenticatedAs($user);
        $this->get('/inicio')->assertOk()->assertDontSee('href="'.route('users.index').'"', false);
    }

    public function test_detached_role_is_rejected_on_the_next_authenticated_request(): void
    {
        $this->catalogs();
        $company = $this->company();
        $user = $this->member($company);
        $role = Role::create(['name' => 'Gestión de cuentas', 'company_id' => $company->id]);
        $role->permissions()->attach(Permission::where('code', 'access.users.manage')->value('id'));
        $user->roles()->attach($role);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Sesion.Buko.2026!'])->assertOk();
        $this->get('/usuarios')->assertOk();

        $user->roles()->detach();
        Auth::forgetGuards();

        $this->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);
        $this->get('/inicio')->assertOk()->assertDontSee('href="'.route('users.index').'"', false);
    }

    public function test_revoked_company_module_blocks_existing_direct_and_role_grants_on_the_next_request(): void
    {
        $this->catalogs();
        $company = $this->company();
        $user = $this->member($company, ['access.users.manage']);
        $role = Role::create(['name' => 'Gestión de sucursales', 'company_id' => $company->id]);
        $role->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));
        $user->roles()->attach($role);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Sesion.Buko.2026!'])->assertOk();
        $this->get('/usuarios')->assertOk();
        $this->get('/sucursales')->assertOk();

        $company->modules()->detach();
        Auth::forgetGuards();

        $this->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);
        $this->getJson('/sucursales')->assertForbidden()->assertJsonPath('success', false);
        $this->get('/inicio')->assertOk()
            ->assertDontSee('href="'.route('users.index').'"', false)
            ->assertDontSee('href="'.route('branches.index').'"', false);
    }

    public function test_company_deactivation_terminates_an_existing_session_on_its_next_request(): void
    {
        $this->catalogs();
        $company = $this->company();
        $user = $this->member($company, ['access.users.manage']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Sesion.Buko.2026!'])->assertOk();
        $this->get('/usuarios')->assertOk();

        $company->update(['status_id' => $this->statusFor('companies', false)->id]);
        Auth::forgetGuards();

        $this->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);
        $this->assertGuest();
        $this->getJson('/inicio')->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_system_permissions_attached_to_a_company_user_never_open_global_routes(): void
    {
        $this->catalogs();
        $user = $this->member($this->company(), ['system.companies.manage', 'system.statuses.manage', 'system.menus.manage']);

        $this->actingAs($user)->getJson('/empresas')->assertForbidden()->assertJsonPath('success', false);
        $this->getJson('/estados')->assertForbidden()->assertJsonPath('success', false);
        $this->getJson('/menus')->assertForbidden()->assertJsonPath('success', false);
        $this->get('/inicio')->assertOk()
            ->assertDontSee('href="'.route('companies.index').'"', false)
            ->assertDontSee('href="'.route('statuses.index').'"', false)
            ->assertDontSee('href="'.route('menus.index').'"', false);
    }
}
