<?php

namespace Tests\Feature;

use App\Models\EmpresaExterno;
use App\Models\FotoVisitaExterno;
use App\Models\User;
use App\Models\VisitaExterno;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsaBaseExternaDePrueba;
use Tests\TestCase;

class VisitasTest extends TestCase
{
    use RefreshDatabase, UsaBaseExternaDePrueba;

    private User $guardia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseExterna();
        Storage::fake('r2');

        $this->guardia = User::factory()->create(['rol' => 'caseta']);
    }

    public function test_registra_entrada_con_firma_privada_en_r2(): void
    {
        $empresa = $this->crearEmpresa('Walmart');

        $respuesta = $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco Torres',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => $this->firmaPng(),
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.firma_url');

        $visita = VisitaExterno::sole();
        $this->assertSame('Walmart', $visita->empresa);
        Storage::disk('r2')->assertExists($visita->getRawOriginal('firma_url'));
    }

    public function test_entrada_con_fotos_de_placas_y_vehiculo(): void
    {
        $empresa = $this->crearEmpresa('Walmart');

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Entrega',
            'firma' => $this->firmaPng(),
            'foto_placas' => $this->fotoJpeg(),
            'foto_vehiculo' => $this->fotoJpeg(),
        ])->assertCreated();

        $visita = VisitaExterno::sole();
        $fotos = FotoVisitaExterno::with('requerimientoFotoVisita')->where('visita_id', $visita->id)->get();

        $this->assertCount(2, $fotos);
        $this->assertEqualsCanonicalizing(['Placas', 'Vehículo'], $fotos->pluck('requerimientoFotoVisita.parte')->all());
        $fotos->each(fn ($foto) => Storage::disk('r2')->assertExists($foto->foto_url));
        $this->assertStringStartsWith('fotos/visitas/', $fotos->first()->foto_url);
        // Firma + 2 fotos
        $this->assertCount(3, Storage::disk('r2')->allFiles());

        $admin = User::factory()->create(['rol' => 'admin']);
        $this->actingAs($admin)->get(route('admin.entradas-salidas.historial'))
            ->assertOk()
            ->assertSee('Vehículo');
    }

    public function test_entrada_sin_fotos_sigue_funcionando_y_foto_invalida_se_rechaza(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $datos = [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => $this->firmaPng(),
        ];

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), $datos)->assertCreated();
        $this->assertSame(0, FotoVisitaExterno::count());

        $this->postJson(route('caseta.visitas.entrada'), [
            ...$datos,
            'foto_placas' => 'data:image/jpeg;base64,'.base64_encode('no es una imagen'),
        ])->assertUnprocessable()->assertJsonPath('success', false);

        // La foto inválida no deja la segunda visita ni archivos sueltos (solo la firma de la primera).
        $this->assertSame(1, VisitaExterno::count());
        $this->assertCount(1, Storage::disk('r2')->allFiles());
    }

    public function test_entrada_con_empresa_nueva_la_da_de_alta(): void
    {
        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
            'empresa_nueva' => true,
            'nombre_empresa' => 'Transportes Nuevos',
            'nombre_persona' => 'Ana',
            'persona_dirigida' => 'Patio',
            'motivo' => 'Entrega',
            'firma' => $this->firmaPng(),
        ])->assertCreated();

        $this->assertDatabaseHas('empresas', ['nombre' => 'Transportes Nuevos', 'razon_social' => ''], 'db_externa');
    }

    public function test_si_falla_la_base_de_datos_se_borra_la_firma_subida(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        Schema::connection('db_externa')->drop('visitas');

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
                'codigo_qr' => $empresa->id,
                'nombre_persona' => 'Paco',
                'persona_dirigida' => 'Compras',
                'motivo' => 'Junta',
                'firma' => $this->firmaPng(),
            ]);
            $this->fail('Se esperaba un error de base de datos.');
        } catch (QueryException) {
            $this->assertSame([], Storage::disk('r2')->allFiles());
        }
    }

    public function test_rechaza_firma_que_no_es_png(): void
    {
        $empresa = $this->crearEmpresa('Walmart');

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => 'data:image/png;base64,'.base64_encode('<svg></svg>'),
        ])->assertUnprocessable();

        $this->assertSame(0, VisitaExterno::count());
    }

    public function test_salida_cierra_la_unica_visita_activa_de_la_empresa(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $visita = $this->crearVisita('Walmart', 'Paco');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id])
            ->assertOk()
            ->assertJsonPath('data.id', $visita->id);

        $this->assertNotNull($visita->fresh()->fecha_salida);
    }

    public function test_salida_pide_elegir_si_hay_varias_personas_de_la_empresa(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $paco = $this->crearVisita('Walmart', 'Paco');
        $ana = $this->crearVisita('Walmart', 'Ana');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id])
            ->assertStatus(409)
            ->assertJsonPath('requiere_seleccion', true)
            ->assertJsonCount(2, 'visitas');

        $this->assertNull($paco->fresh()->fecha_salida);
        $this->assertNull($ana->fresh()->fecha_salida);

        $this->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id, 'visita_id' => $paco->id])
            ->assertOk();

        $this->assertNotNull($paco->fresh()->fecha_salida);
        $this->assertNull($ana->fresh()->fecha_salida);
    }

    public function test_salida_no_cierra_visita_de_otra_empresa(): void
    {
        $walmart = $this->crearEmpresa('Walmart');
        $this->crearEmpresa('Soriana');
        $this->crearVisita('Walmart', 'Paco');
        $deSoriana = $this->crearVisita('Soriana', 'Luis');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $walmart->id, 'visita_id' => $deSoriana->id])
            ->assertNotFound();

        $this->assertNull($deSoriana->fresh()->fecha_salida);
    }

    public function test_salida_repetida_conserva_la_primera_y_cancela_la_otra(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $visita = $this->crearVisita('Walmart', 'Paco');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id])
            ->assertOk()
            ->assertJsonMissingPath('cancelado');

        $primeraSalida = $visita->fresh()->fecha_salida;
        $this->travel(3)->minutes();

        $this->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id])
            ->assertOk()
            ->assertJsonPath('cancelado', true);

        $this->assertTrue($visita->fresh()->fecha_salida->equalTo($primeraSalida));
    }

    public function test_salidas_repetidas_sin_conexion_se_cancelan_al_sincronizar(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $visita = $this->crearVisita('Walmart', 'Paco');
        $primera = now()->subMinutes(20)->startOfSecond();

        // Dos escaneos guardados sin conexión con un minuto de diferencia.
        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.salida'), [
            'codigo_qr' => $empresa->id,
            'registrado_en' => $primera->toIso8601String(),
            'id_cliente' => '11111111-1111-4111-8111-111111111111',
        ])->assertOk();

        $this->postJson(route('caseta.visitas.salida'), [
            'codigo_qr' => $empresa->id,
            'registrado_en' => $primera->copy()->addMinute()->toIso8601String(),
            'id_cliente' => '22222222-2222-4222-8222-222222222222',
        ])->assertOk()->assertJsonPath('cancelado', true);

        $this->assertTrue($visita->fresh()->fecha_salida->equalTo($primera));
    }

    public function test_elegir_a_alguien_que_ya_salio_se_cancela_como_repetida(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $paco = $this->crearVisita('Walmart', 'Paco', salida: true);
        $this->crearVisita('Walmart', 'Ana');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id, 'visita_id' => $paco->id])
            ->assertOk()
            ->assertJsonPath('cancelado', true);
    }

    public function test_escaneo_repetido_no_cierra_la_salida_de_otra_persona(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $paco = $this->crearVisita('Walmart', 'Paco');
        $ana = $this->crearVisita('Walmart', 'Ana');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id, 'visita_id' => $paco->id])
            ->assertOk();

        // Segundo escaneo inmediato: Ana sigue dentro y no debe cerrarse sola.
        $this->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id])
            ->assertStatus(409)
            ->assertJsonPath('requiere_seleccion', true);

        $this->assertNull($ana->fresh()->fecha_salida);
    }

    public function test_lista_de_personas_dentro(): void
    {
        $this->crearVisita('Walmart', 'Paco Dentro');
        $this->crearVisita('Soriana', 'Ana Salio', salida: true);

        $this->actingAs($this->guardia)
            ->getJson(route('caseta.visitas.activas'))
            ->assertOk()
            ->assertJsonCount(1, 'visitas')
            ->assertJsonPath('visitas.0.nombre_persona', 'Paco Dentro')
            ->assertJsonPath('visitas.0.empresa', 'Walmart')
            ->assertJsonMissingPath('visitas.0.firma_url');

        $this->get(route('caseta.salida'))->assertOk()->assertSee('Personas dentro');
    }

    public function test_dar_salida_con_boton_sin_escanear_qr(): void
    {
        $this->crearEmpresa('Walmart');
        $paco = $this->crearVisita('Walmart', 'Paco');
        $ana = $this->crearVisita('Walmart', 'Ana');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['visita_id' => $ana->id])
            ->assertOk()
            ->assertJsonMissingPath('cancelado');

        $this->assertNotNull($ana->fresh()->fecha_salida);
        $this->assertNull($paco->fresh()->fecha_salida);

        // Segundo toque (o reenvío sin conexión) sobre la misma persona: se conserva la primera salida.
        $this->postJson(route('caseta.visitas.salida'), ['visita_id' => $ana->id])
            ->assertOk()
            ->assertJsonPath('cancelado', true);

        $this->postJson(route('caseta.visitas.salida'), ['visita_id' => 999])->assertNotFound();
        $this->postJson(route('caseta.visitas.salida'), [])->assertUnprocessable();
    }

    public function test_guarda_quien_registro_entrada_y_salida(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $this->guardia->update(['name' => 'Juan Guardia']);
        $otro = User::factory()->create(['rol' => 'caseta', 'name' => 'Ana Guardia']);
        $admin = User::factory()->create(['rol' => 'admin']);

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => $this->firmaPng(),
        ])->assertCreated();

        $visita = VisitaExterno::sole();

        $this->actingAs($otro)
            ->postJson(route('caseta.visitas.salida'), ['visita_id' => $visita->id])
            ->assertOk();

        $this->assertDatabaseHas('bitacora_visitas', ['visita_id' => $visita->id, 'tipo' => 'entrada', 'user_id' => $this->guardia->id, 'usuario_nombre' => 'Juan Guardia']);
        $this->assertDatabaseHas('bitacora_visitas', ['visita_id' => $visita->id, 'tipo' => 'salida', 'user_id' => $otro->id, 'usuario_nombre' => 'Ana Guardia']);

        $this->actingAs($admin)->get(route('admin.entradas-salidas.historial'))
            ->assertOk()
            ->assertSee('Juan Guardia')
            ->assertSee('Ana Guardia');
    }

    public function test_registro_sin_conexion_se_anota_a_quien_lo_hizo_aunque_lo_envie_otra_sesion(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $otro = User::factory()->create(['rol' => 'caseta']);

        // Lo registró el guardia (firma cifrada en su página), pero se envía con la sesión de otro.
        $this->actingAs($otro)->postJson(route('caseta.visitas.entrada'), [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => $this->firmaPng(),
            'registrado_por' => Crypt::encryptString((string) $this->guardia->id),
        ])->assertCreated();

        $this->assertDatabaseHas('bitacora_visitas', ['tipo' => 'entrada', 'user_id' => $this->guardia->id]);

        // Una firma alterada no se acepta: se usa la sesión actual.
        $visita = VisitaExterno::sole();
        $this->postJson(route('caseta.visitas.salida'), ['visita_id' => $visita->id, 'registrado_por' => 'falsa'])->assertOk();
        $this->assertDatabaseHas('bitacora_visitas', ['tipo' => 'salida', 'user_id' => $otro->id]);
    }

    public function test_salida_sin_conexion_de_una_entrada_sin_conexion(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $idEntrada = '33333333-3333-4333-8333-333333333333';

        // Si la salida llegara antes que su entrada, se rechaza para reintentar después.
        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['entrada_id_cliente' => $idEntrada])
            ->assertNotFound();

        $this->postJson(route('caseta.visitas.entrada'), [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => $this->firmaPng(),
            'id_cliente' => $idEntrada,
            'registrado_en' => now()->subHours(2)->toIso8601String(),
        ])->assertCreated();

        $this->postJson(route('caseta.visitas.salida'), [
            'entrada_id_cliente' => $idEntrada,
            'id_cliente' => '44444444-4444-4444-8444-444444444444',
            'registrado_en' => now()->subHour()->toIso8601String(),
        ])->assertOk()->assertJsonMissingPath('cancelado');

        $visita = VisitaExterno::sole();
        $this->assertNotNull($visita->fecha_salida);
        $this->assertTrue($visita->fecha_salida->greaterThan($visita->fecha_entrada));
    }

    public function test_salida_sin_visitas_activas_responde_404(): void
    {
        $empresa = $this->crearEmpresa('Walmart');

        $this->actingAs($this->guardia)
            ->postJson(route('caseta.visitas.salida'), ['codigo_qr' => $empresa->id])
            ->assertNotFound();
    }

    public function test_historial_filtra_por_estatus_y_busqueda(): void
    {
        $admin = User::factory()->create(['rol' => 'admin']);
        $this->crearVisita('Walmart', 'Paco Dentro');
        $this->crearVisita('Walmart', 'Ana Salio', salida: true);
        $this->crearVisita('Soriana', 'Luis 100%');

        $this->actingAs($admin)->get(route('admin.entradas-salidas.historial', ['estatus' => 'salio']))
            ->assertOk()
            ->assertSee('Ana Salio')
            ->assertDontSee('Paco Dentro');

        $this->get(route('admin.entradas-salidas.historial', ['estatus' => 'dentro']))
            ->assertSee('Paco Dentro')
            ->assertDontSee('Ana Salio');

        // El % se busca como texto literal, no como comodín.
        $this->get(route('admin.entradas-salidas.historial', ['buscar' => '%']))
            ->assertSee('Luis 100%')
            ->assertDontSee('Paco Dentro');
    }

    public function test_entrada_reenviada_sin_conexion_no_se_duplica(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $datos = [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => $this->firmaPng(),
            'id_cliente' => '9b2f6c1e-4a3d-4c5b-8e7f-1a2b3c4d5e6f',
        ];

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), $datos)->assertCreated();
        $this->postJson(route('caseta.visitas.entrada'), $datos)
            ->assertOk()
            ->assertJsonPath('duplicado', true);

        $this->assertSame(1, VisitaExterno::count());
        $this->assertCount(1, Storage::disk('r2')->allFiles());
    }

    public function test_registro_sin_conexion_conserva_la_hora_del_celular(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $horaEntrada = now()->subHours(3)->startOfSecond();
        $horaSalida = now()->subHour()->startOfSecond();

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
            'codigo_qr' => $empresa->id,
            'nombre_persona' => 'Paco',
            'persona_dirigida' => 'Compras',
            'motivo' => 'Junta',
            'firma' => $this->firmaPng(),
            'registrado_en' => $horaEntrada->toIso8601String(),
        ])->assertCreated();

        $this->postJson(route('caseta.visitas.salida'), [
            'codigo_qr' => $empresa->id,
            'registrado_en' => $horaSalida->toIso8601String(),
        ])->assertOk();

        $visita = VisitaExterno::sole();
        $this->assertTrue($visita->fecha_entrada->equalTo($horaEntrada));
        $this->assertTrue($visita->fecha_salida->equalTo($horaSalida));
    }

    public function test_hora_del_celular_en_el_futuro_se_ignora_y_salida_nunca_antes_de_entrada(): void
    {
        $empresa = $this->crearEmpresa('Walmart');
        $visita = $this->crearVisita('Walmart', 'Paco');

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.salida'), [
            'codigo_qr' => $empresa->id,
            'registrado_en' => now()->subDays(2)->toIso8601String(),
        ])->assertOk();

        $this->assertTrue($visita->fresh()->fecha_salida->greaterThanOrEqualTo($visita->fecha_entrada));

        $otra = $this->crearVisita('Walmart', 'Ana');
        $this->postJson(route('caseta.visitas.salida'), [
            'codigo_qr' => $empresa->id,
            'registrado_en' => now()->addDay()->toIso8601String(),
        ])->assertOk();

        $this->assertTrue($otra->fresh()->fecha_salida->lessThanOrEqualTo(now()));
    }

    public function test_empresa_nueva_repetida_reutiliza_la_existente(): void
    {
        $this->crearEmpresa('Transportes Nuevos');

        $this->actingAs($this->guardia)->postJson(route('caseta.visitas.entrada'), [
            'empresa_nueva' => true,
            'nombre_empresa' => 'Transportes Nuevos',
            'nombre_persona' => 'Ana',
            'persona_dirigida' => 'Patio',
            'motivo' => 'Entrega',
            'firma' => $this->firmaPng(),
        ])->assertCreated();

        $this->assertSame(1, EmpresaExterno::count());
    }

    public function test_endpoint_csrf_para_el_service_worker(): void
    {
        $this->getJson(route('caseta.csrf'))->assertUnauthorized();

        $this->actingAs($this->guardia)
            ->getJson(route('caseta.csrf'))
            ->assertOk()
            ->assertJsonStructure(['token'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_catalogo_de_empresas_para_el_buscador(): void
    {
        $this->crearEmpresa('Walmart');
        $this->crearEmpresa('Abarrotes Ñandú');

        $this->getJson(route('caseta.empresas'))->assertUnauthorized();

        $this->actingAs($this->guardia)
            ->getJson(route('caseta.empresas'))
            ->assertOk()
            ->assertJsonCount(2, 'empresas')
            ->assertJsonPath('empresas.0.nombre', 'Abarrotes Ñandú')
            ->assertJsonStructure(['empresas' => [['id', 'nombre']]]);

        $this->get(route('caseta.entrada'))->assertOk()->assertSee('empresa_busqueda', false);
        $this->get(route('caseta.salida'))->assertOk()->assertSee('empresa_busqueda', false);
    }

    public function test_qr_de_empresa_se_genera_en_el_servidor(): void
    {
        $empresa = $this->crearEmpresa('Walmart');

        $this->actingAs($this->guardia)
            ->get(route('caseta.qr.show', $empresa))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    private function crearEmpresa(string $nombre): EmpresaExterno
    {
        return EmpresaExterno::create([
            'nombre' => $nombre,
            'razon_social' => '',
            'categoria' => '',
            'logo_url' => '',
            'sitio_web' => '',
        ]);
    }

    private function crearVisita(string $empresa, string $nombre, bool $salida = false): VisitaExterno
    {
        return VisitaExterno::create([
            'nombre_persona' => $nombre,
            'empresa' => $empresa,
            'motivo_visita' => 'Junta',
            'persona_a_visitar' => 'Compras',
            'firma_url' => '',
            'fecha_entrada' => now()->subHour(),
            'fecha_salida' => $salida ? now() : null,
        ]);
    }

    private function fotoJpeg(): string
    {
        $imagen = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($imagen);

        return 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
    }

    private function firmaPng(): string
    {
        $imagen = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($imagen);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }
}
