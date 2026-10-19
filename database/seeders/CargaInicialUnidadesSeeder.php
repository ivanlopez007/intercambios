<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\TipoCaja;
use App\Models\Tractocamion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga inicial de las cajas y tractocamiones de Industrial Hefesto:
 *
 * - data/archivo.txt: cajas (NUMERACION, PLACAS, ESTADO, No° de serie, MODELO, AÑO), separado por tabuladores.
 * - data/unidades_gps.csv: unidades del GPS; se cargan las de tipo "Tractocamión".
 *
 * Los VIN se guardan tal como vienen. Cuando el archivo no trae marca o año de una caja, se toman
 * del VIN (fabricante y año). Se puede ejecutar varias veces: una unidad que ya existe no se duplica
 * ni se modifica.
 *
 *   php artisan db:seed --class=CargaInicialUnidadesSeeder
 */
class CargaInicialUnidadesSeeder extends Seeder
{
    public const DUENO = 'Industrial Hefesto';

    /** Fabricante según los 3 primeros caracteres del VIN. */
    private const FABRICANTES = [
        '1JJ' => 'Wabash', '1GR' => 'Great Dane', '1DW' => 'Stoughton', '1UY' => 'Utility',
        '3H3' => 'Hyundai Translead', '3AW' => 'Fruehauf',
    ];

    /** Cómo empieza la columna MODELO del archivo de cajas => marca. */
    private const MARCAS = [
        'GREAT DANE' => 'Great Dane', 'GD' => 'Great Dane', 'STOUGHTON' => 'Stoughton', 'UTILITY' => 'Utility',
        'WABASH' => 'Wabash', 'FRUEHAUF' => 'Fruehauf', 'HYUNDAI' => 'Hyundai Translead',
    ];

    private const ESTADOS = [
        'MEXICO' => 'México', 'MAINE' => 'Maine', 'OKLAHOMA' => 'Oklahoma', 'TEXAS' => 'Texas',
        'TENNESSEE' => 'Tennessee', 'ILLINOIS' => 'Illinois', 'INDIANA' => 'Indiana',
    ];

    public array $resultado = ['cajas_nuevas' => 0, 'cajas_existentes' => 0, 'tractos_nuevos' => 0, 'tractos_existentes' => 0, 'omitidas' => []];

    public function run(): void
    {
        DB::connection('db_externa')->transaction(function () {
            $dueno = Cliente::activos()->where('nombre', self::DUENO)->first() ?? Cliente::create(['nombre' => self::DUENO]);
            $this->cargarCajas(base_path('data/archivo.txt'), $dueno);
            $this->cargarTractos(base_path('data/unidades_gps.csv'));
        });

        $r = $this->resultado;
        $this->command?->info("Cajas: {$r['cajas_nuevas']} nuevas, {$r['cajas_existentes']} ya existían.");
        $this->command?->info("Tractocamiones: {$r['tractos_nuevos']} nuevos, {$r['tractos_existentes']} ya existían.");
        foreach ($r['omitidas'] as $motivo) {
            $this->command?->line("  Omitida: {$motivo}");
        }
    }

    private function cargarCajas(string $archivo, Cliente $dueno): void
    {
        if (! is_file($archivo)) {
            throw new RuntimeException("No se encontró {$archivo}");
        }

        $tipoSeca = TipoCaja::where('nombre', 'Caja seca')->value('id');
        $lineas = preg_split('/\r\n|\n/', trim(file_get_contents($archivo)));
        array_shift($lineas); // encabezados

        foreach ($lineas as $linea) {
            // trim() no quita el espacio "duro" (U+00A0) que traen algunas celdas copiadas de Excel.
            [$numero, $placa, $estado, $vin, $modelo, $anio] = array_map(
                fn ($v) => preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $v),
                array_pad(explode("\t", $linea), 6, ''),
            );

            if ($numero === '') {
                continue;
            }

            if (Caja::where('cliente_id', $dueno->id)->where('numero_economico', $numero)->exists()) {
                $this->resultado['cajas_existentes']++;

                continue;
            }

            // "Stoughton Dry Van 53'" → marca Stoughton, modelo Dry Van, 53 pies.
            $marca = null;
            $pies = null;
            $esDryVan = stripos($modelo, 'dry van') !== false;
            if ($modelo !== '') {
                foreach (self::MARCAS as $inicio => $nombre) {
                    if (str_starts_with(strtoupper($modelo).' ', $inicio.' ')) {
                        $marca = $nombre;
                        break;
                    }
                }
                if (preg_match("/(\d+)\s*'/", $modelo, $m)) {
                    $pies = (int) $m[1];
                }
            }

            Caja::create([
                'cliente_id' => $dueno->id,
                'tipo_caja_id' => $esDryVan ? $tipoSeca : null,
                'numero_economico' => $numero,
                'placa' => $placa === '' ? null : preg_replace('/[\s-]/', '', $placa),
                'estado_placa' => self::ESTADOS[strtoupper($estado)] ?? ($estado ?: null),
                'marca' => $marca ?? $this->fabricante($vin),
                'modelo' => $esDryVan ? 'Dry Van' : null,
                'vin' => $vin === '' ? null : strtoupper($vin),
                'anio' => $anio !== '' ? (int) $anio : $this->anioDelVin($vin),
                'tamano_pies' => $pies,
            ]);
            $this->resultado['cajas_nuevas']++;
        }
    }

    private function cargarTractos(string $archivo): void
    {
        if (! is_file($archivo)) {
            throw new RuntimeException("No se encontró {$archivo}");
        }

        $f = fopen($archivo, 'r');
        if (fread($f, 3) !== "\xEF\xBB\xBF") { // marca BOM que agrega Excel
            rewind($f);
        }
        $encabezados = fgetcsv($f, escape: '');

        while (($fila = fgetcsv($f, escape: '')) !== false) {
            $u = array_combine($encabezados, $fila);

            if ($u['Tipo'] !== 'Tractocamión') {
                $this->resultado['omitidas'][] = ($u['Número económico'] ?: $u['Serie GPS'])." ({$u['Tipo']})";

                continue;
            }

            // La misma unidad puede venir en dos GPS (por ejemplo, uno marcado "NO FUNCIONA").
            if (Tractocamion::where('numero_economico', $u['Número económico'])->exists()) {
                $this->resultado['tractos_existentes']++;

                continue;
            }

            Tractocamion::create([
                'numero_economico' => $u['Número económico'],
                'placa' => $u['Placa'] ?: null,
                'estado_placa' => null,
                'marca' => $u['Marca'] ?: null,
                'modelo' => $u['Modelo'] ?: null,
                'vin' => $u['VIN'] ?: null,
                'anio' => $u['Año'] !== '' ? (int) $u['Año'] : null,
                'es_permisionario' => false,
            ]);
            $this->resultado['tractos_nuevos']++;
        }

        fclose($f);
    }

    private function fabricante(string $vin): ?string
    {
        return self::FABRICANTES[strtoupper(substr($vin, 0, 3))] ?? null;
    }

    private function anioDelVin(string $vin): ?int
    {
        $anios = array_combine(str_split('Y123456789ABCDEFGHJKLMNPRS'), range(2000, 2025));

        // Con un VIN incompleto la posición 10 está recorrida y daría un año equivocado.
        return strlen($vin) === 17 ? ($anios[strtoupper($vin[9])] ?? null) : null;
    }
}
