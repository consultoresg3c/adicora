<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Status;
use App\Models\StatusType;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class StatusListingTest extends TestCase
{
    use RefreshDatabase;

    private function foundation(): User
    {
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Estados.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('email', 'admin@buko.local')->firstOrFail();
    }

    public function test_statuses_are_grouped_by_their_own_context_ids(): void
    {
        $admin = $this->foundation();
        $types = StatusType::orderBy('id')->get();

        $response = $this->actingAs($admin)->get('/estados');

        $response->assertViewHas('groups', function ($groups) use ($types): bool {
            return $groups->keys()->all() === $types->pluck('id')->all()
                && $groups->every(fn ($items, $typeId) => $items->every(fn (Status $item) => $item->status_type_id === $typeId));
        });
        foreach ($types as $type) {
            $response->assertSee('id="status-group-'.$type->id.'"', false)->assertSee($type->name);
        }
        $response->assertSee('aria-label="Paginación del listado"', false)->assertSee('registros');
    }

    public function test_search_context_and_availability_filters_work_together(): void
    {
        $admin = $this->foundation();
        $companiesType = StatusType::where('code', 'companies')->firstOrFail();
        $usersType = StatusType::where('code', 'users')->firstOrFail();
        $matching = Status::create(['status_type_id' => $companiesType->id, 'name' => 'Revisión documental', 'is_active' => false]);
        Status::create(['status_type_id' => $companiesType->id, 'name' => 'Revisión habilitada', 'is_active' => true]);
        Status::create(['status_type_id' => $usersType->id, 'name' => 'Revisión de acceso', 'is_active' => false]);

        $this->actingAs($admin)->get('/estados?'.http_build_query([
            'q' => 'revisión', 'status_type_id' => $companiesType->id, 'is_active' => '0',
        ]))->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->getCollection()->modelKeys() === [$matching->id])
            ->assertSee('Revisión documental')->assertDontSee('Revisión habilitada')->assertDontSee('Revisión de acceso');
    }

    public function test_filtered_pagination_preserves_query_and_shows_total(): void
    {
        $admin = $this->foundation();
        $type = StatusType::where('code', 'companies')->firstOrFail();
        for ($index = 1; $index <= 17; $index++) {
            Status::create(['status_type_id' => $type->id, 'name' => sprintf('Revisión %02d', $index), 'is_active' => true]);
        }

        $response = $this->actingAs($admin)->get('/estados?'.http_build_query([
            'q' => 'Revisión', 'status_type_id' => $type->id, 'is_active' => '1', 'page' => 2,
        ]));

        $response->assertViewHas('records', fn (LengthAwarePaginator $records) => $records->total() === 17 && $records->count() === 2 && $records->currentPage() === 2)
            ->assertSee('Revisión 16')->assertSee('Revisión 17')->assertDontSee('Revisión 01');
        $records = $response->viewData('records');
        parse_str(parse_url($records->previousPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('Revisión', $query['q']);
        $this->assertSame((string) $type->id, $query['status_type_id']);
        $this->assertSame('1', $query['is_active']);
    }

    public function test_direct_edit_opens_the_embedded_form_outside_the_current_page(): void
    {
        $admin = $this->foundation();
        $type = StatusType::where('code', 'users')->firstOrFail();
        $status = Status::create(['status_type_id' => $type->id, 'name' => 'Último estado', 'is_active' => false]);

        $response = $this->actingAs($admin)->get('/estados?'.http_build_query(['q' => 'Sin coincidencias', 'editar' => $status->id]))
            ->assertViewHas('openModal', true)->assertViewHas('record', fn (Status $record) => $record->is($status))
            ->assertViewHas('initialTemplate', 'edit-status-'.$status->id)
            ->assertSee('id="edit-status-'.$status->id.'"', false)->assertSee('value="Último estado"', false)
            ->assertSee('name="_method" value="PUT"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="edit-status-'.$status->id.'"'));
    }

    public function test_status_listing_refuses_invalid_filters_without_exposing_a_fragment(): void
    {
        $admin = $this->foundation();

        $this->actingAs($admin)->getJson('/estados?status_type_id=texto&is_active=3')
            ->assertUnprocessable()->assertJsonValidationErrors(['status_type_id', 'is_active'])
            ->assertJsonPath('success', false);
    }

    public function test_empty_search_keeps_the_filters_and_pagination_visible(): void
    {
        $admin = $this->foundation();

        $this->actingAs($admin)->get('/estados?q=Noexisteestado')
            ->assertSee('Sin estados para estos filtros')->assertSee('Sin registros')
            ->assertSee('aria-label="Paginación del listado"', false)
            ->assertSee('data-list-filters', false)->assertSee('id="create-status"', false);
    }

    public function test_listing_requests_return_only_the_results_and_local_edit_templates(): void
    {
        $admin = $this->foundation();
        $status = Status::whereHas('type', fn ($query) => $query->where('code', 'users'))->where('is_active', true)->firstOrFail();

        $response = $this->actingAs($admin)->getJson('/estados?q=Activo', ['X-Buko-Listing' => 'true'])
            ->assertJsonPath('success', true)->assertJsonStructure(['success', 'message', 'data' => ['html'], 'errors', 'meta']);

        $html = $response->json('data.html');
        $this->assertStringContainsString('data-list-results', $html);
        $this->assertStringContainsString('id="edit-status-'.$status->id.'"', $html);
        $this->assertStringContainsString('action="'.route('statuses.update', $status).'"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringNotContainsString('data-list-filters', $html);
        $this->assertStringNotContainsString('id="create-status"', $html);
        $this->assertStringNotContainsString('<dialog', $html);
        $this->assertStringNotContainsString('data-record=', $html);
    }

    public function test_unauthorized_users_cannot_read_the_embedded_catalog(): void
    {
        $this->foundation();
        $user = User::factory()->create([
            'status_id' => Status::whereHas('type', fn ($query) => $query->where('code', 'users'))->where('is_active', true)->value('id'),
        ]);
        $role = Role::create(['name' => 'Consulta global', 'company_id' => null]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)->getJson('/estados')->assertForbidden()->assertJsonPath('success', false);
    }
}
