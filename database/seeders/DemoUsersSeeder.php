<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo users can only be created in local or testing environments.');
        }

        $users = [
            [
                'name' => 'Administrador de prueba',
                'email' => 'admin.demo@intercambios.test',
                'rol' => 'admin',
            ],
            [
                'name' => 'Usuario de caseta de prueba',
                'email' => 'caseta.demo@intercambios.test',
                'rol' => 'caseta',
            ],
        ];

        foreach ($users as $attributes) {
            $user = User::firstOrNew(['email' => $attributes['email']]);

            if ($user->exists) {
                $this->command?->warn("User {$attributes['email']} already exists; it was left unchanged.");

                continue;
            }

            $password = Str::password(24);

            $user->fill([
                ...$attributes,
                'password' => $password,
                'activo' => true,
            ])->save();

            $this->command?->info("Created {$attributes['rol']} user: {$attributes['email']}");
            $this->command?->info("Temporary password: {$password}");
        }
    }
}
