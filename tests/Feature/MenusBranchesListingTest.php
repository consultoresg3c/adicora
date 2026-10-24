<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\MenuItem;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class MenusBranchesListingTest extends TestCase
{
    use RefreshDatabase;

    private function foundation(): User
    {
        $this->withoutVite();
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Listings.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('email', 'admin@buko.local')->firstOrFail();
    }

    private function statusId(string $type, bool $active = true): int
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))
            ->where('is_active', $active)->firstOrFail()->id;
    }

    private function company(string $name, string $taxId): Company
    {
        $company = Company::create(['name' => $name, 'tax_id' => $taxId, 'status_id' => $this->statusId('companies')]);
        $company->modules()->sync(Module::pluck('id'));

        return $company;
    }

    public function test_menu_search_and_filters_are_combined_with_case_insensitive_matches(): void
    {
        $admin = $this->foundation();
        $leaf = MenuItem::where('route_name', 'branches.index')->firstOrFail();

        $this->actingAs($admin)->get('/menus?'.http_build_query([
            'q' => 'sUcUrSa', 'kind' => 'child', 'parent_id' => $leaf->parent_id,
            'module_id' => $leaf->module_id, 'status_id' => $leaf->status_id,
        ]))->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 1 && $records->first()->is($leaf));

        $this->get('/menus?kind=parent')->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 4 && $records->getCollection()->every(fn (MenuItem $item) => $item->is_parent));
        $this->get('/menus?q=sucursales&status_id='.$this->statusId('menus', false))
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 0)
            ->assertSee('aria-label="Paginación del listado"', false)->assertSee('Sin registros');
    }

    public function test_menu_pagination_preserves_search_filters_and_page_size(): void
    {
        $admin = $this->foundation();
        $statusId = $this->statusId('menus');
        foreach (range(1, 18) as $number) {
            MenuItem::create(['name' => 'Archivo '.$number, 'is_parent' => true, 'icon' => 'folders', 'sort_order' => $number, 'status_id' => $statusId]);
        }

        $this->actingAs($admin)->get('/menus?q=archivo&kind=parent&per_page=15')
            ->assertViewHas('records', function (LengthAwarePaginator $records): bool {
                parse_str(parse_url($records->nextPageUrl(), PHP_URL_QUERY), $query);

                return $records->total() === 18 && $records->count() === 15
                    && $query === ['q' => 'archivo', 'kind' => 'parent', 'per_page' => '15', 'page' => '2'];
            });
        $this->get('/menus?q=archivo&kind=parent&per_page=15&page=2')
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->count() === 3 && $records->currentPage() === 2);
    }

    public function test_branch_filters_cannot_expose_another_company_and_keep_modal_data_local(): void
    {
        $this->foundation();
        $company = $this->company('Empresa del Caribe', 'J-40352671-0');
        $other = $this->company('Empresa privada ajena', 'J-40352672-9');
        $activeStatus = $this->statusId('branches');
        $branch = Branch::create(['name' => 'Sede del Caribe', 'address' => 'Calle Central', 'company_id' => $company->id, 'status_id' => $activeStatus]);
        Branch::create(['name' => 'Sucursal confidencial', 'company_id' => $other->id, 'status_id' => $activeStatus]);
        $manager = User::factory()->create(['company_id' => $company->id, 'status_id' => $this->statusId('users')]);
        $manager->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));

        $this->actingAs($manager)->get('/sucursales?'.http_build_query(['q' => 'cEnTrAl', 'status_id' => $activeStatus, 'company_id' => $other->id]))
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 1 && $records->first()->is($branch))
            ->assertSee('id="create-branch"', false)->assertDontSee('Empresa privada ajena')->assertDontSee('Sucursal confidencial');
        $this->get('/sucursales?q=confidencial')->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 0)
            ->assertDontSee('Sucursal confidencial')->assertSee('aria-label="Paginación del listado"', false)->assertSee('Sin registros');
    }

    public function test_super_admin_can_filter_branch_company_status_and_paginate_search_results(): void
    {
        $admin = $this->foundation();
        $company = $this->company('Empresa del Caribe', 'J-40352671-0');
        $other = $this->company('Empresa del Orinoco', 'J-40352672-9');
        $activeStatus = $this->statusId('branches');
        foreach (range(1, 16) as $number) {
            Branch::create(['name' => 'Sede '.$number, 'company_id' => $company->id, 'status_id' => $activeStatus]);
        }
        Branch::create(['name' => 'Sede ajena', 'company_id' => $other->id, 'status_id' => $activeStatus]);
        Branch::create(['name' => 'Sede cerrada', 'company_id' => $company->id, 'status_id' => $this->statusId('branches', false)]);

        $this->actingAs($admin)->get('/sucursales?'.http_build_query(['q' => 'sede', 'company_id' => $company->id, 'status_id' => $activeStatus, 'per_page' => 15]))
            ->assertViewHas('records', function (LengthAwarePaginator $records) use ($company, $activeStatus): bool {
                parse_str(parse_url($records->nextPageUrl(), PHP_URL_QUERY), $query);

                return $records->total() === 16 && $records->count() === 15
                    && $query['q'] === 'sede' && $query['company_id'] === (string) $company->id
                    && $query['status_id'] === (string) $activeStatus && $query['per_page'] === '15';
            });
    }

    public function test_zero_is_a_search_term_for_menus_and_branches(): void
    {
        $admin = $this->foundation();
        $menu = MenuItem::create(['name' => 'Archivo 0', 'is_parent' => true, 'icon' => 'folders', 'status_id' => $this->statusId('menus')]);
        $company = $this->company('Empresa del Caribe', 'J-40352671-0');
        $branch = Branch::create(['name' => 'Sucursal 0', 'company_id' => $company->id, 'status_id' => $this->statusId('branches')]);
        Branch::create(['name' => 'Sucursal Central', 'company_id' => $company->id, 'status_id' => $this->statusId('branches')]);

        $this->actingAs($admin)->get('/menus?q=0')
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 1 && $records->first()->is($menu));
        $this->get('/sucursales?q=0')
            ->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 1 && $records->first()->is($branch));
    }

    public function test_direct_edit_includes_a_local_template_when_the_record_is_outside_the_results(): void
    {
        $admin = $this->foundation();
        $menu = MenuItem::where('route_name', 'branches.index')->firstOrFail();
        $company = $this->company('Empresa del Caribe', 'J-40352671-0');
        $branch = Branch::create(['name' => 'Sucursal Central', 'company_id' => $company->id, 'status_id' => $this->statusId('branches')]);

        $this->actingAs($admin)->get('/menus?q=SinCoincidencias&editar='.$menu->id)
            ->assertViewHas('openModal', true)->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 0)
            ->assertSee('id="edit-menu-'.$menu->id.'"', false)->assertSee('action="'.route('menus.update', $menu).'"', false);
        $this->get('/sucursales?q=SinCoincidencias&editar='.$branch->id)
            ->assertViewHas('openModal', true)->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 0)
            ->assertSee('id="edit-branch-'.$branch->id.'"', false)->assertSee('value="Sucursal Central"', false);
    }

    public function test_listing_requests_return_only_results_and_their_local_edit_templates(): void
    {
        $admin = $this->foundation();
        $menu = MenuItem::where('route_name', 'branches.index')->firstOrFail();

        $response = $this->actingAs($admin)->withHeader('X-Buko-Listing', 'true')->getJson('/menus?q=Sucursales')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['html'], 'errors', 'meta'])
            ->assertJsonMissingPath('data.navigation');

        $this->assertStringContainsString('data-list-results', $response->json('data.html'));
        $this->assertStringContainsString('id="edit-menu-'.$menu->id.'"', $response->json('data.html'));
        $this->assertStringContainsString('id="delete-menu-'.$menu->id.'"', $response->json('data.html'));
        $this->assertStringNotContainsString('id="create-menu"', $response->json('data.html'));
        $this->assertStringNotContainsString('<dialog', $response->json('data.html'));
        $this->assertStringNotContainsString('<html', $response->json('data.html'));
    }
}
