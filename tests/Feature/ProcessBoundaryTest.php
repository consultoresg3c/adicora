<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProcessDefinition;
use App\Models\ProcessStep;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\PurchaseSetupService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private ProcessDefinition $process;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Boundary.Test!']);
        $this->seed(FoundationSeeder::class);
        $this->admin = User::where('email', 'admin@buko.local')->firstOrFail();
        $company = Company::create(['name' => 'Yraida Guillén', 'tax_id' => 'J-70000001', 'status_id' => Status::whereHas('type', fn ($q) => $q->where('code', 'companies'))->where('is_active', true)->value('id')]);
        app(PurchaseSetupService::class)->prepare($company, $this->admin, 'Local.Boundary.Test!');
        $this->buyer = User::where('email', 'comprador@yraida.test')->firstOrFail();
        $this->process = ProcessDefinition::firstOrFail();
    }

    private function data(PurchaseStage $row): array
    {
        return $row->only(['process_stage_id', 'responsible_role_id', 'completion_status_id', 'initial_status_id', 'field_name', 'position', 'is_start', 'is_end']) + ['version' => $this->process->fresh()->version];
    }

    private function buyerData(): array
    {
        return ['intent' => 'advance', 'supplier_id' => Supplier::firstOrFail()->id, 'amount' => '20.00', 'purchase_date' => '2026-10-20'];
    }

    public function test_new_stage_defaults_both_flags_off_and_forms_preserve_edit_values(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/procesos');
        preg_match('~<template id="create-process-stage".*?</template>~s', $response->getContent(), $match);
        $this->assertNotEmpty($match);
        foreach (['is_start', 'is_end'] as $field) {
            preg_match('~<input[^>]*type="checkbox"[^>]*name="'.$field.'"[^>]*>~', $match[0], $checkbox);
            $this->assertNotEmpty($checkbox);
            $this->assertStringNotContainsString('checked', $checkbox[0]);
        }
        $first = $this->process->stages()->firstOrFail();
        $this->postJson('/procesos/'.$this->process->id.'/etapas', ['new_stage_name' => 'Revisión adicional', 'responsible_role_id' => $first->responsible_role_id, 'completion_status_id' => $first->completion_status_id, 'position' => 2, 'version' => $this->process->version])->assertOk();
        $new = $this->process->stages()->where('position', 2)->firstOrFail();
        $this->assertFalse($new->is_start);
        $this->assertFalse($new->is_end);
        $this->assertNull($new->initial_status_id);
        $this->assertTrue($first->fresh()->is_start);
        $this->assertTrue($this->process->stages()->where('field_name', 'payment_date')->firstOrFail()->is_end);
    }

    public function test_multiple_starts_for_same_role_require_explicit_choice_and_reject_unassigned_stages(): void
    {
        $first = $this->process->stages()->firstOrFail();
        $approval = $this->process->stages()->where('field_name', 'approval_date')->firstOrFail();
        $data = $this->data($approval);
        $data['is_start'] = true;
        $data['initial_status_id'] = $first->initial_status_id;
        $data['responsible_role_id'] = $first->responsible_role_id;
        $this->actingAs($this->admin)->putJson('/procesos/'.$this->process->id.'/etapas/'.$approval->id, $data)->assertOk();
        $this->assertSame(2, $this->process->stages()->where('is_start', true)->count());
        $this->actingAs($this->buyer)->get('/compras')->assertViewHas('canCreate', true);
        $this->get('/compras/crear')->assertOk()->assertViewIs('modules.purchases.choose-start')->assertSee('Aprobación nivel1')->assertSee('solicitud compra');
        $this->get('/compras/crear?start_stage_id='.$approval->id)->assertViewHas('fieldViews', fn ($fields) => $fields->contains(fn ($item) => $item['field']->attribute === 'approval_date' && $item['editable']))->assertSee('name="start_stage_id"', false);
        $this->postJson('/compras', ['intent' => 'advance', 'approval_date' => '2026-10-21'])->assertUnprocessable()->assertJsonValidationErrors('start_stage_id');
        $receipt = $this->process->stages()->where('field_name', 'receipt_date')->firstOrFail();
        $this->getJson('/compras/crear?start_stage_id='.$receipt->id)->assertNotFound();
        $this->postJson('/compras', ['intent' => 'save', 'start_stage_id' => $receipt->id])->assertUnprocessable()->assertJsonValidationErrors('start_stage_id');
        $this->postJson('/compras', ['intent' => 'advance', 'start_stage_id' => $approval->id, 'approval_date' => '2026-10-21'])->assertOk();
        $record = PurchaseRequest::firstOrFail();
        $this->assertSame($receipt->id, $record->purchase_stage_id);
        $this->assertNull($record->purchase_date);
        $this->assertSame('2026-10-21', $record->approval_date->format('Y-m-d'));
        $change = $approval->activities()->latest('id')->firstOrFail()->changes;
        $this->assertSame(['before' => false, 'after' => true], $change['is_start']);
    }

    public function test_new_start_for_another_role_is_available_only_to_that_role(): void
    {
        $approval = $this->process->stages()->where('field_name', 'approval_date')->firstOrFail();
        $data = $this->data($approval);
        $data['is_start'] = true;
        $data['initial_status_id'] = $this->process->stages()->firstOrFail()->initial_status_id;
        $this->actingAs($this->admin)->putJson('/procesos/'.$this->process->id.'/etapas/'.$approval->id, $data)->assertOk();
        $approver = User::where('email', 'aprobador.n1@yraida.test')->firstOrFail();
        $this->actingAs($approver)->get('/compras/crear')->assertOk()->assertViewHas('fieldViews', fn ($fields) => $fields->contains(fn ($item) => $item['field']->attribute === 'approval_date' && $item['editable']));
        $this->postJson('/compras', ['intent' => 'save', 'start_stage_id' => $approval->id])->assertOk();
        $this->assertSame($approver->id, PurchaseRequest::firstOrFail()->created_by_id);
        $receiver = User::where('email', 'receptor.verificador@yraida.test')->firstOrFail();
        $this->actingAs($receiver)->getJson('/compras/crear?start_stage_id='.$approval->id)->assertForbidden();
        $this->postJson('/compras', ['intent' => 'save', 'start_stage_id' => $approval->id])->assertForbidden();
    }

    public function test_end_flag_terminates_at_a_middle_stage_and_removes_its_outgoing_edge(): void
    {
        $approval = $this->process->stages()->where('field_name', 'approval_date')->firstOrFail();
        $data = $this->data($approval);
        $data['is_end'] = true;
        $this->actingAs($this->admin)->putJson('/procesos/'.$this->process->id.'/etapas/'.$approval->id, $data)->assertOk();
        $this->assertFalse(ProcessStep::where('origin_stage_id', $approval->process_stage_id)->exists());
        $this->assertSame(2, $this->process->stages()->where('is_end', true)->count());
        $this->actingAs($this->buyer)->postJson('/compras', $this->buyerData())->assertOk();
        $record = PurchaseRequest::firstOrFail();
        $approver = User::where('email', 'aprobador.n1@yraida.test')->firstOrFail();
        $this->actingAs($approver)->get('/compras/'.$record->id)->assertViewHas('isFinalStage', true);
        $this->putJson('/compras/'.$record->id, ['intent' => 'advance', 'version' => $record->version, 'approval_date' => '2026-10-21'])->assertOk();
        $this->assertNotNull($record->fresh()->completed_at);
        $this->assertSame($approval->id, $record->fresh()->purchase_stage_id);
        $this->assertNull($record->fresh()->receipt_date);
    }

    public function test_last_position_without_end_flag_never_finishes_implicitly(): void
    {
        $payment = $this->process->stages()->where('field_name', 'payment_date')->firstOrFail();
        $data = $this->data($payment);
        $data['is_end'] = false;
        $this->actingAs($this->admin)->putJson('/procesos/'.$this->process->id.'/etapas/'.$payment->id, $data)->assertOk();
        $this->get('/procesos')->assertSee('Sin destino definido');
        $this->actingAs($this->buyer)->postJson('/compras', $this->buyerData())->assertOk();
        $record = PurchaseRequest::firstOrFail();
        foreach ([['aprobador.n1@yraida.test', 'approval_date', '2026-10-21'], ['receptor.verificador@yraida.test', 'receipt_date', '2026-10-22']] as [$email, $field, $date]) {
            $this->actingAs(User::where('email', $email)->firstOrFail())->putJson('/compras/'.$record->id, ['intent' => 'advance', 'version' => $record->fresh()->version, $field => $date])->assertOk();
        }
        $this->actingAs(User::where('email', 'pagador@yraida.test')->firstOrFail())->putJson('/compras/'.$record->id, ['intent' => 'advance', 'version' => $record->fresh()->version, 'payment_date' => '2026-10-23'])->assertUnprocessable()->assertJsonValidationErrors('workflow');
        $this->assertNull($record->fresh()->completed_at);
        $this->assertNull($record->fresh()->payment_date);
    }

    public function test_start_requires_initial_status_and_flags_validate_as_booleans(): void
    {
        $row = $this->process->stages()->where('field_name', 'approval_date')->firstOrFail();
        $data = $this->data($row);
        $data['is_start'] = true;
        $this->actingAs($this->admin)->putJson('/procesos/'.$this->process->id.'/etapas/'.$row->id, $data)->assertUnprocessable()->assertJsonValidationErrors('initial_status_id');
        $this->assertFalse($row->fresh()->is_start);
        $data['is_start'] = 'not-a-boolean';
        $data['is_end'] = 'not-a-boolean';
        $this->putJson('/procesos/'.$this->process->id.'/etapas/'.$row->id, $data)->assertUnprocessable()->assertJsonValidationErrors(['is_start', 'is_end']);
        $first = $this->process->stages()->firstOrFail();
        $data = $this->data($first);
        $data['is_start'] = false;
        $this->putJson('/procesos/'.$this->process->id.'/etapas/'.$first->id, $data)->assertOk();
        $this->assertNull($first->fresh()->initial_status_id);
        $this->actingAs($this->buyer)->get('/compras')->assertViewHas('canCreate', false);
        $this->getJson('/compras/crear')->assertForbidden();
    }
}
