<?php

namespace Tests\Concerns;

use App\Models\EmpleadoRh;
use App\Models\User;
use App\Services\AccesoCaseta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Empleados de RH (tabla users), sus accesos a sistemas y perfiles de Caseta vinculados,
 * para probar el login con identidad y contraseña únicas.
 */
trait CreaEmpleadosRh
{
    protected function crearSistemas(): void
    {
        DB::table('sistemas')->insert([
            ['clave' => 'rh', 'nombre' => 'App de RH', 'activo' => true],
            ['clave' => EmpleadoRh::SISTEMA, 'nombre' => 'Intercambios', 'activo' => true],
        ]);
    }

    protected function crearEmpleado(
        string $numero,
        string $email,
        string $password = 'clave-de-rh',
        string $nombre = 'Natalia',
        string $apellidoP = 'Martinez',
        string $apellidoM = 'Tafoya',
        bool $acceso = true,
        bool $mustChange = false,
    ): int {
        $area = DB::table('areas')->value('id') ?? DB::table('areas')->insertGetId(['area' => 'ADMINISTRACION']);

        $id = DB::table('users')->insertGetId([
            'numero_empleado' => $numero,
            'email' => $email,
            'password' => Hash::make($password),
            'must_change_password' => $mustChange,
            'area_id' => $area,
        ]);

        DB::table('info_usuarios')->insert([
            'usuario_id' => $id, 'nombre' => $nombre, 'apellido_p' => $apellidoP, 'apellido_m' => $apellidoM,
        ]);

        if ($acceso) {
            $this->darAcceso($id);
        }

        return $id;
    }

    protected function darAcceso(int $empleadoId): void
    {
        DB::table('sistema_usuario')->insert([
            'user_id' => $empleadoId,
            'sistema_id' => DB::table('sistemas')->where('clave', EmpleadoRh::SISTEMA)->value('id'),
        ]);
    }

    protected function quitarAcceso(int $empleadoId): void
    {
        DB::table('sistema_usuario')->where('user_id', $empleadoId)->delete();
    }

    /** Perfil de Caseta vinculado, con su contraseña local anulada (hash aleatorio). */
    protected function perfilVinculado(int $empleadoId, string $email, string $rol = 'admin', string $name = 'Nombre Viejo'): User
    {
        return User::factory()->create([
            'user_id' => $empleadoId,
            'email' => $email,
            'name' => $name,
            'rol' => $rol,
            'password' => Str::random(64),
        ]);
    }

    /** Sesión de un perfil vinculado como la deja el login (con la huella de su contraseña de RH). */
    protected function comoVinculado(User $perfil): static
    {
        $huella = app(AccesoCaseta::class)->huella(EmpleadoRh::findOrFail($perfil->user_id));

        return $this->actingAs($perfil)->withSession([AccesoCaseta::HUELLA => $huella]);
    }
}
