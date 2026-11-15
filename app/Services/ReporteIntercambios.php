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

namespace App\Services;

use App\Models\Intercambio;
use DateTimeInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as OpcionesXlsx;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reportes del historial de intercambios y del patio, en PDF o Excel, con las columnas que
 * elija el usuario. Cada columna es [título, fn (fila) => valor, ¿básica?]; en el historial la
 * fila es un Intercambio y en el patio una unidad de EstadoPatio.
 */
class ReporteIntercambios
{
    /** Más filas vuelven el PDF demasiado pesado; para eso está el Excel. */
    public const MAX_FILAS_PDF = 1000;

    /** @return array<string, array{0: string, 1: callable, 2: bool}> */
    public static function columnasHistorial(): array
    {
        return [
            'folio' => ['Folio', fn (Intercambio $i) => $i->folio, true],
            'fecha' => ['Fecha y hora', fn (Intercambio $i) => $i->fecha_hora, true],
            'movimiento' => ['Movimiento', fn (Intercambio $i) => ucfirst($i->tipo_movimiento), true],
            'status' => ['Status', fn (Intercambio $i) => ucfirst($i->status), true],
            ...self::columnasIntercambio(),
        ];
    }

    /** @return array<string, array{0: string, 1: callable, 2: bool}> */
    public static function columnasPatio(): array
    {
        $de = fn (callable $valor) => fn (object $u) => $valor($u->intercambio);

        return [
            'folio' => ['Folio de entrada', $de(fn (Intercambio $i) => $i->folio), true],
            'fecha' => ['Fecha de entrada', $de(fn (Intercambio $i) => $i->fecha_hora), true],
            'tiempo' => ['Tiempo en patio', fn (object $u) => $u->tiempo, true],
            'horas' => ['Horas en patio', fn (object $u) => $u->horas, false],
            ...array_map(fn (array $c) => [$c[0], $de($c[1]), $c[2]], self::columnasIntercambio()),
            'hallazgos' => ['Hallazgos C-TPAT', fn (object $u) => $u->hallazgos
                ->map(fn ($r) => trim(($r->punto?->nombre ?? 'Punto').($r->observaciones ? ': '.$r->observaciones : '')))
                ->implode('; '), true],
            'llantas_alerta' => ['Llantas bajo mínimo', fn (object $u) => $u->llantas_rojas, false],
            'alerta' => ['Con alerta', fn (object $u) => $u->con_alerta ? 'Sí' : 'No', true],
        ];
    }

    /** Columnas comunes: datos guardados en el intercambio. */
    private static function columnasIntercambio(): array
    {
        return [
            'caja' => ['Caja', fn (Intercambio $i) => $i->caja_numero_economico, true],
            'caja_placa' => ['Placa caja', fn (Intercambio $i) => $i->caja_placa, false],
            'dueno' => ['Dueño de la caja', fn (Intercambio $i) => $i->caja_cliente, true],
            'carga' => ['Carga', fn (Intercambio $i) => [Intercambio::CARGADA => 'Cargada', Intercambio::VACIA => 'Vacía'][$i->estado_carga] ?? null, true],
            'tipo' => ['Tipo de intercambio', fn (Intercambio $i) => $i->tipo_intercambio ? Str::upper($i->tipo_intercambio) : null, false],
            'cliente' => ['Cliente del viaje', fn (Intercambio $i) => $i->cliente?->nombre, false],
            'origen' => ['Origen', fn (Intercambio $i) => $i->origen?->nombre, false],
            'destino' => ['Destino', fn (Intercambio $i) => $i->destino?->nombre, false],
            'carta_porte' => ['Carta porte', fn (Intercambio $i) => $i->referencia_carta_porte, false],
            'sello' => ['Sello', fn (Intercambio $i) => $i->numero_sello, false],
            'tracto' => ['Tracto', fn (Intercambio $i) => $i->tracto_numero_economico, true],
            'tracto_placa' => ['Placa tracto', fn (Intercambio $i) => $i->tracto_placa, false],
            'linea' => ['Línea / permisionario', fn (Intercambio $i) => $i->tracto_linea ?: 'Propio', false],
            'odometro' => ['Odómetro (km)', fn (Intercambio $i) => $i->odometro, false],
            'operador' => ['Operador', fn (Intercambio $i) => $i->operador_nombre, true],
            'num_empleado' => ['No. empleado', fn (Intercambio $i) => $i->operador_numero_empleado, false],
            'licencia' => ['Licencia', fn (Intercambio $i) => $i->operador_licencia, false],
            'guardia' => ['Guardia', fn (Intercambio $i) => $i->guardia_nombre, true],
            'danos_caja' => ['Daños caja', fn (Intercambio $i) => $i->comentarios_caja, false],
            'danos_tracto' => ['Daños tracto', fn (Intercambio $i) => $i->comentarios_tracto, false],
            'observaciones' => ['Observaciones', fn (Intercambio $i) => $i->observaciones, false],
        ];
    }

