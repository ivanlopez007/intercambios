<?php

namespace Database\Seeders;

use App\Models\EmpleadoRh;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $name || ! $email || ! $password) {
            throw new RuntimeException('Set ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD before running db:seed.');
        }

        $email = mb_strtolower(trim($email));

        // Solo cuentas genéricas: a un empleado de RH se le da acceso desde RH, y nunca se
        // escribe la contraseña de un perfil vinculado.
        if (EmpleadoRh::conCorreo($email)->exists() || User::where('email', $email)->whereNotNull('user_id')->exists()) {
            throw new RuntimeException("{$email} es de un empleado de RH: dale acceso a Intercambios desde la app de RH.");
        }

        User::updateOrCreate(
            ['email' => $email, 'user_id' => null],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'rol' => 'admin',
                'activo' => true,
            ],
        );
    }
}
