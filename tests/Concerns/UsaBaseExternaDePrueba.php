<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sustituye la conexión `db_externa` por una base SQLite en memoria durante la prueba,
 * con las tablas mínimas que usa la aplicación. Nunca toca la base externa real.
 */
trait UsaBaseExternaDePrueba
{
    protected function prepararBaseExterna(): void
    {
        config(['database.connections.db_externa' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('db_externa');

        Schema::connection('db_externa')->create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('razon_social');
            $table->string('categoria');
            $table->string('logo_url');
            $table->string('sitio_web');
            $table->timestamps();
        });

        Schema::connection('db_externa')->create('visitas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_persona');
            $table->string('empresa');
            $table->string('motivo_visita');
            $table->string('persona_a_visitar');
            $table->string('firma_url');
            $table->dateTime('fecha_entrada');
            $table->dateTime('fecha_salida')->nullable();
            $table->timestamps();
        });

        Schema::connection('db_externa')->create('requerimiento_foto_visitas', function (Blueprint $table) {
            $table->id();
            $table->string('parte');
            $table->timestamps();
        });

        Schema::connection('db_externa')->create('foto_visitas', function (Blueprint $table) {
            $table->id();
            $table->text('foto_url');
            $table->foreignId('requerimiento_foto_visita_id')->constrained('requerimiento_foto_visitas')->cascadeOnDelete();
            $table->foreignId('visita_id')->constrained('visitas')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Tablas mínimas de RH (operadores) y las del módulo de intercambios, copiadas de las
     * migraciones de la app de RH. Llamar después de prepararBaseExterna().
     */
    protected function prepararTablasIntercambio(): void
    {
        $schema = Schema::connection('db_externa');

        $schema->create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('area');
            $table->softDeletes();
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('numero_empleado')->unique();
            $table->string('email');
            $table->string('password');
            $table->unsignedBigInteger('rol_id')->default(1);
            $table->foreignId('area_id')->constrained('areas');
            $table->softDeletes();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('info_usuarios', function (Blueprint $table) {
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

        $schema->create('tipo_licencias', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_licencia');
            $table->timestamps();
        });

        $schema->create('documento_operadors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operador_id')->constrained('users')->cascadeOnDelete();
            $table->string('numero_licencia')->nullable();
            $table->foreignId('tipo_licencia_id')->constrained('tipo_licencias');
            $table->date('fecha_vencimiento_licencia')->nullable();
            $table->date('fecha_vencimiento_apto')->nullable();
            $table->timestamps();
        });

        $schema->create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('rfc', 13)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $schema->create('tipos_caja', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $schema->create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('tipo_caja_id')->nullable()->constrained('tipos_caja')->nullOnDelete();
            $table->string('numero_economico', 30)->index();
            $table->string('placa', 20)->nullable();
            $table->string('estado_placa', 50)->nullable();
            $table->string('marca', 50)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('vin', 17)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->unsignedSmallInteger('tamano_pies')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['cliente_id', 'numero_economico']);
        });

        $schema->create('lineas_transportistas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('rfc', 13)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $schema->create('tractocamiones', function (Blueprint $table) {
            $table->id();
            $table->string('numero_economico', 30)->index();
            $table->string('placa', 20)->nullable();
            $table->string('estado_placa', 50)->nullable();
            $table->string('marca', 50)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('vin', 17)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->boolean('es_permisionario')->default(false);
            $table->foreignId('linea_transportista_id')->nullable()->constrained('lineas_transportistas');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $schema->create('destinos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $schema->create('puntos_inspeccion', function (Blueprint $table) {
            $table->id();
            $table->string('seccion', 20);
            $table->string('nombre');
            $table->foreignId('parent_id')->nullable()->constrained('puntos_inspeccion');
            $table->string('tipo_respuesta', 20);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $schema->create('folios_intercambio', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedInteger('ultimo')->default(0);
            $table->timestamps();
        });

        $schema->create('intercambios', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('status', 15)->default('borrador');
            $table->unsignedTinyInteger('paso_actual')->default(1);
            $table->string('tipo_movimiento', 10);
            $table->dateTime('fecha_hora');
            $table->foreignId('operador_id')->constrained('users');
            $table->unsignedBigInteger('guardia_id');
            $table->string('guardia_nombre');
            $table->foreignId('caja_id')->nullable()->constrained('cajas');
            $table->foreignId('tractocamion_id')->nullable()->constrained('tractocamiones');
            $table->unsignedInteger('odometro')->nullable();
            $table->foreignId('intercambio_relacionado_id')->nullable()->constrained('intercambios')->nullOnDelete();
            $table->string('estado_carga', 10)->nullable();
            $table->string('tipo_intercambio', 10)->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('origen_id')->nullable()->constrained('destinos');
            $table->foreignId('destino_id')->nullable()->constrained('destinos');
            $table->string('referencia_carta_porte', 100)->nullable();
            $table->string('numero_sello', 50)->nullable();
            $table->text('comentarios_caja')->nullable();
            $table->text('comentarios_tracto')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('firma_operador_path')->nullable();
            $table->string('firma_guardia_path')->nullable();
            $table->string('operador_nombre')->nullable();
            $table->string('operador_numero_empleado', 30)->nullable();
            $table->string('operador_licencia', 50)->nullable();
            $table->date('operador_licencia_vence')->nullable();
            $table->date('operador_apto_medico_vence')->nullable();
            $table->string('caja_numero_economico', 30)->nullable();
            $table->string('caja_placa', 20)->nullable();
            $table->string('caja_cliente')->nullable();
            $table->string('tracto_numero_economico', 30)->nullable();
            $table->string('tracto_placa', 20)->nullable();
            $table->string('tracto_linea')->nullable();
            $table->timestamp('completado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();
            $table->timestamps();
        });

        $schema->create('llantas_intercambio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intercambio_id')->constrained('intercambios')->cascadeOnDelete();
            $table->unsignedTinyInteger('posicion');
            $table->string('marca', 50)->nullable();
            $table->unsignedTinyInteger('profundidad_32')->nullable();
            $table->unsignedSmallInteger('psi')->nullable();
            $table->string('tipo', 10)->nullable();
            $table->string('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['intercambio_id', 'posicion']);
        });

        $schema->create('revision_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intercambio_id')->constrained('intercambios')->cascadeOnDelete();
            $table->foreignId('punto_inspeccion_id')->constrained('puntos_inspeccion');
            $table->string('resultado', 15);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['intercambio_id', 'punto_inspeccion_id']);
        });

        $schema->create('fotos_intercambio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intercambio_id')->constrained('intercambios')->cascadeOnDelete();
            $table->string('tipo', 15);
            $table->foreignId('revision_punto_id')->nullable()->constrained('revision_puntos')->cascadeOnDelete();
            $table->foreignId('llanta_intercambio_id')->nullable()->constrained('llantas_intercambio')->cascadeOnDelete();
            $table->string('unidad', 10)->nullable();
            $table->string('path');
            $table->timestamps();
        });
    }
}
