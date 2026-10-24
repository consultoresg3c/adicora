<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingResponseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['buko.initial_admin.email' => 'admin@buko.local', 'buko.initial_admin.password' => 'Buko.Test.2026!']);
        $this->seed(FoundationSeeder::class);

        return User::where('is_super_admin', true)->firstOrFail();
    }

    public function test_fetch_returns_only_the_blade_results_and_uniform_json_contract(): void
    {
        $this->actingAs($this->admin());
        foreach (['/estados', '/menus', '/sucursales'] as $url) {
            $response = $this->getJson($url, ['X-Buko-Listing' => 'true'])->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['success', 'message', 'data' => ['html'], 'errors', 'meta'])
                ->assertHeader('Vary', 'Accept, X-Buko-Listing');
            $html = $response->json('data.html');
            $this->assertStringContainsString('data-list-results', $html);
            $this->assertStringNotContainsString('data-list-filters', $html);
            $this->assertDoesNotMatchRegularExpression('/<!doctype|<html\b|<head\b|<script\b|<aside\b/i', $html);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_normal_navigation_and_long_forms_remain_complete_blade_pages(): void
    {
        $this->actingAs($this->admin());
        foreach (['/inicio', '/estados', '/menus', '/empresas/create'] as $url) {
            $this->get($url)->assertOk()->assertSee('<!doctype html>', false)->assertSee('data-app-shell', false);
        }
        $this->get('/estados')->assertSee('create-status', false)->assertSee('data-dialog-template', false)
            ->assertDontSee('data-record=', false)->assertDontSee('data-module-src', false);
    }

    public function test_user_values_are_escaped_in_both_cells_and_blade_form_templates(): void
    {
        $this->actingAs($this->admin());
        $payload = '"><img src=x onerror=alert(1)>';
        $status = Status::whereHas('type', fn ($query) => $query->where('code', 'menus'))->firstOrFail();
        $status->update(['name' => $payload]);
        MenuItem::where('is_parent', false)->firstOrFail()->update(['name' => $payload]);

        foreach (['/menus', '/estados'] as $url) {
            $html = $this->getJson($url, ['X-Buko-Listing' => 'true'])->assertOk()->json('data.html');
            $this->assertStringContainsString(e($payload), $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringNotContainsString('value="'.$payload, $html);
        }
    }

    public function test_guest_fetches_keep_the_authentication_error_contract(): void
    {
        $this->getJson('/menus', ['X-Buko-Listing' => 'true'])->assertUnauthorized()
            ->assertJsonPath('success', false)->assertJsonMissingPath('data.html');
    }
}
