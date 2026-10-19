<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaBaseExternaDePrueba;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase, UsaBaseExternaDePrueba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseExterna();
        $this->prepararTablasIntercambio();
    }

    public function test_caseta_user_cannot_open_admin_pages(): void
    {
        $caseta = User::factory()->create(['rol' => 'caseta']);

        foreach (['/admin', '/admin/entradas-salidas', '/admin/empresas', '/admin/intercambios/historial', '/admin/intercambios/patio'] as $url) {
            $this->actingAs($caseta)->get($url)->assertForbidden();
        }
    }

    public function test_caseta_user_cannot_manage_empresas(): void
    {
        $caseta = User::factory()->create(['rol' => 'caseta']);

        $this->actingAs($caseta)
            ->post('/admin/empresas', ['nombre' => 'Intrusa'])
            ->assertForbidden();

        $this->assertDatabaseMissing('empresas', ['nombre' => 'Intrusa'], 'db_externa');
    }

    public function test_caseta_user_can_open_caseta_pages(): void
    {
        $caseta = User::factory()->create(['rol' => 'caseta']);

        foreach (['/caseta', '/caseta/entrada', '/caseta/salida', '/caseta/qr'] as $url) {
            $this->actingAs($caseta)->get($url)->assertOk();
        }
    }

    public function test_caseta_menu_has_entradas_y_salidas_links_and_no_admin_links(): void
    {
        $caseta = User::factory()->create(['rol' => 'caseta']);

        $this->actingAs($caseta)->get('/caseta')
            ->assertOk()
            ->assertSee('Entradas y Salidas')
            ->assertSee(route('caseta.entrada'), false)
            ->assertSee(route('caseta.salida'), false)
            ->assertDontSee(route('admin.empresas.index'), false)
            ->assertDontSee(route('admin.entradas-salidas.historial'), false);
    }

    public function test_admin_can_open_admin_and_caseta_pages(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);

        foreach (['/admin', '/admin/entradas-salidas', '/admin/empresas', '/admin/intercambios/historial', '/admin/intercambios/patio', '/caseta', '/caseta/entrada'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_home_redirects_each_role_to_its_menu(): void
    {
        $this->actingAs(User::factory()->create(['rol' => 'admin']))
            ->get('/')->assertRedirect(route('admin.dashboard'));

        $this->actingAs(User::factory()->create(['rol' => 'caseta']))
            ->get('/')->assertRedirect(route('caseta.menu'));
    }

    public function test_old_urls_redirect_to_new_ones(): void
    {
        $this->get('/hefesto/entrada')->assertRedirect('/caseta/entrada')->assertStatus(301);
        $this->get('/dashboard')->assertRedirect('/admin');
        $this->get('/historial-entradas-salidas')->assertRedirect('/admin/entradas-salidas');
    }
}
