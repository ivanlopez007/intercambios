<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\LineaTransportista;
use App\Models\Operador;
use App\Models\TipoCaja;
use App\Models\Tractocamion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Agrega más datos de demostración (solo local) sobre los de DemoIntercambiosSeeder: más clientes,
 * cajas y tractos, y la historia de entradas y salidas de cada caja de los últimos 15 días.
 * Se anota en el mismo registro, así que `php artisan intercambios:borrar-demo` también lo quita.
 *
 *   php artisan db:seed --class=MasDemoIntercambiosSeeder
 */
class MasDemoIntercambiosSeeder extends DemoIntercambiosSeeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Los datos de demostración solo se crean en local o testing.');
        }

        if (! Storage::disk('local')->exists(self::ARCHIVO)) {
            parent::run();
        }

        $this->creados = json_decode(Storage::disk('local')->get(self::ARCHIVO), true);

        $operadores = Operador::pluck('id')->values();
        $guardia = User::where('rol', 'admin')->where('activo', true)->first();

        if ($operadores->isEmpty() || ! $guardia) {
            throw new RuntimeException('Se necesita al menos un operador en RH y un usuario admin activo.');
        }

        $catalogos = DB::connection('db_externa')->transaction(fn () => $this->crearMasCatalogos());
        $this->guardarRegistro();

        $escenarios = $this->generarEscenarios($catalogos, $operadores->all());
        $antes = count($this->creados['intercambios'] ?? []);
        $this->registrarEscenarios($escenarios, $catalogos, $guardia, 100 + $antes);
        $this->guardarRegistro();

        $this->command?->info('Intercambios agregados: '.(count($this->creados['intercambios']) - $antes).'. Total de demostración: '.count($this->creados['intercambios']).'.');
        $this->command?->info('Para borrar todo: php artisan intercambios:borrar-demo');
    }

    private function crearMasCatalogos(): array
    {
        $tipos = TipoCaja::pluck('id', 'nombre');
        $sufijo = strtoupper(Str::random(2)); // evita choques si se ejecuta más de una vez

        $clientes = collect([
            'nissan' => ['Nissan Mexicana', 'NMA961202HQ1'],
            'ternium' => ['Ternium México', 'TME050617AA4'],
            'lala' => ['Grupo Lala', 'GLA850101LA3'],
            'bimbo' => ['Grupo Bimbo', 'GBI810615RI5'],
            'deere' => ['John Deere', null],
            'whirlpool' => ['Whirlpool México', 'WME880912KP2'],
        ])->map(fn ($c) => Cliente::activos()->where('nombre', $c[0])->first()
            ?? $this->anotar('clientes', Cliente::create(['nombre' => $c[0], 'rfc' => $c[1]])));

        $linea = LineaTransportista::activos()->where('nombre', 'Autotransportes Garza')->first()
            ?? $this->anotar('lineas', LineaTransportista::create(['nombre' => 'Autotransportes Garza']));

        foreach (['Ramos Arizpe', 'San Luis Potosí', 'Aguascalientes', 'Eagle Pass, TX', 'Monclova'] as $nombre) {
            Destino::activos()->where('nombre', $nombre)->exists() || $this->anotar('destinos', Destino::create(['nombre' => $nombre]));
        }
        $destinos = Destino::activos()->get()->keyBy('nombre');

        $definicion = [
            ['nissan', 'NS-401', 'Caja seca', 'Utility', 2022], ['nissan', 'NS-402', 'Caja seca', 'Utility', 2022],
            ['nissan', 'NS-415', 'Cortina', 'Wabash', 2021], ['ternium', 'TM-88', 'Plataforma', 'Fontaine', 2018, 48],
            ['ternium', 'TM-90', 'Plataforma', 'Fontaine', 2019, 48], ['lala', 'LL-2201', 'Caja refrigerada', 'Great Dane', 2023],
            ['lala', 'LL-2207', 'Caja refrigerada', 'Utility', 2020], ['bimbo', 'BB-310', 'Caja seca', 'Stoughton', 2019],
            ['bimbo', 'BB-311', 'Caja seca', 'Stoughton', 2019], ['deere', 'JD-07', 'Plataforma', 'Great Dane', 2021, 48],
            ['whirlpool', 'WP-1150', 'Caja seca', 'Wabash', 2020], ['whirlpool', 'WP-1162', 'Cortina', 'Hyundai Translead', 2022],
            ['whirlpool', 'WP-1171', 'Caja seca', 'Wabash', 2023], ['lala', 'LL-2215', 'Caja refrigerada', 'Great Dane', 2024],
        ];

        $cajas = [];
        foreach ($definicion as $n => $c) {
            $cajas[$c[1]] = $this->anotar('cajas', Caja::create([
                'cliente_id' => $clientes[$c[0]]->id, 'tipo_caja_id' => $tipos[$c[2]] ?? null,
                'numero_economico' => $c[1], 'placa' => sprintf('%02d-U%s-%dK', 60 + $n, chr(65 + $n), $n),
                'estado_placa' => ['Nuevo León', 'Coahuila', 'Texas', 'San Luis Potosí'][$n % 4],
                'marca' => $c[3], 'modelo' => '53 FT', 'vin' => strtoupper(Str::random(17)), 'anio' => $c[4], 'tamano_pies' => $c[5] ?? 53,
            ]));
        }

        $tractos = Tractocamion::activos()->get()->keyBy('numero_economico')->all();
        foreach ([
            ['T-103', 'Freightliner', 'Cascadia', 2023, false], ['T-104', 'Volvo', 'VNL 760', 2022, false],
            ['T-105', 'Kenworth', 'T880', 2020, false], ['P-202', 'International', 'LT625', 2018, true], ['P-203', 'Peterbilt', '579', 2021, true],
        ] as $n => [$numero, $marca, $modelo, $anio, $permisionario]) {
            if (isset($tractos[$numero])) {
                $numero .= "-{$sufijo}";
            }
            $tractos[$numero] = $this->anotar('tractos', Tractocamion::create([
                'numero_economico' => $numero, 'placa' => sprintf('4%d-AB-%dC', 6 + $n, 3 + $n), 'estado_placa' => 'Nuevo León',
                'marca' => $marca, 'modelo' => $modelo, 'vin' => strtoupper(Str::random(17)), 'anio' => $anio,
                'es_permisionario' => $permisionario, 'linea_transportista_id' => $permisionario ? $linea->id : null,
            ]));
        }

        return ['destinos' => $destinos, 'cajas' => $cajas, 'tractos' => $tractos];
    }

    /**
     * Historia de cada caja. Patrones: solo entrada, salida y regreso, o entrada y salida
     * (esta última ya no está en el patio). Las alertas se reparten entre las cajas.
     */
    private function generarEscenarios(array $c, array $operadores): array
    {
        $tractos = array_keys($c['tractos']);
        $destinos = $c['destinos']->keys()->all();
        $base = $c['destinos']->has('Patio Monterrey') ? 'Patio Monterrey' : $destinos[0];
        $fuera = array_values(array_diff($destinos, [$base]));

        $hallazgos = [
            ['Piso interior' => 'Tabla del piso suelta cerca de la puerta.'],
            ['Paredes laterales' => 'Panel lateral con perforación pequeña.', 'Remaches' => 'Remaches recientes en panel 4.'],
            ['Luces' => 'Calavera derecha fundida.'],
            ['Polvos' => 'Residuos de polvo blanco en el piso.'],
            ['Topes' => 'Tope trasero doblado.'],
        ];
        $danos = [
            [['Lado derecho' => 'golpe'], 'Golpe en panel lateral derecho, a la altura del eje trasero.'],
            [['Trasera (puertas)' => 'corte', 'Piso' => 'golpe'], 'Corte en el hule de la puerta y golpe en el piso trasero.'],
            [['Techo' => 'agujero'], 'Agujero pequeño en el techo; posible filtración.'],
            [['Frente' => 'golpe', 'Lado izquierdo' => 'corte'], 'Golpe en la esquina frontal y corte en la lona lateral.'],
        ];

        $escenarios = [];
        $k = 0;

        foreach (array_keys($c['cajas']) as $i => $caja) {
            $patron = [['entrada'], ['salida', 'entrada'], ['entrada', 'salida'], ['salida', 'entrada']][$i % 4];
            $hace = 340 - $i * 22; // horas antes de ahora del primer movimiento
            $lugar = $fuera[$i % count($fuera)];

            foreach ($patron as $m => $movimiento) {
                $cargada = ($i + $m) % 3 !== 0;
                $e = [
                    'hace' => max(2, $hace - $m * (60 + ($i % 5) * 18)),
                    'mov' => $movimiento,
                    'op' => $operadores[$k % count($operadores)],
                    'caja' => $caja,
                    'tracto' => $tractos[$k % count($tractos)],
                    'carga' => $cargada ? 'cargada' : 'vacia',
                    'tipo' => ['impo', 'expo', 'local'][$k % 3],
                    'origen' => $movimiento === 'entrada' ? $lugar : $base,
                    'destino' => $movimiento === 'entrada' ? $base : $lugar,
                    'sello' => $cargada ? 'S-'.(90100 + $k) : null,
                ];

                // Alertas solo en la última entrada de algunas cajas (las que quedan en patio).
                if ($movimiento === 'entrada' && $m === count($patron) - 1) {
                    if ($i % 3 === 0) {
                        $e['hallazgos'] = $hallazgos[$i % count($hallazgos)];
                    }
                    if ($i % 4 === 1) {
                        [$e['lados'], $e['comentarios_caja']] = $danos[$i % count($danos)];
                        $e['problemas'] = ['caja' => 'DANO '.$caja];
                    }
                    if ($i % 5 === 2) {
                        $e['llantas_bajas'] = [12 + $i % 6 => 1 + $i % 2, 3 => 1]; // 1/32: bajo el mínimo legal
                    }
                    if ($i === 6) {
                        $e['comentarios_tracto'] = 'Fuga de aire en la línea de servicio del tracto.';
                        $e['problemas'] = ['tracto' => 'FUGA DE AIRE'];
                    }
                }

                $escenarios[] = $e;
                $k++;
            }
        }

        // En orden cronológico, para que cada entrada encuentre su salida relacionada.
        usort($escenarios, fn ($a, $b) => $b['hace'] <=> $a['hace']);

        return $escenarios;
    }
}
