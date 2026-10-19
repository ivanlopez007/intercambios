<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién registró cada entrada y salida. Vive en la base local para no modificar
 * la tabla `visitas` de la base externa; `visita_id` es el id de esa tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_visitas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visita_id');
            $table->string('tipo', 10); // entrada | salida
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('usuario_nombre');
            $table->uuid('id_cliente')->nullable()->unique();
            $table->timestamp('registrado_en')->nullable();
            $table->timestamps();

            $table->unique(['visita_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_visitas');
    }
};
