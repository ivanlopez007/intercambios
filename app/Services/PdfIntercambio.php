<?php

namespace App\Services;

use App\Models\Intercambio;
use App\Models\PuntoInspeccion;
use App\Models\RevisionPunto;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * PDF del contrato de intercambio de remolque y de la inspección C-TPAT de un intercambio,
 * con el mismo formato que los documentos en papel.
 */
class PdfIntercambio
{
    /**
     * Llantas de la caja en el contrato: [abreviatura, número en el contrato, posición en la app].
     * La app numera la caja 11-18: eje delantero 11 izq. ext., 12 izq. int., 13 der. int., 14 der. ext.;
     * eje trasero 15-18 en el mismo orden.
     */
    public const LLANTAS_CONTRATO = [
        ['LFO', 1, 11], ['LFI', 2, 12], ['LRI', 6, 16], ['LRO', 5, 15],
        ['RFO', 4, 14], ['RFI', 3, 13], ['RRO', 8, 18], ['RRI', 7, 17],
    ];

    public function contrato(Intercambio $intercambio): string
    {
        $intercambio->loadMissing('caja.tipoCaja');

        // Columna inicial = la salida; columna final = la entrada con la que regresó la caja.
        if ($intercambio->tipo_movimiento === Intercambio::SALIDA) {
            $inicial = $intercambio;
            $final = Intercambio::completados()->where('intercambio_relacionado_id', $intercambio->id)->latest('fecha_hora')->first();
        } else {
            $inicial = $intercambio->relacionado;
            $final = $intercambio;
        }

        return $this->render('pdf.contrato-intercambio', [
            'i' => $intercambio,
            'columnas' => ['INSPECCIÓN INICIAL' => $this->columnaContrato($inicial), 'INSPECCIÓN FINAL' => $this->columnaContrato($final)],
            'empresa' => config('intercambios.empresa'),
            'logo' => $this->imagenLocal(config('intercambios.empresa.logo')),
        ]);
    }

    public function ctpat(Intercambio $intercambio): string
    {
        $intercambio->loadMissing(['caja.tipoCaja', 'destino', 'revisionPuntos.punto']);

        $revision = $intercambio->revisionPuntos->filter(fn ($r) => $r->punto)->keyBy('punto_inspeccion_id');
        $puntos = PuntoInspeccion::whereIn('seccion', [PuntoInspeccion::SECCION_CTPAT, PuntoInspeccion::SECCION_OLORES, PuntoInspeccion::SECCION_RESIDUOS])
            ->orderBy('orden')->orderBy('id')->get();
        $ctpat = $puntos->where('seccion', PuntoInspeccion::SECCION_CTPAT)->whereNull('parent_id')->values();

        // Hallazgos repartidos como en el formato: puntos 1-10 son del tracto; el resto, del remolque.
        $hallazgos = $intercambio->revisionPuntos->filter(fn ($r) => $r->tieneHallazgo() && $r->punto);
        $esTracto = fn ($r) => $r->punto->seccion === PuntoInspeccion::SECCION_CTPAT && ! $r->punto->parent_id && $r->punto->orden <= 10;

        return $this->render('pdf.inspeccion-ctpat', [
            'i' => $intercambio,
            'ctpat' => $ctpat,
            'olores' => $puntos->where('seccion', PuntoInspeccion::SECCION_OLORES)->values(),
            'residuos' => $puntos->where('seccion', PuntoInspeccion::SECCION_RESIDUOS)->values(),
            'complemento' => $puntos->whereIn('parent_id', $ctpat->pluck('id'))->values(),
            'revision' => $revision,
            'obsRemolque' => array_filter([$intercambio->comentarios_caja, ...$hallazgos->reject($esTracto)->map(fn ($r) => "{$r->punto->nombre}: {$r->observaciones}")->all(), $intercambio->observaciones]),
            'obsTracto' => array_filter([$intercambio->comentarios_tracto, ...$hallazgos->filter($esTracto)->map(fn ($r) => "{$r->punto->nombre}: {$r->observaciones}")->all()]),
            'firma' => $this->imagenR2($intercambio->firma_operador_path),
            'empresa' => config('intercambios.empresa'),
            'logo' => $this->imagenLocal(config('intercambios.empresa.logo')),
        ]);
    }

    /** Datos de una columna del contrato (inspección inicial o final). Null si aún no existe. */
    private function columnaContrato(?Intercambio $i): ?array
    {
        if (! $i) {
            return null;
        }

        $i->loadMissing(['cliente', 'origen', 'destino', 'llantas', 'revisionPuntos.punto']);
        $porSeccion = fn (string $seccion) => $i->revisionPuntos
            ->filter(fn ($r) => $r->punto?->seccion === $seccion)
            ->mapWithKeys(fn ($r) => [$r->punto->nombre => $r->resultado]);

        $remolque = $porSeccion(PuntoInspeccion::SECCION_REMOLQUE);
        $cargadores = $i->revisionPuntos->first(fn ($r) => $r->punto?->seccion === PuntoInspeccion::SECCION_CTPAT && $r->punto->nombre === 'Cargadores');

        // Checklist del contrato: los puntos del remolque más "Cargadores" de la revisión C-TPAT.
        $checklist = collect(PuntoInspeccion::PUNTOS_CONTRATO[PuntoInspeccion::SECCION_REMOLQUE])
            ->mapWithKeys(fn ($nombre) => [$nombre => $remolque[$nombre] ?? null])
            ->put('Cargadores', $cargadores?->resultado);

        $danos = $porSeccion(PuntoInspeccion::SECCION_DANOS_CAJA);
        $lados = collect(PuntoInspeccion::PUNTOS_CONTRATO[PuntoInspeccion::SECCION_DANOS_CAJA])
            ->map(fn ($nombre) => RevisionPunto::DANOS[$danos[$nombre] ?? ''] ?? null);

        $llantas = $i->llantas->keyBy('posicion');

        return [
            'intercambio' => $i,
            'transportista' => $i->tracto_linea ?: config('intercambios.empresa.nombre'),
            'checklist' => $checklist,
            'lados' => $lados,
            'llantas' => collect(self::LLANTAS_CONTRATO)->map(fn ($l) => ['etiqueta' => "{$l[0]} ({$l[1]})", 'llanta' => $llantas->get($l[2])]),
            'comentarios' => array_filter([$i->comentarios_caja, $i->observaciones]),
            'firma' => $this->imagenR2($i->firma_operador_path),
        ];
    }

    private function render(string $vista, array $datos): string
    {
        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('isFontSubsettingEnabled', true); // solo los caracteres usados: PDF más ligero

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view($vista, $datos)->render(), 'UTF-8');
        $pdf->setPaper('letter');
        $pdf->render();

        return $pdf->output();
    }

    /** Imagen guardada en R2 como data URI (para incrustarla en el PDF), o null si no se puede leer. */
    private function imagenR2(?string $ruta): ?string
    {
        if (! $ruta) {
            return null;
        }

        try {
            $disco = Storage::disk(RegistrarIntercambio::DISCO);

            return 'data:'.($disco->mimeType($ruta) ?: 'image/png').';base64,'.base64_encode($disco->get($ruta));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function imagenLocal(?string $ruta): ?string
    {
        return $ruta && is_file($ruta) ? 'data:'.mime_content_type($ruta).';base64,'.base64_encode(file_get_contents($ruta)) : null;
    }
}
