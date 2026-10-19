@extends('admin.layout.layout')

@section('title', 'Patio')
@section('page-title', 'Cajas en patio')

@php
$filtros = [
    'todos' => ['Todas', $resumen['total']],
    'cargadas' => ['Cargadas', $resumen['cargadas']],
    'vacias' => ['Vacías', $resumen['vacias']],
    'alertas' => ['Con alerta', $resumen['con_alerta']],
];
$kpi = 'bg-white p-4 rounded-2xl shadow-sm border border-gray-200';
$seccion = 'rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-1';
$seccionAlerta = 'rounded-xl bg-rose-50 border border-rose-200 p-3 space-y-1';
$tituloSeccion = 'text-[11px] font-black uppercase tracking-wider text-slate-500 mb-1';
$plural = fn (int $n, string $uno, string $varios) => $n.' '.($n === 1 ? $uno : $varios);
$secciones = \App\Models\PuntoInspeccion::SECCIONES;
// Mantiene los filtros actuales al cambiar uno.
$enlace = fn (array $cambios) => route('admin.intercambios.patio', array_filter(array_merge(request()->only(['filtro', 'dueno', 'buscar']), $cambios), fn ($v) => $v !== null && $v !== ''));

// Insignias de alerta de una unidad.
$alertas = fn ($u) => array_filter([
    $u->excede_tiempo ? ['fa-clock', 'Más de '.$horasAlerta.' h'] : null,
    $u->hallazgos->isNotEmpty() ? ['fa-shield-halved', $plural($u->hallazgos->count(), 'hallazgo C-TPAT', 'hallazgos C-TPAT')] : null,
    $u->danos ? ['fa-triangle-exclamation', 'Daños'] : null,
    $u->llantas_rojas ? ['fa-circle-exclamation', $plural($u->llantas_rojas, 'llanta bajo mínimo', 'llantas bajo mínimo')] : null,
]);
@endphp

