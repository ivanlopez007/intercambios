@extends('admin.layout.layout')

@section('title', $intercambio->folio)
@section('page-title', 'Intercambio '.$intercambio->folio)

@use('App\Models\PuntoInspeccion')
@use('App\Models\RevisionPunto')

@php
$i = $intercambio;
$bloque = 'bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-sm';
$titulo = 'text-sm font-black text-slate-900 uppercase tracking-wide mb-3';
$vencimiento = function ($fecha) use ($diasAviso) {
    if (! $fecha) return ['Sin dato', 'text-slate-500'];
    if ($fecha->isPast() && ! $fecha->isToday()) return ['Vencido', 'text-rose-700'];
    if ($fecha->lte(now()->addDays($diasAviso))) return ['Por vencer', 'text-amber-700'];
    return ['Vigente', 'text-emerald-700'];
};
$coloresLlanta = ['rojo' => 'bg-rose-500 text-white border-rose-700', 'amarillo' => 'bg-amber-400 text-slate-900 border-amber-600', 'verde' => 'bg-emerald-500 text-white border-emerald-700'];
$llantas = $i->llantas->keyBy('posicion');
$ejes = [
    'Tracto' => [[1, null, null, 2], [3, 4, 5, 6], [7, 8, 9, 10]],
    'Caja' => [[11, 12, 13, 14], [15, 16, 17, 18]],
];
$miniatura = fn ($foto) => route('admin.intercambios.foto', [$i, $foto]);
@endphp

