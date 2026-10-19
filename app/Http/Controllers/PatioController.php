<?php

namespace App\Http\Controllers;

use App\Models\Intercambio;
use App\Services\EstadoPatio;
use Illuminate\Http\Request;

/**
 * Cajas que están en el patio (ver App\Services\EstadoPatio), con filtros.
 */
class PatioController extends Controller
{
    public function index(Request $request, EstadoPatio $patio)
    {
        $horasAlerta = (int) config('intercambios.horas_alerta_patio');
        $entradas = $patio->unidades();
        $resumen = $patio->resumen($entradas);

        // Cajas por dueño, de más a menos.
        $porDueno = $entradas->countBy(fn ($u) => $u->intercambio->caja_cliente ?? 'Sin dueño')->sortDesc();

        // Filtros (se aplican después de calcular el resumen, que siempre es de todo el patio).
        $filtro = $request->input('filtro', 'todos');
        $dueno = (string) $request->input('dueno', '');
        $buscar = mb_strtolower(trim((string) $request->input('buscar')));

        $unidades = $entradas
            ->filter(fn ($u) => match ($filtro) {
                'cargadas' => $u->intercambio->estado_carga === Intercambio::CARGADA,
                'vacias' => $u->intercambio->estado_carga === Intercambio::VACIA,
                'alertas' => $u->con_alerta,
                default => true,
            })
            ->filter(fn ($u) => $dueno === '' || ($u->intercambio->caja_cliente ?? 'Sin dueño') === $dueno)
            ->filter(fn ($u) => $buscar === '' || str_contains(mb_strtolower(implode(' ', [
                $u->intercambio->caja_numero_economico, $u->intercambio->caja_placa, $u->intercambio->caja_cliente,
                $u->intercambio->tracto_numero_economico, $u->intercambio->operador_nombre, $u->intercambio->folio,
                $u->intercambio->numero_sello, $u->intercambio->cliente?->nombre,
            ])), $buscar))
            ->values();

        return view('admin.intercambio.patio', compact('unidades', 'resumen', 'filtro', 'dueno', 'porDueno', 'horasAlerta'));
    }
}
