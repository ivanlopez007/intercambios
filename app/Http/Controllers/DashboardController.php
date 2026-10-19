<?php

namespace App\Http\Controllers;

use App\Models\Intercambio;
use App\Models\Operador;
use App\Models\RevisionPunto;
use App\Models\VisitaExterno;
use App\Services\EstadoPatio;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Dashboard del administrador: patio, movimientos, alertas, documentos de operadores y visitas.
 */
class DashboardController extends Controller
{
    public function __invoke(EstadoPatio $patio)
    {
        $unidades = $patio->unidades();
        $resumen = $patio->resumen($unidades);

        $ayer = Intercambio::completados()->whereBetween('fecha_hora', [today()->subDay(), today()->subDay()->endOfDay()])->count();

        return view('admin.dashboard', [
            'resumen' => $resumen,
            'movimientosAyer' => $ayer,
            'porDia' => $this->porDia(14),
            'porDueno' => $this->porDueno($unidades),
            'tiempoEnPatio' => $this->tiempoEnPatio($unidades),
            'hallazgos' => $this->hallazgosFrecuentes(30),
            'documentos' => $this->documentosPorVencer(),
            'recientes' => Intercambio::completados()->latest('fecha_hora')->latest('id')->limit(8)
                ->get(['id', 'folio', 'tipo_movimiento', 'fecha_hora', 'caja_numero_economico', 'caja_cliente', 'tracto_numero_economico', 'guardia_nombre']),
            'visitas' => [
                'dentro' => VisitaExterno::whereNull('fecha_salida')->count(),
                'hoy' => VisitaExterno::whereBetween('fecha_entrada', [today(), today()->endOfDay()])->count(),
            ],
        ]);
    }

    /** Entradas y salidas por día de los últimos N días (incluye hoy), con ceros en los días sin movimientos. */
    private function porDia(int $dias): array
    {
        $desde = today()->subDays($dias - 1);
        $conteo = Intercambio::completados()->where('fecha_hora', '>=', $desde)->get(['fecha_hora', 'tipo_movimiento'])
            ->groupBy(fn ($i) => $i->fecha_hora->toDateString())
            ->map->countBy('tipo_movimiento');

        return collect(range(0, $dias - 1))->map(function ($n) use ($desde, $conteo) {
            $dia = $desde->copy()->addDays($n);

            return [
                'fecha' => $dia->toDateString(),
                'etiqueta' => $this->diaCorto($dia),
                'entradas' => $conteo[$dia->toDateString()][Intercambio::ENTRADA] ?? 0,
                'salidas' => $conteo[$dia->toDateString()][Intercambio::SALIDA] ?? 0,
            ];
        })->all();
    }

    /** Cajas en patio por dueño: los 7 con más cajas y el resto en "Otros". */
    private function porDueno(Collection $unidades): array
    {
        $porDueno = $unidades->countBy(fn ($u) => $u->intercambio->caja_cliente ?? 'Sin dueño')->sortDesc();
        $principales = $porDueno->take(7);
        $otros = $porDueno->skip(7)->sum();

        return $principales->map(fn ($n, $nombre) => ['nombre' => $nombre, 'cajas' => $n])->values()
            ->when($otros > 0, fn ($c) => $c->push(['nombre' => 'Otros', 'cajas' => $otros]))
            ->all();
    }

    private function tiempoEnPatio(Collection $unidades): array
    {
        $rangos = ['< 1 día' => [0, 24], '1–3 días' => [24, 72], '3–7 días' => [72, 168], '> 7 días' => [168, PHP_INT_MAX]];

        return collect($rangos)->map(fn ($r, $nombre) => [
            'rango' => $nombre,
            'cajas' => $unidades->filter(fn ($u) => $u->horas >= $r[0] && $u->horas < $r[1])->count(),
        ])->values()->all();
    }

    /** Puntos de revisión con más hallazgos en los últimos N días. */
    private function hallazgosFrecuentes(int $dias): array
    {
        return RevisionPunto::query()
            ->whereIn('resultado', RevisionPunto::CON_HALLAZGO)
            ->whereHas('intercambio', fn ($q) => $q->completados()->where('fecha_hora', '>=', today()->subDays($dias)))
            ->with('punto:id,nombre')
            ->get(['id', 'punto_inspeccion_id'])
            ->countBy(fn ($r) => $r->punto?->nombre ?? 'Punto eliminado')
            ->sortDesc()
            ->take(6)
            ->map(fn ($n, $nombre) => ['punto' => $nombre, 'veces' => $n])
            ->values()
            ->all();
    }

    /** Operadores con licencia o apto médico vencido o por vencer, del más urgente al menos. */
    private function documentosPorVencer(): Collection
    {
        $limite = today()->addDays((int) config('intercambios.dias_aviso_vencimiento'));

        return Operador::with(['info', 'documento'])->get()
            ->flatMap(fn (Operador $o) => collect([
                'Licencia' => $o->documento?->fecha_vencimiento_licencia,
                'Apto médico' => $o->documento?->fecha_vencimiento_apto,
            ])
                ->filter(fn ($fecha) => $fecha && $fecha->lte($limite))
                ->map(fn ($fecha, $documento) => (object) [
                    'operador' => $o->nombre_completo,
                    'documento' => $documento,
                    'fecha' => $fecha,
                    'vencido' => $fecha->lt(today()),
                    'dias' => (int) today()->diffInDays($fecha, false),
                ])->values())
            ->sortBy('fecha')
            ->values();
    }

    private function diaCorto(CarbonInterface $dia): string
    {
        $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

        return $dias[$dia->dayOfWeek].' '.$dia->format('d');
    }
}
