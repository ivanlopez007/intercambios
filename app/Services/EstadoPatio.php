<?php

namespace App\Services;

use App\Models\Intercambio;
use App\Models\RevisionPunto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cajas que están en el patio: aquellas cuyo último intercambio completado es una entrada.
 * Se calcula con los intercambios; no hay una tabla de patio aparte. Lo usan la pantalla de
 * patio y el dashboard.
 */
class EstadoPatio
{
    /**
     * Una unidad por caja en patio, de la que lleva más tiempo a la más reciente, con sus alertas.
     */
    public function unidades(): Collection
    {
        $horasAlerta = (int) config('intercambios.horas_alerta_patio');

        return $this->ultimasEntradas()
            ->with([
                'caja:id,cliente_id,tipo_caja_id,numero_economico,placa,marca,modelo,anio,tamano_pies',
                'caja.tipoCaja:id,nombre',
                'cliente:id,nombre',
                'origen:id,nombre',
                'destino:id,nombre',
                'relacionado:id,folio,fecha_hora',
                'llantas:id,intercambio_id,posicion,profundidad_32,psi',
                // Solo los puntos con hallazgo, con su nombre para mostrarlos.
                'revisionPuntos' => fn ($q) => $q->whereIn('resultado', RevisionPunto::CON_HALLAZGO)->with('punto:id,nombre,seccion'),
                'fotos:id,intercambio_id,tipo',
            ])
            ->orderBy('fecha_hora')
            ->get()
            ->map(function (Intercambio $i) use ($horasAlerta) {
                $horas = (int) $i->fecha_hora->diffInHours(now());
                $llantasAlerta = $i->llantas->filter(fn ($l) => in_array($l->color, ['rojo', 'amarillo'], true))->values();
                $unidad = (object) [
                    'intercambio' => $i,
                    'horas' => $horas,
                    'tiempo' => self::tiempo($i->fecha_hora->diffInMinutes(now())),
                    'hallazgos' => $i->revisionPuntos,
                    'llantas_alerta' => $llantasAlerta,
                    'llantas_rojas' => $llantasAlerta->where('color', 'rojo')->count(),
                    'danos' => filled($i->comentarios_caja) || filled($i->comentarios_tracto),
                    'excede_tiempo' => $horas >= $horasAlerta,
                    'fotos' => $i->fotos->countBy('tipo'),
                    'operador_vencido' => collect([$i->operador_licencia_vence, $i->operador_apto_medico_vence])
                        ->contains(fn ($fecha) => $fecha && $fecha->lt($i->fecha_hora->copy()->startOfDay())),
                ];
                $unidad->con_alerta = $unidad->hallazgos->isNotEmpty() || $unidad->llantas_rojas > 0 || $unidad->danos || $unidad->excede_tiempo;

                return $unidad;
            });
    }

    /** Totales del patio y movimientos de hoy. */
    public function resumen(Collection $unidades): array
    {
        // Rango de fechas (no whereDate) para que la consulta use el índice por fecha.
        $hoy = Intercambio::completados()->whereBetween('fecha_hora', [today(), today()->endOfDay()]);

        return [
            'total' => $unidades->count(),
            'cargadas' => $unidades->where('intercambio.estado_carga', Intercambio::CARGADA)->count(),
            'vacias' => $unidades->where('intercambio.estado_carga', Intercambio::VACIA)->count(),
            'con_alerta' => $unidades->where('con_alerta', true)->count(),
            'excede_tiempo' => $unidades->where('excede_tiempo', true)->count(),
            'promedio' => $unidades->isEmpty() ? null : self::tiempo((int) $unidades->avg(fn ($u) => $u->intercambio->fecha_hora->diffInMinutes(now()))),
            'entradas_hoy' => (clone $hoy)->where('tipo_movimiento', Intercambio::ENTRADA)->count(),
            'salidas_hoy' => (clone $hoy)->where('tipo_movimiento', Intercambio::SALIDA)->count(),
            'con_hallazgos' => $unidades->filter(fn ($u) => $u->hallazgos->isNotEmpty())->count(),
            'con_danos' => $unidades->where('danos', true)->count(),
            'llantas_rojas' => $unidades->filter(fn ($u) => $u->llantas_rojas > 0)->count(),
        ];
    }

    /**
     * Último intercambio completado de cada caja, solo si es una entrada. Si hay dos en la misma
     * fecha y hora, cuenta el de id mayor.
     */
    private function ultimasEntradas(): Builder
    {
        return Intercambio::query()
            ->where('intercambios.status', Intercambio::STATUS_COMPLETADO)
            ->where('intercambios.tipo_movimiento', Intercambio::ENTRADA)
            ->whereNotNull('intercambios.caja_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('intercambios as posterior')
                    ->whereColumn('posterior.caja_id', 'intercambios.caja_id')
                    ->where('posterior.status', Intercambio::STATUS_COMPLETADO)
                    ->where(fn ($q) => $q->whereColumn('posterior.fecha_hora', '>', 'intercambios.fecha_hora')
                        ->orWhere(fn ($q) => $q->whereColumn('posterior.fecha_hora', 'intercambios.fecha_hora')->whereColumn('posterior.id', '>', 'intercambios.id')));
            });
    }

    public static function tiempo(int $minutos): string
    {
        $dias = intdiv($minutos, 1440);
        $horas = intdiv($minutos % 1440, 60);

        return match (true) {
            $dias > 0 => "{$dias} d {$horas} h",
            $horas > 0 => "{$horas} h ".($minutos % 60).' min',
            default => ($minutos % 60).' min',
        };
    }
}
