<?php

namespace App\Console\Commands;

use App\Models\BitacoraIntercambio;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\Intercambio;
use App\Models\LineaTransportista;
use App\Models\Tractocamion;
use App\Services\RegistrarIntercambio;
use Database\Seeders\DemoIntercambiosSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deshace lo creado por DemoIntercambiosSeeder (anotado en storage/app/demo-intercambios.json):
 * intercambios, sus fotos y firmas en R2, catálogos y el contador de folios.
 */
class BorrarDemoIntercambios extends Command
{
    protected $signature = 'intercambios:borrar-demo';

    protected $description = 'Borra los datos de demostración de intercambios';

    public function handle(): int
    {
        $archivo = DemoIntercambiosSeeder::ARCHIVO;

        if (! Storage::disk('local')->exists($archivo)) {
            $this->info('No hay datos de demostración.');

            return self::SUCCESS;
        }

        $demo = json_decode(Storage::disk('local')->get($archivo), true);
        $intercambios = collect($demo['intercambios'] ?? []);

        // Si alguien usó una caja, un tracto o un catálogo de demostración en un intercambio real,
        // no se borra nada para no romperlo.
        $reales = Intercambio::whereNotIn('id', $intercambios->pluck('id'))
            ->where(fn ($q) => $q->whereIn('caja_id', $demo['cajas'] ?? [])
                ->orWhereIn('tractocamion_id', $demo['tractos'] ?? [])
                ->orWhereIn('cliente_id', $demo['clientes'] ?? [])
                ->orWhereIn('origen_id', $demo['destinos'] ?? [])
                ->orWhereIn('destino_id', $demo['destinos'] ?? []))
            ->pluck('folio');

        if ($reales->isNotEmpty()) {
            $this->error('Hay intercambios reales que usan datos de demostración: '.$reales->join(', ').'. No se borró nada.');

            return self::FAILURE;
        }

        DB::connection('db_externa')->transaction(function () use ($demo, $intercambios) {
            Intercambio::whereIn('id', $intercambios->pluck('id'))->update(['intercambio_relacionado_id' => null]);
            Intercambio::whereIn('id', $intercambios->pluck('id'))->delete(); // llantas, revisión y fotos se borran en cascada
            Caja::whereIn('id', $demo['cajas'] ?? [])->delete();
            Tractocamion::whereIn('id', $demo['tractos'] ?? [])->delete();
            Cliente::whereIn('id', $demo['clientes'] ?? [])->delete();
            LineaTransportista::whereIn('id', $demo['lineas'] ?? [])->delete();
            Destino::whereIn('id', $demo['destinos'] ?? [])->delete();

            // El contador queda en el folio más alto que sigue existiendo en el año (nunca baja de
            // un folio real, para no repetirlo). Si ya no queda ninguno, vuelve a empezar.
            $folios = DB::connection('db_externa')->table('folios_intercambio')->where('anio', $demo['anio']);
            $ultimoQueda = Intercambio::where('folio', 'like', "INT-{$demo['anio']}-%")->pluck('folio')
                ->map(fn ($folio) => (int) substr($folio, -6))->max();

            $ultimoQueda
                ? (clone $folios)->update(['ultimo' => max($ultimoQueda, (int) ($demo['folio_previo'] ?? 0)), 'updated_at' => now()])
                : ($demo['folio_previo'] === null ? (clone $folios)->delete() : (clone $folios)->update(['ultimo' => $demo['folio_previo'], 'updated_at' => now()]));
        });

        BitacoraIntercambio::whereIn('id_cliente', $intercambios->pluck('id_cliente'))->delete();

        foreach ($intercambios as $i) {
            Storage::disk(RegistrarIntercambio::DISCO)->deleteDirectory(RegistrarIntercambio::carpeta($i['id_cliente']));
        }

        Storage::disk('local')->delete($archivo);
        $this->info("Datos de demostración borrados ({$intercambios->count()} intercambios).");

        return self::SUCCESS;
    }
}
