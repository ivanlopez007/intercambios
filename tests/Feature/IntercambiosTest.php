<?php

namespace Tests\Feature;

use App\Models\BitacoraIntercambio;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\FotoIntercambio;
use App\Models\Intercambio;
use App\Models\LineaTransportista;
use App\Models\LlantaIntercambio;
use App\Models\Operador;
use App\Models\PuntoInspeccion;
use App\Models\RevisionPunto;
use App\Models\TipoCaja;
use App\Models\Tractocamion;
use App\Models\User;
use App\Services\PdfIntercambio;
use Database\Seeders\DemoIntercambiosSeeder;
use Database\Seeders\MasDemoIntercambiosSeeder;
use Database\Seeders\PuntosInspeccionContratoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\UsaBaseExternaDePrueba;
use Tests\TestCase;

class IntercambiosTest extends TestCase
{
    use RefreshDatabase, UsaBaseExternaDePrueba;

    private User $admin;

    private int $operadorId;

    private Cliente $cliente;

    private Caja $caja;

    private Tractocamion $tracto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBaseExterna();
        $this->prepararTablasIntercambio();
        Storage::fake('r2');

        $this->admin = User::factory()->create(['rol' => 'admin', 'name' => 'Guardia Admin']);

        $db = DB::connection('db_externa');
        $areaOperador = $db->table('areas')->insertGetId(['area' => 'OPERADOR']);
        $areaRh = $db->table('areas')->insertGetId(['area' => 'RH']);
        $licencia = $db->table('tipo_licencias')->insertGetId(['tipo_licencia' => 'Federal Tipo B']);

        $this->operadorId = $this->crearUsuarioRh('HEF-1', $areaOperador, 'JUAN', 'PÉREZ', 'LÓPEZ');
        $db->table('documento_operadors')->insert([
            'operador_id' => $this->operadorId, 'numero_licencia' => 'LIC-1', 'tipo_licencia_id' => $licencia,
            'fecha_vencimiento_licencia' => now()->subDay()->toDateString(), 'fecha_vencimiento_apto' => now()->addYear()->toDateString(),
        ]);
        $this->crearUsuarioRh('HEF-2', $areaOperador, 'BAJA', 'DADO', 'DE', eliminado: true);
        $this->crearUsuarioRh('HEF-3', $areaRh, 'ANA', 'RH', 'RH');

        PuntoInspeccion::query()->insert(['seccion' => 'ctpat', 'nombre' => 'Llantas', 'tipo_respuesta' => 'ok_falla', 'orden' => 1]);
        $puertas = PuntoInspeccion::query()->insertGetId(['seccion' => 'ctpat', 'nombre' => 'Puertas', 'tipo_respuesta' => 'ok_falla', 'orden' => 2]);
        PuntoInspeccion::query()->insert([
            ['seccion' => 'ctpat', 'nombre' => 'Bisagras', 'parent_id' => $puertas, 'tipo_respuesta' => 'ok_falla', 'orden' => 1, 'activo' => true],
            ['seccion' => 'olores', 'nombre' => 'Diésel', 'parent_id' => null, 'tipo_respuesta' => 'deteccion', 'orden' => 1, 'activo' => true],
            ['seccion' => 'residuos', 'nombre' => 'Vidrio', 'parent_id' => null, 'tipo_respuesta' => 'deteccion', 'orden' => 1, 'activo' => false],
        ]);

