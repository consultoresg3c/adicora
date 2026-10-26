<?php

namespace Tests\Feature;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\ProcessDefinition;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\PurchaseSetupService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    private ProcessDefinition $process;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Process.Test!']);
        $this->seed(FoundationSeeder::class);
        $this->admin = User::where('email', 'admin@buko.local')->firstOrFail();
        $this->company = Company::create(['name' => 'Yraida Guillén', 'tax_id' => 'J-80000001', 'status_id' => Status::whereHas('type', fn ($query) => $query->where('code', 'companies'))->where('is_active', true)->value('id')]);
        app(PurchaseSetupService::class)->prepare($this->company, $this->admin, 'Local.Process.Password!');
        $this->process = ProcessDefinition::firstOrFail();
    }

    private function stageData(int $position = 2): array
    {
        return ['new_stage_name' => 'Control documental', 'responsible_role_id' => Role::where('company_id', $this->company->id)->where('name', 'comprador')->firstOrFail()->id, 'completion_status_id' => $this->process->stages()->firstOrFail()->completion_status_id, 'position' => $position, 'field_name' => null, 'version' => $this->process->fresh()->version];
    }

    public function test_grouped_table_shows_process_name_business_status_roles_and_terminal_stage(): void
    {
        $this->actingAs($this->admin)->get('/procesos')->assertOk()->assertSee('Nombre proceso')->assertSee('value="Compras"', false)
            ->assertSee('Estación origen')->assertSee('Estación destino')->assertSee('Rol')->assertSee('Estatus')
            ->assertSee('compra aprobada nivel 1')->assertSee('Compra pagada')->assertSee('Fin del proceso')->assertSee('pagador')
            ->assertSee('data-dialog-template="create-process-stage"', false)->assertSee('id="create-process"', false)
            ->assertViewHas('records', fn ($records) => $records->total() === 4);
        $row = $this->process->stages()->firstOrFail();
        $this->get('/procesos/'.$this->process->id.'/etapas/'.$row->id.'/edit')->assertRedirect(route('processes.index', ['process_id' => $this->process->id, 'editar' => $row->id]));
        $this->get('/procesos?process_id='.$this->process->id.'&editar='.$row->id)->assertViewHas('initialTemplate', 'edit-process-stage-'.$row->id);
        $this->getJson('/procesos/'.$this->process->id.'/etapas/create')->assertRedirect(route('processes.index', ['process_id' => $this->process->id, 'nuevo' => 1]));
    }

    public function test_add_move_edit_and_remove_stage_reconnect_the_actual_flow_and_audit(): void
    {
        $original = $this->process->stages()->get();
        $this->actingAs($this->admin)->postJson('/procesos/'.$this->process->id.'/etapas', $this->stageData())->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['success', 'message', 'data', 'errors', 'meta']);
        $rows = $this->process->stages()->get();
        $review = $rows[1];
        $this->assertSame([1, 2, 3, 4, 5], $rows->pluck('position')->all());
        $this->assertSame('Control documental', $review->stage->name);
        $this->assertNull($review->field_name);
        $this->assertDatabaseHas('process_steps', ['process_definition_id' => $this->process->id, 'origin_stage_id' => $original[0]->process_stage_id, 'destination_stage_id' => $review->process_stage_id, 'deleted_at' => null]);
        $this->assertDatabaseHas('process_steps', ['origin_stage_id' => $review->process_stage_id, 'destination_stage_id' => $original[1]->process_stage_id, 'deleted_at' => null]);
        $data = $this->stageData(3);
        unset($data['new_stage_name']);
        $data['process_stage_id'] = $review->process_stage_id;
        $data['responsible_role_id'] = Role::where('name', 'aprobador N1')->value('id');
        $this->putJson('/procesos/'.$this->process->id.'/etapas/'.$review->id, $data)->assertOk();
        $this->assertSame(3, $review->fresh()->position);
        $this->assertSame($data['responsible_role_id'], $review->fresh()->responsible_role_id);
        $this->deleteJson('/procesos/'.$this->process->id.'/etapas/'.$review->id, ['version' => $this->process->fresh()->version])->assertOk();
        $this->assertSoftDeleted($review);
        $this->assertSame($original->pluck('id')->all(), $this->process->stages()->pluck('id')->all());
        $this->assertSame([1, 2, 3, 4], $this->process->stages()->pluck('position')->all());
        $this->assertSame(3, $this->process->steps()->count());
        $this->assertSame(['created', 'updated', 'deleted'], $review->activities()->orderBy('id')->pluck('action')->all());
        $this->get('/etapas-compras/'.$review->id.'/historial')->assertOk()->assertSee('Registro eliminado');
        $this->get('/flujos-procesos/'.$this->process->id.'/historial')->assertOk()->assertSee('Secuencia de etapas');
    }

    public function test_stage_changes_are_blocked_while_requests_are_in_progress_but_name_can_change(): void
    {
        $buyer = User::where('email', 'comprador@yraida.test')->firstOrFail();
        $this->actingAs($buyer)->postJson('/compras', ['intent' => 'save'])->assertOk();
        $rows = $this->process->stages()->get();
        $count = AuditEntry::count();
        $this->actingAs($this->admin)->postJson('/procesos/'.$this->process->id.'/etapas', $this->stageData())->assertUnprocessable()->assertJsonValidationErrors('process');
        $this->deleteJson('/procesos/'.$this->process->id.'/etapas/'.$rows[1]->id, ['version' => $this->process->version])->assertUnprocessable();
        $this->assertSame($count, AuditEntry::count());
        $this->assertSame(4, $this->process->stages()->count());
        $this->putJson('/procesos/'.$this->process->id, ['name' => 'Compras de suministros', 'version' => $this->process->version])->assertOk();
        $this->assertSame('Compras de suministros', $this->process->fresh()->name);
    }

    public function test_inserted_review_stage_is_executable(): void
    {
        $this->actingAs($this->admin)->postJson('/procesos/'.$this->process->id.'/etapas', $this->stageData())->assertOk();
        $buyer = User::where('email', 'comprador@yraida.test')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $this->actingAs($buyer)->postJson('/compras', ['intent' => 'advance', 'supplier_id' => $supplier->id, 'amount' => '20.00', 'purchase_date' => '2026-10-20'])->assertOk();
        $request = PurchaseRequest::firstOrFail();
        $this->assertNull($request->currentStage->field_name);
        $this->assertSame($buyer->roles->first()->id, $request->currentStage->responsible_role_id);
        $this->get('/compras/'.$request->id)->assertOk()->assertViewHas('editable', true)->assertViewHas('field', null)->assertSee('Esta etapa requiere revisar');
        $this->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version])->assertOk();
        $this->assertSame('approval_date', $request->fresh()->currentStage->field_name);
    }

    public function test_removing_a_date_stage_preserves_chronology_and_closed_request_history(): void
    {
        $approval = $this->process->stages()->where('field_name', 'approval_date')->firstOrFail();
        $this->actingAs($this->admin)->deleteJson('/procesos/'.$this->process->id.'/etapas/'.$approval->id, ['version' => $this->process->version])->assertOk();
        $buyer = User::where('email', 'comprador@yraida.test')->firstOrFail();
        $receiver = User::where('email', 'receptor.verificador@yraida.test')->firstOrFail();
        $payer = User::where('email', 'pagador@yraida.test')->firstOrFail();
        $this->actingAs($buyer)->postJson('/compras', ['intent' => 'advance', 'supplier_id' => Supplier::firstOrFail()->id, 'amount' => '20.00', 'purchase_date' => '2026-10-20'])->assertOk();
        $request = PurchaseRequest::firstOrFail();
        $this->assertSame('receipt_date', $request->currentStage->field_name);
        $this->actingAs($receiver)->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, 'receipt_date' => '2026-10-19'])->assertUnprocessable()->assertJsonValidationErrors('receipt_date');
        $this->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, 'receipt_date' => '2026-10-21'])->assertOk();
        $request->refresh();
        $terminal = $request->currentStage;
        $this->actingAs($payer)->putJson('/compras/'.$request->id, ['intent' => 'advance', 'version' => $request->version, 'payment_date' => '2026-10-22'])->assertOk();
        $this->actingAs($this->admin)->deleteJson('/procesos/'.$this->process->id.'/etapas/'.$terminal->id, ['version' => $this->process->fresh()->version])->assertOk();
        $this->assertSoftDeleted($terminal);
        $this->assertSame($terminal->id, $request->fresh()->currentStage->id);
        $this->actingAs($payer)->get('/compras/'.$request->id)->assertOk()->assertSee('Solicitud finalizada')->assertSee('Pago');
        $this->get('/solicitudes-compra/'.$request->id.'/historial')->assertOk();
    }

    public function test_validation_scope_optimistic_version_and_original_history_are_preserved(): void
    {
        $rows = $this->process->stages()->get();
        $this->actingAs($this->admin)->postJson('/procesos/'.$this->process->id.'/etapas', [])->assertUnprocessable()->assertJsonValidationErrors(['process_stage_id', 'responsible_role_id', 'completion_status_id', 'position', 'version']);
        $bad = $this->stageData();
        $bad['completion_status_id'] = Status::whereHas('type', fn ($query) => $query->where('code', 'users'))->value('id');
        $bad['responsible_role_id'] = Role::create(['name' => 'Rol global'])->id;
        $this->postJson('/procesos/'.$this->process->id.'/etapas', $bad)->assertUnprocessable()->assertJsonValidationErrors(['responsible_role_id', 'completion_status_id']);
        $bad = $this->stageData(1);
        $this->postJson('/procesos/'.$this->process->id.'/etapas', $bad)->assertUnprocessable()->assertJsonValidationErrors('field_name');
        $this->deleteJson('/procesos/'.$this->process->id.'/etapas/'.$rows[0]->id, ['version' => $this->process->version])->assertUnprocessable();
        $this->postJson('/procesos/'.$this->process->id.'/etapas', $this->stageData())->assertOk();
        $this->putJson('/procesos/'.$this->process->id, ['name' => 'Cambio obsoleto', 'version' => $this->process->version])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame('Compras', $this->process->fresh()->name);
    }

    public function test_process_and_stage_management_are_denied_to_company_users_and_guests(): void
    {
        $this->getJson('/procesos')->assertUnauthorized();
        $worker = User::where('company_id', $this->company->id)->firstOrFail();
        $this->actingAs($worker)->getJson('/procesos')->assertForbidden();
        $this->postJson('/procesos', ['name' => 'No autorizado', 'company_id' => $this->company->id])->assertForbidden();
        $this->postJson('/procesos/'.$this->process->id.'/etapas', $this->stageData())->assertForbidden();
        $this->putJson('/procesos/'.$this->process->id, ['name' => 'No autorizado', 'version' => $this->process->version])->assertForbidden();
        $this->deleteJson('/procesos/'.$this->process->id, ['version' => $this->process->version])->assertForbidden();
    }

    public function test_process_create_delete_name_noop_and_escaped_listing_fragment(): void
    {
        $company = Company::create(['name' => 'Segunda empresa', 'tax_id' => 'J-80000002', 'status_id' => $this->company->status_id]);
        $this->actingAs($this->admin)->postJson('/procesos', ['name' => '<script>alert("proceso")</script>', 'company_id' => $company->id])->assertOk();
        $process = ProcessDefinition::where('company_id', $company->id)->firstOrFail();
        $this->get('/procesos?process_id='.$process->id)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
        $this->putJson('/procesos/'.$process->id, ['name' => $process->name, 'version' => $process->version])->assertOk();
        $this->assertSame(1, $process->activities()->count());
        $this->postJson('/procesos', ['name' => 'Duplicado', 'company_id' => $company->id])->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $row = $this->process->stages()->firstOrFail();
        $this->putJson('/procesos/'.$process->id.'/etapas/'.$row->id, $this->stageData())->assertNotFound();
        $this->deleteJson('/procesos/'.$process->id, ['version' => $process->version])->assertOk();
        $this->assertSoftDeleted($process);
        $this->get('/flujos-procesos/'.$process->id.'/historial')->assertOk();
        $response = $this->withHeader('X-Buko-Listing', 'true')->getJson('/procesos?process_id='.$this->process->id.'&q=aprobador&per_page=10');
        $response->assertOk()->assertJsonStructure(['success', 'message', 'data' => ['html'], 'errors', 'meta']);
        $this->assertStringContainsString('Nombre proceso', $response->json('data.html'));
        $this->assertStringContainsString('Aprobación nivel1', $response->json('data.html'));
        $this->assertStringNotContainsString('<script', $response->json('data.html'));
        $this->assertStringNotContainsString('<html', $response->json('data.html'));
    }
}
