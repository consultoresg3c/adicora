<?php

namespace Tests\Feature;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\Module;
use App\Models\Permission;
use App\Models\ProcessStep;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\PurchaseSetupService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private array $workers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Purchases.Test!']);
        $this->seed(FoundationSeeder::class);
        $this->admin = User::where('email', 'admin@buko.local')->firstOrFail();
        $this->company = Company::create(['name' => 'Yraida Guillén', 'tax_id' => 'J-10000001', 'status_id' => $this->active('companies')]);
        app(PurchaseSetupService::class)->prepare($this->company, $this->admin, 'Local.Test.Password!');
        $this->workers = User::where('company_id', $this->company->id)->orderBy('id')->get()->all();
    }

    private function active(string $type): int
    {
        return Status::whereHas('type', fn ($query) => $query->where('code', $type))->where('is_active', true)->firstOrFail()->id;
    }

    private function buyerData(string $intent = 'advance'): array
    {
        return ['intent' => $intent, 'supplier_id' => Supplier::where('company_id', $this->company->id)->value('id'), 'amount' => '125.75', 'purchase_date' => '2026-10-20'];
    }

    private function start(): PurchaseRequest
    {
        $this->actingAs($this->workers[0])->postJson('/compras', $this->buyerData())->assertOk()->assertJsonPath('success', true);

        return PurchaseRequest::latest('id')->firstOrFail();
    }

    public function test_full_flow_manual_dates_field_ownership_pending_tracking_and_finalization(): void
    {
        $request = $this->start();
        $this->assertSame('compra solicitada', $request->status->name);
        $this->assertSame('USD', $request->currency);
        $this->assertSame('125.75', $request->amount);
        $this->assertSame('approval_date', $request->currentStage->field_name);
        $this->get('/compras')->assertViewHas('records', fn ($records) => $records->isEmpty());
        $this->get('/compras/tracking')->assertViewHas('records', fn ($records) => $records->contains('id', $request->id));
        foreach ([1 => ['approval_date', '2026-10-21', 'compra aprobada nivel 1'], 2 => ['receipt_date', '2026-10-22', 'Compra recibida y verificada'], 3 => ['payment_date', '2026-10-23', 'Compra pagada']] as $index => [$field, $date, $status]) {
            $worker = $this->workers[$index];
            $request->refresh();
            $this->actingAs($worker)->get('/compras')->assertOk()->assertViewHas('records', fn ($records) => $records->contains('id', $request->id));
            $this->get('/compras/'.$request->id)->assertOk()->assertSee('name="'.$field.'"', false)->assertDontSee('name="amount"', false);
            $this->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, $field => $date])->assertOk()->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);
            $this->assertSame($date, $request->fresh()->{$field}->format('Y-m-d'));
            $this->assertSame($status, $request->fresh()->status->name);
            $this->get('/compras')->assertViewHas('records', fn ($records) => $records->isEmpty());
            $this->get('/compras/tracking')->assertViewHas('records', fn ($records) => $records->contains('id', $request->id));
            $this->get('/solicitudes-compra/'.$request->id.'/historial')->assertOk();
        }
        $this->assertNotNull($request->fresh()->completed_at);
        $this->assertSame(4, $request->activities()->count());
        foreach ($this->workers as $worker) {
            $this->actingAs($worker)->get('/compras/'.$request->id)->assertOk()->assertDontSee('data-purchase-form', false)->assertSee('Solicitud finalizada');
            $this->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->fresh()->version])->assertForbidden();
        }
    }

    public function test_tracking_is_personal_even_for_a_peer_with_the_same_role_and_history_urls(): void
    {
        $request = $this->start();
        $peer = User::factory()->create(['company_id' => $this->company->id, 'status_id' => $this->active('users')]);
        $peer->roles()->attach($this->workers[0]->roles->first());
        $this->actingAs($peer)->get('/compras/tracking')->assertViewHas('records', fn ($records) => $records->isEmpty());
        $this->getJson('/compras/'.$request->id)->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/solicitudes-compra/'.$request->id.'/historial')->assertNotFound();
        $this->actingAs($this->workers[1])->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, 'approval_date' => '2026-10-21'])->assertOk();
        $peerApprover = User::factory()->create(['company_id' => $this->company->id, 'status_id' => $this->active('users')]);
        $peerApprover->roles()->attach($this->workers[1]->roles->first());
        $this->actingAs($peerApprover)->getJson('/compras/'.$request->id)->assertNotFound();
        $this->get('/compras/tracking')->assertViewHas('records', fn ($records) => $records->isEmpty());
    }

    public function test_draft_save_noop_version_conflict_and_no_stage_skipping(): void
    {
        $this->actingAs($this->workers[0])->postJson('/compras', ['intent' => 'save'])->assertOk();
        $request = PurchaseRequest::firstOrFail();
        $this->assertSame('Borrador', $request->status->name);
        $this->get('/compras')->assertViewHas('records', fn ($records) => $records->contains('id', $request->id));
        $this->putJson('/compras/'.$request->id, ['intent' => 'save', 'version' => $request->version])->assertOk();
        $this->assertSame(1, $request->activities()->count());
        $this->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version])->assertUnprocessable()->assertJsonValidationErrors(['supplier_id', 'amount', 'purchase_date']);
        $this->putJson('/compras/'.$request->id, $this->buyerData('save') + ['version' => $request->version])->assertOk();
        $this->putJson('/compras/'.$request->id, $this->buyerData() + ['version' => $request->version])->assertUnprocessable()->assertJsonValidationErrors('version');
        $request->refresh();
        $this->putJson('/compras/'.$request->id, $this->buyerData() + ['version' => $request->version, 'payment_date' => '2026-10-22', 'status_id' => 1, 'company_id' => 99])->assertUnprocessable()->assertJsonValidationErrors(['payment_date', 'status_id', 'company_id']);
        $this->assertSame('purchase_date', $request->fresh()->currentStage->field_name);
        $this->assertSame(2, $request->activities()->count());
    }

    public function test_other_company_module_disabled_deleted_role_and_unassigned_stages_are_denied(): void
    {
        $request = $this->start();
        $this->get('/compras/crear')->assertOk();
        $this->actingAs($this->workers[2])->get('/compras/crear')->assertForbidden();
        $this->getJson('/compras/'.$request->id)->assertNotFound();
        $other = Company::create(['name' => 'Otra empresa', 'tax_id' => 'J-10000002', 'status_id' => $this->active('companies')]);
        $other->modules()->attach(Module::where('code', 'purchases')->value('id'));
        $outsider = User::factory()->create(['company_id' => $other->id, 'status_id' => $this->active('users')]);
        $outsider->permissions()->attach(Permission::where('code', 'purchases.requests.work')->value('id'));
        $outsider->roles()->attach($this->workers[1]->roles->first());
        $this->actingAs($outsider)->get('/compras')->assertViewHas('records', fn ($records) => $records->isEmpty());
        $this->getJson('/compras/'.$request->id)->assertNotFound();
        $this->putJson('/compras/'.$request->id, [])->assertNotFound();
        $this->getJson('/solicitudes-compra/'.$request->id.'/historial')->assertNotFound();
        $this->company->modules()->detach(Module::where('code', 'purchases')->value('id'));
        $this->actingAs($this->workers[1]->fresh())->getJson('/compras')->assertForbidden();
        $this->company->modules()->attach(Module::where('code', 'purchases')->value('id'));
        $this->workers[1]->roles->first()->delete();
        $this->actingAs($this->workers[1]->fresh())->getJson('/compras')->assertForbidden();
    }

    public function test_foreign_supplier_invalid_amount_date_and_nonowned_fields_are_rejected(): void
    {
        $request = $this->start();
        $this->actingAs($this->workers[1])->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, 'approval_date' => '2026-10-19', 'amount' => '999'])->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, 'approval_date' => '2026-10-19'])->assertUnprocessable()->assertJsonValidationErrors('approval_date');
        $other = Company::create(['name' => 'Otra', 'tax_id' => 'J-10000003', 'status_id' => $this->active('companies')]);
        $supplier = Supplier::create(['company_id' => $other->id, 'name' => 'Proveedor privado ajeno', 'status_id' => $this->active('suppliers')]);
        $bad = $this->buyerData();
        $bad['supplier_id'] = $supplier->id;
        $bad['amount'] = '1.234';
        $this->actingAs($this->workers[0])->postJson('/compras', $bad)->assertUnprocessable()->assertJsonValidationErrors(['supplier_id', 'amount']);
        $this->get('/compras/crear')->assertDontSee('Proveedor privado ajeno');
        $supplier = Supplier::findOrFail($this->buyerData()['supplier_id']);
        $supplier->delete();
        $this->postJson('/compras', $this->buyerData())->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
    }

    public function test_invalid_or_missing_transition_rolls_back_changes_and_audit(): void
    {
        $request = $this->start();
        $before = $request->activities()->count();
        ProcessStep::where('origin_stage_id', $request->currentStage->process_stage_id)->delete();
        $this->actingAs($this->workers[1])->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, 'approval_date' => '2026-10-21'])->assertUnprocessable()->assertJsonValidationErrors('workflow');
        $this->assertNull($request->fresh()->approval_date);
        $this->assertSame($request->version, $request->fresh()->version);
        $this->assertSame($before, $request->activities()->count());
    }

    public function test_setup_is_repeatable_preserves_passwords_and_does_not_grant_system_access(): void
    {
        $this->assertSame(4, PurchaseStage::count());
        $this->assertSame(3, ProcessStep::count());
        $this->assertSame(4, User::where('company_id', $this->company->id)->count());
        $this->assertDatabaseHas('suppliers', ['name' => 'Librería de Nacho', 'company_id' => $this->company->id]);
        $buyer = $this->workers[0];
        $buyer->update(['password' => 'Changed.User.Password!']);
        $count = AuditEntry::count();
        app(PurchaseSetupService::class)->prepare($this->company->fresh(), $this->admin, 'Another.Local.Password!');
        $this->assertTrue(Hash::check('Changed.User.Password!', $buyer->fresh()->password));
        $this->assertSame($count, AuditEntry::count());
        $this->assertSame(4, PurchaseStage::count());
        $this->actingAs($buyer->fresh())->getJson('/procesos')->assertForbidden();
        $this->getJson('/usuarios')->assertForbidden();
        $this->getJson('/proveedores')->assertForbidden();
        $this->get('/inicio')->assertSee(route('purchases.index'), false)->assertSee(route('purchases.tracking'), false)->assertDontSee(route('suppliers.index'), false);
        $this->post('/logout')->assertRedirect();
        $this->post('/login', ['email' => $buyer->email, 'password' => 'Changed.User.Password!'])->assertRedirect(route('dashboard'));
    }

    public function test_supplier_crud_modals_xss_company_scope_and_audited_soft_delete(): void
    {
        $supplier = Supplier::firstOrFail();
        $this->actingAs($this->admin)->putJson('/proveedores/'.$supplier->id, ['company_id' => $this->company->id, 'name' => '<script>alert("proveedor")</script>', 'status_id' => $this->active('suppliers')])->assertOk();
        $this->get('/proveedores')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)->assertSee('id="edit-supplier-'.$supplier->id.'"', false);
        $this->getJson('/proveedores/create')->assertRedirect(route('suppliers.index', ['nuevo' => 1]));
        $this->get('/proveedores?editar='.$supplier->id)->assertViewHas('openModal', true);
        $this->actingAs($this->workers[0])->get('/compras/crear')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
        $request = $this->start();
        $response = $this->actingAs($this->workers[1])->withHeader('X-Buko-Listing', 'true')->getJson('/compras?q=alert&per_page=10');
        $response->assertOk()->assertJsonStructure(['success', 'message', 'data' => ['html'], 'errors', 'meta']);
        $this->assertStringContainsString('&lt;script&gt;', $response->json('data.html'));
        $this->assertStringNotContainsString('<script>alert', $response->json('data.html'));
        $this->getJson('/compras?status_id='.$this->active('purchase_requests'))->assertOk();
        $this->getJson('/compras?per_page=11')->assertUnprocessable();
        $this->actingAs($this->admin)->deleteJson('/proveedores/'.$supplier->id)->assertOk();
        $this->assertSoftDeleted($supplier);
        $this->get('/proveedores/'.$supplier->id.'/historial')->assertOk()->assertSee('Registro eliminado');
        $this->actingAs($this->workers[0])->get('/compras/'.$request->id)->assertOk()->assertSee('&lt;script&gt;', false);
    }
}
