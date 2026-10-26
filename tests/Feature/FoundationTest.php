<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\StatusType;
use App\Models\User;
use App\Services\Access\AccessService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function foundation(): User
    {
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Test.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('email', 'admin@buko.local')->firstOrFail();
    }

    private function statusFor(string $type, bool $active = true): Status
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))->where('is_active', $active)->firstOrFail();
    }

    private function company(string $taxId = 'J-45781203-6'): Company
    {
        $company = Company::create(['name' => 'Inversiones Ávila', 'tax_id' => $taxId, 'status_id' => $this->statusFor('companies')->id]);
        $company->modules()->sync(Module::pluck('id'));

        return $company;
    }

    private function member(Company $company, array $abilities = []): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'status_id' => $this->statusFor('users')->id]);
        $user->permissions()->sync(Permission::whereIn('code', $abilities)->pluck('id'));

        return $user;
    }

    public function test_login_accepts_active_account_and_returns_the_shared_contract(): void
    {
        $admin = $this->foundation();

        $this->postJson('/login', ['email' => ' ADMIN@BUKO.LOCAL ', 'password' => 'Buko.Test.2026!'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['success', 'message', 'data' => ['redirect'], 'errors', 'meta']);
        $this->assertAuthenticatedAs($admin);
        $this->postJson('/logout')->assertOk()->assertJsonPath('success', true);
        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_disclose_account_existence(): void
    {
        $this->foundation();
        foreach (['admin@buko.local', 'unknown@buko.local'] as $email) {
            $this->postJson('/login', ['email' => $email, 'password' => 'incorrecta'])
                ->assertUnprocessable()->assertJsonPath('success', false)
                ->assertJsonPath('errors.email.0', 'No pudimos iniciar sesión con esos datos.');
        }
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $this->foundation();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => 'admin@buko.local', 'password' => 'incorrecta'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'admin@buko.local', 'password' => 'Buko.Test.2026!'])->assertStatus(429)->assertJsonPath('success', false);
    }

    public function test_missing_login_fields_return_spanish_validation_errors(): void
    {
        $this->postJson('/login', [])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Escribe tu correo electrónico.')
            ->assertJsonPath('errors.password.0', 'Escribe tu contraseña.');
    }

    public function test_guests_and_unprivileged_users_cannot_manage_records(): void
    {
        $this->foundation();
        $this->getJson('/empresas')->assertUnauthorized()->assertJsonPath('success', false);
        $this->get('/empresas')->assertRedirect(route('login'));
        $member = $this->member($this->company());
        $this->actingAs($member)->getJson('/usuarios')->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_company_can_be_created_with_module_ids_and_updated(): void
    {
        $admin = $this->foundation();
        $moduleId = Module::where('code', 'organization')->value('id');
        $data = ['name' => 'Servicios Orinoco', 'tax_id' => 'J-30457126-9', 'status_id' => $this->statusFor('companies')->id, 'module_ids' => [$moduleId]];
        $this->actingAs($admin)->postJson('/empresas', $data)->assertOk()->assertJsonPath('success', true);
        $company = Company::where('tax_id', 'J-30457126-9')->firstOrFail();
        $this->assertDatabaseHas('company_module', ['company_id' => $company->id, 'module_id' => $moduleId]);
        $data['name'] = 'Servicios Orinoco C.A.';
        $data['module_ids'] = [];
        $this->putJson('/empresas/'.$company->id, $data)->assertOk();
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Servicios Orinoco C.A.']);
        $this->assertDatabaseMissing('company_module', ['company_id' => $company->id]);
    }

    public function test_company_admin_cannot_access_another_tenant_or_spoof_company_id(): void
    {
        $this->foundation();
        $own = $this->company();
        $other = $this->company('J-44896352-0');
        $member = $this->member($own, ['organization.branches.manage', 'access.users.manage', 'access.roles.manage']);
        $branch = Branch::create(['name' => 'Sucursal ajena', 'company_id' => $other->id, 'status_id' => $this->statusFor('branches')->id]);
        $otherUser = $this->member($other);
        $otherRole = Role::create(['company_id' => $other->id, 'name' => 'Administración ajena']);

        $this->actingAs($member)->get('/sucursales/'.$branch->id.'/edit')->assertNotFound();
        $this->putJson('/sucursales/'.$branch->id, [])->assertNotFound();
        $this->get('/usuarios/'.$otherUser->id.'/edit')->assertNotFound();
        $this->get('/roles/'.$otherRole->id.'/edit')->assertNotFound();
        $this->get('/sucursales')->assertOk()->assertDontSee('Sucursal ajena');
        $this->postJson('/sucursales', ['name' => 'Intento', 'company_id' => $other->id, 'status_id' => $this->statusFor('branches')->id])->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $this->assertDatabaseMissing('branches', ['name' => 'Intento']);
    }

    public function test_branch_uses_actor_company_when_company_id_is_omitted(): void
    {
        $this->foundation();
        $company = $this->company();
        $member = $this->member($company, ['organization.branches.manage']);
        $this->actingAs($member)->postJson('/sucursales', ['name' => 'Los Palos Grandes', 'status_id' => $this->statusFor('branches')->id])->assertOk();
        $this->assertDatabaseHas('branches', ['company_id' => $company->id, 'name' => 'Los Palos Grandes']);
    }

    public function test_statuses_from_another_context_are_rejected(): void
    {
        $admin = $this->foundation();
        $this->actingAs($admin)->postJson('/empresas', ['name' => 'Empresa', 'tax_id' => 'J-41123456-8', 'status_id' => $this->statusFor('users')->id])
            ->assertUnprocessable()->assertJsonValidationErrors('status_id');
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_direct_and_role_permissions_require_an_enabled_company_module(): void
    {
        $this->foundation();
        $company = $this->company();
        $member = $this->member($company, ['access.users.manage']);
        $role = Role::create(['name' => 'Responsable de sedes', 'company_id' => $company->id]);
        $role->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));
        $member->roles()->attach($role);
        $access = app(AccessService::class);

        $this->assertTrue($access->allows($member, 'access.users.manage'));
        $this->assertTrue($access->allows($member, 'organization.branches.manage'));
        $this->assertFalse($access->allows($member, 'system.companies.manage'));
        $company->modules()->detach();
        $this->assertFalse($access->allows($member->fresh(), 'access.users.manage'));
        $this->assertFalse($access->allows($member->fresh(), 'organization.branches.manage'));
        $this->actingAs($member->fresh())->getJson('/usuarios')->assertForbidden();
    }

    public function test_foreign_company_roles_do_not_grant_permissions_even_if_attached(): void
    {
        $this->foundation();
        $member = $this->member($this->company());
        $role = Role::create(['name' => 'Rol externo', 'company_id' => $this->company('J-49273156-2')->id]);
        $role->permissions()->attach(Permission::where('code', 'access.users.manage')->value('id'));
        $member->roles()->attach($role);

        $this->assertFalse(app(AccessService::class)->allows($member, 'access.users.manage'));
    }

    public function test_company_admin_cannot_delegate_permissions_they_do_not_have(): void
    {
        $this->foundation();
        $company = $this->company();
        $member = $this->member($company, ['access.roles.manage']);
        $this->actingAs($member)->postJson('/roles', ['name' => 'Escalamiento', 'permission_ids' => [Permission::where('code', 'access.users.manage')->value('id')]])
            ->assertUnprocessable()->assertJsonValidationErrors('permission_ids');
        $this->assertDatabaseMissing('roles', ['name' => 'Escalamiento']);
    }

    public function test_user_creation_hashes_password_and_assigns_own_company_roles(): void
    {
        $admin = $this->foundation();
        $company = $this->company();
        $permissionId = Permission::where('code', 'access.users.manage')->value('id');
        $this->actingAs($admin)->postJson('/roles', ['name' => 'Administrador de empresa', 'company_id' => $company->id, 'permission_ids' => [$permissionId]])->assertOk();
        $role = Role::where('name', 'Administrador de empresa')->firstOrFail();
        $data = ['name' => 'Mariana Rojas', 'email' => 'mariana@avila.test', 'password' => 'Mariana.Test.2026!', 'password_confirmation' => 'Mariana.Test.2026!', 'company_id' => $company->id, 'status_id' => $this->statusFor('users')->id, 'role_ids' => [$role->id]];
        $this->postJson('/usuarios', $data)->assertOk();
        $user = User::where('email', 'mariana@avila.test')->firstOrFail();
        $this->assertTrue(Hash::check('Mariana.Test.2026!', $user->password));
        $this->assertDatabaseHas('role_user', ['role_id' => $role->id, 'user_id' => $user->id]);
        $this->assertFalse($user->is_super_admin);
        $this->assertTrue(app(AccessService::class)->allows($user, 'access.users.manage'));
        $data['password'] = null;
        unset($data['password_confirmation']);
        $data['name'] = 'Mariana Rojas Pérez';
        $this->putJson('/usuarios/'.$user->id, $data)->assertOk();
        $this->assertTrue(Hash::check('Mariana.Test.2026!', $user->fresh()->password));
    }

    public function test_user_cannot_be_elevated_to_super_admin_or_assigned_foreign_roles(): void
    {
        $admin = $this->foundation();
        $company = $this->company();
        $other = $this->company('J-48723164-3');
        $role = Role::create(['name' => 'Ajeno', 'company_id' => $other->id]);
        $data = ['name' => 'Valentina Díaz', 'email' => 'valentina@avila.test', 'password' => 'Valentina.Test.2026!', 'password_confirmation' => 'Valentina.Test.2026!', 'company_id' => $company->id, 'status_id' => $this->statusFor('users')->id, 'role_ids' => [$role->id]];
        $this->actingAs($admin)->postJson('/usuarios', $data)->assertUnprocessable()->assertJsonValidationErrors('role_ids');
        $data['role_ids'] = [];
        $data['is_super_admin'] = true;
        $this->postJson('/usuarios', $data)->assertUnprocessable()->assertJsonValidationErrors('is_super_admin');
        $this->assertDatabaseMissing('users', ['email' => 'valentina@avila.test']);
    }

    public function test_inactive_company_and_user_cannot_keep_or_start_a_session(): void
    {
        $this->foundation();
        $company = $this->company();
        $user = $this->member($company);
        $user->update(['password' => 'Account.Test.2026!']);
        $company->update(['status_id' => $this->statusFor('companies', false)->id]);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Account.Test.2026!'])->assertUnprocessable();
        $this->actingAs($user->fresh())->getJson('/inicio')->assertForbidden();
        $this->assertGuest();
        $company->update(['status_id' => $this->statusFor('companies')->id]);
        $user->update(['status_id' => $this->statusFor('users', false)->id]);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Account.Test.2026!'])->assertUnprocessable();
    }

    public function test_states_can_be_added_dynamically_but_cannot_change_context(): void
    {
        $admin = $this->foundation();
        $typeId = StatusType::where('code', 'companies')->value('id');
        $this->actingAs($admin)->postJson('/estados', ['name' => 'En revisión', 'status_type_id' => $typeId, 'is_active' => false])->assertOk();
        $status = Status::where('name', 'En revisión')->firstOrFail();
        $this->assertFalse($status->is_active);
        $this->putJson('/estados/'.$status->id, ['name' => 'En revisión', 'status_type_id' => StatusType::where('code', 'users')->value('id'), 'is_active' => true])->assertUnprocessable();
        $this->assertSame($typeId, $status->fresh()->status_type_id);
    }

    public function test_super_admin_and_own_account_are_protected_from_management_edits(): void
    {
        $admin = $this->foundation();
        $this->actingAs($admin)->get('/usuarios/'.$admin->id.'/edit')->assertForbidden();
        $company = $this->company();
        $member = $this->member($company, ['access.users.manage']);
        $this->actingAs($member)->get('/usuarios/'.$member->id.'/edit')->assertForbidden();
    }

    public function test_all_initial_screens_render_and_escape_user_content(): void
    {
        $admin = $this->foundation();
        $company = $this->company();
        $company->update(['name' => '<script>alert("empresa")</script>']);
        $branch = Branch::create(['name' => 'Sede Caracas', 'company_id' => $company->id, 'status_id' => $this->statusFor('branches')->id]);
        $member = $this->member($company);
        $role = Role::create(['name' => 'Operaciones', 'company_id' => $company->id]);
        $this->actingAs($admin);
        foreach (['/inicio', '/empresas', '/empresas/create', '/empresas/'.$company->id.'/edit', '/sucursales', '/sucursales?nuevo=1', '/sucursales?editar='.$branch->id, '/usuarios', '/usuarios/create?company_id='.$company->id, '/usuarios/'.$member->id.'/edit', '/roles', '/roles/create?company_id='.$company->id, '/roles/'.$role->id.'/edit', '/estados', '/estados?nuevo=1', '/estados?editar='.$this->statusFor('users')->id] as $url) {
            $this->get($url)->assertOk()->assertDontSee('<script>alert("empresa")</script>', false);
        }
        $this->get('/empresas')->assertSee('&lt;script&gt;', false);
    }

    public function test_seeder_can_be_repeated_without_resetting_admin_credentials(): void
    {
        $admin = $this->foundation();
        $statusCount = Status::count();
        $admin->update(['password' => 'Changed.Test.2026!']);
        $this->seed(FoundationSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('statuses', $statusCount);
        $this->assertSame(6, Status::whereHas('type', fn ($query) => $query->whereIn('code', ['companies', 'branches', 'users']))->count());
        $this->assertTrue(Hash::check('Changed.Test.2026!', $admin->fresh()->password));
    }

    public function test_not_found_json_uses_the_shared_error_contract(): void
    {
        $this->getJson('/ruta-inexistente')->assertNotFound()->assertJsonPath('success', false)->assertJsonStructure(['message', 'data', 'errors', 'meta']);
    }

    public function test_user_manager_cannot_take_over_an_account_with_more_permissions(): void
    {
        $this->foundation();
        $company = $this->company();
        $manager = $this->member($company, ['access.users.manage']);
        $target = $this->member($company, ['access.users.manage', 'access.roles.manage']);
        $originalPassword = $target->password;

        $this->actingAs($manager)->get('/usuarios/'.$target->id.'/edit')->assertForbidden();
        $this->putJson('/usuarios/'.$target->id, [
            'name' => $target->name, 'email' => $target->email,
            'password' => 'Takeover.Test.2026!', 'password_confirmation' => 'Takeover.Test.2026!',
            'status_id' => $target->status_id,
        ])->assertForbidden();
        $this->assertSame($originalPassword, $target->fresh()->password);
    }
}