        $this->cliente = Cliente::create(['nombre' => 'Walmart']);
        $this->caja = Caja::create(['cliente_id' => $this->cliente->id, 'numero_economico' => '5310', 'placa' => 'ABC-1']);
        $this->tracto = Tractocamion::create(['numero_economico' => 'T-1', 'placa' => 'TR-1']);
    }

    public function test_solo_lee_operadores_activos_del_area_de_operadores(): void
    {
        $this->assertSame(['HEF-1'], Operador::pluck('numero_empleado')->all());

        $datos = $this->actingAs($this->admin)->getJson(route('admin.intercambios.datos'))->assertOk();

        $datos->assertJsonCount(1, 'operadores')
            ->assertJsonPath('operadores.0.nombre', 'JUAN PÉREZ LÓPEZ')
            ->assertJsonPath('operadores.0.licencia', 'LIC-1')
            ->assertJsonMissingPath('operadores.0.rfc')
            ->assertJsonCount(4, 'puntos'); // el inactivo no se envía
    }

    public function test_los_operadores_son_de_solo_lectura(): void
    {
        $this->expectException(LogicException::class);

        $operador = Operador::first();
        $operador->email = 'otro@ejemplo.com';
        $operador->save();
    }

    public function test_registra_un_intercambio_completo_con_folio_consecutivo_y_copia_de_datos(): void
    {
        $datos = $this->datosValidos(cargada: true);

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertCreated()
            ->assertJsonPath('folio', 'INT-'.now()->year.'-000001');

        $intercambio = Intercambio::sole();
        $this->assertSame('completado', $intercambio->status);
        $this->assertSame($this->admin->id, $intercambio->guardia_id);
        $this->assertSame('Guardia Admin', $intercambio->guardia_nombre);
        $this->assertSame('JUAN PÉREZ LÓPEZ', $intercambio->operador_nombre);
        $this->assertSame('5310', $intercambio->caja_numero_economico);
        $this->assertSame('Walmart', $intercambio->caja_cliente);
        $this->assertSame($this->cliente->id, $intercambio->cliente_id); // por defecto, el dueño de la caja
        $this->assertNotNull($intercambio->completado_at);
        $this->assertSame(18, $intercambio->llantas()->count());
        $this->assertSame(4, $intercambio->revisionPuntos()->count()); // una fila por cada punto activo
        $this->assertSame(2, $intercambio->fotos()->where('tipo', 'sello')->count());
        Storage::disk('r2')->assertExists([$intercambio->firma_operador_path, $intercambio->firma_guardia_path]);

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos())
            ->assertCreated()
            ->assertJsonPath('folio', 'INT-'.now()->year.'-000002');
    }

    public function test_un_reenvio_del_mismo_intercambio_no_lo_duplica(): void
    {
        $datos = $this->datosValidos();

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertOk()
            ->assertJsonPath('duplicado', true)
            ->assertJsonPath('folio', 'INT-'.now()->year.'-000001');

        $this->assertSame(1, Intercambio::count());
    }

    public function test_el_folio_reinicia_al_cambiar_de_anio(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 31)->setTime(12, 0));
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos())
            ->assertJsonPath('folio', 'INT-2026-000001');

        $this->travelTo(now()->setDate(2027, 1, 1)->setTime(12, 0));
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos())
            ->assertJsonPath('folio', 'INT-2027-000001');
    }

    public function test_registra_caja_tracto_cliente_y_linea_nuevos(): void
    {
        $datos = $this->datosValidos();
        $datos['caja'] = ['nuevo' => [
            'cliente_nuevo' => ['nombre' => 'Costco', 'rfc' => null],
            'numero_economico' => '900', 'placa' => 'NUEVA-1',
        ]];
        $datos['tracto'] = ['nuevo' => [
            'numero_economico' => 'P-7', 'placa' => 'PER-7', 'es_permisionario' => true,
            'linea_nueva' => ['nombre' => 'Transportes del Norte'],
        ]];
        $datos['destino_nuevo'] = 'Laredo';

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $intercambio = Intercambio::with(['caja.cliente', 'tractocamion.lineaTransportista', 'destino'])->sole();
        $this->assertSame('Costco', $intercambio->caja->cliente->nombre);
        $this->assertSame('900', $intercambio->caja->numero_economico);
        $this->assertTrue($intercambio->tractocamion->es_permisionario);
        $this->assertSame('Transportes del Norte', $intercambio->tracto_linea);
        $this->assertSame('Laredo', $intercambio->destino->nombre);
        $this->assertSame(1, LineaTransportista::count());
    }

    public function test_caja_nueva_repetida_para_el_mismo_duenio_usa_la_existente(): void
    {
        $datos = $this->datosValidos();
        $datos['caja'] = ['nuevo' => ['cliente_id' => $this->cliente->id, 'numero_economico' => '5310', 'placa' => 'OTRA']];

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $this->assertSame(1, Caja::count());
        $this->assertSame($this->caja->id, Intercambio::sole()->caja_id);
    }

    public function test_detecta_el_intercambio_relacionado(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos(movimiento: 'salida'))->assertCreated();
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos(movimiento: 'entrada'))->assertCreated();

        [$salida, $entrada] = Intercambio::orderBy('id')->get();
        $this->assertNull($salida->intercambio_relacionado_id);
        $this->assertSame($salida->id, $entrada->intercambio_relacionado_id);
    }

    public function test_caja_cargada_exige_sello_y_al_menos_una_foto(): void
    {
        $datos = $this->datosValidos(cargada: true);
        $datos['numero_sello'] = null;
        $datos['fotos'] = [];

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numero_sello']);

        $datos['numero_sello'] = 'S-1';
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fotos']);
    }

    public function test_caja_vacia_no_guarda_sello(): void
    {
        $datos = $this->datosValidos();
        $datos['numero_sello'] = 'NO-DEBE';

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $this->assertNull(Intercambio::sole()->numero_sello);
    }

    public function test_llantas_sin_medir_se_guardan_con_valores_por_defecto(): void
    {
        $datos = $this->datosValidos();
        array_pop($datos['llantas']); // la 18 no llega
        $datos['llantas'][0] = ['posicion' => 1, 'profundidad_32' => null, 'psi' => '', 'marca' => 'Michelin'];
        $datos['llantas'][1] = ['posicion' => 2, 'profundidad_32' => 7]; // sin PSI

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $llantas = Intercambio::sole()->llantas->keyBy('posicion');
        $this->assertCount(18, $llantas);
        $this->assertSame([4, 100, 'Michelin'], [$llantas[1]->profundidad_32, $llantas[1]->psi, $llantas[1]->marca]);
        $this->assertSame([7, 100], [$llantas[2]->profundidad_32, $llantas[2]->psi]);
        $this->assertSame([4, 100], [$llantas[18]->profundidad_32, $llantas[18]->psi]);
    }

    public function test_rechaza_valores_de_llanta_fuera_de_rango(): void
    {
        $datos = $this->datosValidos();
        $datos['llantas'][0]['profundidad_32'] = 40;

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['llantas.0.profundidad_32']);
    }

    public function test_umbrales_de_color_de_llantas(): void
    {
        $this->assertSame('rojo', LlantaIntercambio::color(1, 3));
        $this->assertSame('amarillo', LlantaIntercambio::color(1, 4));
        $this->assertSame('amarillo', LlantaIntercambio::color(2, 6));
        $this->assertSame('verde', LlantaIntercambio::color(2, 7));
        $this->assertSame('rojo', LlantaIntercambio::color(3, 1));
        $this->assertSame('amarillo', LlantaIntercambio::color(11, 2));
        $this->assertSame('amarillo', LlantaIntercambio::color(18, 4));
        $this->assertSame('verde', LlantaIntercambio::color(18, 5));
    }

    public function test_la_precarga_de_llantas_trae_marca_y_tipo_del_ultimo_intercambio(): void
    {
        $datos = $this->datosValidos();
        $datos['llantas'][0]['marca'] = 'Michelin';
        $datos['llantas'][0]['tipo'] = 'renovada';
        $datos['llantas'][17]['marca'] = 'Bridgestone';
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $this->actingAs($this->admin)->getJson(route('admin.intercambios.datos'))
            ->assertJsonPath("llantas_tractos.{$this->tracto->id}.1.marca", 'Michelin')
            ->assertJsonPath("llantas_tractos.{$this->tracto->id}.1.tipo", 'renovada')
            ->assertJsonPath("llantas_cajas.{$this->caja->id}.18.marca", 'Bridgestone')
            ->assertJsonMissingPath("llantas_cajas.{$this->caja->id}.1");
    }

    public function test_exige_observaciones_cuando_un_punto_no_cumple(): void
    {
        $datos = $this->datosValidos();
        $datos['revision'][0]['resultado'] = RevisionPunto::NO_CUMPLE;

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['revision']);

        $datos['revision'][0]['observaciones'] = 'Llanta con bulto';
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $this->assertSame('Llanta con bulto', RevisionPunto::where('resultado', 'no_cumple')->sole()->observaciones);
    }

    public function test_exige_revisar_todos_los_puntos_activos(): void
    {
        $datos = $this->datosValidos();
        array_pop($datos['revision']);

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['revision']);
    }

    public function test_falta_una_foto_por_subir_responde_409_para_reintentar(): void
    {
        $datos = $this->datosValidos();
        $datos['fotos'][] = ['uuid' => (string) Str::uuid(), 'tipo' => 'problema', 'unidad' => 'caja'];

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertStatus(409);

        $this->assertSame(0, Intercambio::count());
        $this->assertSame([], Storage::disk('r2')->allFiles("intercambios/{$datos['id_cliente']}/firmas"));
    }

    public function test_no_se_suben_fotos_a_un_intercambio_ya_registrado(): void
    {
        $datos = $this->datosValidos();
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $this->subirFoto($datos['id_cliente'], 'problema')->assertForbidden();
    }

    public function test_descartar_borra_las_fotos_de_un_intercambio_no_registrado(): void
    {
        $id = (string) Str::uuid();
        $this->subirFoto($id, 'sello')->assertCreated();
        $this->subirFoto($id, 'problema')->assertCreated();
        $this->assertCount(2, Storage::disk('r2')->allFiles("intercambios/{$id}"));

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.descartar'), ['intercambio' => $id])->assertOk();
        // Se puede repetir (la cola puede reenviarlo).
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.descartar'), ['intercambio' => $id])->assertOk();

        $this->assertSame([], Storage::disk('r2')->allFiles("intercambios/{$id}"));
    }

    public function test_descartar_conserva_las_fotos_de_un_intercambio_registrado(): void
    {
        $datos = $this->datosValidos(cargada: true);
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.descartar'), ['intercambio' => $datos['id_cliente']])->assertOk();

        Storage::disk('r2')->assertExists(FotoIntercambio::pluck('path')->all());
    }

    public function test_el_comando_borra_solo_fotos_huerfanas_viejas(): void
    {
        $registrado = $this->datosValidos(cargada: true);
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $registrado)->assertCreated();

        $huerfanoViejo = (string) Str::uuid();
        $this->subirFoto($huerfanoViejo, 'sello')->assertCreated();
        $rutaVieja = Storage::disk('r2')->allFiles("intercambios/{$huerfanoViejo}")[0];
        touch(Storage::disk('r2')->path($rutaVieja), now()->subDays(80)->getTimestamp());

        $huerfanoReciente = (string) Str::uuid();
        $this->subirFoto($huerfanoReciente, 'sello')->assertCreated();

        $this->artisan('intercambios:limpiar-fotos')->assertSuccessful();

        $this->assertSame([], Storage::disk('r2')->allFiles("intercambios/{$huerfanoViejo}"));
        $this->assertCount(1, Storage::disk('r2')->allFiles("intercambios/{$huerfanoReciente}"));
        Storage::disk('r2')->assertExists(FotoIntercambio::pluck('path')->all());
    }

    public function test_datos_de_demostracion_se_crean_y_se_borran(): void
    {
        Storage::fake('local');
        $this->crearUsuarioRh('HEF-9', DB::connection('db_externa')->table('areas')->where('area', 'OPERADOR')->value('id'), 'PEDRO', 'SOLIS', 'RUIZ');
        PuntoInspeccion::query()->insert(['seccion' => 'ctpat', 'nombre' => 'Remaches', 'tipo_respuesta' => 'ok_falla', 'orden' => 5]);
        foreach (['Caja seca', 'Caja refrigerada', 'Plataforma', 'Cortina'] as $tipo) {
            TipoCaja::create(['nombre' => $tipo]);
        }

        $this->seed(DemoIntercambiosSeeder::class);

        $this->assertSame(8, Intercambio::count());
        $this->assertSame('INT-'.now()->year.'-000008', Intercambio::orderByDesc('id')->value('folio'));
        $this->assertSame(3, Intercambio::whereNotNull('intercambio_relacionado_id')->count());
        $this->assertGreaterThan(0, FotoIntercambio::where('tipo', 'punto')->count());
        Storage::disk('r2')->assertExists(FotoIntercambio::pluck('path')->all());

        // Más datos sobre los anteriores; el borrado quita todo.
        $this->seed(MasDemoIntercambiosSeeder::class);
        $this->assertGreaterThan(20, Intercambio::count());
        $this->assertSame(Intercambio::count(), Intercambio::distinct()->count('folio'));
        $this->actingAs($this->admin)->get(route('admin.intercambios.patio'))
            ->assertOk()
            ->assertViewHas('resumen', fn ($r) => $r['total'] > 10);

        $this->artisan('intercambios:borrar-demo')->assertSuccessful();

        $this->assertSame(0, Intercambio::count());
        $this->assertSame(0, FotoIntercambio::count());
        $this->assertSame(['Walmart'], Cliente::pluck('nombre')->all()); // el del setUp se conserva
        $this->assertSame([], Storage::disk('r2')->allFiles('intercambios'));
        $this->assertNull(DB::connection('db_externa')->table('folios_intercambio')->value('ultimo'));
        Storage::disk('local')->assertMissing(DemoIntercambiosSeeder::ARCHIVO);
    }

    public function test_guarda_las_18_fotos_de_la_unidad_con_su_vista(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos())->assertCreated();

        $recorrido = FotoIntercambio::where('tipo', 'recorrido')->get();
        $this->assertCount(18, $recorrido);
        $this->assertEqualsCanonicalizing(FotoIntercambio::vistas(), $recorrido->pluck('vista')->all());

        $frontal = $recorrido->firstWhere('vista', 'trc_frontal');
        $this->assertSame('tracto', $frontal->unidad);
        $this->assertSame('TRC - Frontal', $frontal->etiqueta_vista);
        $this->assertSame('caja', $recorrido->firstWhere('vista', 'caja_trasera')->unidad);
        Storage::disk('r2')->assertExists($recorrido->pluck('path')->all());

        $this->actingAs($this->admin)->get(route('admin.intercambios.show', Intercambio::sole()))
            ->assertOk()
            ->assertSee('Fotos de la unidad')
            ->assertSee('CAJA - Parte trasera (puertas)');
    }

    public function test_exige_todas_las_fotos_de_la_unidad(): void
    {
        $datos = $this->datosValidos();
        $datos['fotos'] = array_values(array_filter($datos['fotos'], fn ($f) => ($f['vista'] ?? null) !== 'caja_trasera'));

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fotos'])
            ->assertJsonFragment(['Faltan fotos de la unidad: CAJA - Parte trasera (puertas).']);
    }

    public function test_foto_de_la_unidad_requiere_una_vista_valida(): void
    {
        $this->subirFoto((string) Str::uuid(), 'recorrido')->assertUnprocessable()->assertJsonValidationErrors(['vista']);
        $this->subirFoto((string) Str::uuid(), 'recorrido', null, 'no_existe')->assertUnprocessable()->assertJsonValidationErrors(['vista']);
    }

    public function test_el_patio_muestra_las_cajas_cuyo_ultimo_movimiento_es_entrada(): void
    {
        $otra = Caja::create(['cliente_id' => $this->cliente->id, 'numero_economico' => '7777', 'placa' => 'OTRA-1']);
        $registrar = function (string $movimiento, Caja $caja, int $haceHoras, bool $cargada = false) {
            $datos = $this->datosValidos(cargada: $cargada, movimiento: $movimiento);
            $datos['caja'] = ['id' => $caja->id];
            $datos['fecha_hora'] = now()->subHours($haceHoras)->format('Y-m-d\TH:i');
            $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();
        };

        $registrar('salida', $this->caja, 100);
        $registrar('entrada', $this->caja, 80, cargada: true); // 5310: sigue dentro, más de 72 h
        $registrar('entrada', $otra, 10);
        $registrar('salida', $otra, 2);                          // 7777: ya salió

        $this->actingAs($this->admin)->get(route('admin.intercambios.patio'))
            ->assertOk()
            ->assertViewHas('resumen', fn ($r) => $r['total'] === 1 && $r['cargadas'] === 1 && $r['excede_tiempo'] === 1)
            ->assertViewHas('unidades', fn ($u) => $u->count() === 1 && $u->first()->intercambio->caja_numero_economico === '5310')
            ->assertSee('Más de 72 h');

        $this->actingAs($this->admin)->get(route('admin.intercambios.patio', ['filtro' => 'vacias']))
            ->assertViewHas('unidades', fn ($u) => $u->isEmpty());

        $this->actingAs($this->admin)->get(route('admin.intercambios.patio', ['dueno' => 'Walmart']))
            ->assertViewHas('unidades', fn ($u) => $u->count() === 1)
            ->assertSee('Ver toda la información')
            ->assertSee('Salida anterior');

        $this->actingAs($this->admin)->get(route('admin.intercambios.patio', ['dueno' => 'Otro dueño']))
            ->assertViewHas('unidades', fn ($u) => $u->isEmpty());
    }

    public function test_depura_las_fotos_con_mas_de_cinco_anios_y_conserva_los_datos(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos(cargada: true))->assertCreated();
        $viejo = Intercambio::sole();
        $rutas = $viejo->fotos()->pluck('path')->all();
        $viejo->update(['fecha_hora' => now()->subYears(5)->subDay()]);

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos())->assertCreated();
        $reciente = Intercambio::latest('id')->first();

        $this->artisan('intercambios:depurar-fotos')->assertSuccessful();

        $this->assertSame(0, $viejo->fotos()->count());
        foreach ($rutas as $ruta) {
            Storage::disk('r2')->assertMissing($ruta);
        }
        Storage::disk('r2')->assertExists([$viejo->firma_operador_path, $viejo->firma_guardia_path]);
        $this->assertSame(18, $viejo->llantas()->count());
        $this->assertSame(18, $reciente->fotos()->count());

        $this->actingAs($this->admin)->get(route('admin.intercambios.show', $viejo))
            ->assertOk()
            ->assertSee('se eliminaron al cumplir 5 años');
    }

    public function test_contrato_de_intercambio_y_ctpat_en_pdf_con_los_puntos_del_remolque(): void
    {
        $this->seed(PuntosInspeccionContratoSeeder::class);
        $this->seed(PuntosInspeccionContratoSeeder::class); // no duplica
        $this->assertSame(13, PuntoInspeccion::where('seccion', 'remolque')->count());
        $this->assertSame(6, PuntoInspeccion::where('seccion', 'danos_caja')->count());

        $marcar = function (array $datos, string $nombre, string $resultado, ?string $obs = null) {
            $id = PuntoInspeccion::where('nombre', $nombre)->value('id');
            foreach ($datos['revision'] as &$fila) {
                if ($fila['punto_inspeccion_id'] === $id) {
                    $fila['resultado'] = $resultado;
                    $fila['observaciones'] = $obs;
                }
            }

            return $datos;
        };

        // Un daño con un resultado que no es de daño se rechaza.
        $mal = $marcar($this->datosValidos(), 'Frente', 'cumple');
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $mal)->assertUnprocessable();

        $salida = $marcar($this->datosValidos(cargada: true, movimiento: 'salida'), 'Lado izquierdo', 'golpe');
        $salida = $marcar($salida, 'Luces', 'no_cumple', 'Calavera derecha rota');
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $salida)->assertCreated();
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos())->assertCreated();

        [$s, $e] = Intercambio::orderBy('id')->get();
        $this->assertSame('golpe', $s->revisionPuntos()->whereHas('punto', fn ($q) => $q->where('nombre', 'Lado izquierdo'))->value('resultado'));

        foreach ([$s, $e] as $intercambio) {
            foreach (['contrato', 'ctpat'] as $documento) {
                $respuesta = $this->actingAs($this->admin)->get(route('admin.intercambios.pdf', [$intercambio, $documento]));
                $respuesta->assertOk()->assertHeader('Content-Type', 'application/pdf');
                $this->assertStringStartsWith('%PDF', $respuesta->getContent());
            }
        }

        // El HTML del contrato de la entrada lleva la salida como inspección inicial.
        $html = view('pdf.contrato-intercambio', (function () use ($e) {
            $pdf = new PdfIntercambio;
            $columna = (fn ($i) => $this->columnaContrato($i))->call($pdf, $s = $e->relacionado);

            return ['i' => $e, 'columnas' => ['INSPECCIÓN INICIAL' => $columna, 'INSPECCIÓN FINAL' => null], 'empresa' => config('intercambios.empresa'), 'logo' => null];
        })())->render();
        $this->assertStringContainsString($s->folio, $html);
        $this->assertStringContainsString('Calavera derecha rota', view('pdf.inspeccion-ctpat', [
            'i' => $s->load('revisionPuntos.punto'), 'ctpat' => collect(), 'olores' => collect(), 'residuos' => collect(), 'complemento' => collect(),
            'revision' => collect(), 'obsRemolque' => ['Luces: Calavera derecha rota'], 'obsTracto' => [], 'firma' => null,
            'empresa' => config('intercambios.empresa'), 'logo' => null,
        ])->render());

        $this->actingAs($this->admin)->get(route('admin.intercambios.show', $s))
            ->assertOk()
            ->assertSee('Contrato de intercambio')
            ->assertSee('Golpe (G)');
    }

    public function test_al_registrar_borra_las_fotos_subidas_que_ya_no_se_usan(): void
    {
        $datos = $this->datosValidos();
        // Una foto que se subió mientras se capturaba y luego se reemplazó.
        $reemplazada = (string) Str::uuid();
        $this->subirFoto($datos['id_cliente'], 'recorrido', $reemplazada, 'trc_frontal')->assertCreated();
        $this->assertCount(19, Storage::disk('r2')->allFiles("intercambios/{$datos['id_cliente']}/recorrido"));

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $archivos = Storage::disk('r2')->allFiles("intercambios/{$datos['id_cliente']}");
        $this->assertCount(18 + 2, $archivos); // 18 de la unidad + 2 firmas
        $this->assertEmpty(array_filter($archivos, fn ($ruta) => str_contains($ruta, $reemplazada)));
        Storage::disk('r2')->assertExists(FotoIntercambio::pluck('path')->all());
    }

    public function test_dashboard_con_datos_reales(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos(cargada: true))->assertCreated();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('resumen', fn ($r) => $r['total'] === 1 && $r['entradas_hoy'] === 1)
            ->assertViewHas('porDia', fn ($d) => count($d) === 14 && end($d)['entradas'] === 1)
            ->assertViewHas('porDueno', fn ($d) => $d === [['nombre' => 'Walmart', 'cajas' => 1]])
            ->assertViewHas('documentos', fn ($d) => $d->first()?->vencido === true) // la licencia del operador venció ayer
            ->assertSee('Intercambios por día')
            ->assertSee('JUAN PÉREZ LÓPEZ');
    }

    public function test_respeta_al_guardia_que_capturo_sin_conexion(): void
    {
        $otro = User::factory()->create(['rol' => 'admin', 'name' => 'Turno Noche']);
        $datos = $this->datosValidos();
        $datos['registrado_por'] = Crypt::encryptString((string) $otro->id);

        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $datos)->assertCreated();

        $this->assertSame('Turno Noche', Intercambio::sole()->guardia_nombre);
        $this->assertSame($otro->id, BitacoraIntercambio::sole()->user_id);
    }

    public function test_listado_y_detalle(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.intercambios.store'), $this->datosValidos(cargada: true))->assertCreated();
        $intercambio = Intercambio::sole();

        $this->actingAs($this->admin)->get(route('admin.intercambios.historial', ['caja' => '5310']))
            ->assertOk()
            ->assertSee($intercambio->folio);

        $this->actingAs($this->admin)->get(route('admin.intercambios.historial', ['caja' => 'NO-EXISTE']))
            ->assertOk()
            ->assertDontSee($intercambio->folio);

        $this->actingAs($this->admin)->get(route('admin.intercambios.show', $intercambio))
            ->assertOk()
            ->assertSee('JUAN PÉREZ LÓPEZ')
            ->assertSee('Vencido');

        $foto = FotoIntercambio::first();
        $this->actingAs($this->admin)->get(route('admin.intercambios.foto', [$intercambio, $foto]))->assertRedirect();
    }

    public function test_el_asistente_y_los_catalogos_cargan(): void
    {
        $this->actingAs($this->admin)->get(route('admin.intercambios.nuevo'))->assertOk();

        foreach (['clientes', 'tipos-caja', 'cajas', 'lineas-transportistas', 'tractocamiones', 'destinos', 'puntos-inspeccion'] as $catalogo) {
            $this->actingAs($this->admin)->get(route('admin.catalogos.index', $catalogo))->assertOk();
        }
    }

    public function test_catalogos_se_desactivan_y_validan_duplicados(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.catalogos.estado', ['clientes', $this->cliente->id]))
            ->assertRedirect();
        $this->assertFalse($this->cliente->fresh()->activo);

        $this->actingAs($this->admin)
            ->post(route('admin.catalogos.store', 'cajas'), ['cliente_id' => $this->cliente->id, 'numero_economico' => '5310', 'placa' => 'X'])
            ->assertSessionHasErrors('numero_economico');

        $this->actingAs($this->admin)
            ->post(route('admin.catalogos.store', 'tractocamiones'), ['numero_economico' => 'T-9', 'placa' => 'X', 'es_permisionario' => '1'])
            ->assertSessionHasErrors('linea_transportista_id');

        $this->actingAs($this->admin)
            ->post(route('admin.catalogos.store', 'puntos-inspeccion'), ['nombre' => 'Nuevo', 'orden' => 1])
            ->assertNotFound();
    }

    public function test_caseta_no_entra_a_intercambios_de_admin(): void
    {
        $caseta = User::factory()->create(['rol' => 'caseta']);

        $this->actingAs($caseta)->get(route('admin.intercambios.nuevo'))->assertForbidden();
        $this->actingAs($caseta)->postJson(route('admin.intercambios.store'), [])->assertForbidden();
    }

    /**
     * Intercambio válido. Las fotos del sello se suben antes, como lo hace el service worker.
     */
    private function datosValidos(bool $cargada = false, string $movimiento = 'entrada'): array
    {
        $id = (string) Str::uuid();
        $fotos = [];

        if ($cargada) {
            foreach (range(1, 2) as $n) {
                $uuid = (string) Str::uuid();
                $this->subirFoto($id, 'sello', $uuid)->assertCreated();
                $fotos[] = ['uuid' => $uuid, 'tipo' => 'sello'];
            }
        }

        // Las 18 fotos obligatorias de la unidad.
        foreach (FotoIntercambio::vistas() as $vista) {
            $uuid = (string) Str::uuid();
            $this->subirFoto($id, 'recorrido', $uuid, $vista)->assertCreated();
            $fotos[] = ['uuid' => $uuid, 'tipo' => 'recorrido', 'vista' => $vista];
        }

        return [
            'id_cliente' => $id,
            'tipo_movimiento' => $movimiento,
            'fecha_hora' => now()->subMinutes(5)->format('Y-m-d\TH:i'),
            'operador_id' => $this->operadorId,
            'caja' => ['id' => $this->caja->id],
            'tracto' => ['id' => $this->tracto->id],
            'estado_carga' => $cargada ? 'cargada' : 'vacia',
            'tipo_intercambio' => 'impo',
            'numero_sello' => $cargada ? 'SELLO-1' : null,
            'llantas' => array_map(fn ($p) => ['posicion' => $p, 'profundidad_32' => 10, 'psi' => 100], range(1, 18)),
            'revision' => PuntoInspeccion::activos()->get()->map(fn ($p) => [
                'punto_inspeccion_id' => $p->id,
                'resultado' => ['deteccion' => 'no_detectado', 'dano' => 'sin_dano'][$p->tipo_respuesta] ?? 'cumple',
            ])->all(),
            'fotos' => $fotos,
            'firma_operador' => $this->firmaPng(),
            'firma_guardia' => $this->firmaPng(),
        ];
    }

    private function subirFoto(string $intercambio, string $tipo, ?string $uuid = null, ?string $vista = null)
    {
        return $this->actingAs($this->admin)->post(route('admin.intercambios.fotos.store'), array_filter([
            'intercambio' => $intercambio,
            'uuid' => $uuid ?? (string) Str::uuid(),
            'tipo' => $tipo,
            'vista' => $vista,
            'foto' => UploadedFile::fake()->image('foto.jpg', 40, 30),
        ]), ['Accept' => 'application/json']);
    }

    private function crearUsuarioRh(string $numero, int $area, string $nombre, string $paterno, string $materno, bool $eliminado = false): int
    {
        $db = DB::connection('db_externa');
        $id = $db->table('users')->insertGetId([
            'numero_empleado' => $numero, 'email' => strtolower($numero).'@ejemplo.com', 'password' => 'x',
            'area_id' => $area, 'deleted_at' => $eliminado ? now() : null,
        ]);
        $db->table('info_usuarios')->insert(['usuario_id' => $id, 'nombre' => $nombre, 'apellido_p' => $paterno, 'apellido_m' => $materno]);

        return $id;
    }

    private function firmaPng(): string
    {
        $imagen = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($imagen);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }
}
