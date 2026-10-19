@extends('admin.layout.layout')

@section('title', 'Historial de Intercambios')
@section('page-title', 'Historial de Intercambios')

@php
$filtro = 'w-full py-2.5 px-3 text-sm text-gray-800 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition';
$estilosStatus = [
    'completado' => 'bg-emerald-100 text-emerald-800',
    'borrador' => 'bg-slate-200 text-slate-700',
    'cancelado' => 'bg-rose-100 text-rose-800',
];
@endphp

@section('contenido')
<div class="space-y-5">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Intercambios</h2>
            <p class="text-xs text-gray-500">Estado de cajas y tractocamiones al entrar o salir del patio.</p>
        </div>
        <a href="{{ route('admin.intercambios.nuevo') }}"
            class="px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
            <i class="fa-solid fa-plus"></i> Nuevo intercambio
        </a>
    </div>

    <form method="GET" action="{{ route('admin.intercambios.historial') }}" x-data="{ abierto: @js(request()->hasAny(['folio', 'caja', 'cliente', 'movimiento', 'status', 'desde', 'hasta'])) }"
        class="bg-white p-4 sm:p-5 rounded-2xl shadow-sm border border-gray-200">
        <button type="button" @click="abierto = !abierto" class="w-full flex items-center justify-between text-sm font-bold text-slate-700 md:hidden">
            <span><i class="fa-solid fa-filter"></i> Filtros</span>
            <i class="fa-solid fa-chevron-down transition" :class="abierto && 'rotate-180'"></i>
        </button>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4 md:mt-0" :class="abierto ? '' : 'hidden md:grid'">
            <div>
                <label for="f-folio" class="block text-xs font-semibold text-gray-600 mb-1">Folio</label>
                <input id="f-folio" type="text" name="folio" value="{{ request('folio') }}" placeholder="INT-2026-…" class="{{ $filtro }}">
            </div>
            <div>
                <label for="f-caja" class="block text-xs font-semibold text-gray-600 mb-1">No. económico de la caja</label>
                <input id="f-caja" type="text" name="caja" value="{{ request('caja') }}" class="{{ $filtro }}">
            </div>
            <div>
                <label for="f-cliente" class="block text-xs font-semibold text-gray-600 mb-1">Cliente</label>
                <input id="f-cliente" type="text" name="cliente" value="{{ request('cliente') }}" placeholder="Dueño o cliente del viaje" class="{{ $filtro }}">
            </div>
            <div>
                <label for="f-movimiento" class="block text-xs font-semibold text-gray-600 mb-1">Movimiento</label>
                <select id="f-movimiento" name="movimiento" class="{{ $filtro }}">
                    <option value="">Todos</option>
                    <option value="entrada" @selected(request('movimiento') === 'entrada')>Entrada</option>
                    <option value="salida" @selected(request('movimiento') === 'salida')>Salida</option>
                </select>
            </div>
            <div>
                <label for="f-status" class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                <select id="f-status" name="status" class="{{ $filtro }}">
                    <option value="">Todos</option>
                    @foreach (\App\Models\Intercambio::STATUS as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-desde" class="block text-xs font-semibold text-gray-600 mb-1">Desde</label>
                <input id="f-desde" type="date" name="desde" value="{{ request('desde') }}" class="{{ $filtro }}">
            </div>
            <div>
                <label for="f-hasta" class="block text-xs font-semibold text-gray-600 mb-1">Hasta</label>
                <input id="f-hasta" type="date" name="hasta" value="{{ request('hasta') }}" class="{{ $filtro }}">
            </div>
            <div class="flex items-end gap-2">
                <a href="{{ route('admin.intercambios.historial') }}" class="flex-1 text-center px-4 py-2.5 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-50">Limpiar</a>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700">Filtrar</button>
            </div>
        </div>
    </form>

    @if ($intercambios->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-sm text-gray-500">
        <i class="fa-solid fa-truck-ramp-box text-3xl text-gray-300 mb-2"></i>
        <p>No hay intercambios con esos filtros.</p>
    </div>
    @else

    {{-- Celular: tarjetas --}}
    <div class="space-y-3 md:hidden">
        @foreach ($intercambios as $i)
        <a href="{{ route('admin.intercambios.show', $i) }}" class="block bg-white rounded-2xl border border-gray-200 p-4 shadow-sm active:bg-slate-50">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-black text-indigo-700">{{ $i->folio }}</p>
                    <p class="text-xs text-gray-500">{{ $i->fecha_hora->format('d/m/Y H:i') }}</p>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $i->tipo_movimiento === 'entrada' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ ucfirst($i->tipo_movimiento) }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $estilosStatus[$i->status] ?? '' }}">{{ ucfirst($i->status) }}</span>
                </div>
            </div>
            <div class="mt-2 text-sm text-slate-700 space-y-0.5">
                <p><i class="fa-solid fa-trailer w-5 text-slate-400"></i> {{ $i->caja_numero_economico ?? '—' }} <span class="text-gray-400">· {{ $i->caja_cliente }}</span></p>
                <p><i class="fa-solid fa-truck w-5 text-slate-400"></i> {{ $i->tracto_numero_economico ?? '—' }}</p>
                <p><i class="fa-solid fa-id-card w-5 text-slate-400"></i> {{ $i->operador_nombre ?? '—' }}</p>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Pantallas anchas: tabla --}}
    <div class="hidden md:block bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="py-3.5 px-4 font-semibold">Folio</th>
                        <th class="py-3.5 px-4 font-semibold">Fecha</th>
                        <th class="py-3.5 px-4 font-semibold">Movimiento</th>
                        <th class="py-3.5 px-4 font-semibold">Caja</th>
                        <th class="py-3.5 px-4 font-semibold">Tracto</th>
                        <th class="py-3.5 px-4 font-semibold">Operador</th>
                        <th class="py-3.5 px-4 font-semibold">Status</th>
                        <th class="py-3.5 px-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($intercambios as $i)
                    <tr class="hover:bg-gray-50/80">
                        <td class="py-3 px-4 font-bold text-indigo-700 whitespace-nowrap">{{ $i->folio }}</td>
                        <td class="py-3 px-4 whitespace-nowrap">{{ $i->fecha_hora->format('d/m/Y H:i') }}</td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $i->tipo_movimiento === 'entrada' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ ucfirst($i->tipo_movimiento) }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="font-semibold text-gray-800">{{ $i->caja_numero_economico ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $i->caja_cliente }}</div>
                        </td>
                        <td class="py-3 px-4">{{ $i->tracto_numero_economico ?? '—' }}</td>
                        <td class="py-3 px-4">{{ $i->operador_nombre ?? '—' }}</td>
                        <td class="py-3 px-4"><span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $estilosStatus[$i->status] ?? '' }}">{{ ucfirst($i->status) }}</span></td>
                        <td class="py-3 px-4 text-right">
                            <a href="{{ route('admin.intercambios.show', $i) }}" class="px-3 py-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg border border-indigo-200 text-xs font-semibold">Ver</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{ $intercambios->links() }}
    @endif
</div>
@endsection
