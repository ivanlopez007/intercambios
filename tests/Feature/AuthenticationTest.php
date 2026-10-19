<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_application_routes(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/entradas-salidas')->assertRedirect(route('login'));
        $this->get('/caseta')->assertRedirect(route('login'));
        $this->post('/caseta/visitas/salida')->assertRedirect(route('login'));
        $this->post('/admin/empresas')->assertRedirect(route('login'));
    }

    public function test_diagnostic_endpoint_is_not_available(): void
    {
        $this->get('/us-ex')->assertNotFound();
    }

    public function test_admin_lands_on_dashboard_after_login(): void
    {
        $user = User::factory()->create([
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertOk();

        $this->post(route('logout'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_caseta_user_lands_on_caseta_menu_after_login(): void
    {
        $user = User::factory()->create(['rol' => 'caseta']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('caseta.menu'));
    }

    public function test_caseta_user_is_not_sent_back_to_an_admin_page_after_login(): void
    {
        $user = User::factory()->create(['rol' => 'caseta']);

        $this->get('/admin/empresas')->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('caseta.menu'));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'activo' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create(['rol' => 'caseta']);

        $this->actingAs($user)->get('/caseta')->assertOk();

        $user->update(['activo' => false]);

        $this->get('/caseta')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_user_gets_json_401_from_caseta_api(): void
    {
        $user = User::factory()->create(['rol' => 'caseta', 'activo' => false]);

        $this->actingAs($user)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => 1])
            ->assertUnauthorized();

        $this->assertGuest();
    }
}