@section('contenido')
<div class="max-w-4xl mx-auto space-y-4 pb-6" x-data="{ foto: null }" @keydown.escape.window="foto = null">

    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('admin.intercambios.historial') }}" class="text-sm font-bold text-slate-600"><i class="fa-solid fa-chevron-left"></i> Historial</a>
        <div class="flex flex-wrap gap-1 text-xs font-bold">
            <span class="px-2.5 py-1 rounded-full {{ $i->tipo_movimiento === 'entrada' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ ucfirst($i->tipo_movimiento) }}</span>
            <span class="px-2.5 py-1 rounded-full {{ $i->status === 'completado' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">{{ ucfirst($i->status) }}</span>
        </div>
    </div>

    @if ($i->status === 'completado')
    <div class="grid grid-cols-2 gap-2">
        <a href="{{ route('admin.intercambios.pdf', [$i, 'contrato']) }}" target="_blank"
            class="flex items-center justify-center gap-2 rounded-xl bg-white border border-slate-300 px-3 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50">
            <i class="fa-solid fa-file-pdf text-rose-600"></i> Contrato de intercambio
        </a>
        <a href="{{ route('admin.intercambios.pdf', [$i, 'ctpat']) }}" target="_blank"
            class="flex items-center justify-center gap-2 rounded-xl bg-white border border-slate-300 px-3 py-3 text-sm font-bold text-slate-800 hover:bg-slate-50">
            <i class="fa-solid fa-file-pdf text-rose-600"></i> Inspección C-TPAT
        </a>
    </div>
    @endif

    <div class="{{ $bloque }} grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
        <div><p class="text-xs text-slate-500">Folio</p><p class="font-black text-indigo-700">{{ $i->folio }}</p></div>
        <div><p class="text-xs text-slate-500">Fecha y hora</p><p class="font-bold">{{ $i->fecha_hora->format('d/m/Y H:i') }}</p></div>
        <div><p class="text-xs text-slate-500">Guardia</p><p class="font-bold">{{ $i->guardia_nombre }}</p></div>
        <div>
            <p class="text-xs text-slate-500">Intercambio relacionado</p>
            @if ($i->relacionado)
            <a href="{{ route('admin.intercambios.show', $i->relacionado) }}" class="font-bold text-blue-700">{{ $i->relacionado->folio }}</a>
            @else
            <p class="font-bold text-slate-400">—</p>
            @endif
        </div>
    </div>

    @if ($i->fecha_hora->lt(now()->subYears(config('intercambios.anios_conservacion_fotos'))))
    <div class="rounded-2xl bg-slate-200 border border-slate-300 text-slate-700 text-sm font-semibold p-3">
        <i class="fa-solid fa-box-archive"></i> Las fotos de este intercambio se eliminaron al cumplir {{ config('intercambios.anios_conservacion_fotos') }} años. Los datos, la revisión y las firmas se conservan.
    </div>
    @endif

    {{-- Operador --}}
    <div class="{{ $bloque }}">
        <h3 class="{{ $titulo }}">Operador</h3>
        <div class="grid sm:grid-cols-4 gap-3 text-sm">
            <div class="sm:col-span-2"><p class="font-black text-slate-900">{{ $i->operador_nombre }}</p><p class="text-slate-500">No. empleado {{ $i->operador_numero_empleado }}</p></div>
            <div><p class="text-xs text-slate-500">Licencia</p><p class="font-bold">{{ $i->operador_licencia ?? 'Sin licencia registrada' }}</p></div>
            <div class="space-y-1">
                @foreach (['Licencia vence' => $i->operador_licencia_vence, 'Apto médico vence' => $i->operador_apto_medico_vence] as $etiqueta => $fecha)
                @php([$estado, $color] = $vencimiento($fecha))
                <p class="text-xs"><span class="text-slate-500">{{ $etiqueta }}:</span> <span class="font-bold">{{ $fecha?->format('d/m/Y') ?? '—' }}</span> <span class="font-bold {{ $color }}">{{ $estado }}</span></p>
                @endforeach
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Datos al momento del intercambio.</p>
    </div>

    {{-- Unidades y viaje --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="{{ $bloque }} text-sm space-y-1">
            <h3 class="{{ $titulo }}"><i class="fa-solid fa-trailer"></i> Caja</h3>
            <p class="text-lg font-black">{{ $i->caja_numero_economico }}</p>
            <p>Dueño: <span class="font-bold">{{ $i->caja_cliente }}</span></p>
            <p>Placa: <span class="font-bold">{{ $i->caja_placa ?? '—' }}</span></p>
            @if ($i->caja)
            <p class="text-slate-500">{{ collect([$i->caja->tipoCaja?->nombre, $i->caja->marca, $i->caja->modelo, $i->caja->anio, $i->caja->tamano_pies ? $i->caja->tamano_pies.' pies' : null])->filter()->join(' · ') }}</p>
            @endif
        </div>
        <div class="{{ $bloque }} text-sm space-y-1">
            <h3 class="{{ $titulo }}"><i class="fa-solid fa-truck"></i> Tractocamión</h3>
            <p class="text-lg font-black">{{ $i->tracto_numero_economico }}</p>
            <p>Placa: <span class="font-bold">{{ $i->tracto_placa ?? '—' }}</span></p>
            <p>{{ $i->tracto_linea ? 'Permisionario · '.$i->tracto_linea : 'Propio' }}</p>
            @if ($i->odometro !== null)<p>Odómetro: <span class="font-bold">{{ number_format($i->odometro) }}</span></p>@endif
        </div>
    </div>

    <div class="{{ $bloque }} grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
        <div><p class="text-xs text-slate-500">Carga</p><p class="font-bold">{{ $i->estado_carga === 'cargada' ? 'Cargada' : 'Vacía' }}</p></div>
        <div><p class="text-xs text-slate-500">Tipo</p><p class="font-bold uppercase">{{ $i->tipo_intercambio }}</p></div>
        <div><p class="text-xs text-slate-500">Cliente del viaje</p><p class="font-bold">{{ $i->cliente?->nombre ?? '—' }}</p></div>
        <div><p class="text-xs text-slate-500">Carta porte</p><p class="font-bold">{{ $i->referencia_carta_porte ?? '—' }}</p></div>
        <div class="col-span-2"><p class="text-xs text-slate-500">Origen → destino</p><p class="font-bold">{{ $i->origen?->nombre ?? '—' }} → {{ $i->destino?->nombre ?? '—' }}</p></div>
        @if ($i->estado_carga === 'cargada')
        <div class="col-span-2"><p class="text-xs text-slate-500">Sello</p><p class="font-black">{{ $i->numero_sello }}</p></div>
        @endif
    </div>

    @if ($fotos->has('sello'))
    <div class="{{ $bloque }}">
        <h3 class="{{ $titulo }}">Fotos del sello</h3>
        <div class="flex flex-wrap gap-2">
            @foreach ($fotos['sello'] as $f)
            <button type="button" @click="foto = '{{ $miniatura($f) }}'" class="w-24 h-24 rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
                <img src="{{ $miniatura($f) }}" alt="Sello" loading="lazy" class="w-full h-full object-cover">
            </button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Llantas --}}
    <div class="{{ $bloque }}">
        <h3 class="{{ $titulo }}">Llantas</h3>
        <div class="grid md:grid-cols-[16rem_1fr] gap-5">
            <div class="mx-auto w-full max-w-[16rem] space-y-1">
                @foreach ($ejes as $unidad => $filas)
                <p class="text-center text-[11px] font-black uppercase tracking-wider text-slate-400 mt-2">{{ $unidad }}</p>
                @foreach ($filas as $fila)
                <div class="grid grid-cols-[1fr_1fr_2rem_1fr_1fr] items-center gap-1">
                    @foreach ($fila as $j => $p)
                    @if ($j === 2)<div class="h-1 bg-slate-300 rounded-full"></div>@endif
                    @if ($p)
                    @php($llanta = $llantas->get($p))
                    <div class="h-14 rounded-lg border-2 flex flex-col items-center justify-center text-xs font-black leading-tight {{ $coloresLlanta[$llanta?->color] ?? 'bg-white border-slate-300' }}">
                        <span>{{ $p }}</span><span class="text-[10px] font-semibold">{{ $llanta?->profundidad_32 }}/32</span>
                    </div>
                    @else
                    <div></div>
                    @endif
                    @endforeach
                </div>
                @endforeach
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="text-slate-500 uppercase"><tr><th class="py-1 pr-2">#</th><th class="py-1 pr-2">Marca</th><th class="py-1 pr-2">Prof.</th><th class="py-1 pr-2">PSI</th><th class="py-1 pr-2">Tipo</th><th class="py-1">Obs.</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($i->llantas as $l)
                        <tr>
                            <td class="py-1.5 pr-2 font-bold">{{ $l->posicion }}</td>
                            <td class="py-1.5 pr-2">{{ $l->marca ?? '—' }}</td>
                            <td class="py-1.5 pr-2 font-bold {{ ['rojo' => 'text-rose-700', 'amarillo' => 'text-amber-700'][$l->color] ?? '' }}">{{ $l->profundidad_32 }}/32</td>
                            <td class="py-1.5 pr-2">{{ $l->psi }}</td>
                            <td class="py-1.5 pr-2">{{ $l->tipo ? ucfirst($l->tipo) : '—' }}</td>
                            <td class="py-1.5">{{ $l->observaciones }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if ($fotos->has('llanta'))
        <div class="flex flex-wrap gap-2 mt-3">
            @foreach ($fotos['llanta'] as $f)
            <button type="button" @click="foto = '{{ $miniatura($f) }}'" class="relative w-20 h-20 rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
                <img src="{{ $miniatura($f) }}" alt="Llanta" loading="lazy" class="w-full h-full object-cover">
                <span class="absolute bottom-0 inset-x-0 bg-slate-900/70 text-white text-[10px] font-bold">Llanta {{ $i->llantas->firstWhere('id', $f->llanta_intercambio_id)?->posicion }}</span>
            </button>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Revisión --}}
    <div class="{{ $bloque }} space-y-4">
        <h3 class="{{ $titulo }}">Revisión C-TPAT</h3>
        @foreach (PuntoInspeccion::SECCIONES as $seccion => $nombreSeccion)
        @if ($revisionPorSeccion->has($seccion))
        <div>
            <p class="text-xs font-black text-slate-500 uppercase mb-2">{{ $nombreSeccion }}</p>
            <div class="grid sm:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                @foreach ($revisionPorSeccion[$seccion] as $r)
                <div class="{{ $r->tieneHallazgo() ? 'col-span-full rounded-xl bg-rose-50 border border-rose-200 p-3' : 'py-1.5 border-b border-slate-100' }}">
                    <div class="flex justify-between gap-2">
                        <span class="{{ $r->punto?->parent_id ? 'pl-4 text-slate-600' : 'font-semibold' }}">{{ $r->punto?->nombre }}</span>
                        <span class="font-bold {{ $r->esNegativo() ? 'text-rose-700' : ($r->resultado === 'no_aplica' ? 'text-slate-400' : 'text-emerald-700') }}">{{ RevisionPunto::ETIQUETAS[$r->resultado] ?? $r->resultado }}</span>
                    </div>
                    @if ($r->tieneHallazgo())
                    <p class="text-slate-700 mt-1">{{ $r->observaciones }}</p>
                    @php($fotosPunto = $i->fotos->where('revision_punto_id', $r->id))
                    @if ($fotosPunto->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach ($fotosPunto as $f)
                        <button type="button" @click="foto = '{{ $miniatura($f) }}'" class="w-16 h-16 rounded-lg overflow-hidden border border-slate-200"><img src="{{ $miniatura($f) }}" alt="Evidencia" loading="lazy" class="w-full h-full object-cover"></button>
                        @endforeach
                    </div>
                    @endif
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
        @endforeach
    </div>

    {{-- Comentarios --}}
    <div class="{{ $bloque }} text-sm space-y-2">
        <h3 class="{{ $titulo }}">Comentarios y daños</h3>
        @forelse (array_filter(['Caja' => $i->comentarios_caja, 'Tracto' => $i->comentarios_tracto, 'Observaciones' => $i->observaciones]) as $etiqueta => $texto)
        <p><span class="font-bold">{{ $etiqueta }}:</span> {{ $texto }}</p>
        @empty
        <p class="text-slate-500">Sin comentarios.</p>
        @endforelse
        @if ($fotos->has('problema'))
        <div class="flex flex-wrap gap-2 pt-1">
            @foreach ($fotos['problema'] as $f)
            <button type="button" @click="foto = '{{ $miniatura($f) }}'" class="relative w-24 h-24 rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
                <img src="{{ $miniatura($f) }}" alt="Problema" loading="lazy" class="w-full h-full object-cover">
                <span class="absolute bottom-0 inset-x-0 bg-slate-900/70 text-white text-[10px] font-bold">{{ ucfirst($f->unidad) }}</span>
            </button>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Fotos de la unidad (recorrido), en el orden de config('intercambios.recorrido') --}}
    @if ($recorrido->isNotEmpty())
    <div class="{{ $bloque }}">
        <h3 class="{{ $titulo }}">Fotos de la unidad <span class="text-slate-400 normal-case font-semibold">({{ $recorrido->count() }})</span></h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach ($recorrido as $f)
            <button type="button" @click="foto = '{{ $miniatura($f) }}'" class="text-left rounded-xl overflow-hidden border border-slate-200 bg-white">
                <img src="{{ $miniatura($f) }}" alt="{{ $f->etiqueta_vista }}" loading="lazy" class="w-full aspect-[4/3] object-cover bg-slate-100">
                <span class="block px-2 py-1.5 text-xs font-bold text-slate-700">{{ $f->etiqueta_vista }}</span>
            </button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Firmas --}}
    <div class="grid grid-cols-2 gap-4">
        @foreach (['operador' => 'Firma del operador', 'guardia' => 'Firma del guardia'] as $quien => $etiqueta)
        <div class="{{ $bloque }}">
            <p class="text-xs font-bold text-slate-500 mb-2">{{ $etiqueta }}</p>
            @if ($i->{"firma_{$quien}_path"})
            <img src="{{ route('admin.intercambios.firma', [$i, $quien]) }}" alt="{{ $etiqueta }}" class="w-full h-28 object-contain bg-white rounded-lg border border-slate-100">
            @else
            <p class="text-slate-400 text-sm">Sin firma</p>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Visor de fotos --}}
    <div x-show="foto" x-cloak class="fixed inset-0 z-[70] bg-slate-950/90 flex items-center justify-center p-3" @click="foto = null" role="dialog" aria-modal="true">
        <img :src="foto" alt="Foto ampliada" class="max-w-full max-h-full rounded-xl">
        <button type="button" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/20 text-white" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>
    </div>
</div>
@endsection
