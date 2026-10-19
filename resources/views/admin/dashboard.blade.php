@extends('admin.layout.layout')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('vite')
@vite('resources/js/graficas.js')
@endpush

@php
$tarjeta = 'bg-white rounded-2xl border border-slate-200 shadow-sm';
$titulo = 'text-sm font-black text-slate-900';
$subtitulo = 'text-xs text-slate-500';
$tabla = 'w-full text-xs text-left text-slate-600';
$movimientosHoy = $resumen['entradas_hoy'] + $resumen['salidas_hoy'];
$totalDias = collect($porDia);
@endphp

@section('contenido')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-black text-slate-900">Hola, {{ explode(' ', auth()->user()->name)[0] }}</h2>
            <p class="text-xs text-slate-500">{{ now()->locale('es')->isoFormat('dddd D [de] MMMM, HH:mm') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.intercambios.nuevo') }}" class="flex-1 sm:flex-none text-center px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold">
                <i class="fa-solid fa-plus"></i> Nuevo intercambio
            </a>
            <a href="{{ route('admin.intercambios.patio') }}" class="flex-1 sm:flex-none text-center px-4 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 text-sm font-bold hover:bg-slate-50">
                <i class="fa-solid fa-square-parking"></i> Patio
            </a>
        </div>
    </div>

    {{-- Cifras principales --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <a href="{{ route('admin.intercambios.patio') }}" class="{{ $tarjeta }} p-4 hover:border-blue-300 transition">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Cajas en patio</p>
            <p class="text-4xl font-black text-slate-900 mt-1">{{ $resumen['total'] }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $resumen['cargadas'] }} cargadas · {{ $resumen['vacias'] }} vacías</p>
            <p class="text-xs text-slate-500">Promedio: {{ $resumen['promedio'] ?? '—' }}</p>
        </a>

        <a href="{{ route('admin.intercambios.historial', ['desde' => today()->toDateString(), 'hasta' => today()->toDateString()]) }}" class="{{ $tarjeta }} p-4 hover:border-blue-300 transition">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Intercambios hoy</p>
            <p class="text-4xl font-black text-slate-900 mt-1">{{ $movimientosHoy }}</p>
            <p class="text-xs text-slate-500 mt-1"><i class="fa-solid fa-right-to-bracket text-slate-400"></i> {{ $resumen['entradas_hoy'] }} entradas · <i class="fa-solid fa-right-from-bracket text-slate-400"></i> {{ $resumen['salidas_hoy'] }} salidas</p>
            <p class="text-xs text-slate-500">Ayer: {{ $movimientosAyer }}</p>
        </a>

        <a href="{{ route('admin.intercambios.patio', ['filtro' => 'alertas']) }}" class="{{ $tarjeta }} p-4 transition {{ $resumen['con_alerta'] ? 'border-rose-200 hover:border-rose-400' : 'hover:border-blue-300' }}">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Cajas con alerta</p>
            <p class="text-4xl font-black mt-1 {{ $resumen['con_alerta'] ? 'text-rose-700' : 'text-slate-900' }}">
                @if ($resumen['con_alerta'])<i class="fa-solid fa-triangle-exclamation text-2xl align-middle"></i>@else<i class="fa-solid fa-circle-check text-2xl align-middle text-emerald-600"></i>@endif
                {{ $resumen['con_alerta'] }}
            </p>
            <p class="text-xs text-slate-500 mt-1 leading-snug">{{ $resumen['con_hallazgos'] }} hallazgos · {{ $resumen['con_danos'] }} daños · {{ $resumen['llantas_rojas'] }} llantas · {{ $resumen['excede_tiempo'] }} &gt; {{ config('intercambios.horas_alerta_patio') }} h</p>
        </a>

        <a href="{{ route('admin.entradas-salidas.historial', ['estatus' => 'dentro']) }}" class="{{ $tarjeta }} p-4 hover:border-blue-300 transition">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Visitantes dentro</p>
            <p class="text-4xl font-black text-slate-900 mt-1">{{ $visitas['dentro'] }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $visitas['hoy'] }} {{ $visitas['hoy'] === 1 ? 'visita' : 'visitas' }} hoy</p>
        </a>
    </div>

    {{-- Movimientos por día y tiempo en patio --}}
    <div class="grid lg:grid-cols-3 gap-4">
        <section class="{{ $tarjeta }} p-4 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                <div>
                    <h3 class="{{ $titulo }}">Intercambios por día</h3>
                    <p class="{{ $subtitulo }}">Últimos 14 días · {{ $totalDias->sum('entradas') }} entradas y {{ $totalDias->sum('salidas') }} salidas</p>
                </div>
                <div class="flex gap-3 text-xs text-slate-600" aria-hidden="true">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: var(--serie-1)"></span>Entradas</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background: var(--serie-2)"></span>Salidas</span>
                </div>
            </div>
            <div class="h-64"><canvas id="grafica-por-dia" role="img" aria-label="Entradas y salidas por día de los últimos 14 días"></canvas></div>
            <details class="mt-2">
                <summary class="text-xs font-semibold text-slate-500 cursor-pointer">Ver tabla</summary>
                <table class="{{ $tabla }} mt-2">
                    <thead><tr class="text-slate-400"><th class="py-1">Día</th><th>Entradas</th><th>Salidas</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($porDia as $d)<tr><td class="py-1">{{ $d['etiqueta'] }}</td><td>{{ $d['entradas'] }}</td><td>{{ $d['salidas'] }}</td></tr>@endforeach
                    </tbody>
                </table>
            </details>
        </section>

        <section class="{{ $tarjeta }} p-4">
            <h3 class="{{ $titulo }}">Tiempo en patio</h3>
            <p class="{{ $subtitulo }} mb-3">Cajas por tiempo desde su entrada</p>
            <div class="h-64"><canvas id="grafica-tiempo" role="img" aria-label="Cajas en patio por tiempo de permanencia"></canvas></div>
            <details class="mt-2">
                <summary class="text-xs font-semibold text-slate-500 cursor-pointer">Ver tabla</summary>
                <table class="{{ $tabla }} mt-2">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tiempoEnPatio as $t)<tr><td class="py-1">{{ $t['rango'] }}</td><td class="text-right">{{ $t['cajas'] }}</td></tr>@endforeach
                    </tbody>
                </table>
            </details>
        </section>
    </div>

    {{-- Dueños, hallazgos y documentos --}}
    <div class="grid lg:grid-cols-3 gap-4">
        <section class="{{ $tarjeta }} p-4">
            <h3 class="{{ $titulo }}">Cajas en patio por dueño</h3>
            <p class="{{ $subtitulo }} mb-3">Quién tiene más cajas dentro</p>
            @if ($porDueno)
            <div style="height: {{ max(160, count($porDueno) * 34) }}px"><canvas id="grafica-duenos" role="img" aria-label="Cajas en patio por dueño"></canvas></div>
            <details class="mt-2">
                <summary class="text-xs font-semibold text-slate-500 cursor-pointer">Ver tabla</summary>
                <table class="{{ $tabla }} mt-2"><tbody class="divide-y divide-slate-100">
                    @foreach ($porDueno as $d)<tr><td class="py-1">{{ $d['nombre'] }}</td><td class="text-right">{{ $d['cajas'] }}</td></tr>@endforeach
                </tbody></table>
            </details>
            @else
            <p class="text-sm text-slate-500 py-8 text-center">No hay cajas en el patio.</p>
            @endif
        </section>

        <section class="{{ $tarjeta }} p-4">
            <h3 class="{{ $titulo }}">Hallazgos más frecuentes</h3>
            <p class="{{ $subtitulo }} mb-3">Puntos que no cumplieron o con detección · últimos 30 días</p>
            @if ($hallazgos)
            <div style="height: {{ max(160, count($hallazgos) * 34) }}px"><canvas id="grafica-hallazgos" role="img" aria-label="Hallazgos más frecuentes de los últimos 30 días"></canvas></div>
            <details class="mt-2">
                <summary class="text-xs font-semibold text-slate-500 cursor-pointer">Ver tabla</summary>
                <table class="{{ $tabla }} mt-2"><tbody class="divide-y divide-slate-100">
                    @foreach ($hallazgos as $h)<tr><td class="py-1">{{ $h['punto'] }}</td><td class="text-right">{{ $h['veces'] }}</td></tr>@endforeach
                </tbody></table>
            </details>
            @else
            <p class="text-sm text-emerald-700 font-semibold py-8 text-center"><i class="fa-solid fa-circle-check"></i> Sin hallazgos en 30 días.</p>
            @endif
        </section>

        <section class="{{ $tarjeta }} p-4">
            <h3 class="{{ $titulo }}">Documentos de operadores</h3>
            <p class="{{ $subtitulo }} mb-3">Vencidos o que vencen en {{ config('intercambios.dias_aviso_vencimiento') }} días o menos</p>
            <ul class="divide-y divide-slate-100">
                @forelse ($documentos->take(8) as $d)
                <li class="py-2 flex items-start justify-between gap-2 text-sm">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800 truncate">{{ $d->operador }}</p>
                        <p class="text-xs text-slate-500">{{ $d->documento }} · {{ $d->fecha->format('d/m/Y') }}</p>
                    </div>
                    @if ($d->vencido)
                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800"><i class="fa-solid fa-circle-xmark"></i> Vencido</span>
                    @else
                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800"><i class="fa-solid fa-clock"></i> {{ $d->dias === 0 ? 'Hoy' : 'En '.$d->dias.' d' }}</span>
                    @endif
                </li>
                @empty
                <li class="py-8 text-center text-sm text-emerald-700 font-semibold"><i class="fa-solid fa-circle-check"></i> Todos los documentos están vigentes.</li>
                @endforelse
            </ul>
            @if ($documentos->count() > 8)<p class="text-xs text-slate-500 pt-2">Y {{ $documentos->count() - 8 }} más.</p>@endif
        </section>
    </div>

    {{-- Últimos intercambios --}}
    <section class="{{ $tarjeta }} overflow-hidden">
        <div class="p-4 flex items-center justify-between">
            <div>
                <h3 class="{{ $titulo }}">Últimos intercambios</h3>
                <p class="{{ $subtitulo }}">Los 8 más recientes</p>
            </div>
            <a href="{{ route('admin.intercambios.historial') }}" class="text-sm font-bold text-blue-700">Ver historial <i class="fa-solid fa-arrow-right text-xs"></i></a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($recientes as $r)
            <a href="{{ route('admin.intercambios.show', $r) }}" class="px-4 py-3 flex items-center gap-3 hover:bg-slate-50 text-sm">
                <span class="w-9 h-9 shrink-0 rounded-xl flex items-center justify-center {{ $r->tipo_movimiento === 'entrada' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}" title="{{ ucfirst($r->tipo_movimiento) }}">
                    <i class="fa-solid {{ $r->tipo_movimiento === 'entrada' ? 'fa-right-to-bracket' : 'fa-right-from-bracket' }}"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-900 truncate">{{ ucfirst($r->tipo_movimiento) }} · caja {{ $r->caja_numero_economico }} <span class="font-normal text-slate-500">· {{ $r->caja_cliente }}</span></p>
                    <p class="text-xs text-slate-500 truncate">{{ $r->folio }} · tracto {{ $r->tracto_numero_economico }} · {{ $r->guardia_nombre }}</p>
                </div>
                <span class="shrink-0 text-xs text-slate-500 text-right">{{ $r->fecha_hora->format('d/m') }}<br>{{ $r->fecha_hora->format('H:i') }}</span>
            </a>
            @empty
            <p class="px-4 py-8 text-center text-sm text-slate-500">Todavía no hay intercambios.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection

@push('styles')
<style>
    /* Colores de las gráficas (paleta validada: azul y naranja distinguibles con daltonismo). */
    :root { --serie-1: #2a78d6; --serie-2: #eb6834; }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const Chart = window.Chart;
        if (!Chart) return;

        const css = getComputedStyle(document.documentElement);
        const serie1 = css.getPropertyValue('--serie-1').trim();
        const serie2 = css.getPropertyValue('--serie-2').trim();
        const texto = '#52514e';
        const rejilla = '#ecebe7';

        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 11;
        Chart.defaults.color = texto;

        // Barras: máximo 24 px, 4 px redondeado en el extremo y recto en la base.
        const barra = (color) => ({ backgroundColor: color, maxBarThickness: 24, borderRadius: 4, borderSkipped: 'start' });
        const ejeValores = { beginAtZero: true, ticks: { precision: 0 }, grid: { color: rejilla }, border: { display: false } };
        // Etiquetas siempre horizontales: si no caben, se omiten algunas (el tooltip y la tabla tienen todas).
        const ejeCategorias = { grid: { display: false }, border: { color: rejilla }, ticks: { maxRotation: 0, autoSkipPadding: 6 } };
        const tooltip = { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8, boxPadding: 4 };

        const porDia = @json($porDia);
        new Chart(document.getElementById('grafica-por-dia'), {
            type: 'bar',
            data: {
                labels: porDia.map((d) => d.etiqueta),
                datasets: [
                    { label: 'Entradas', data: porDia.map((d) => d.entradas), ...barra(serie1) },
                    { label: 'Salidas', data: porDia.map((d) => d.salidas), ...barra(serie2) },
                ],
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                datasets: { bar: { categoryPercentage: 0.7, barPercentage: 0.92 } },
                plugins: { legend: { display: false }, tooltip },
                scales: { x: ejeCategorias, y: ejeValores },
            },
        });

        const tiempo = @json($tiempoEnPatio);
        new Chart(document.getElementById('grafica-tiempo'), {
            type: 'bar',
            data: { labels: tiempo.map((t) => t.rango), datasets: [{ label: 'Cajas', data: tiempo.map((t) => t.cajas), ...barra(serie1) }] },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip }, scales: { x: ejeCategorias, y: ejeValores } },
        });

        const horizontal = (id, etiquetas, valores, nombre) => {
            const lienzo = document.getElementById(id);
            if (!lienzo) return;
            new Chart(lienzo, {
                type: 'bar',
                data: { labels: etiquetas, datasets: [{ label: nombre, data: valores, ...barra(serie1), maxBarThickness: 18 }] },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip },
                    scales: { x: { ...ejeValores, position: 'top' }, y: ejeCategorias },
                },
            });
        };

        const duenos = @json($porDueno);
        horizontal('grafica-duenos', duenos.map((d) => d.nombre), duenos.map((d) => d.cajas), 'Cajas');

        const hallazgos = @json($hallazgos);
        horizontal('grafica-hallazgos', hallazgos.map((h) => h.punto), hallazgos.map((h) => h.veces), 'Veces');
    });
</script>
@endpush
