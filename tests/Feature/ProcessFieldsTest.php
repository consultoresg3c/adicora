<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProcessDefinition;
use App\Models\ProcessField;
use App\Models\PurchaseRequest;
use App\Models\PurchaseStage;
use App\Models\Status;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\PurchaseSetupService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $buyer;

    private ProcessDefinition $process;

    private PurchaseStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Fields.Test.Local!']);
        $this->seed(FoundationSeeder::class);
        $this->admin = User::where('email', 'admin@buko.local')->firstOrFail();
        $company = Company::create(['name' => 'Yraida Guillén', 'tax_id' => 'FIELDS-TEST', 'status_id' => Status::whereHas('type', fn ($q) => $q->where('code', 'companies'))->where('is_active', true)->value('id')]);
        app(PurchaseSetupService::class)->prepare($company, $this->admin, 'Fields.Worker.Local!');
        $this->process = ProcessDefinition::firstOrFail();
        $this->stage = $this->process->stages()->firstOrFail();
        $this->buyer = User::where('company_id', $company->id)->where('name', 'comprador')->firstOrFail();
    }

    private function configure(PurchaseStage $stage, array $extra = [])
    {
        $data = $stage->only(['process_stage_id', 'responsible_role_id', 'completion_status_id', 'initial_status_id', 'position', 'is_start', 'is_end', 'review_only']);

        return $this->actingAs($this->admin)->putJson('/procesos/'.$this->process->id.'/etapas/'.$stage->id, array_replace($data, ['version' => $this->process->fresh()->version], $extra));
    }

    private function add(string $name, string $type, array $options = []): ProcessField
    {
        $this->configure($this->stage, ['new_field' => ['name' => $name, 'data_type' => $type] + $options])->assertOk();

        return $this->process->fields()->where('name', $name)->firstOrFail();
    }

    private function purchaseData(): array
    {
        return ['supplier_id' => Supplier::firstOrFail()->id, 'amount' => '10.00', 'purchase_date' => '2026-10-20'];
    }

    public function test_required_custom_fields_allow_drafts_but_block_sending_and_preserve_noop_audit(): void
    {
        $field = $this->add('Referencia', 'text', ['text_length' => 12, 'is_required' => true]);
        $this->actingAs($this->buyer)->postJson('/compras', ['intent' => 'save'])->assertOk();
        $purchase = PurchaseRequest::firstOrFail();
        $this->putJson('/compras/'.$purchase->id, ['intent' => 'advance', 'version' => $purchase->version] + $this->purchaseData())->assertUnprocessable()->assertJsonValidationErrors('custom_fields.'.$field->id);
        $this->putJson('/compras/'.$purchase->id, ['intent' => 'save', 'version' => $purchase->version, 'custom_fields' => [$field->id => 'REF-1']])->assertOk();
        $purchase->refresh();
        $events = $purchase->activities()->count();
        $this->putJson('/compras/'.$purchase->id, ['intent' => 'save', 'version' => $purchase->version])->assertOk();
        $this->assertSame($events, $purchase->activities()->count());
        $this->assertSame($purchase->version, $purchase->fresh()->version);
        $this->putJson('/compras/'.$purchase->id, ['intent' => 'advance', 'version' => $purchase->version] + $this->purchaseData())->assertOk();
        $this->assertDatabaseHas('purchase_field_values', ['purchase_request_id' => $purchase->id, 'process_field_id' => $field->id, 'value' => 'REF-1']);
        $this->assertSame('hidden', $purchase->fresh()->currentStage->fieldRules()->where('process_field_id', $field->id)->value('mode'));
    }

    public function test_all_six_types_validate_and_store_exact_values_including_zero(): void
    {
        $specs = [
            ['Texto', 'text', ['text_length' => 8], 'hola', 'hola', 'demasiado largo'],
            ['Entero', 'integer', [], '0', '0', '1.2'],
            ['Número', 'numeric', ['decimal_places' => 3], '-123.450', '-123.450', '1.2345'],
            ['Fecha', 'date', [], '2026-10-26', '2026-10-26', '2026-02-30'],
            ['Hora', 'time', [], '13:45', '13:45:00', '25:00'],
            ['Momento', 'datetime', [], '2026-10-26T13:45', '2026-10-26 13:45:00', '2026-02-30T13:45'],
        ];
        $valid = [];
        $invalid = [];
        $expected = [];
        foreach ($specs as [$name, $type, $options, $value, $stored, $bad]) {
            $field = $this->add($name, $type, $options);
            $valid[$field->id] = $value;
            $invalid[$field->id] = $bad;
            $expected[$field->id] = $stored;
        }
        $this->actingAs($this->buyer)->postJson('/compras', ['intent' => 'save', 'custom_fields' => $invalid])->assertUnprocessable()->assertJsonValidationErrors(array_map(fn ($id) => 'custom_fields.'.$id, array_keys($invalid)));
        $this->postJson('/compras', ['intent' => 'save', 'custom_fields' => $valid])->assertOk();
        $purchase = PurchaseRequest::firstOrFail();
        foreach ($expected as $id => $value) {
            $this->assertDatabaseHas('purchase_field_values', ['purchase_request_id' => $purchase->id, 'process_field_id' => $id, 'value' => $value]);
        }
        $this->get('/compras/'.$purchase->id)->assertOk()->assertSee('type="datetime-local"', false)->assertSee('step="0.001"', false);
    }

    public function test_amount_can_be_edited_in_multiple_stages_and_hidden_fields_do_not_leak(): void
    {
        $field = $this->add('Secreto interno', 'text', ['text_length' => 60]);
        $approval = $this->process->stages()->where('position', 2)->firstOrFail();
        $amount = $this->process->fields()->where('attribute', 'amount')->firstOrFail();
        $supplier = $this->process->fields()->where('attribute', 'supplier_id')->firstOrFail();
        $this->configure($approval, ['field_rules' => [$amount->id => ['mode' => 'edit', 'is_required' => true], $supplier->id => ['mode' => 'hidden']]])->assertOk();
        $this->actingAs($this->buyer)->postJson('/compras', ['intent' => 'advance', 'custom_fields' => [$field->id => 'VALOR-PRIVADO-123']] + $this->purchaseData())->assertOk();
        $purchase = PurchaseRequest::firstOrFail();
        $approver = User::where('company_id', $this->buyer->company_id)->where('name', 'aprobador N1')->firstOrFail();
        $this->actingAs($approver)->get('/compras/'.$purchase->id)->assertOk()->assertSee('name="amount"', false)->assertDontSee('VALOR-PRIVADO-123')->assertDontSee('Secreto interno')->assertDontSee('Librería de Nacho');
        $this->get('/solicitudes-compra/'.$purchase->id.'/historial')->assertOk()->assertDontSee('VALOR-PRIVADO-123')->assertDontSee('Secreto interno');
        $this->get('/compras')->assertOk()->assertDontSee('Librería de Nacho');
        $this->get('/compras?q=Nacho')->assertViewHas('records', fn ($records) => $records->isEmpty());
        $this->putJson('/compras/'.$purchase->id, ['intent' => 'save', 'version' => $purchase->version, 'custom_fields' => [$field->id => 'intrusión']])->assertUnprocessable()->assertJsonValidationErrors('custom_fields.'.$field->id);
        $this->putJson('/compras/'.$purchase->id, ['intent' => 'advance', 'version' => $purchase->version, 'amount' => '25.50', 'approval_date' => '2026-10-21'])->assertOk();
        $this->assertSame('25.50', $purchase->fresh()->amount);
    }

    public function test_field_changes_work_during_active_purchases_and_stale_forms_are_rejected(): void
    {
        $oldVersion = $this->process->version;
        $this->actingAs($this->buyer)->postJson('/compras', ['intent' => 'save'])->assertOk();
        $purchase = PurchaseRequest::firstOrFail();
        $field = $this->add('Observaciones', 'text', ['text_length' => 100]);
        $this->assertSame(3, $this->process->stages()->whereHas('fieldRules', fn ($q) => $q->where('process_field_id', $field->id)->where('mode', 'hidden'))->count());
        $this->actingAs($this->buyer)->putJson('/compras/'.$purchase->id, ['intent' => 'save', 'version' => $purchase->version, 'configuration_version' => $oldVersion])->assertUnprocessable()->assertJsonValidationErrors('configuration_version');
        $this->configure($this->stage, ['position' => 2])->assertUnprocessable()->assertJsonValidationErrors('process');
    }

    public function test_review_only_prevents_editing_and_invalid_matrix_assignments_are_rejected(): void
    {
        $amount = $this->process->fields()->where('attribute', 'amount')->firstOrFail();
        $this->configure($this->stage, ['field_rules' => [$amount->id => ['mode' => 'read', 'is_required' => true]]])->assertUnprocessable()->assertJsonValidationErrors('field_rules.'.$amount->id.'.is_required');
        $this->configure($this->stage, ['field_rules' => [999999 => ['mode' => 'edit']]])->assertUnprocessable()->assertJsonValidationErrors('field_rules');
        $this->configure($this->stage, ['review_only' => true])->assertOk();
        $this->actingAs($this->buyer)->get('/compras/crear')->assertOk()->assertDontSee('name="amount"', false);
        $this->postJson('/compras', ['intent' => 'save', 'amount' => '100'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_definition_validation_escape_soft_delete_and_permissions(): void
    {
        $this->configure($this->stage, ['new_field' => ['name' => 'Sin longitud', 'data_type' => 'text']])->assertUnprocessable()->assertJsonValidationErrors('new_field.text_length');
        $this->configure($this->stage, ['new_field' => ['name' => 'Sin precisión', 'data_type' => 'numeric']])->assertUnprocessable()->assertJsonValidationErrors('new_field.decimal_places');
        $field = $this->add('<script>alert("campo")</script>', 'text', ['text_length' => 20]);
        $this->actingAs($this->admin)->get('/procesos')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)->assertSee('Lectura')->assertSee('Obligatorio');
        $this->actingAs($this->buyer)->postJson('/compras', ['intent' => 'save', 'custom_fields' => [$field->id => '<b>dato</b>']])->assertOk();
        $purchase = PurchaseRequest::firstOrFail();
        $this->get('/compras/'.$purchase->id)->assertSee('&lt;b&gt;dato&lt;/b&gt;', false)->assertDontSee('<b>dato</b>', false);
        $url = '/procesos/'.$this->process->id.'/campos/'.$field->id;
        $this->deleteJson($url, ['version' => $this->process->fresh()->version])->assertForbidden();
        $this->actingAs($this->admin)->deleteJson($url, ['version' => $this->process->fresh()->version])->assertOk();
        $this->assertSoftDeleted($field);
        $this->assertDatabaseHas('purchase_field_values', ['process_field_id' => $field->id, 'value' => '<b>dato</b>']);
        $this->get('/campos-procesos/'.$field->id.'/historial')->assertOk()->assertSee('Registro eliminado');
        $this->actingAs($this->buyer)->get('/compras/'.$purchase->id)->assertDontSee('&lt;b&gt;dato&lt;/b&gt;', false);
    }
}
