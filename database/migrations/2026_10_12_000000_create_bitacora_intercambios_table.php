<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intercambios recibidos de cada dispositivo. Vive en la base local; `intercambio_id` es el id
 * de la tabla `intercambios` de la base de RH. El UUID del dispositivo evita duplicados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_intercambios', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_cliente')->unique();
            $table->unsignedBigInteger('intercambio_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('usuario_nombre');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_intercambios');
    }
};
