<?php

namespace Database\Seeders;

use App\Models\BitacoraIntercambio;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\Intercambio;
use App\Models\LineaTransportista;
use App\Models\Operador;
use App\Models\PuntoInspeccion;
use App\Models\TipoCaja;
use App\Models\Tractocamion;
use App\Models\User;
use App\Services\RegistrarIntercambio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Datos de demostración del módulo de intercambios (solo local): catálogos e intercambios
 * completos con fotos y firmas generadas, registrados con la misma lógica que el asistente.
 *
 * Todo lo creado se anota en storage/app/demo-intercambios.json y se deshace con
 * `php artisan intercambios:borrar-demo`.
 */
class DemoIntercambiosSeeder extends Seeder
{
    public const ARCHIVO = 'demo-intercambios.json';

    protected array $creados = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Los datos de demostración solo se crean en local o testing.');
        }

        if (Storage::disk('local')->exists(self::ARCHIVO)) {
            $this->command?->warn('Ya hay datos de demostración. Bórralos primero con: php artisan intercambios:borrar-demo');

            return;
        }

        $operadores = Operador::with('documento')->get();
        $guardia = User::where('rol', 'admin')->where('activo', true)->first();

        if ($operadores->isEmpty() || ! $guardia) {
            throw new RuntimeException('Se necesita al menos un operador en RH y un usuario admin activo.');
        }

        $this->creados = [
            'folio_previo' => DB::connection('db_externa')->table('folios_intercambio')->where('anio', now()->year)->value('ultimo'),
            'anio' => now()->year,
        ];

        $catalogos = DB::connection('db_externa')->transaction(fn () => $this->crearCatalogos());
        $this->guardarRegistro();

        $this->crearIntercambios($catalogos, $operadores, $guardia);
        $this->guardarRegistro();

        $this->command?->info('Intercambios de demostración: '.count($this->creados['intercambios'] ?? []));
        $this->command?->info('Para borrarlos: php artisan intercambios:borrar-demo');
    }

    private function crearCatalogos(): array
    {
        $tipos = TipoCaja::pluck('id', 'nombre');

        $clientes = collect([
            'walmart' => ['Walmart de México', 'WME970714R89'],
            'costco' => ['Costco de México', 'CME910715UB9'],
            'mabe' => ['Mabe', 'MAB911203RR7'],
            'home' => ['Home Depot México', null],
        ])->map(fn ($c) => $this->anotar('clientes', Cliente::create(['nombre' => $c[0], 'rfc' => $c[1]])));

        $lineas = collect([
            'norte' => 'Transportes del Norte',
            'laredo' => 'Fletes Laredo Express',
        ])->map(fn ($nombre) => $this->anotar('lineas', LineaTransportista::create(['nombre' => $nombre])));

        $destinos = collect([
            'mty' => 'Patio Monterrey', 'nld' => 'Nuevo Laredo', 'ltx' => 'Laredo, TX',
            'sal' => 'Saltillo', 'qro' => 'Querétaro',
        ])->map(fn ($nombre) => $this->anotar('destinos', Destino::create(['nombre' => $nombre])));

        $caja = fn ($cliente, $numero, $tipo, $placa, $marca, $anio, $pies = 53) => $this->anotar('cajas', Caja::create([
            'cliente_id' => $clientes[$cliente]->id, 'tipo_caja_id' => $tipos[$tipo] ?? null, 'numero_economico' => $numero,
            'placa' => $placa, 'estado_placa' => 'Nuevo León', 'marca' => $marca, 'modelo' => $marca === 'Utility' ? '4000D-X' : 'Duraplate',
            'vin' => strtoupper(Str::random(17)), 'anio' => $anio, 'tamano_pies' => $pies,
        ]));

        $cajas = [
            'w5310' => $caja('walmart', '5310', 'Caja seca', '78-UJ-3B', 'Utility', 2021),
            'c5310' => $caja('costco', '5310', 'Caja refrigerada', '81-UK-7C', 'Great Dane', 2022),
            'w5402' => $caja('walmart', '5402', 'Caja seca', '78-UJ-9F', 'Wabash', 2019),
            'm118' => $caja('mabe', 'MB-118', 'Plataforma', '92-UL-1A', 'Utility', 2020, 48),
            'h77' => $caja('home', 'HD-77', 'Cortina', '55-UM-4D', 'Wabash', 2023),
        ];

        $tracto = fn ($numero, $placa, $marca, $modelo, $anio, $linea = null) => $this->anotar('tractos', Tractocamion::create([
            'numero_economico' => $numero, 'placa' => $placa, 'estado_placa' => 'Nuevo León', 'marca' => $marca,
            'modelo' => $modelo, 'vin' => strtoupper(Str::random(17)), 'anio' => $anio,
            'es_permisionario' => (bool) $linea, 'linea_transportista_id' => $linea ? $lineas[$linea]->id : null,
        ]));

        $tractos = [
            't101' => $tracto('T-101', '45-AB-1C', 'Freightliner', 'Cascadia', 2022),
            't102' => $tracto('T-102', '45-AB-2C', 'Kenworth', 'T680', 2021),
            'p201' => $tracto('P-201', '33-XY-9Z', 'International', 'LT625', 2019, 'norte'),
        ];

        return compact('clientes', 'destinos', 'cajas', 'tractos');
    }

    private function crearIntercambios(array $c, $operadores, User $guardia): void
    {
        $op1 = $operadores->first()->id;
        $op2 = $operadores->skip(1)->first()?->id ?? $op1;
        $d = $c['destinos'];

        // Historia de varios días: salidas y regresos de las mismas cajas (intercambio relacionado).
        $escenarios = [
            ['hace' => 120, 'mov' => 'salida', 'op' => $op1, 'caja' => 'w5310', 'tracto' => 't101', 'carga' => 'cargada', 'tipo' => 'expo', 'origen' => 'mty', 'destino' => 'ltx', 'sello' => 'S-48213'],
            ['hace' => 100, 'mov' => 'salida', 'op' => $op2, 'caja' => 'c5310', 'tracto' => 'p201', 'carga' => 'cargada', 'tipo' => 'impo', 'origen' => 'mty', 'destino' => 'nld', 'sello' => 'S-55102',
                'comentarios_tracto' => 'Espejo lateral derecho estrellado.', 'problemas' => ['tracto' => 'ESPEJO ESTRELLADO']],
            ['hace' => 72, 'mov' => 'entrada', 'op' => $op1, 'caja' => 'w5310', 'tracto' => 't101', 'carga' => 'vacia', 'tipo' => 'expo', 'origen' => 'ltx', 'destino' => 'mty',
                'comentarios_caja' => 'Raspón nuevo en la pared lateral izquierda.', 'problemas' => ['caja' => 'RASPON LATERAL'],
                'lados' => ['Lado izquierdo' => 'golpe'], 'hallazgos' => ['Loderas' => 'Lodera trasera izquierda rota.']],
            ['hace' => 50, 'mov' => 'salida', 'op' => $op2, 'caja' => 'm118', 'tracto' => 't102', 'carga' => 'cargada', 'tipo' => 'local', 'origen' => 'mty', 'destino' => 'sal', 'sello' => 'S-60017',
                'llantas_bajas' => [1 => 3, 14 => 3]],
            ['hace' => 30, 'mov' => 'entrada', 'op' => $op2, 'caja' => 'c5310', 'tracto' => 'p201', 'carga' => 'cargada', 'tipo' => 'impo', 'origen' => 'nld', 'destino' => 'mty', 'sello' => 'S-77420',
                'hallazgos' => ['Puertas interior/exterior' => 'Sello de puerta con marcas de manipulación.', 'Remaches' => 'Dos remaches nuevos en la puerta derecha.'],
                'observaciones' => 'Se notificó a seguridad antes de liberar la unidad.'],
            ['hace' => 20, 'mov' => 'entrada', 'op' => $op1, 'caja' => 'h77', 'tracto' => 't102', 'carga' => 'vacia', 'tipo' => 'local', 'origen' => 'qro', 'destino' => 'mty',
                'hallazgos' => ['Diésel' => 'Olor fuerte a diésel en el interior de la caja.']],
            ['hace' => 8, 'mov' => 'salida', 'op' => $op1, 'caja' => 'w5402', 'tracto' => 't101', 'carga' => 'cargada', 'tipo' => 'expo', 'origen' => 'mty', 'destino' => 'ltx', 'sello' => 'S-81234'],
            ['hace' => 3, 'mov' => 'entrada', 'op' => $op2, 'caja' => 'm118', 'tracto' => 't102', 'carga' => 'vacia', 'tipo' => 'local', 'origen' => 'sal', 'destino' => 'mty',
                'llantas_bajas' => [14 => 4], 'comentarios_caja' => 'La llanta 14 sigue baja; se reportó a taller.'],
        ];

        $this->registrarEscenarios($escenarios, $c, $guardia);
    }

    /**
     * Registra cada escenario con la misma lógica que el asistente. Claves: hace (horas), mov,
     * op (id de operador), caja, tracto, carga, tipo, origen, destino y, opcionales: sello,
     * hallazgos [punto => observación], lados [lado => golpe|corte|agujero], llantas_bajas
     * [posición => profundidad], problemas [unidad => texto], comentarios_caja/_tracto, observaciones.
     */
    protected function registrarEscenarios(array $escenarios, array $c, User $guardia, int $desplazamiento = 0): void
    {
        $d = $c['destinos'];
        $registrar = app(RegistrarIntercambio::class);
        $puntos = PuntoInspeccion::activos()->get();
        $marcas = ['Michelin', 'Bridgestone', 'Goodyear', 'Continental'];

        foreach ($escenarios as $n => $e) {
            $n += $desplazamiento;
            $id = (string) Str::uuid();
            $caja = $c['cajas'][$e['caja']];
            $fotos = [];

            $foto = function (string $tipo, string $texto, array $extra = []) use ($id, &$fotos) {
                $uuid = (string) Str::uuid();
                Storage::disk(RegistrarIntercambio::DISCO)->put(
                    RegistrarIntercambio::rutaFoto($id, $tipo, $uuid, 'jpg', $extra['vista'] ?? null),
                    $this->imagen($texto, $tipo),
                    ['visibility' => 'private', 'ContentType' => 'image/jpeg'],
                );
                $fotos[] = ['uuid' => $uuid, 'tipo' => $tipo, ...$extra];
            };

            if ($e['carga'] === 'cargada') {
                $foto('sello', "SELLO {$e['sello']}");
                $foto('sello', "SELLO {$e['sello']} COLOCADO");
            }

            $revision = $puntos->map(function ($p) use ($e, $foto) {
                // Daños de la caja por lado (G, C o A) del contrato de intercambio.
                if ($p->tipo_respuesta === 'dano') {
                    return ['punto_inspeccion_id' => $p->id, 'resultado' => $e['lados'][$p->nombre] ?? 'sin_dano', 'observaciones' => null];
                }

                $hallazgo = $e['hallazgos'][$p->nombre] ?? null;
                if ($hallazgo) {
                    $foto('punto', mb_strtoupper($p->nombre), ['punto_inspeccion_id' => $p->id]);
                }

                return [
                    'punto_inspeccion_id' => $p->id,
                    'resultado' => $hallazgo
                        ? ($p->tipo_respuesta === 'deteccion' ? 'detectado' : 'no_cumple')
                        : ($p->tipo_respuesta === 'deteccion' ? 'no_detectado' : ($p->nombre === 'Cargadores' && $e['carga'] === 'vacia' ? 'no_aplica' : 'cumple')),
                    'observaciones' => $hallazgo,
                ];
            })->all();

            foreach (config('intercambios.recorrido') as $vista) {
                $foto('recorrido', mb_strtoupper($vista['etiqueta']), ['vista' => $vista['clave']]);
            }

            foreach ($e['problemas'] ?? [] as $unidad => $texto) {
                $foto('problema', $texto, ['unidad' => $unidad]);
            }

            $llantas = collect(range(1, 18))->map(fn ($p) => [
                'posicion' => $p,
                'marca' => $marcas[($p + $n) % 4],
                'profundidad_32' => $e['llantas_bajas'][$p] ?? (($p * 7 + $n * 3) % 9) + 6,
                'psi' => 95 + (($p + $n) % 4) * 5,
                'tipo' => $p > 10 && $p % 3 === 0 ? 'renovada' : 'original',
                'observaciones' => isset($e['llantas_bajas'][$p]) ? 'Desgaste irregular' : null,
            ])->all();

            foreach (array_keys($e['llantas_bajas'] ?? []) as $p) {
                $foto('llanta', "LLANTA {$p}", ['posicion' => $p]);
            }

            $intercambio = $registrar->ejecutar([
                'id_cliente' => $id,
                'tipo_movimiento' => $e['mov'],
                'fecha_hora' => now()->subHours($e['hace'])->format('Y-m-d H:i'),
                'operador_id' => $e['op'],
                'caja' => ['id' => $caja->id],
                'tracto' => ['id' => $c['tractos'][$e['tracto']]->id],
                'odometro' => 150000 + $n * 1375,
                'estado_carga' => $e['carga'],
                'tipo_intercambio' => $e['tipo'],
                'origen_id' => $d[$e['origen']]->id,
                'destino_id' => $d[$e['destino']]->id,
                'referencia_carta_porte' => 'CP-'.(20260 + $n),
                'numero_sello' => $e['sello'] ?? null,
                'llantas' => $llantas,
                'revision' => $revision,
                'comentarios_caja' => $e['comentarios_caja'] ?? null,
                'comentarios_tracto' => $e['comentarios_tracto'] ?? null,
                'observaciones' => $e['observaciones'] ?? null,
                'fotos' => $fotos,
                'firma_operador' => $this->firma($n),
                'firma_guardia' => $this->firma($n + 7),
            ], $guardia);

            BitacoraIntercambio::create([
                'id_cliente' => $id, 'intercambio_id' => $intercambio->id,
                'user_id' => $guardia->id, 'usuario_nombre' => $guardia->name,
            ]);

            $this->creados['intercambios'][] = ['id' => $intercambio->id, 'id_cliente' => $id, 'folio' => $intercambio->folio];
            $this->command?->line("  {$intercambio->folio} · {$e['mov']} · caja {$caja->numero_economico}");
        }
    }

    protected function anotar(string $grupo, $modelo)
    {
        $this->creados[$grupo][] = $modelo->id;

        return $modelo;
    }

    protected function guardarRegistro(): void
    {
        Storage::disk('local')->put(self::ARCHIVO, json_encode($this->creados, JSON_PRETTY_PRINT));
    }

    /** Foto JPEG de ejemplo con un texto, de un color según el tipo. */
    protected function imagen(string $texto, string $tipo): string
    {
        $img = imagecreatetruecolor(800, 600);
        [$r, $g, $b] = ['sello' => [30, 64, 175], 'punto' => [190, 18, 60], 'problema' => [180, 83, 9], 'llanta' => [51, 65, 85], 'recorrido' => [15, 118, 110]][$tipo];
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $blanco = imagecolorallocate($img, 255, 255, 255);
        imagerectangle($img, 20, 20, 779, 579, $blanco);
        imagestring($img, 5, 40, 40, 'FOTO DE DEMOSTRACION', $blanco);
        imagestring($img, 5, 40, 290, $texto, $blanco);
        ob_start();
        imagejpeg($img, null, 80);

        return ob_get_clean();
    }

    /** Firma PNG de ejemplo: un trazo ondulado distinto para cada número. */
    protected function firma(int $semilla): string
    {
        $img = imagecreatetruecolor(500, 160);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $tinta = imagecolorallocate($img, 15, 23, 42);
        imagesetthickness($img, 3);
        $prevX = 30;
        $prevY = 90;
        for ($x = 40; $x <= 470; $x += 10) {
            $y = (int) (90 + sin(($x + $semilla * 13) / (18 + $semilla % 5)) * 35 * cos($x / 90));
            imageline($img, $prevX, $prevY, $x, $y, $tinta);
            [$prevX, $prevY] = [$x, $y];
        }
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }
}
