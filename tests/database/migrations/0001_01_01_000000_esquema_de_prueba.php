<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SOLO PARA PRUEBAS (SQLite en memoria; tests/TestCase.php impide usar otra base).
 *
 * En producción estas tablas viven en la base de RH (`laravel`) y las crea la app de RH;
 * esta app no tiene migraciones propias. Aquí se copia lo mínimo para el login con
 * identidad única (users, info_usuarios, sistemas, sistema_usuario) y las tablas de Caseta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('area');
            $table->softDeletes();
            $table->timestamps();
        });

        // Empleados de RH.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('numero_empleado')->unique();
            $table->string('email');
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->unsignedBigInteger('rol_id')->default(1);
            $table->foreignId('area_id')->constrained('areas');
            $table->softDeletes();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('info_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('apellido_p');
            $table->string('apellido_m');
            $table->string('rfc')->default('cifrado');
            $table->string('curp')->default('cifrado');
            $table->string('telefono')->default('');
            $table->timestamps();
        });

        Schema::create('sistemas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 50)->unique();
            $table->string('nombre', 100);
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('sistema_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sistema_id')->constrained('sistemas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'sistema_id']);
        });

        // Perfiles de Caseta.
        Schema::create('usuarios_caseta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('rol')->default('caseta');
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('bitacora_visitas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visita_id');
            $table->string('tipo', 10);
            $table->foreignId('user_id')->nullable()->constrained('usuarios_caseta')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->uuid('id_cliente')->nullable()->unique();
            $table->timestamp('registrado_en')->nullable();
            $table->timestamps();
            $table->unique(['visita_id', 'tipo']);
        });

        Schema::create('bitacora_intercambios', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_cliente')->unique();
            $table->unsignedBigInteger('intercambio_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('usuarios_caseta')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->timestamps();
        });
    }
};