@section('contenido')
<div class="space-y-5">

    {{-- Resumen --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="{{ $kpi }}">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">En patio</p>
            <p class="text-3xl font-extrabold text-slate-900 mt-1">{{ $resumen['total'] }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $plural($resumen['cargadas'], 'cargada', 'cargadas') }} · {{ $plural($resumen['vacias'], 'vacía', 'vacías') }}</p>
        </div>
        <div class="{{ $kpi }}">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tiempo promedio</p>
            <p class="text-2xl font-extrabold text-slate-900 mt-1">{{ $resumen['promedio'] ?? '—' }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $resumen['excede_tiempo'] }} con más de {{ $horasAlerta }} h</p>
        </div>
        <div class="{{ $kpi }}">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Con alerta</p>
            <p class="text-3xl font-extrabold {{ $resumen['con_alerta'] ? 'text-rose-600' : 'text-emerald-600' }} mt-1">{{ $resumen['con_alerta'] }}</p>
            <p class="text-xs text-gray-500 mt-1 leading-snug">
                {{ $resumen['con_hallazgos'] }} hallazgos · {{ $resumen['con_danos'] }} daños · {{ $resumen['llantas_rojas'] }} llantas
            </p>
        </div>
        <div class="{{ $kpi }}">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Movimientos hoy</p>
            <p class="text-lg font-extrabold text-slate-900 mt-1">
                <span class="text-emerald-600"><i class="fa-solid fa-right-to-bracket"></i> {{ $resumen['entradas_hoy'] }}</span>
                <span class="text-rose-600 ml-2"><i class="fa-solid fa-right-from-bracket"></i> {{ $resumen['salidas_hoy'] }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-1">entradas · salidas</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white p-3 sm:p-4 rounded-2xl shadow-sm border border-gray-200 space-y-3">
        <div class="flex gap-2 overflow-x-auto -mx-1 px-1 pb-1">
            @foreach ($filtros as $clave => [$etiqueta, $cantidad])
            <a href="{{ $enlace(['filtro' => $clave === 'todos' ? null : $clave]) }}"
                class="shrink-0 px-3 py-2 rounded-xl text-sm font-bold border transition {{ $filtro === $clave ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                {{ $etiqueta }} <span class="ml-1 px-1.5 rounded-full text-xs {{ $filtro === $clave ? 'bg-white/20' : 'bg-gray-100' }}">{{ $cantidad }}</span>
            </a>
            @endforeach
        </div>

        @if ($porDueno->count() > 1)
        <div>
            <p class="text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1.5">Por dueño</p>
            <div class="flex gap-2 overflow-x-auto -mx-1 px-1 pb-1">
                @foreach ($porDueno as $nombre => $cantidad)
                <a href="{{ $enlace(['dueno' => $dueno === $nombre ? null : $nombre]) }}"
                    class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold border transition {{ $dueno === $nombre ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                    {{ $nombre }} <span class="ml-0.5 opacity-70">{{ $cantidad }}</span>
                    @if ($dueno === $nombre)<i class="fa-solid fa-xmark ml-0.5"></i>@endif
                </a>
                @endforeach
            </div>
        </div>
        @endif

        <form method="GET" action="{{ route('admin.intercambios.patio') }}" class="flex gap-2">
            @if ($filtro !== 'todos')<input type="hidden" name="filtro" value="{{ $filtro }}">@endif
            @if ($dueno !== '')<input type="hidden" name="dueno" value="{{ $dueno }}">@endif
            <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Caja, placa, sello, cliente, tracto u operador"
                class="flex-1 min-w-0 py-2.5 px-3 text-base sm:text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-bold" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    @if ($unidades->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-sm text-gray-500">
        <i class="fa-solid fa-square-parking text-3xl text-gray-300 mb-2"></i>
        <p>{{ $resumen['total'] ? 'Ninguna caja coincide con los filtros.' : 'No hay cajas en el patio.' }}</p>
    </div>
    @else

    <p class="text-xs text-gray-500">{{ $plural($unidades->count(), 'caja', 'cajas') }} · de la que lleva más tiempo a la más reciente</p>

    <div class="grid lg:grid-cols-2 gap-3 items-start">
        @foreach ($unidades as $u)
        @php($i = $u->intercambio)
        <article x-data="{ abierto: false }" class="bg-white rounded-2xl border shadow-sm overflow-hidden {{ $u->con_alerta ? 'border-rose-200' : 'border-gray-200' }}">
            <div class="p-4 space-y-2">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-lg font-black text-slate-900 leading-tight">{{ $i->caja_numero_economico }} <span class="text-sm font-semibold text-gray-400">{{ $i->caja_placa }}</span></p>
                        <p class="text-sm text-gray-600 truncate">{{ $i->caja_cliente }}@if ($i->caja?->tipoCaja) · {{ $i->caja->tipoCaja->nombre }}@endif</p>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $i->estado_carga === 'cargada' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600' }}">{{ $i->estado_carga === 'cargada' ? 'Cargada' : 'Vacía' }}</span>
                        <span class="text-sm font-black {{ $u->excede_tiempo ? 'text-rose-600' : 'text-slate-800' }}"><i class="fa-regular fa-clock"></i> {{ $u->tiempo }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm text-slate-600">
                    <p class="truncate"><i class="fa-solid fa-right-to-bracket text-emerald-600 w-4"></i> {{ $i->fecha_hora->format('d/m/Y H:i') }}</p>
                    <p class="truncate text-indigo-600 font-semibold">{{ $i->folio }}</p>
                    <p class="truncate"><i class="fa-solid fa-truck text-slate-400 w-4"></i> {{ $i->tracto_numero_economico }}@if ($i->tracto_linea) · {{ $i->tracto_linea }}@endif</p>
                    <p class="truncate"><i class="fa-solid fa-id-card text-slate-400 w-4"></i> {{ $i->operador_nombre }}</p>
                    @if ($i->numero_sello)
                    <p class="truncate"><i class="fa-solid fa-lock text-slate-400 w-4"></i> Sello {{ $i->numero_sello }}</p>
                    @endif
                    <p class="truncate"><i class="fa-solid fa-building text-slate-400 w-4"></i> {{ $i->cliente?->nombre ?? $i->caja_cliente }}</p>
                </div>

                @if ($alertas($u))
                <div class="flex flex-wrap gap-1">
                    @foreach ($alertas($u) as [$icono, $texto])
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800"><i class="fa-solid {{ $icono }}"></i> {{ $texto }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Toda la información --}}
            <div x-show="abierto" x-cloak class="px-4 pb-4 grid sm:grid-cols-2 gap-2 text-sm">
                <div class="{{ $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-trailer"></i> Caja</p>
                    <p>{{ collect([$i->caja?->tipoCaja?->nombre, $i->caja?->marca, $i->caja?->modelo, $i->caja?->anio])->filter()->join(' · ') ?: '—' }}</p>
                    @if ($i->caja?->tamano_pies)<p>{{ $i->caja->tamano_pies }} pies</p>@endif
                    <p>Dueño: <span class="font-semibold">{{ $i->caja_cliente }}</span></p>
                </div>

                <div class="{{ $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-route"></i> Viaje</p>
                    <p><span class="uppercase font-semibold">{{ $i->tipo_intercambio }}</span> · {{ $i->estado_carga === 'cargada' ? 'Cargada' : 'Vacía' }}</p>
                    <p>Cliente: <span class="font-semibold">{{ $i->cliente?->nombre ?? '—' }}</span></p>
                    <p>{{ $i->origen?->nombre ?? '—' }} → {{ $i->destino?->nombre ?? '—' }}</p>
                    @if ($i->referencia_carta_porte)<p>Carta porte: {{ $i->referencia_carta_porte }}</p>@endif
                    @if ($i->numero_sello)<p>Sello: <span class="font-semibold">{{ $i->numero_sello }}</span></p>@endif
                </div>

                <div class="{{ $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-truck"></i> Tracto y operador</p>
                    <p><span class="font-semibold">{{ $i->tracto_numero_economico }}</span> · {{ $i->tracto_placa }}</p>
                    <p>{{ $i->tracto_linea ? 'Permisionario · '.$i->tracto_linea : 'Propio' }}@if ($i->odometro !== null) · {{ number_format($i->odometro) }} km @endif</p>
                    <p><span class="font-semibold">{{ $i->operador_nombre }}</span> ({{ $i->operador_numero_empleado }})</p>
                    <p class="{{ $u->operador_vencido ? 'text-rose-700 font-semibold' : '' }}">
                        Licencia: {{ $i->operador_licencia ?? 'sin registrar' }}@if ($u->operador_vencido) · documentos vencidos al entrar @endif
                    </p>
                </div>

                <div class="{{ $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-user-shield"></i> Entrada</p>
                    <p>{{ $i->fecha_hora->format('d/m/Y H:i') }} · {{ $u->tiempo }} en patio</p>
                    <p>Recibió: <span class="font-semibold">{{ $i->guardia_nombre }}</span></p>
                    @if ($i->relacionado)
                    <p>Salida anterior: <a href="{{ route('admin.intercambios.show', $i->relacionado) }}" class="font-semibold text-indigo-600">{{ $i->relacionado->folio }}</a> ({{ $i->relacionado->fecha_hora->format('d/m/Y') }})</p>
                    @else
                    <p class="text-slate-500">Sin salida anterior registrada</p>
                    @endif
                </div>

                <div class="{{ $u->hallazgos->isNotEmpty() ? $seccionAlerta : $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-shield-halved"></i> Revisión C-TPAT</p>
                    @forelse ($u->hallazgos as $h)
                    <p><span class="font-semibold text-rose-800">{{ $h->punto?->nombre }}</span>
                        @if ($h->punto?->seccion !== 'ctpat')<span class="text-xs text-slate-500">({{ $secciones[$h->punto?->seccion] ?? '' }})</span>@endif: {{ $h->observaciones }}</p>
                    @empty
                    <p class="text-emerald-700 font-semibold">Sin hallazgos</p>
                    @endforelse
                </div>

                <div class="{{ $u->danos ? $seccionAlerta : $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-triangle-exclamation"></i> Daños y observaciones</p>
                    @if ($i->comentarios_caja)<p><span class="font-semibold">Caja:</span> {{ $i->comentarios_caja }}</p>@endif
                    @if ($i->comentarios_tracto)<p><span class="font-semibold">Tracto:</span> {{ $i->comentarios_tracto }}</p>@endif
                    @if ($i->observaciones)<p><span class="font-semibold">Observaciones:</span> {{ $i->observaciones }}</p>@endif
                    @if (! $i->comentarios_caja && ! $i->comentarios_tracto && ! $i->observaciones)
                    <p class="text-emerald-700 font-semibold">Sin daños</p>
                    @endif
                </div>

                <div class="{{ $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-circle-dot"></i> Llantas</p>
                    @forelse ($u->llantas_alerta as $l)
                    <p class="{{ $l->color === 'rojo' ? 'text-rose-700 font-semibold' : 'text-amber-700' }}">
                        Llanta {{ $l->posicion }} ({{ $l->posicion <= 10 ? 'tracto' : 'caja' }}): {{ $l->profundidad_32 }}/32 · {{ $l->psi }} PSI · {{ $l->color === 'rojo' ? 'bajo el mínimo' : 'alerta' }}
                    </p>
                    @empty
                    <p class="text-emerald-700 font-semibold">Todas en buen estado</p>
                    @endforelse
                </div>

                <div class="{{ $seccion }}">
                    <p class="{{ $tituloSeccion }}"><i class="fa-solid fa-camera"></i> Fotos</p>
                    <p>Unidad: {{ $u->fotos['recorrido'] ?? 0 }} de {{ count(config('intercambios.recorrido')) }}</p>
                    @if ($u->fotos['sello'] ?? 0)<p>Sello: {{ $u->fotos['sello'] }}</p>@endif
                    @if ($u->fotos['problema'] ?? 0)<p>Daños: {{ $u->fotos['problema'] }}</p>@endif
                    @if (($u->fotos['punto'] ?? 0) + ($u->fotos['llanta'] ?? 0))<p>Evidencia: {{ ($u->fotos['punto'] ?? 0) + ($u->fotos['llanta'] ?? 0) }}</p>@endif
                </div>
            </div>

            <div class="flex border-t border-gray-100 text-sm font-bold">
                <button type="button" @click="abierto = !abierto" :aria-expanded="abierto" class="flex-1 py-3 text-slate-700 hover:bg-gray-50">
                    <span x-text="abierto ? 'Ocultar información' : 'Ver toda la información'"></span>
                    <i class="fa-solid fa-chevron-down text-xs transition" :class="abierto && 'rotate-180'"></i>
                </button>
                <a href="{{ route('admin.intercambios.show', $i) }}" class="flex-1 py-3 text-center text-indigo-600 border-l border-gray-100 hover:bg-indigo-50">
                    Detalle y fotos <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            </div>
        </article>
        @endforeach
    </div>
    @endif

    <p class="text-xs text-gray-400 text-center">Una caja aparece aquí si su último intercambio registrado fue una entrada. Al registrar su salida, deja de aparecer.</p>
</div>
@endsection
