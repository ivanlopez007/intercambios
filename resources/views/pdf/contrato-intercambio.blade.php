<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Contrato de intercambio {{ $i->folio }}</title>
@php
$marca = fn ($resultado) => match ($resultado) {
    'cumple' => '✓',
    'no_cumple' => '✗',
    'no_aplica' => 'N/A',
    default => '',
};
$caja = $i->caja;
@endphp
<style>
    @page { margin: 22px 26px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    td, th { padding: 2px 3px; vertical-align: top; }
    .borde td, .borde th { border: 1px solid #333; }
    .titulo { font-size: 12px; font-weight: bold; text-align: center; }
    .sub { font-size: 10px; font-weight: bold; text-align: center; }
    .folio { font-size: 15px; font-weight: bold; text-align: right; }
    .etq { font-weight: bold; }
    .linea { border-bottom: 1px solid #333; }
    .aviso { font-weight: bold; font-size: 9.5px; text-align: center; margin: 5px 0; }
    .col { width: 50%; padding: 0 3px; }
    .caja-col { border: 2px solid #111; }
    .centro { text-align: center; }
    .lado { border: 1px solid #555; height: 34px; text-align: center; vertical-align: middle; font-size: 9px; color: #444; }
    .marca-dano { font-size: 14px; font-weight: bold; color: #b91c1c; }
    .ok { font-size: 11px; font-weight: bold; text-align: center; width: 22px; }
    .x { color: #b91c1c; }
    .comentarios { height: 120px; }
    .firma img { height: 34px; }
    .gris { color: #666; }
    .vacia { color: #999; text-align: center; padding: 30px 0; }
</style>
</head>
<body>

<table>
    <tr>
        <td style="width: 26%">@if ($logo)<img src="{{ $logo }}" style="height: 42px">@endif</td>
        <td style="width: 54%">
            <div class="sub">{{ mb_strtoupper($caja?->tipoCaja?->nombre ?? 'Caja') }}</div>
            <div class="titulo">CONTRATO DE INTERCAMBIO DE REMOLQUE<br>RECIBO Y REPORTE DE INSPECCIÓN DE SEGURIDAD</div>
        </td>
        <td style="width: 20%" class="folio">{{ $i->folio }}</td>
    </tr>
</table>

<table style="margin-top: 4px">
    <tr>
        <td><span class="etq">PUNTO DE INTERCAMBIO:</span> <span class="linea">{{ mb_strtoupper($empresa['punto_intercambio']) }}</span></td>
        <td><span class="etq">DE:</span> <span class="linea">{{ mb_strtoupper($empresa['nombre']) }}</span></td>
        <td style="text-align: right"><span class="etq">REMOLQUE:</span> <span style="font-size: 12px; font-weight: bold" class="linea">{{ $i->caja_numero_economico }}</span></td>
    </tr>
</table>
<table>
    <tr>
        <td><span class="etq">VIN:</span> <span class="linea">{{ $caja?->vin ?? '—' }}</span></td>
        <td><span class="etq">MARCA:</span> <span class="linea">{{ $caja?->marca ?? '—' }}</span></td>
        <td><span class="etq">AÑO:</span> <span class="linea">{{ $caja?->anio ?? '—' }}</span></td>
        <td><span class="etq">PLACA:</span> <span class="linea">{{ $i->caja_placa ?? '—' }}</span></td>
        <td><span class="etq">ESTADO:</span> <span class="linea">{{ $caja?->estado_placa ?? '—' }}</span></td>
        <td><span class="etq">DUEÑO:</span> <span class="linea">{{ $i->caja_cliente }}</span></td>
    </tr>
</table>

<div class="aviso">MARQUE CLARAMENTE TODO DAÑO O DEFICIENCIA ENCONTRADO CON "G" GOLPE, "C" CORTE O "A" AGUJERO</div>

{{-- Encabezado de viaje y diagrama de daños, inspección inicial y final --}}
<table>
    <tr>
        @foreach ($columnas as $titulo => $c)
        <td class="col">
            <table class="caja-col">
                @if ($c)
                @php($x = $c['intercambio'])
                <tr>
                    <td><span class="etq">TRANSPORTISTA:</span> {{ $c['transportista'] }}</td>
                    <td><span class="etq">TRACTOR:</span> {{ $x->tracto_numero_economico }}</td>
                    <td style="text-align: right" class="etq">{{ $x->estado_carga === 'cargada' ? 'CARGADA' : 'VACÍA' }}</td>
                </tr>
                <tr>
                    <td><span class="etq"># VIAJE:</span> {{ $x->referencia_carta_porte ?? '—' }}</td>
                    <td colspan="2"><span class="etq">CLIENTE:</span> {{ $x->cliente?->nombre ?? $x->caja_cliente }}</td>
                </tr>
                <tr>
                    <td><span class="etq">ORIGEN:</span> {{ $x->origen?->nombre ?? '—' }}</td>
                    <td colspan="2"><span class="etq">DESTINO:</span> {{ $x->destino?->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="etq" style="font-size: 10px">{{ $titulo }} <span class="gris" style="font-size: 8px">({{ $x->folio }} · {{ $x->tipo_movimiento === 'salida' ? 'salida' : 'entrada' }})</span></td>
                    <td><span class="etq">SELLO(S):</span> {{ $x->numero_sello ?? '—' }}</td>
                </tr>
                <tr>
                    <td colspan="3">
                        <table>
                            <tr>
                                @foreach (['LS' => 'Lado izq.', 'F' => 'Frente', 'T' => 'Techo'] as $codigo => $nombre)
                                <td class="lado">{{ $codigo }}<br><span class="marca-dano">{{ $c['lados'][$codigo] ?? '' }}</span></td>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach (['FL' => 'Piso', 'RS' => 'Lado der.', 'R' => 'Trasera'] as $codigo => $nombre)
                                <td class="lado">{{ $codigo }}<br><span class="marca-dano">{{ $c['lados'][$codigo] ?? '' }}</span></td>
                                @endforeach
                            </tr>
                        </table>
                        <div class="gris" style="font-size: 7px">LS lado izquierdo · F frente · T techo · FL piso · RS lado derecho · R trasera</div>
                    </td>
                </tr>
                @else
                <tr><td class="etq" style="font-size: 10px">{{ $titulo }}</td></tr>
                <tr><td class="vacia">Pendiente: la caja aún no regresa.</td></tr>
                @endif
            </table>
        </td>
        @endforeach
    </tr>
</table>

<div class="aviso">Si no tiene defecto, marca con ✓ · Si tiene defecto marca con "✗"</div>

{{-- Checklist, llantas, comentarios y firmas --}}
<table>
    <tr>
        @foreach ($columnas as $titulo => $c)
        <td class="col">
            @if ($c)
            @php($x = $c['intercambio'])
            <table class="borde">
                @foreach ($c['checklist']->chunk(3) as $fila)
                <tr>
                    @foreach ($fila as $nombre => $resultado)
                    <td class="etq" style="font-size: 7.5px">{{ mb_strtoupper($nombre) }}</td>
                    <td class="ok {{ $resultado === 'no_cumple' ? 'x' : '' }}">{{ $marca($resultado) }}</td>
                    @endforeach
                    @for ($k = $fila->count(); $k < 3; $k++)<td></td><td></td>@endfor
                </tr>
                @endforeach
            </table>

            <table class="borde" style="margin-top: 4px">
                <tr><th colspan="5" style="font-size: 7.5px">Las llantas deben tener un mínimo de 4/32 de profundidad por ley</th></tr>
                <tr><th>Posición</th><th>Marca</th><th>Profundidad</th><th>Orig (O)<br>Rec (R)</th><th>PSI</th></tr>
                @foreach ($c['llantas'] as $fila)
                @php($l = $fila['llanta'])
                <tr>
                    <td class="etq">{{ $fila['etiqueta'] }}</td>
                    <td class="centro">{{ $l?->marca }}</td>
                    <td class="centro {{ $l?->color === 'rojo' ? 'x' : '' }}">{{ $l?->profundidad_32 }}/32</td>
                    <td class="centro">{{ ['original' => 'O', 'renovada' => 'R'][$l?->tipo] ?? '' }}</td>
                    <td class="centro">{{ $l?->psi }}</td>
                </tr>
                @endforeach
            </table>

            <table class="borde" style="margin-top: 4px">
                <tr><td class="comentarios"><span class="etq">COMENTARIOS:</span><br>
                    @forelse ($c['comentarios'] as $texto)
                    {!! nl2br(e($texto)) !!}<br>
                    @empty
                    <span class="gris">Sin comentarios.</span>
                    @endforelse
                </td></tr>
            </table>

            <table style="margin-top: 6px">
                <tr><td style="width: 30%" class="etq">OPERADOR:</td><td class="linea">{{ $x->operador_nombre }}</td></tr>
                <tr><td class="etq">FIRMA:</td><td class="linea firma">@if ($c['firma'])<img src="{{ $c['firma'] }}">@endif</td></tr>
                <tr><td class="etq">RECIBIDA POR:</td><td class="linea">{{ $x->guardia_nombre }} &nbsp; <span class="etq">FECHA:</span> {{ $x->fecha_hora->format('d-m-Y H:i') }}</td></tr>
            </table>
            @endif
        </td>
        @endforeach
    </tr>
</table>

</body>
</html>
