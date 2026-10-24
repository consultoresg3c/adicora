<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortFormModalTest extends TestCase
{
    use RefreshDatabase;

    private function foundation(): User
    {
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Modal.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('email', 'admin@buko.local')->firstOrFail();
    }

    private function statusFor(string $type): Status
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))
            ->where('is_active', true)->firstOrFail();
    }

    private function company(string $taxId = 'J-40352671-0'): Company
    {
        $company = Company::create([
            'name' => 'Servicios del Tuy',
            'tax_id' => $taxId,
            'status_id' => $this->statusFor('companies')->id,
        ]);
        $company->modules()->sync(Module::pluck('id'));

        return $company;
    }

    private function branchManager(Company $company): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'status_id' => $this->statusFor('users')->id,
        ]);
        $user->permissions()->attach(Permission::where('code', 'organization.branches.manage')->value('id'));

        return $user;
    }

    public function test_status_modal_is_already_present_and_escapes_edit_data(): void
    {
        $admin = $this->foundation();
        $status = $this->statusFor('users');
        $status->update(['name' => '<script>alert("estado")</script>']);

        $this->actingAs($admin)->get('/estados')
            ->assertSee('id="create-status"', false)->assertSee('id="edit-status-'.$status->id.'"', false)
            ->assertSee('data-dialog-template="edit-status-'.$status->id.'"', false)
            ->assertSee('name="name"', false)->assertSee('name="status_type_id"', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("estado")</script>', false)
            ->assertDontSee('data-record=', false)->assertDontSee('data-create-record=', false)
            ->assertDontSee('data-remote-dialog', false);

        $this->getJson('/estados/create')->assertRedirect(route('statuses.index', ['nuevo' => 1]));
        $this->getJson('/estados/'.$status->id.'/edit')
            ->assertRedirect(route('statuses.index', ['editar' => $status->id]));
    }

    public function test_status_html_routes_open_the_modal_on_the_listing(): void
    {
        $admin = $this->foundation();
        $status = $this->statusFor('users');

        $this->actingAs($admin)->get('/estados/create')->assertRedirect(route('statuses.index', ['nuevo' => 1]));
        $this->get('/estados/'.$status->id.'/edit')->assertRedirect(route('statuses.index', ['editar' => $status->id]));

        $this->get('/estados?nuevo=1')->assertOk()->assertViewHas('openModal', true)
            ->assertSee('<dialog', false)->assertSee('name="name"', false);
        $this->get('/estados?editar='.$status->id)->assertOk()->assertViewHas('openModal', true)
            ->assertViewHas('record', fn (Status $record) => $record->is($status));
    }

    public function test_branch_modal_is_embedded_with_only_own_company_record_data(): void
    {
        $this->foundation();
        $company = $this->company();
        $other = $this->company('J-40352672-9');
        $other->update(['name' => 'Empresa ajena privada']);
        $manager = $this->branchManager($company);
        $branch = Branch::create([
            'name' => 'Sucursal de Charallave',
            'company_id' => $company->id,
            'status_id' => $this->statusFor('branches')->id,
        ]);

        $this->actingAs($manager)->getJson('/sucursales/create')
            ->assertRedirect(route('branches.index', ['nuevo' => 1]));
        $this->getJson('/sucursales/'.$branch->id.'/edit')
            ->assertRedirect(route('branches.index', ['editar' => $branch->id]));
        $this->get('/sucursales')->assertSee('id="create-branch"', false)
            ->assertSee('id="edit-branch-'.$branch->id.'"', false)
            ->assertSee('action="'.route('branches.update', $branch).'"', false)
            ->assertSee('name="address"', false)->assertSee('Sucursal de Charallave')
            ->assertDontSee('Empresa ajena privada')->assertDontSee('data-remote-dialog', false)->assertDontSee('data-record=', false);
    }

    public function test_branch_html_routes_open_the_modal_on_the_listing(): void
    {
        $this->foundation();
        $company = $this->company();
        $manager = $this->branchManager($company);
        $branch = Branch::create([
            'name' => 'Sucursal de Cúa',
            'company_id' => $company->id,
            'status_id' => $this->statusFor('branches')->id,
        ]);

        $this->actingAs($manager)->get('/sucursales/create')->assertRedirect(route('branches.index', ['nuevo' => 1]));
        $this->get('/sucursales/'.$branch->id.'/edit')->assertRedirect(route('branches.index', ['editar' => $branch->id]));

        $this->get('/sucursales?nuevo=1')->assertOk()->assertViewHas('openModal', true)
            ->assertSee('<dialog', false)->assertSee('name="name"', false);
        $this->get('/sucursales?editar='.$branch->id)->assertOk()->assertViewHas('openModal', true)
            ->assertViewHas('record', fn (Branch $record) => $record->is($branch));
    }

    public function test_foreign_branch_forms_return_404_through_json_and_listing_query(): void
    {
        $this->foundation();
        $manager = $this->branchManager($this->company());
        $branch = Branch::create([
            'name' => 'Sucursal confidencial ajena',
            'company_id' => $this->company('J-40352672-9')->id,
            'status_id' => $this->statusFor('branches')->id,
        ]);

        $this->actingAs($manager)->getJson('/sucursales/'.$branch->id.'/edit')
            ->assertNotFound()->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta'])
            ->assertDontSee('Sucursal confidencial ajena');
        $this->get('/sucursales?editar='.$branch->id)->assertNotFound()->assertDontSee('Sucursal confidencial ajena');
    }

    public function test_short_form_validation_returns_field_errors_without_saving_records(): void
    {
        $admin = $this->foundation();
        $statusCount = Status::count();

        $this->actingAs($admin)->postJson('/estados', [])->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonValidationErrors(['name', 'status_type_id'])
            ->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);
        $this->assertDatabaseCount('statuses', $statusCount);

        $manager = $this->branchManager($this->company());
        $this->actingAs($manager)->postJson('/sucursales', [])->assertUnprocessable()
            ->assertJsonPath('success', false)->assertJsonValidationErrors(['name', 'status_id'])
            ->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);
        $this->assertDatabaseCount('branches', 0);
    }
}
