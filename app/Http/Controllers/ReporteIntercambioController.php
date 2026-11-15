<?php

/*
 * ************************************************************
 * Reportes y exportación (historial y patio)
 * Archivos necesarios:
 *   ReporteIntercambioController.php, ReporteIntercambios.php, intercambio/exportar.blade.php,
 *   pdf/reporte.blade.php
 * Fecha: 10 nov 2026 - 13 nov 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

namespace App\Http\Controllers;

use App\Models\Intercambio;
use App\Services\EstadoPatio;
use App\Services\ReporteIntercambios;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta el historial de intercambios y el patio a PDF o Excel.
 *
 * - alcance=filtros (por defecto): lo mismo que se ve en pantalla con los filtros actuales.
 * - alcance=todo: sin filtros (reporte general).
 * - columnas[]: columnas elegidas; sin ninguna, todas.
 */
class ReporteIntercambioController extends Controller
{
    public function historial(Request $request, ReporteIntercambios $reporte): Response
    {
        $this->validar($request);

        $filtros = $request->input('alcance') === 'todo' ? [] : $request->only(Intercambio::FILTROS);
        $columnas = ReporteIntercambios::elegir(ReporteIntercambios::columnasHistorial(), $request->input('columnas'));

        $consulta = Intercambio::query()
            ->with(['cliente:id,nombre', 'origen:id,nombre', 'destino:id,nombre'])
            ->filtrar($filtros)
            ->latest('fecha_hora')->latest('id');

        $total = (clone $consulta)->count();
        $filas = $request->input('formato') === 'excel'
            ? $consulta->lazy(500)
            : $consulta->limit(ReporteIntercambios::MAX_FILAS_PDF)->get();

        return $reporte->descargar($request->input('formato'), 'Historial de intercambios', $columnas, $filas, $this->describirHistorial($filtros), $total);
    }

    public function patio(Request $request, EstadoPatio $patio, ReporteIntercambios $reporte): Response
    {
        $this->validar($request);

        $filtros = $request->input('alcance') === 'todo' ? [] : $request->only(EstadoPatio::FILTROS);
        $columnas = ReporteIntercambios::elegir(ReporteIntercambios::columnasPatio(), $request->input('columnas'));
        $unidades = $patio->filtrar($patio->unidades(), $filtros);

        $filas = $request->input('formato') === 'excel' ? $unidades : $unidades->take(ReporteIntercambios::MAX_FILAS_PDF);

        return $reporte->descargar($request->input('formato'), 'Cajas en patio', $columnas, $filas, $this->describirPatio($filtros), $unidades->count());
    }

    private function validar(Request $request): void
    {
        $request->validate([
            'formato' => ['required', Rule::in(['pdf', 'excel'])],
            'alcance' => ['nullable', Rule::in(['filtros', 'todo'])],
            'columnas' => ['nullable', 'array'],
            'columnas.*' => ['string'],
        ]);
    }

    /** @return array<string, string> */
    private function describirHistorial(array $filtros): array
    {
        $etiquetas = ['folio' => 'Folio', 'caja' => 'Caja', 'tracto' => 'Tracto', 'cliente' => 'Cliente', 'movimiento' => 'Movimiento', 'status' => 'Status', 'desde' => 'Desde', 'hasta' => 'Hasta'];

        return collect($filtros)
            ->filter(fn ($valor) => is_string($valor) && trim($valor) !== '')
            ->mapWithKeys(fn ($valor, $clave) => [$etiquetas[$clave] => in_array($clave, ['movimiento', 'status'], true) ? ucfirst($valor) : $valor])
            ->all();
    }

    /** @return array<string, string> */
    private function describirPatio(array $filtros): array
    {
        $vistas = ['cargadas' => 'Cargadas', 'vacias' => 'Vacías', 'alertas' => 'Con alerta'];

        return array_filter([
            'Mostrar' => $vistas[$filtros['filtro'] ?? ''] ?? null,
            'Dueño' => $filtros['dueno'] ?? null,
            'Búsqueda' => $filtros['buscar'] ?? null,
        ], fn ($valor) => is_string($valor) && trim($valor) !== '');
    }
}