    /**
     * Columnas pedidas, en el orden del catálogo. Sin selección válida, todas.
     *
     * @param  array<string, array>  $catalogo
     */
    public static function elegir(array $catalogo, mixed $pedidas): array
    {
        $pedidas = is_array($pedidas) ? $pedidas : [];
        $elegidas = array_intersect_key($catalogo, array_flip(array_filter($pedidas, 'is_string')));

        return $elegidas ?: $catalogo;
    }

    /**
     * @param  string  $formato  pdf o excel
     * @param  iterable  $filas  Intercambios o unidades del patio
     * @param  array<string, string>  $filtros  descripción legible de los filtros aplicados
     */
    public function descargar(string $formato, string $titulo, array $columnas, iterable $filas, array $filtros, int $total): Response
    {
        $nombre = Str::slug($titulo).'-'.now()->format('Y-m-d-His');

        return $formato === 'excel'
            ? $this->excel($nombre, $titulo, $columnas, $filas)
            : $this->pdf($nombre, $titulo, $columnas, $filas, $filtros, $total);
    }

    private function pdf(string $nombre, string $titulo, array $columnas, iterable $filas, array $filtros, int $total): Response
    {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isFontSubsettingEnabled', true);

        $filasTexto = [];
        foreach ($filas as $fila) {
            $filasTexto[] = array_map(fn (array $c) => self::texto(($c[1])($fila)), $columnas);
        }

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('pdf.reporte', [
            'titulo' => $titulo,
            'empresa' => config('intercambios.empresa'),
            'logo' => is_file($logo = config('intercambios.empresa.logo')) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
            'encabezados' => array_column($columnas, 0),
            'filas' => $filasTexto,
            'filtros' => $filtros,
            'total' => $total,
            'generadoPor' => auth()->user()?->nombre_mostrado,
        ])->render(), 'UTF-8');
        $pdf->setPaper('letter', count($columnas) > 5 ? 'landscape' : 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function excel(string $nombre, string $titulo, array $columnas, iterable $filas): Response
    {
        $opciones = new OpcionesXlsx(DEFAULT_COLUMN_WIDTH: 18);
        $ruta = tempnam(sys_get_temp_dir(), 'reporte');

        $writer = new Writer($opciones);
        $writer->openToFile($ruta);
        $hoja = $writer->getCurrentSheet();
        $hoja->setName(Str::limit($titulo, 31, ''));
        $hoja->setSheetView((new SheetView)->withFreezeRow(2));

        $encabezado = new Style(fontBold: true, fontColor: 'FFFFFF', backgroundColor: '0F172A');
        $fecha = new Style(format: 'dd/mm/yyyy hh:mm');
        $writer->addRow(Row::fromValuesWithStyle(array_column($columnas, 0), $encabezado));

        foreach ($filas as $fila) {
            $writer->addRow(new Row(array_map(function (array $c) use ($fila, $fecha) {
                $valor = ($c[1])($fila);

                return $valor instanceof DateTimeInterface
                    ? Cell::fromValue(\DateTimeImmutable::createFromInterface($valor), $fecha)
                    : Cell::fromValue(is_int($valor) || is_float($valor) ? $valor : self::texto($valor));
            }, array_values($columnas))));
        }

        $writer->close();

        return response()->download($ruta, $nombre.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    private static function texto(mixed $valor): string
    {
        return match (true) {
            $valor instanceof DateTimeInterface => $valor->format('d/m/Y H:i'),
            $valor === null => '',
            is_int($valor) || is_float($valor) => number_format($valor, is_int($valor) ? 0 : 2),
            default => (string) $valor,
        };
    }
}
