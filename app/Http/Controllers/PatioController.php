<?php

/*
 * ************************************************************
 * Estado del patio
 * Archivos necesarios:
 *   PatioController.php, EstadoPatio.php, intercambio/patio.blade.php
 * Fecha: 03 nov 2026 - 07 nov 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

namespace App\Http\Controllers;

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
        $unidades = $patio->filtrar($entradas, $request->only(EstadoPatio::FILTROS));

        return view('admin.intercambio.patio', compact('unidades', 'resumen', 'filtro', 'dueno', 'porDueno', 'horasAlerta'));
    }
}
