<?php

namespace Database\Seeders;

use App\Models\PuntoInspeccion;
use Illuminate\Database\Seeder;

/**
 * Agrega al catálogo puntos_inspeccion (base de RH) los puntos del contrato de intercambio de
 * remolque: el checklist del remolque y los lados de la caja para marcar daños (G, C o A).
 * Se puede ejecutar varias veces: no duplica ni cambia los que ya existen.
 *
 *   php artisan db:seed --class=PuntosInspeccionContratoSeeder
 */
class PuntosInspeccionContratoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            PuntoInspeccion::SECCION_REMOLQUE => PuntoInspeccion::RESPUESTA_OK_FALLA,
            PuntoInspeccion::SECCION_DANOS_CAJA => PuntoInspeccion::RESPUESTA_DANO,
        ];

        $creados = 0;

        foreach (PuntoInspeccion::PUNTOS_CONTRATO as $seccion => $puntos) {
            foreach (array_values($puntos) as $orden => $nombre) {
                $punto = PuntoInspeccion::query()->firstOrNew(['seccion' => $seccion, 'nombre' => $nombre]);

                if (! $punto->exists) {
                    $punto->forceFill([
                        'tipo_respuesta' => $tipos[$seccion],
                        'orden' => $orden + 1,
                        'activo' => true,
                    ])->save();
                    $creados++;
                }
            }
        }

        $this->command?->info("Puntos del contrato agregados: {$creados}.");
    }
}
