<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\MenuItem;
use App\Models\Module;
use App\Models\Permission;
use App\Models\ProcessStage;
use App\Models\Role;
use App\Models\Status;
use App\Models\StatusType;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Access\AccessService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RecordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->withoutVite();
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Test.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('is_super_admin', true)->firstOrFail();
    }

    private function statusId(string $type, bool $active = true): int
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))
            ->where('is_active', $active)->firstOrFail()->id;
    }

    private function company(): Company
    {
        $sequence = Company::withTrashed()->count() + 1;
        $company = Company::create(['name' => 'Empresa de prueba '.$sequence, 'tax_id' => 'J-0000000-'.$sequence, 'status_id' => $this->statusId('companies')]);
        $company->modules()->sync(Module::pluck('id'));

        return $company;
    }

    private function member(Company $company, array $abilities = []): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'status_id' => $this->statusId('users')]);
        $user->permissions()->sync(Permission::whereIn('code', $abilities)->pluck('id'));

        return $user;
    }

    private function record(string $resource, ?Company $company = null): Model
    {
        return match ($resource) {
            'empresas' => $company ?? $this->company(),
            'sucursales' => Branch::create(['company_id' => ($company ?? $this->company())->id, 'name' => 'Sucursal para eliminar', 'status_id' => $this->statusId('branches')]),
            'usuarios' => $this->member($company ?? $this->company()),
            'roles' => Role::create(['company_id' => ($company ?? $this->company())->id, 'name' => 'Rol para eliminar']),
            'estados' => Status::create(['status_type_id' => StatusType::where('code', 'companies')->value('id'), 'name' => 'Estado sin referencias', 'is_active' => true]),
            'menus' => MenuItem::create(['name' => 'Menú sin hijos', 'is_parent' => true, 'icon' => 'briefcase', 'status_id' => $this->statusId('menus'), 'sort_order' => 90]),
            'proveedores' => Supplier::create(['company_id' => ($company ?? $this->company())->id, 'name' => 'Proveedor para eliminar', 'status_id' => $this->statusId('suppliers')]),
            'etapas-procesos' => ProcessStage::create(['name' => 'Etapa para eliminar', 'status_id' => $this->statusId('process_stages')]),
        };
    }

    private function userData(Company $company): array
    {
        return ['company_id' => $company->id, 'name' => 'Cuenta auditada', 'email' => 'auditada@example.test', 'password' => 'Original.Pass.2026!', 'password_confirmation' => 'Original.Pass.2026!', 'status_id' => $this->statusId('users'), 'role_ids' => [], 'permission_ids' => []];
    }

    public function test_role_options_are_inside_edit_and_print_only_saved_permissions_with_escaped_names(): void
    {
        $admin = $this->admin();
        $role = $this->record('roles');
        $payload = '<img src=x onerror=alert("rol")>';
        $role->update(['name' => $payload]);
        $permission = Permission::where('code', 'access.users.manage')->firstOrFail();
        $role->permissions()->attach($permission);

        $this->actingAs($admin)->get('/roles')->assertOk()
            ->assertDontSee('data-dialog-template="delete-role-', false);
        $this->get('/roles/create')->assertOk()->assertDontSee('data-role-options', false);
        $response = $this->get('/roles/'.$role->id.'/edit')->assertOk()
            ->assertSee('aria-label="Opciones del rol"', false)
            ->assertSee('data-dialog-template="delete-role-'.$role->id.'"', false)
            ->assertSee('action="'.route('roles.destroy', $role).'"', false)
            ->assertSee('Imprimir detalle')->assertSee('Eliminar rol')
            ->assertDontSee($payload, false);

        preg_match('/<template id="print-role-'.$role->id.'">(.*?)<\/template>/s', $response->getContent(), $matches);
        $this->assertStringContainsString(e($payload), $matches[1]);
        $this->assertStringContainsString(e($permission->name), $matches[1]);
        $this->assertStringNotContainsString(e(Permission::where('code', 'access.roles.manage')->value('name')), $matches[1]);
    }

    #[TestWith(['empresas', 'company'])]
    #[TestWith(['sucursales', 'branch'])]
    #[TestWith(['usuarios', 'user'])]
    #[TestWith(['roles', 'role'])]
    #[TestWith(['estados', 'status'])]
    #[TestWith(['menus', 'menu'])]
    #[TestWith(['proveedores', 'supplier'])]
    #[TestWith(['etapas-procesos', 'processStage'])]
    public function test_options_are_in_edit_forms_and_print_escaped_saved_values(string $resource, string $key): void
    {
        $admin = $this->admin();
        $record = $this->record($resource);
        $payload = '<img src=x onerror=alert("registro")>';
        $record->update(['name' => $payload]);

        $listing = $this->actingAs($admin)->get('/'.$resource)->assertOk();
        preg_match('/<table\b.*?<\/table>/s', $listing->getContent(), $table);
        $this->assertStringNotContainsString('name="trash"', $table[0]);
        $this->assertStringNotContainsString('data-dialog-template="delete-', $table[0]);
        $this->assertStringNotContainsString('list-check', $table[0]);
        $this->assertStringNotContainsString('/historial', $table[0]);
        $this->assertStringNotContainsString('actions-column', $table[0]);

        $response = $this->withSession(['_old_input' => ['name' => 'Cambio pendiente']])
            ->followingRedirects()->get('/'.$resource.'/'.$record->id.'/edit')->assertOk()
            ->assertSee('data-record-options', false)
            ->assertSee('data-record-print="print-'.$key.'-'.$record->id.'"', false)
            ->assertSee('data-dialog-target="record-confirm-dialog"', false)
            ->assertDontSee($payload, false);

        preg_match_all('/<details[^>]*data-record-options[^>]*>(.*?)<\/details>/s', $response->getContent(), $options);
        $recordOptions = collect($options[1])->first(fn (string $html) => str_contains($html, 'data-record-print="print-'.$key.'-'.$record->id.'"'));
        $this->assertNotNull($recordOptions);
        $this->assertStringContainsString('href="'.route('records.history', ['resource' => $resource, 'record' => $record->id]).'">Ver historial</a>', $recordOptions);

        preg_match('/<template id="print-'.$key.'-'.$record->id.'">(.*?)<\/template>/s', $response->getContent(), $print);
        $this->assertStringContainsString(e($payload), $print[1]);
        $this->assertStringNotContainsString('Cambio pendiente', $print[1]);
        $this->assertStringNotContainsString('<input', $print[1]);
        if ($record instanceof User) {
            $this->assertStringNotContainsString($record->password, $print[1]);
        }
    }

    public function test_own_account_cannot_open_record_options_or_delete_itself(): void
    {
        $this->admin();
        $actor = $this->member($this->company(), ['access.users.manage']);

        $this->actingAs($actor)->get('/usuarios')->assertOk()
            ->assertDontSee('href="'.route('users.edit', $actor).'"', false);
        $this->get('/usuarios/'.$actor->id.'/edit')->assertForbidden();
        $this->deleteJson('/usuarios/'.$actor->id)->assertForbidden();
        $this->assertNotSoftDeleted($actor);
    }

    #[TestWith(['empresas'])]
    #[TestWith(['sucursales'])]
    #[TestWith(['usuarios'])]
    #[TestWith(['roles'])]
    #[TestWith(['estados'])]
    #[TestWith(['menus'])]
    public function test_delete_keeps_the_record_and_its_id_with_an_audited_deleted_lifecycle(string $resource): void
    {
        $admin = $this->admin();
        $record = $this->record($resource);
        $name = $record->name;

        $this->actingAs($admin)->deleteJson('/'.$resource.'/'.$record->id)->assertOk()
            ->assertJsonPath('success', true)->assertJsonStructure(['success', 'message', 'data' => ['redirect'], 'errors', 'meta'])
            ->assertSessionHas('history_url', route('records.history', ['resource' => $resource, 'record' => $record->id]));

        $this->assertSoftDeleted($record);
        $this->assertDatabaseHas($record->getTable(), ['id' => $record->id, 'name' => $name]);
        $deleted = $record::withTrashed()->findOrFail($record->id);
        $this->assertSame(-1, $deleted->lifecycle_status);
        $entry = $deleted->activities()->sole();
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame('deleted', $entry->action);
        $this->assertSame(['before' => 1, 'after' => -1], $entry->changes['lifecycle_status']);
        $this->getJson('/'.$resource.'/'.$record->id.'/edit')->assertNotFound();
        $this->get('/'.$resource.'/'.$record->id.'/historial')->assertSee($name);
    }

    public function test_deleting_a_company_preserves_children_and_pivots_but_revokes_access(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $user = $this->member($company, ['organization.branches.manage']);
        $branch = $this->record('sucursales', $company);
        $moduleIds = $company->modules->pluck('id')->all();

        $this->actingAs($admin)->deleteJson('/empresas/'.$company->id)->assertOk();

        $this->assertSoftDeleted($company);
        $this->assertNotSoftDeleted($branch);
        $this->assertNotSoftDeleted($user);
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'company_id' => $company->id]);
        $this->assertSame($moduleIds, $company->modules()->pluck('modules.id')->all());
        $this->assertFalse(app(AccessService::class)->isActive($user->fresh()));
        $this->actingAs($user->fresh())->getJson('/sucursales')->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_a_deleted_account_cannot_log_in_even_with_its_correct_password(): void
    {
        $admin = $this->admin();
        $user = $this->member($this->company());
        $user->update(['password' => 'Valid.Secret.2026!']);
        $this->actingAs($admin)->deleteJson('/usuarios/'.$user->id)->assertOk();
        $this->postJson('/logout')->assertOk();

        $this->postJson('/login', ['email' => $user->email, 'password' => 'Valid.Secret.2026!'])->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_deleting_a_role_preserves_assignments_but_removes_its_permissions_from_access(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $user = $this->member($company);
        $role = $this->record('roles', $company);
        $permissionId = Permission::where('code', 'organization.branches.manage')->value('id');
        $role->permissions()->attach($permissionId);
        $user->roles()->attach($role);
        $this->assertTrue(app(AccessService::class)->allows($user->fresh(), 'organization.branches.manage'));

        $this->actingAs($admin)->deleteJson('/roles/'.$role->id)->assertOk();

        $this->assertDatabaseHas('role_user', ['role_id' => $role->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $permissionId]);
        $this->assertFalse(app(AccessService::class)->allows($user->fresh(), 'organization.branches.manage'));
        $this->actingAs($user->fresh())->getJson('/sucursales')->assertForbidden();
    }

    #[TestWith(['sucursales'])]
    #[TestWith(['usuarios'])]
    #[TestWith(['roles'])]
    public function test_other_tenants_cannot_delete_records_or_read_their_history(string $resource): void
    {
        $this->admin();
        $actor = $this->member($this->company(), ['organization.branches.manage', 'access.users.manage', 'access.roles.manage']);
        $foreignRecord = $this->record($resource, $this->company());

        $this->actingAs($actor)->deleteJson('/'.$resource.'/'.$foreignRecord->id)->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/'.$resource.'/'.$foreignRecord->id.'/historial')->assertNotFound()->assertJsonPath('success', false);

        $this->assertNotSoftDeleted($foreignRecord);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_delete_requires_authentication_and_the_module_permission(): void
    {
        $this->admin();
        $company = $this->company();
        $branch = $this->record('sucursales', $company);

        $this->deleteJson('/sucursales/'.$branch->id)->assertUnauthorized()->assertJsonPath('success', false);
        $this->actingAs($this->member($company))->deleteJson('/sucursales/'.$branch->id)->assertForbidden()->assertJsonPath('success', false);

        $this->assertNotSoftDeleted($branch);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_self_super_admin_and_stronger_grants_are_protected_from_deletion(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $actor = $this->member($company, ['access.users.manage', 'access.roles.manage']);
        $strongerUser = $this->member($company, ['organization.branches.manage']);
        $strongerRole = $this->record('roles', $company);
        $strongerRole->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));

        $this->actingAs($admin)->deleteJson('/usuarios/'.$admin->id)->assertForbidden();
        $this->actingAs($actor)->deleteJson('/usuarios/'.$actor->id)->assertForbidden();
        $this->deleteJson('/usuarios/'.$strongerUser->id)->assertForbidden();
        $this->deleteJson('/roles/'.$strongerRole->id)->assertForbidden();
        $this->getJson('/usuarios/'.$strongerUser->id.'/historial')->assertForbidden();
        $this->getJson('/roles/'.$strongerRole->id.'/historial')->assertForbidden();

        foreach ([$admin, $actor, $strongerUser, $strongerRole] as $record) {
            $this->assertNotSoftDeleted($record);
        }
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_status_references_are_protected_even_when_the_using_record_was_deleted(): void
    {
        $admin = $this->admin();
        $status = $this->record('estados');
        $company = $this->company();
        $company->update(['status_id' => $status->id]);
        $this->actingAs($admin)->deleteJson('/empresas/'.$company->id)->assertOk();

        $this->deleteJson('/estados/'.$status->id)->assertUnprocessable()->assertJsonPath('success', false)
            ->assertJsonValidationErrors('status');

        $this->assertNotSoftDeleted($status);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'status_id' => $status->id]);
        $this->assertDatabaseCount('audit_entries', 1);
    }

    public function test_deleted_companies_statuses_roles_and_menu_parents_cannot_be_assigned(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $deletedCompany = $this->company();
        $deletedCompany->delete();
        $status = Status::create(['status_type_id' => StatusType::where('code', 'users')->value('id'), 'name' => 'Usuario archivado', 'is_active' => true]);
        $status->delete();
        $role = $this->record('roles', $company);
        $role->delete();
        $parent = $this->record('menus');
        $parent->delete();
        $this->actingAs($admin);

        $this->postJson('/sucursales', ['company_id' => $deletedCompany->id, 'name' => 'No crear', 'status_id' => $this->statusId('branches')])->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $data = $this->userData($company);
        $this->postJson('/usuarios', array_replace($data, ['status_id' => $status->id]))->assertUnprocessable()->assertJsonValidationErrors('status_id');
        $this->postJson('/usuarios', array_replace($data, ['role_ids' => [$role->id]]))->assertUnprocessable()->assertJsonValidationErrors('role_ids.0');
        $this->postJson('/menus', ['name' => 'No crear', 'is_parent' => false, 'icon' => 'briefcase', 'sort_order' => 1, 'status_id' => $this->statusId('menus'), 'parent_id' => $parent->id, 'route_name' => 'branches.index'])->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_company_history_tracks_create_update_pivots_and_delete_but_not_a_no_op(): void
    {
        $admin = $this->admin();
        $this->freezeTime();
        $moduleId = Module::where('code', 'organization')->value('id');
        $data = ['name' => 'Empresa inicial', 'tax_id' => 'J-HISTORIAL', 'status_id' => $this->statusId('companies'), 'module_ids' => [$moduleId]];
        $this->actingAs($admin)->postJson('/empresas', $data)->assertOk();
        $company = Company::where('tax_id', 'J-HISTORIAL')->firstOrFail();
        $this->assertSame('created', $company->activities()->sole()->action);
        $data['name'] = 'Empresa actualizada';
        $data['module_ids'] = Module::orderBy('id')->pluck('id')->all();

        $this->putJson('/empresas/'.$company->id, $data)->assertOk()->assertSessionHas('history_url', route('records.history', ['resource' => 'empresas', 'record' => $company->id]));

        $entry = $company->activities()->where('action', 'updated')->sole();
        $this->assertSame(['before' => 'Empresa inicial', 'after' => 'Empresa actualizada'], $entry->changes['name']);
        $this->assertSame(['before' => [$moduleId], 'after' => $data['module_ids']], $entry->changes['module_ids']);
        $this->assertSame($company->id, $entry->company_id);
        $this->assertSame($company->id, $entry->company_record_id);
        $this->get('/empresas/'.$company->id.'/edit')->assertSee('Historial de acciones')->assertSee('Empresa inicial')->assertSee($admin->name);
        $this->putJson('/empresas/'.$company->id, $data)->assertOk();
        $this->assertSame(2, $company->activities()->count());
        $this->deleteJson('/empresas/'.$company->id)->assertOk();
        $this->assertSame(['created', 'updated', 'deleted'], $company->activities()->orderBy('id')->pluck('action')->all());
    }

    public function test_inactivation_uses_zero_without_deleting_the_record_or_changing_the_status_fk_contract(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $inactiveId = $this->statusId('companies', false);

        $this->actingAs($admin)->putJson('/empresas/'.$company->id, ['name' => $company->name, 'tax_id' => $company->tax_id, 'status_id' => $inactiveId, 'module_ids' => Module::pluck('id')->all()])->assertOk();

        $this->assertNotSoftDeleted($company);
        $this->assertSame(0, $company->fresh()->lifecycle_status);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'status_id' => $inactiveId]);
        $this->assertSame(['before' => 1, 'after' => 0], $company->activities()->sole()->changes['lifecycle_status']);
    }

    public function test_user_history_records_grant_changes_and_password_updates_without_secrets(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $role = $this->record('roles', $company);
        $permissionId = Permission::where('code', 'access.users.manage')->value('id');
        $data = $this->userData($company);
        $this->actingAs($admin)->postJson('/usuarios', $data)->assertOk();
        $user = User::where('email', $data['email'])->firstOrFail();
        $originalHash = $user->password;
        $data['role_ids'] = [$role->id];
        $data['permission_ids'] = [$permissionId];
        unset($data['password'], $data['password_confirmation']);
        $this->putJson('/usuarios/'.$user->id, $data)->assertOk();
        $grants = $user->activities()->where('action', 'updated')->sole()->changes;
        $this->assertSame(['before' => [], 'after' => [$role->id]], $grants['role_ids']);
        $this->assertSame(['before' => [], 'after' => [$permissionId]], $grants['permission_ids']);
        $data['password'] = $data['password_confirmation'] = 'Replacement.Pass.2026!';

        $this->putJson('/usuarios/'.$user->id, $data)->assertOk();

        $changes = $user->activities()->latest('id')->firstOrFail()->changes;
        $this->assertSame(['password_changed' => ['before' => null, 'after' => true]], $changes);
        $this->assertTrue(Hash::check('Replacement.Pass.2026!', $user->fresh()->password));
        $history = $user->activities()->pluck('changes')->toJson();
        foreach (['Original.Pass.2026!', 'Replacement.Pass.2026!', $originalHash, $user->fresh()->password] as $secret) {
            $this->assertStringNotContainsString($secret, $history);
        }
        $this->get('/usuarios/'.$user->id.'/historial')->assertDontSee('Replacement.Pass.2026!')->assertDontSee($user->fresh()->password);
    }

    public function test_history_is_available_after_deletion_and_escapes_names_and_change_values_in_fetch_results(): void
    {
        $admin = $this->admin();
        $admin->update(['name' => '<img src=x onerror=alert(1)>']);
        $company = $this->company();
        $payload = '<script>alert("empresa")</script>';
        $this->actingAs($admin)->putJson('/empresas/'.$company->id, ['name' => $payload, 'tax_id' => $company->tax_id, 'status_id' => $company->status_id])->assertOk();
        $this->deleteJson('/empresas/'.$company->id)->assertOk();

        $this->get('/empresas/'.$company->id.'/historial')->assertSee($payload)->assertSee($admin->name)
            ->assertDontSee('<script>alert("empresa")</script>', false)->assertDontSee('<img src=x', false);
        $response = $this->getJson('/empresas/'.$company->id.'/historial?action=updated', ['X-Buko-Listing' => 'true'])->assertOk()
            ->assertJsonPath('success', true)->assertJsonStructure(['success', 'message', 'data' => ['html'], 'errors', 'meta']);

        $html = $response->json('data.html');
        $this->assertStringContainsString(e($payload), $html);
        $this->assertStringContainsString(e($admin->name), $html);
        $this->assertDoesNotMatchRegularExpression('/<!doctype|<html\b|<script\b|<img\b/i', $html);
    }

    public function test_other_tenants_cannot_read_history_even_when_the_record_is_deleted(): void
    {
        $admin = $this->admin();
        $foreignBranch = $this->record('sucursales');
        $actor = $this->member($this->company(), ['organization.branches.manage']);
        $this->actingAs($admin)->deleteJson('/sucursales/'.$foreignBranch->id)->assertOk();

        $this->actingAs($actor)->getJson('/sucursales/'.$foreignBranch->id.'/historial', ['X-Buko-Listing' => 'true'])->assertNotFound()
            ->assertJsonPath('success', false)->assertJsonMissingPath('data.html');
    }

    public function test_company_and_module_changes_are_rolled_back_when_the_audit_cannot_be_saved(): void
    {
        $admin = $this->admin();
        $company = $this->company();
        $moduleId = Module::where('code', 'organization')->value('id');
        $company->modules()->sync([$moduleId]);
        DB::unprepared("CREATE TRIGGER reject_audit_entry BEFORE INSERT ON audit_entries BEGIN SELECT RAISE(ABORT, 'Prueba de fallo de auditoría'); END");

        $this->actingAs($admin)->putJson('/empresas/'.$company->id, ['name' => 'Este cambio debe revertirse', 'tax_id' => $company->tax_id, 'status_id' => $company->status_id, 'module_ids' => Module::pluck('id')->all()])->assertInternalServerError()
            ->assertJsonPath('success', false);

        $this->assertSame($company->name, $company->fresh()->name);
        $this->assertSame([$moduleId], $company->modules()->pluck('modules.id')->all());
        $this->assertDatabaseCount('audit_entries', 0);
    }
}
