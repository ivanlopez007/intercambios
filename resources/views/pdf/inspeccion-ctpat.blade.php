<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Inspección C-TPAT {{ $i->folio }}</title>
@php
// ✓ no contaminado · ✗ sospecha o compartimento contaminado · N/A no aplica
$marca = fn ($punto) => match ($revision->get($punto->id)?->resultado) {
    'cumple', 'no_detectado' => '✓',
    'no_cumple', 'detectado' => '✗',
    'no_aplica' => 'N/A',
    default => '',
};
$esX = fn ($punto) => in_array($revision->get($punto->id)?->resultado, ['no_cumple', 'detectado'], true);
$caja = $i->caja;
$filas = max(10, $ctpat->count() - 10);
@endphp
<style>
    @page { margin: 26px 30px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #333; padding: 3px 4px; vertical-align: middle; }
    .sin-borde td { border: none; }
    .titulo { font-size: 12px; font-weight: bold; text-align: center; }
    .grande { font-size: 15px; font-weight: bold; text-align: center; }
    .etq { font-size: 8px; color: #333; }
    .centro { text-align: center; }
    .ok { width: 26px; text-align: center; font-size: 12px; font-weight: bold; }
    .x { color: #b91c1c; }
    .obs { height: 70px; vertical-align: top; }
    .firma { height: 70px; text-align: center; vertical-align: bottom; }
    .firma img { height: 50px; }
    .leyenda { font-size: 8px; }
    .gris { color: #666; }
</style>
</head>
<body>

<table>
    <tr>
        <td rowspan="2" style="width: 22%" class="centro">@if ($logo)<img src="{{ $logo }}" style="height: 40px">@endif</td>
        <td colspan="6" class="titulo">INSPECCIÓN DE C-TPAT</td>
        <td style="width: 15%" class="centro"><span class="etq">NO. REMOLQUE:</span><br><span class="grande">{{ $i->caja_numero_economico }}</span></td>
    </tr>
    <tr>
        <td class="etq">Marca:</td><td>{{ $caja?->marca ?? '—' }}</td>
        <td class="etq">Dueño Rem:</td><td>{{ $i->caja_cliente }}</td>
        <td class="etq">VIN:</td><td>{{ $caja?->vin ?? '—' }}</td>
        <td rowspan="2" class="centro"><span class="etq">NO. TRACTOR</span><br><span class="grande">{{ $i->tracto_numero_economico }}</span></td>
    </tr>
    <tr>
        <td></td>
        <td class="etq">Placa Rem:</td><td>{{ $i->caja_placa ?? '—' }}</td>
        <td class="etq">Estado:</td><td>{{ $caja?->estado_placa ?? '—' }}</td>
        <td class="etq">Año:</td><td>{{ $caja?->anio ?? '—' }}</td>
    </tr>
</table>

<table style="margin-top: 6px">
    <tr><td class="etq" style="width: 20%">Línea tractor</td><td class="centro">{{ $i->tracto_linea ?: $empresa['nombre'] }}</td><td class="etq" style="width: 20%">Operador</td><td class="centro">{{ $i->operador_nombre }}</td></tr>
    <tr><td class="etq">Fecha/Hora</td><td class="centro">{{ $i->fecha_hora->format('d/m/Y H:i') }}</td><td class="etq">Estatus de remolque</td><td class="centro">{{ mb_strtoupper($i->tipo_movimiento) }} | {{ $i->estado_carga === 'cargada' ? 'CARGADA' : 'VACÍA' }}</td></tr>
    <tr><td class="etq">Folio</td><td class="centro"><b>{{ $i->folio }}</b></td><td class="etq">Destino</td><td class="centro">{{ $i->destino?->nombre ?? '—' }}</td></tr>
    <tr><td class="etq">Carta porte</td><td class="centro">{{ $i->referencia_carta_porte ?? '—' }}</td><td class="etq">Recibió</td><td class="centro">{{ $i->guardia_nombre }}</td></tr>
    <tr><td class="etq">Sello</td><td class="centro">{{ $i->numero_sello ?? '—' }}</td><td colspan="2"></td></tr>
</table>

<table style="margin-top: 10px">
    <tr><td colspan="5" class="centro" style="font-size: 8px">INDICA EL PUNTO INSPECCIONADO CON: ✓ (NO CONTAMINADO) - ✗ (SOSPECHA O COMPARTIMENTO CONTAMINADO) - N/A (NO APLICA)</td></tr>
    @for ($n = 0; $n < $filas; $n++)
    @php($a = $ctpat->get($n))
    @php($b = $ctpat->get($n + 10))
    <tr>
        <td style="width: 24%">@if ($a){{ $n + 1 }}.- {{ mb_strtoupper($a->nombre) }}@endif</td>
        <td class="ok {{ $a && $esX($a) ? 'x' : '' }}">{{ $a ? $marca($a) : '' }}</td>
        <td style="width: 24%">@if ($b){{ $n + 11 }}.- {{ mb_strtoupper($b->nombre) }}@endif</td>
        <td class="ok {{ $b && $esX($b) ? 'x' : '' }}">{{ $b ? $marca($b) : '' }}</td>
        @if ($n === 0)
        <td rowspan="{{ intdiv($filas, 2) }}" class="obs"><b>OBSERVACIONES DE REMOLQUE:</b><br>@foreach ($obsRemolque as $texto){{ $texto }}<br>@endforeach</td>
        @elseif ($n === intdiv($filas, 2))
        <td rowspan="{{ $filas - intdiv($filas, 2) }}" class="obs"><b>OBSERVACIONES DE TRACTOR:</b><br>@foreach ($obsTracto as $texto){{ $texto }}<br>@endforeach</td>
        @endif
    </tr>
    @endfor
</table>

<table class="sin-borde" style="margin-top: 10px">
    <tr>
        @foreach (['OLORES' => $olores, 'RESIDUOS' => $residuos, 'COMPLEMENTO PUNTO #12' => $complemento] as $titulo => $lista)
        <td style="width: 33%; vertical-align: top; padding: 0 4px 0 0">
            <table>
                <tr><th colspan="2" style="font-weight: normal">{{ $titulo }}</th></tr>
                @foreach ($lista as $p)
                <tr><td>{{ mb_strtoupper($p->nombre) }}</td><td class="ok {{ $esX($p) ? 'x' : '' }}">{{ $marca($p) }}</td></tr>
                @endforeach
            </table>
        </td>
        @endforeach
    </tr>
</table>

<table style="margin-top: 26px">
    <tr><th style="font-weight: normal; width: 50%">FIRMA DEL OPERADOR</th><th style="font-weight: normal">NOMBRE DEL INTERCAMBISTA</th></tr>
    <tr>
        <td class="firma">@if ($firma)<img src="{{ $firma }}"><br>@endif<span class="gris">{{ $i->operador_nombre }}</span></td>
        <td class="firma" style="vertical-align: middle; font-size: 11px">{{ $i->guardia_nombre }}</td>
    </tr>
    <tr><td colspan="2" class="leyenda">RECIBÍ DE CONFORMIDAD EL REMOLQUE ARRIBA MENCIONADO EN EL ESTADO Y CONDICIONES FÍSICAS DESCRITAS EN EL PRESENTE DOCUMENTO, RESPONSABILIZÁNDOME DE LOS DAÑOS O FALTANTES DETECTADOS DURANTE LA INSPECCIÓN.</td></tr>
</table>

<p class="gris" style="font-size: 7.5px; margin-top: 8px">{{ $empresa['nombre'] }} · Intercambio {{ $i->folio }} · Generado el {{ now()->format('d/m/Y H:i') }}</p>

</body>
</html>
