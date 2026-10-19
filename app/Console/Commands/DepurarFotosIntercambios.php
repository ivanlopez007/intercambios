<?php

namespace App\Console\Commands;

use App\Models\FotoIntercambio;
use App\Models\Intercambio;
use App\Services\RegistrarIntercambio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Política de conservación: borra de R2 las fotos de los intercambios con más antigüedad que
 * config('intercambios.anios_conservacion_fotos'). Los datos del intercambio, su revisión,
 * llantas y firmas se conservan.
 */
class DepurarFotosIntercambios extends Command
{
    protected $signature = 'intercambios:depurar-fotos {--simular : Solo muestra lo que se borraría}';

    protected $description = 'Borra las fotos de intercambios que ya cumplieron su tiempo de conservación';

    public function handle(): int
    {
        $anios = (int) config('intercambios.anios_conservacion_fotos');
        $limite = now()->subYears($anios);
        $disco = Storage::disk(RegistrarIntercambio::DISCO);
        $intercambios = 0;
        $fotos = 0;

        Intercambio::where('fecha_hora', '<', $limite)
            ->whereHas('fotos')
            ->select(['id', 'folio'])
            ->chunkById(100, function ($lote) use ($disco, &$intercambios, &$fotos) {
                foreach ($lote as $intercambio) {
                    $rutas = FotoIntercambio::where('intercambio_id', $intercambio->id)->pluck('path')->all();

                    if (! $this->option('simular')) {
                        $disco->delete($rutas);
                        FotoIntercambio::where('intercambio_id', $intercambio->id)->delete();
                    }

                    $intercambios++;
                    $fotos += count($rutas);
                }
            });

        $verbo = $this->option('simular') ? 'Se borrarían' : 'Se borraron';
        $this->info("{$verbo} {$fotos} fotos de {$intercambios} intercambios anteriores a {$limite->format('d/m/Y')} ({$anios} años).");

        return self::SUCCESS;
    }
}
