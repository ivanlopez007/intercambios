<?php

namespace App\Console\Commands;

use App\Models\BitacoraIntercambio;
use App\Services\RegistrarIntercambio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Respaldo de la limpieza que hace el asistente al descartar: borra de R2 las carpetas de
 * intercambios que nunca se registraron y cuyas fotos ya son viejas (config dias_fotos_huerfanas).
 */
class LimpiarFotosIntercambios extends Command
{
    protected $signature = 'intercambios:limpiar-fotos {--simular : Solo muestra lo que se borraría}';

    protected $description = 'Borra de R2 las fotos de intercambios que nunca se registraron';

    public function handle(): int
    {
        $disco = Storage::disk(RegistrarIntercambio::DISCO);
        $limite = now()->subDays(config('intercambios.dias_fotos_huerfanas'))->getTimestamp();
        $borradas = 0;

        foreach ($disco->directories('intercambios') as $carpeta) {
            $uuid = basename($carpeta);

            if (! Str::isUuid($uuid) || BitacoraIntercambio::where('id_cliente', $uuid)->exists()) {
                continue;
            }

            // Solo si todas sus fotos son anteriores al límite (el dispositivo pudo seguir sin señal).
            $archivos = $disco->allFiles($carpeta);
            $reciente = collect($archivos)->contains(fn ($archivo) => $disco->lastModified($archivo) >= $limite);

            if ($reciente) {
                continue;
            }

            $this->line(($this->option('simular') ? 'Se borraría ' : 'Borrando ').$carpeta.' ('.count($archivos).' archivos)');

            if (! $this->option('simular')) {
                $disco->deleteDirectory($carpeta);
            }

            $borradas++;
        }

        $this->info($borradas === 0 ? 'No hay fotos huérfanas.' : "Carpetas: {$borradas}.");

        return self::SUCCESS;
    }
}
