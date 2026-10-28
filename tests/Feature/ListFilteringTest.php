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
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_search_matches_name_tax_id_and_email_without_case_sensitivity(): void
    {
        $admin = $this->foundation();
        $byName = $this->company('Servicios ORINOCO', 'J-10000001-0');
        $byTax = $this->company('Servicios Delta', 'ORINOCO-1002');
        $byEmail = $this->company('Servicios Guayana', 'J-10000003-0', ['email' => 'administracion@orinoco.test']);
        $this->company('Servicios Andes', 'J-10000004-0');

        $this->actingAs($admin)->get('/empresas?q=orinoco')
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 3
                && $records->getCollection()->modelKeys() === [$byTax->id, $byEmail->id, $byName->id]);
    }

    public function test_company_status_and_enabled_module_filters_are_combined_with_search(): void
    {
        $admin = $this->foundation();
        $organization = Module::where('code', 'organization')->firstOrFail();
        $access = Module::where('code', 'access')->firstOrFail();
        $expected = $this->company('Capital Servicios', 'J-10000001-0');
        $expected->modules()->sync([$organization->id]);
        $differentModule = $this->company('Capital Equipo', 'J-10000002-0');
        $differentModule->modules()->sync([$access->id]);
        $this->company('Capital Pausada', 'J-10000003-0', ['status_id' => $this->statusFor('companies', false)->id]);
        $this->company('Servicios Delta', 'J-10000004-0');

        $this->actingAs($admin)->get('/empresas?'.http_build_query([
            'q' => 'capital', 'status_id' => $expected->status_id, 'module_id' => $organization->id,
        ]))->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 1
            && $records->getCollection()->modelKeys() === [$expected->id]);
    }

    public function test_company_pagination_keeps_the_search_filters_and_stable_order(): void
    {
        $admin = $this->foundation();
        $companies = collect();
        for ($index = 1; $index <= 22; $index++) {
            $companies->push($this->company('Grupo Capital', 'J-'.str_pad((string) $index, 8, '0', STR_PAD_LEFT).'-0'));
        }

        $response = $this->actingAs($admin)->get('/empresas?'.http_build_query([
            'q' => 'grupo', 'status_id' => $this->statusFor('companies')->id, 'per_page' => 10, 'page' => 2,
        ]));

        $response->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 22
            && $records->perPage() === 10 && $records->currentPage() === 2
            && $records->getCollection()->modelKeys() === $companies->slice(10, 10)->pluck('id')->all());
        $records = $response->viewData('records');
        parse_str(parse_url($records->url(3), PHP_URL_QUERY), $query);
        $this->assertSame('grupo', $query['q']);
        $this->assertSame((string) $this->statusFor('companies')->id, $query['status_id']);
        $this->assertSame('10', $query['per_page']);
        $this->assertSame('3', $query['page']);
        $response->assertSee('name="per_page"', false);
    }

    public function test_user_search_matches_email_and_respects_company_and_status_filters(): void
    {
        $admin = $this->foundation();
        $company = $this->company('Empresa Capital', 'J-10000001-0');
        $otherCompany = $this->company('Empresa Delta', 'J-10000002-0');
        $expected = $this->member($company, ['name' => 'Mariana Rojas', 'email' => 'equipo.capital@example.test']);
        $this->member($company, ['name' => 'Equipo suspendido', 'status_id' => $this->statusFor('users', false)->id]);
        $this->member($otherCompany, ['name' => 'Equipo de otra empresa']);
        $this->member($company, ['name' => 'Cuenta de archivo']);

        $this->actingAs($admin)->get('/usuarios?'.http_build_query([
            'q' => 'EQUIPO', 'status_id' => $expected->status_id, 'company_id' => $company->id,
        ]))->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 1
            && $records->getCollection()->modelKeys() === [$expected->id]);
    }

    public function test_role_search_can_filter_by_company_and_permission_module(): void
    {
        $admin = $this->foundation();
        $company = $this->company('Empresa Capital', 'J-10000001-0');
        $otherCompany = $this->company('Empresa Delta', 'J-10000002-0');
        $organization = Module::where('code', 'organization')->firstOrFail();
        $expected = Role::create(['company_id' => $company->id, 'name' => 'Equipo de sucursales']);
        $expected->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));
        $differentModule = Role::create(['company_id' => $company->id, 'name' => 'Equipo de accesos']);
        $differentModule->permissions()->attach(Permission::where('code', 'access.roles.manage')->value('id'));
        $otherRole = Role::create(['company_id' => $otherCompany->id, 'name' => 'Equipo de otra empresa']);
        $otherRole->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));

        $this->actingAs($admin)->get('/roles?'.http_build_query([
            'q' => 'EQUIPO', 'company_id' => $company->id, 'module_id' => $organization->id,
        ]))->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 1
            && $records->getCollection()->modelKeys() === [$expected->id]);
    }

    public function test_search_and_forged_company_filters_cannot_expose_another_company_users_or_roles(): void
    {
        $this->foundation();
        $own = $this->company('Empresa propia', 'J-10000001-0');
        $other = $this->company('Empresa ajena confidencial', 'J-10000002-0');
        $actor = $this->member($own, ['name' => 'Responsable local']);
        $actor->permissions()->sync(Permission::whereIn('code', ['access.users.manage', 'access.roles.manage'])->pluck('id'));
        $ownUser = $this->member($own, ['name' => 'Equipo local']);
        $this->member($other, ['name' => 'Equipo ajeno confidencial']);
        $ownRole = Role::create(['company_id' => $own->id, 'name' => 'Equipo local']);
        Role::create(['company_id' => $other->id, 'name' => 'Equipo ajeno confidencial']);
        $query = http_build_query(['q' => 'Equipo', 'company_id' => $other->id]);

        $this->actingAs($actor)->get('/usuarios?'.$query)
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->getCollection()->modelKeys() === [$ownUser->id])
            ->assertDontSee('Equipo ajeno confidencial')->assertDontSee('Empresa ajena confidencial');
        $this->get('/roles?'.$query)
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->getCollection()->modelKeys() === [$ownRole->id])
            ->assertDontSee('Equipo ajeno confidencial')->assertDontSee('Empresa ajena confidencial');
        $this->getJson('/empresas?q=Equipo')->assertForbidden()->assertJsonPath('success', false);
    }

    #[DataProvider('listingPaths')]
    public function test_empty_filtered_lists_keep_search_controls_and_pagination(string $path, string $message): void
    {
        $admin = $this->foundation();

        $this->actingAs($admin)->get($path.'?q=registro-inexistente')
            ->assertSee($message)->assertSee('name="q"', false)->assertSee('name="per_page"', false)
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 0);
    }

    public static function listingPaths(): array
    {
        return [
            'companies' => ['/empresas', 'No hay empresas para mostrar'],
            'users' => ['/usuarios', 'No hay usuarios para mostrar'],
            'roles' => ['/roles', 'No hay roles para mostrar'],
        ];
    }

    #[DataProvider('fragmentPaths')]
    public function test_listing_header_returns_only_the_rendered_results_fragment(string $path, string $caption): void
    {
        $admin = $this->foundation();
        $this->displayRecord($path, 'Registro fragmento');

        $response = $this->actingAs($admin)->getJson($path.'?q=fragmento', ['X-Buko-Listing' => 'true'])
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['html'], 'errors', 'meta']);

        $html = $response->json('data.html');
        $this->assertStringContainsString('data-list-results', $html);
        $this->assertStringContainsString($caption, $html);
        $this->assertStringContainsString('Registro fragmento', $html);
        $this->assertStringNotContainsString('name="q"', $html);
        $this->assertStringNotContainsString('app-shell', $html);
    }

    #[DataProvider('fragmentPaths')]
    public function test_listing_fragments_escape_stored_names_and_email_addresses(string $path, string $caption): void
    {
        $admin = $this->foundation();
        $payload = '<img src=x onerror=alert("buko")>';
        $this->displayRecord($path, $payload);

        $response = $this->actingAs($admin)->getJson($path, ['X-Buko-Listing' => 'true'])
            ->assertJsonPath('success', true);

        $html = $response->json('data.html');
        $this->assertStringContainsString($caption, $html);
        $this->assertStringContainsString(e($payload), $html);
        $this->assertStringNotContainsString($payload, $html);
        $this->assertMatchesRegularExpression('/<a class="row-action"[^>]*>'.preg_quote(e($payload), '/').'<\/a>/', $html);
        $this->assertStringContainsString('aria-label="Editar '.e($payload).'"', $html);
        if ($path !== '/roles') {
            $this->assertStringContainsString(e($payload.'@example.test'), $html);
        }
    }

    public function test_protected_roles_and_users_do_not_offer_edit_links_from_their_names(): void
    {
        $this->foundation();
        $company = $this->company('Empresa local', 'J-10000001-0');
        $actor = $this->member($company, ['name' => 'Responsable local']);
        $actor->permissions()->sync(Permission::whereIn('code', ['access.users.manage', 'access.roles.manage'])->pluck('id'));
        $privilegedRole = Role::create(['company_id' => $company->id, 'name' => 'Rol protegido']);
        $privilegedRole->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));
        $protectedUser = $this->member($company, ['name' => 'Usuario protegido']);
        $protectedUser->roles()->attach($privilegedRole);
        $editableRole = Role::create(['company_id' => $company->id, 'name' => 'Rol editable']);
        $editableUser = $this->member($company, ['name' => 'Usuario editable']);

        $this->actingAs($actor)->get('/roles')->assertOk()
            ->assertSee('href="'.route('roles.edit', $editableRole).'"', false)
            ->assertDontSee('href="'.route('roles.edit', $privilegedRole).'"', false)
            ->assertSee('<strong>Rol protegido</strong>', false);
        $this->get('/usuarios')->assertOk()
            ->assertSee('href="'.route('users.edit', $editableUser).'"', false)
            ->assertDontSee('href="'.route('users.edit', $protectedUser).'"', false)
            ->assertSee('<strong>Usuario protegido</strong>', false);
    }

    public static function fragmentPaths(): array
    {
        return [
            'companies' => ['/empresas', 'Empresas registradas'],
            'users' => ['/usuarios', 'Usuarios registrados'],
            'roles' => ['/roles', 'Roles registrados'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_listing_filters_return_the_shared_validation_contract(string $path, array $query, string $field): void
    {
        $admin = $this->foundation();

        $this->actingAs($admin)->getJson($path.'?'.http_build_query($query))
            ->assertUnprocessable()->assertJsonValidationErrors($field)
            ->assertJsonPath('success', false)->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);
    }

    public static function invalidFilters(): array
    {
        return [
            'company search must be scalar' => ['/empresas', ['q' => ['unsafe']], 'q'],
            'user page size is bounded' => ['/usuarios', ['per_page' => 100000], 'per_page'],
            'role company must be an id' => ['/roles', ['company_id' => 'other-company'], 'company_id'],
        ];
    }

    private function foundation(): User
    {
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Test.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('email', 'admin@buko.local')->firstOrFail();
    }

    private function displayRecord(string $path, string $name): void
    {
        match ($path) {
            '/empresas' => $this->company($name, 'J-10000001-0', ['email' => $name.'@example.test']),
            '/usuarios' => User::factory()->create(['name' => $name, 'email' => $name.'@example.test', 'status_id' => $this->statusFor('users')->id]),
            '/roles' => Role::create(['name' => $name]),
        };
    }

    private function statusFor(string $type, bool $active = true): Status
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))->where('is_active', $active)->firstOrFail();
    }

    private function company(string $name, string $taxId, array $attributes = []): Company
    {
        $company = Company::create($attributes + ['name' => $name, 'tax_id' => $taxId, 'status_id' => $this->statusFor('companies')->id]);
        $company->modules()->sync(Module::pluck('id'));

        return $company;
    }

    private function member(Company $company, array $attributes): User
    {
        return User::factory()->create($attributes + ['company_id' => $company->id, 'status_id' => $this->statusFor('users')->id]);
    }
}
