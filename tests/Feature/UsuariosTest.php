<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['rol' => 'admin', 'name' => 'Admin Principal']);
    }

    public function test_admin_ve_la_lista_y_el_formulario(): void
    {
        User::factory()->create(['name' => 'Guardia Uno', 'rol' => 'caseta']);

        $this->actingAs($this->admin)->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('Guardia Uno')
            ->assertSee('Admin Principal');

        $this->get(route('admin.usuarios.create'))->assertOk()->assertSee('Crear usuario');
        $this->get(route('admin.usuarios.edit', User::where('name', 'Guardia Uno')->sole()))->assertOk()->assertSee('Guardar cambios');
        $this->get(route('admin.usuarios.edit', $this->admin))->assertOk()->assertSee('No puedes quitarte el rol');
    }

    public function test_crea_usuario_de_caseta_que_puede_iniciar_sesion(): void
    {
        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
            'name' => 'Guardia Nuevo',
            'email' => 'Guardia@Hefesto.test',
            'rol' => 'caseta',
            'activo' => '1',
            'password' => 'secreta-123',
            'password_confirmation' => 'secreta-123',
        ])->assertRedirect(route('admin.usuarios.index'));

        $nuevo = User::where('email', 'guardia@hefesto.test')->sole();
        $this->assertSame('caseta', $nuevo->rol);
        $this->assertTrue($nuevo->activo);
        $this->assertTrue(Hash::check('secreta-123', $nuevo->password));

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'guardia@hefesto.test', 'password' => 'secreta-123'])
            ->assertRedirect(route('caseta.menu'));
    }

    public function test_crea_administrador(): void
    {
        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
            'name' => 'Otra Admin',
            'email' => 'otra@hefesto.test',
            'rol' => 'admin',
            'password' => 'secreta-123',
            'password_confirmation' => 'secreta-123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'otra@hefesto.test', 'rol' => 'admin']);
    }

    public function test_valida_correo_unico_rol_y_contrasena(): void
    {
        User::factory()->create(['email' => 'repetido@hefesto.test']);

        $this->actingAs($this->admin)->post(route('admin.usuarios.store'), [
            'name' => 'X',
            'email' => 'repetido@hefesto.test',
            'rol' => 'superusuario',
            'password' => 'corta',
            'password_confirmation' => 'otra',
        ])->assertSessionHasErrors(['email', 'rol', 'password']);
    }

    public function test_edita_rol_y_contrasena_opcional(): void
    {
        $guardia = User::factory()->create(['rol' => 'caseta']);
        $hashAnterior = $guardia->password;

        // Sin contraseña: solo cambia el rol.
        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $guardia), [
            'name' => $guardia->name,
            'email' => $guardia->email,
            'rol' => 'admin',
            'activo' => '1',
        ])->assertSessionHasNoErrors();

        $guardia->refresh();
        $this->assertSame('admin', $guardia->rol);
        $this->assertSame($hashAnterior, $guardia->password);

        // Con contraseña nueva.
        $this->put(route('admin.usuarios.update', $guardia), [
            'name' => $guardia->name,
            'email' => $guardia->email,
            'rol' => 'admin',
            'activo' => '1',
            'password' => 'nueva-clave-1',
            'password_confirmation' => 'nueva-clave-1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nueva-clave-1', $guardia->fresh()->password));
    }

    public function test_desactivar_corta_el_acceso(): void
    {
        $guardia = User::factory()->create(['rol' => 'caseta']);

        $this->actingAs($this->admin)
            ->patch(route('admin.usuarios.estado', $guardia))
            ->assertSessionHas('success');

        $this->assertFalse($guardia->fresh()->activo);

        $this->actingAs($guardia->fresh())->get('/caseta')->assertRedirect(route('login'));
    }

    public function test_admin_no_puede_quitarse_el_rol_ni_desactivarse(): void
    {
        $this->actingAs($this->admin)->put(route('admin.usuarios.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'rol' => 'caseta',
            'activo' => '0',
        ])->assertSessionHasErrors(['rol', 'activo']);

        $this->patch(route('admin.usuarios.estado', $this->admin))->assertSessionHas('error');

        $this->admin->refresh();
        $this->assertSame('admin', $this->admin->rol);
        $this->assertTrue($this->admin->activo);
    }

    public function test_caseta_no_puede_administrar_usuarios(): void
    {
        $guardia = User::factory()->create(['rol' => 'caseta']);

        $this->actingAs($guardia)->get(route('admin.usuarios.index'))->assertForbidden();
        $this->post(route('admin.usuarios.store'), [
            'name' => 'Intruso',
            'email' => 'intruso@hefesto.test',
            'rol' => 'admin',
            'password' => 'secreta-123',
            'password_confirmation' => 'secreta-123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@hefesto.test']);
    }
}
