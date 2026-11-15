<?php

namespace Tests\Feature;

use App\Models\EmpleadoRh;
use App\Models\User;
use App\Services\AccesoCaseta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaEmpleadosRh;
use Tests\Concerns\UsaBaseExternaDePrueba;
use Tests\TestCase;

/**
 * Login con identidad y contraseña únicas con RH (ESTADO_BD_UNIFICADA.txt, secciones 5 a 7).
 */
class AccesoRhTest extends TestCase
{
    use CreaEmpleadosRh, RefreshDatabase, UsaBaseExternaDePrueba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseExterna();
        $this->prepararTablasIntercambio();
        $this->crearSistemas();
    }

    private function login(string $email, string $password, bool $recordar = false)
    {
        return $this->post(route('login.store'), array_filter([
            'email' => $email,
            'password' => $password,
            'remember' => $recordar ? '1' : null,
        ]));
    }

    // ---------------------------------------------------------------- Login

    public function test_cuenta_generica_entra_con_su_propia_contrasena(): void
    {
        $generica = User::factory()->create(['email' => 'caseta@industrialhefesto.com', 'rol' => 'caseta', 'password' => 'clave-generica']);

        $this->login('caseta@industrialhefesto.com', 'clave-generica')->assertRedirect(route('caseta.menu'));

        $this->assertAuthenticatedAs($generica);
    }

    public function test_empleado_entra_con_su_contrasena_de_rh_y_resuelve_su_perfil(): void
    {
        $empleado = $this->crearEmpleado('HEF-0000482', 'equipment@industrialhefesto.com', 'clave-de-rh');
        $perfil = $this->perfilVinculado($empleado, 'equipment@industrialhefesto.com');

        $this->login('equipment@industrialhefesto.com', 'clave-de-rh')
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', '¡Bienvenido de nuevo, Natalia Martinez Tafoya!')
            ->assertSessionHas(AccesoCaseta::HUELLA);

        $this->assertAuthenticatedAs($perfil);
        $this->assertSame(1, User::count(), 'No debe crear otro perfil.');
    }

    public function test_un_perfil_vinculado_nunca_entra_con_la_contrasena_local(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'ruben@industrialhefesto.com', 'clave-de-rh');
        $this->perfilVinculado($empleado, 'ruben@industrialhefesto.com');
        User::where('user_id', $empleado)->update(['password' => Hash::make('clave-vieja-de-caseta')]);

        $this->login('ruben@industrialhefesto.com', 'clave-vieja-de-caseta')
            ->assertSessionHasErrors(['email' => AccesoCaseta::ERROR_GENERICO]);

        $this->assertGuest();
    }

    public function test_mayusculas_y_espacios_en_el_correo_no_afectan(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', ' Ivan.Lopez@IndustrialHefesto.com ', 'clave-de-rh');
        $perfil = $this->perfilVinculado($empleado, 'ivan.lopez@industrialhefesto.com');

        $this->login('  IVAN.lopez@industrialhefesto.COM', 'clave-de-rh')->assertRedirect();

        $this->assertAuthenticatedAs($perfil);
    }

    public function test_contrasena_incorrecta_y_correo_inexistente_dan_el_mismo_mensaje(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $this->perfilVinculado($empleado, 'persona@industrialhefesto.com');
        User::factory()->create(['email' => 'caseta@industrialhefesto.com', 'password' => 'clave-generica']);

        foreach ([
            ['persona@industrialhefesto.com', 'incorrecta'],
            ['caseta@industrialhefesto.com', 'incorrecta'],
            ['nadie@industrialhefesto.com', 'clave-de-rh'],
        ] as [$correo, $password]) {
            $this->login($correo, $password)->assertSessionHasErrors(['email' => AccesoCaseta::ERROR_GENERICO]);
            $this->assertGuest();
        }
    }

    public function test_correos_de_relleno_nunca_entran(): void
    {
        foreach (['HEF-0000482@empresa.local', 'alguien@example.com', 'notiene@industrialhefesto.com', 'notiene1@industrialhefesto.com'] as $i => $correo) {
            $this->crearEmpleado("HEF-R{$i}", $correo, 'clave-de-rh');

            $this->login($correo, 'clave-de-rh')->assertSessionHasErrors(['email' => AccesoCaseta::ERROR_GENERICO]);
        }

        $this->assertGuest();
        $this->assertSame(0, User::count(), 'Un correo de relleno no crea perfiles.');
    }

    public function test_sin_intercambios_en_rh_no_entra(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com', acceso: false);
        $this->perfilVinculado($empleado, 'persona@industrialhefesto.com');

        $this->login('persona@industrialhefesto.com', 'clave-de-rh')
            ->assertSessionHasErrors(['email' => AccesoCaseta::SIN_ACCESO]);

        // Con el sistema desactivado en RH tampoco.
        $this->darAcceso($empleado);
        DB::table('sistemas')->where('clave', EmpleadoRh::SISTEMA)->update(['activo' => false]);

        $this->login('persona@industrialhefesto.com', 'clave-de-rh')
            ->assertSessionHasErrors(['email' => AccesoCaseta::SIN_ACCESO]);

        $this->assertGuest();
    }

    public function test_con_cambio_de_contrasena_pendiente_en_rh_no_entra(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com', mustChange: true);
        $this->perfilVinculado($empleado, 'persona@industrialhefesto.com');

        $this->login('persona@industrialhefesto.com', 'clave-de-rh')
            ->assertSessionHasErrors(['email' => AccesoCaseta::CAMBIAR_PASSWORD]);

        $this->assertGuest();
    }

    public function test_empleado_dado_de_baja_no_entra(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $this->perfilVinculado($empleado, 'persona@industrialhefesto.com');
        DB::table('users')->where('id', $empleado)->update(['deleted_at' => now()]);

        $this->login('persona@industrialhefesto.com', 'clave-de-rh')
            ->assertSessionHasErrors(['email' => AccesoCaseta::ERROR_GENERICO]);

        $this->assertGuest();
    }

    public function test_perfil_desactivado_en_caseta_no_entra(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $this->perfilVinculado($empleado, 'persona@industrialhefesto.com')->update(['activo' => false]);

        $this->login('persona@industrialhefesto.com', 'clave-de-rh')
            ->assertSessionHasErrors(['email' => AccesoCaseta::DESACTIVADA]);

        $this->assertGuest();
    }

    public function test_primer_login_de_un_empleado_nuevo_crea_su_perfil_con_rol_caseta(): void
    {
        $empleado = $this->crearEmpleado('HEF-0000900', 'nuevo@industrialhefesto.com', 'clave-de-rh', 'Pedro', 'Ramos', 'Diaz');

        $this->login('nuevo@industrialhefesto.com', 'clave-de-rh')->assertRedirect(route('caseta.menu'));

        $perfil = User::sole();
        $this->assertAuthenticatedAs($perfil);
        $this->assertSame($empleado, $perfil->user_id);
        $this->assertSame('caseta', $perfil->rol);
        $this->assertTrue($perfil->activo);
        $this->assertSame('nuevo@industrialhefesto.com', $perfil->email);
        $this->assertSame('Pedro Ramos Diaz', $perfil->name);
        $this->assertFalse(Hash::check('clave-de-rh', $perfil->password), 'La contraseña local no es la de RH.');
    }

    public function test_si_cambio_el_correo_en_rh_se_actualiza_el_del_perfil(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'correo.nuevo@industrialhefesto.com');
        $perfil = $this->perfilVinculado($empleado, 'correo.viejo@industrialhefesto.com');

        $this->login('correo.nuevo@industrialhefesto.com', 'clave-de-rh')->assertRedirect();

        $this->assertAuthenticatedAs($perfil);
        $this->assertSame('correo.nuevo@industrialhefesto.com', $perfil->fresh()->email);
    }

    public function test_no_toma_el_perfil_de_otra_cuenta_con_el_mismo_correo(): void
    {
        $generica = User::factory()->create(['email' => 'compartido@industrialhefesto.com', 'password' => 'clave-generica']);
        $this->crearEmpleado('HEF-1', 'compartido@industrialhefesto.com', 'clave-de-rh');

        $this->login('compartido@industrialhefesto.com', 'clave-de-rh')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($generica->fresh()->user_id, 'No se vincula por correo.');
        $this->assertSame(1, User::count());
    }

    public function test_el_login_nunca_escribe_en_las_tablas_de_rh(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $antes = DB::table('users')->where('id', $empleado)->first();

        $this->login('persona@industrialhefesto.com', 'clave-de-rh')->assertRedirect();
        $this->post(route('logout'));

        $this->assertEquals($antes, DB::table('users')->where('id', $empleado)->first());
    }

    // ---------------------------------------------------------------- Revalidación en cada petición

    public function test_quitar_intercambios_en_rh_cierra_la_sesion_abierta(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'caseta');

        $this->comoVinculado($perfil)->get(route('caseta.menu'))->assertOk();

        $this->quitarAcceso($empleado);
        $this->get(route('caseta.menu'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => AccesoCaseta::SIN_ACCESO]);
        $this->assertGuest();

        // Las peticiones de la caseta (JSON) reciben 401.
        $this->comoVinculado($perfil)->getJson(route('caseta.visitas.activas'))->assertUnauthorized();

        // Con el acceso de vuelta, entra otra vez.
        $this->darAcceso($empleado);
        $this->login('persona@industrialhefesto.com', 'clave-de-rh')->assertRedirect(route('caseta.menu'));
        $this->assertAuthenticatedAs($perfil);
    }

    public function test_cambiar_la_contrasena_en_rh_cierra_la_sesion_y_solo_sirve_la_nueva(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com', 'clave-vieja');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'caseta');

        $this->login('persona@industrialhefesto.com', 'clave-vieja')->assertRedirect();
        $this->get(route('caseta.menu'))->assertOk();

        DB::table('users')->where('id', $empleado)->update(['password' => Hash::make('clave-nueva')]);

        $this->get(route('caseta.menu'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => AccesoCaseta::PASSWORD_CAMBIO]);
        $this->assertGuest();

        $this->login('persona@industrialhefesto.com', 'clave-vieja')->assertSessionHasErrors(['email' => AccesoCaseta::ERROR_GENERICO]);
        $this->login('persona@industrialhefesto.com', 'clave-nueva')->assertRedirect(route('caseta.menu'));
        $this->assertAuthenticatedAs($perfil);
    }

    public function test_cambio_pendiente_o_baja_en_rh_cierran_la_sesion_abierta(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'caseta');

        DB::table('users')->where('id', $empleado)->update(['must_change_password' => true]);
        $this->comoVinculado($perfil)->get(route('caseta.menu'))
            ->assertSessionHasErrors(['email' => AccesoCaseta::CAMBIAR_PASSWORD]);
        $this->assertGuest();

        DB::table('users')->where('id', $empleado)->update(['must_change_password' => false, 'deleted_at' => now()]);
        $this->actingAs($perfil)->withSession([AccesoCaseta::HUELLA => 'x'])->get(route('caseta.menu'))
            ->assertSessionHasErrors(['email' => AccesoCaseta::SIN_ACCESO]);
        $this->assertGuest();
    }

    public function test_sesion_de_vinculado_sin_huella_se_cierra(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'caseta');

        $this->actingAs($perfil)->get(route('caseta.menu'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => AccesoCaseta::SESION_EXPIRADA]);
    }

    public function test_sesion_restaurada_por_recordarme_usa_la_huella_de_la_cookie(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'caseta');
        $huella = app(AccesoCaseta::class)->huella(EmpleadoRh::find($empleado));

        $this->login('persona@industrialhefesto.com', 'clave-de-rh', recordar: true)
            ->assertCookie(AccesoCaseta::HUELLA, $perfil->id.'|'.$huella);
        $this->post(route('logout'));

        // Sesión nueva (como al volver con la cookie de recordar): la huella llega en la cookie cifrada.
        $this->actingAs($perfil)->withCookie(AccesoCaseta::HUELLA, $perfil->id.'|'.$huella)
            ->get(route('caseta.menu'))->assertOk();

        // La cookie de otro perfil no sirve.
        $this->flushSession();
        $this->actingAs($perfil)->withCookie(AccesoCaseta::HUELLA, ($perfil->id + 1).'|'.$huella)
            ->get(route('caseta.menu'))->assertRedirect(route('login'));

        // Si la contraseña cambió en RH, la cookie vieja ya no sirve.
        DB::table('users')->where('id', $empleado)->update(['password' => Hash::make('otra-clave')]);
        $this->flushSession();
        $this->actingAs($perfil)->withCookie(AccesoCaseta::HUELLA, $perfil->id.'|'.$huella)
            ->get(route('caseta.menu'))
            ->assertSessionHasErrors(['email' => AccesoCaseta::PASSWORD_CAMBIO]);
    }

    public function test_cuenta_generica_no_depende_de_rh(): void
    {
        $generica = User::factory()->create(['rol' => 'caseta']);

        $this->actingAs($generica)->get(route('caseta.menu'))->assertOk();
    }

    // ---------------------------------------------------------------- Admin → Usuarios

    public function test_no_crea_una_generica_con_el_correo_de_un_empleado(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        $this->crearEmpleado('HEF-1', 'Persona@IndustrialHefesto.com ');

        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Intento',
            'email' => 'persona@industrialhefesto.com',
            'rol' => 'caseta',
            'password' => 'secreta-123',
            'password_confirmation' => 'secreta-123',
        ])->assertSessionHasErrors(['email' => 'Ese correo es de un empleado; dale acceso a Intercambios desde la app de RH.']);

        $this->assertSame(1, User::count());
    }

    public function test_un_empleado_dado_de_baja_libera_su_correo_para_una_generica(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        $empleado = $this->crearEmpleado('HEF-1', 'baja@industrialhefesto.com');
        DB::table('users')->where('id', $empleado)->update(['deleted_at' => now()]);

        $this->actingAs($admin)->post(route('admin.usuarios.store'), [
            'name' => 'Caseta 2',
            'email' => 'baja@industrialhefesto.com',
            'rol' => 'caseta',
            'password' => 'secreta-123',
            'password_confirmation' => 'secreta-123',
        ])->assertSessionHasNoErrors();
    }

    public function test_de_un_perfil_vinculado_solo_se_editan_rol_y_activo(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com', 'clave-de-rh');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'caseta');
        $hashLocal = $perfil->password;
        $hashRh = DB::table('users')->where('id', $empleado)->value('password');

        $this->actingAs($admin)->put(route('admin.usuarios.update', $perfil), [
            'name' => 'Otro Nombre',
            'email' => 'otro@industrialhefesto.com',
            'password' => 'intento-123',
            'password_confirmation' => 'intento-123',
            'rol' => 'admin',
            'activo' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.usuarios.index'));

        $perfil->refresh();
        $this->assertSame('admin', $perfil->rol);
        $this->assertFalse($perfil->activo);
        $this->assertSame('Nombre Viejo', $perfil->name);
        $this->assertSame('persona@industrialhefesto.com', $perfil->email);
        $this->assertSame($hashLocal, $perfil->password, 'Nunca se escribe la contraseña local de un vinculado.');
        $this->assertSame($hashRh, DB::table('users')->where('id', $empleado)->value('password'));
    }

    public function test_listado_y_formulario_distinguen_empleados_y_genericas(): void
    {
        $admin = User::factory()->create(['rol' => 'admin', 'name' => 'Caseta 1']);
        $empleado = $this->crearEmpleado('HEF-0000482', 'persona@industrialhefesto.com');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'caseta');

        $this->actingAs($admin)->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('Empleado RH (HEF-0000482)')
            ->assertSee('Natalia Martinez Tafoya')
            ->assertSee('Genérica');

        $this->get(route('admin.usuarios.index', ['buscar' => 'Tafoya']))
            ->assertSee('Natalia Martinez Tafoya')
            ->assertDontSee('Caseta 1</div>', false);

        $this->get(route('admin.usuarios.edit', $perfil))
            ->assertOk()
            ->assertSee('Se administra desde RH')
            ->assertSee('Es la misma de la app de RH')
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="email"', false);

        $this->get(route('admin.usuarios.edit', $admin))
            ->assertOk()
            ->assertSee('name="password"', false);
    }

    public function test_el_propio_vinculado_ve_el_aviso_de_contrasena_de_rh(): void
    {
        $empleado = $this->crearEmpleado('HEF-1', 'persona@industrialhefesto.com');
        $perfil = $this->perfilVinculado($empleado, 'persona@industrialhefesto.com', 'admin');

        $this->comoVinculado($perfil)->get(route('admin.usuarios.edit', $perfil))
            ->assertOk()
            ->assertSee('Tu contraseña es la misma de la app de RH. Cámbiala desde RH.');
    }
}
