{{--
    ************************************************************
    Reportes y exportación (historial y patio)
    Archivos necesarios:
      ReporteIntercambioController.php, ReporteIntercambios.php, intercambio/exportar.blade.php,
      pdf/reporte.blade.php
    Fecha: 10 nov 2026 - 13 nov 2026
    Realizado por: Ivan Lopez
    ************************************************************
--}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $titulo }}</title>
@php
// Con muchas columnas se reduce la letra para que quepan en la hoja.
$letra = count($encabezados) > 14 ? 6 : (count($encabezados) > 9 ? 7 : 8);
@endphp
<style>
    @page { margin: 24px 26px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: {{ $letra }}px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .encabezado td { border: none; vertical-align: middle; }
    .encabezado img { height: 34px; }
    .titulo { font-size: 14px; font-weight: bold; }
    .gris { color: #555; }
    .filtros { margin: 8px 0; font-size: 8px; }
    /* Ancho fijo: las columnas se reparten la hoja y el texto largo se parte, sin salirse. */
    .datos { table-layout: fixed; }
    .datos th { background: #0f172a; color: #fff; text-align: left; padding: 4px 3px; border: 1px solid #0f172a; word-wrap: break-word; }
    .datos td { border: 1px solid #cbd5e1; padding: 3px; vertical-align: top; word-wrap: break-word; }
    .datos tr:nth-child(even) td { background: #f1f5f9; }
    .datos thead { display: table-header-group; }
    .pie { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #666; text-align: right; }
</style>
</head>
<body>

<table class="encabezado">
    <tr>
        @if ($logo)<td style="width: 44px;"><img src="{{ $logo }}" alt=""></td>@endif
        <td>
            <div class="titulo">{{ $titulo }}</div>
            <div class="gris">{{ $empresa['nombre'] }} · {{ $empresa['punto_intercambio'] }}</div>
        </td>
        <td style="text-align: right;" class="gris">
            Generado el {{ now()->format('d/m/Y H:i') }}@if ($generadoPor)<br>por {{ $generadoPor }}@endif
        </td>
    </tr>
</table>

<div class="filtros">
    <strong>Filtros:</strong>
    @forelse ($filtros as $etiqueta => $valor)
    {{ $etiqueta }}: {{ $valor }}@if (! $loop->last) · @endif
    @empty
    ninguno (reporte general)
    @endforelse
    &nbsp;—&nbsp; <strong>{{ number_format($total) }}</strong> {{ $total === 1 ? 'registro' : 'registros' }}
    @if ($total > count($filas))
    <br><strong>Se muestran los primeros {{ number_format(count($filas)) }}.</strong> Para el listado completo, exporta a Excel.
    @endif
</div>

<table class="datos">
    <thead>
        <tr>
            @foreach ($encabezados as $encabezado)
            <th>{{ $encabezado }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
        <tr>
            @foreach ($fila as $valor)
            <td>{{ $valor }}</td>
            @endforeach
        </tr>
        @empty
        <tr><td colspan="{{ count($encabezados) }}" style="text-align: center; padding: 14px;">Sin registros.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">{{ $titulo }} · {{ $empresa['nombre'] }}</div>

</body>
</html>
