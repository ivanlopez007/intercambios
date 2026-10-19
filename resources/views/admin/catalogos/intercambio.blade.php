@extends('admin.layout.layout')

@section('title', $config['titulo'])
@section('page-title', 'Catálogo: '.$config['titulo'])

@php
$campos = $config['campos'];
$editables = collect($campos)->filter(fn ($c) => $c['editable']);
$columnas = collect($campos)->filter(fn ($c) => $c['tabla']);
$base = route('admin.catalogos.store', $config['clave']);
$input = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base sm:text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none';

// Valor que se muestra en la tabla para un campo.
$mostrar = function ($registro, $nombre, $campo) use ($opciones) {
    $valor = $registro->{$nombre};
    return match ($campo['tipo']) {
        'select' => $valor === null ? '—' : ($opciones[$nombre][$valor] ?? $valor),
        'checkbox' => $valor ? 'Sí' : 'No',
        default => $valor ?? '—',
    };
};
$vacios = $editables->map(fn ($c) => $c['tipo'] === 'checkbox' ? false : '')->all();
// Tailwind solo detecta clases escritas completas.
$gridColumnas = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-4'][min($columnas->count(), 4)];
@endphp

@section('contenido')
<div class="max-w-5xl mx-auto space-y-4"
    x-data="{
        abierto: @js($errors->any()),
        editando: @js(old('_id')),
        datos: @js(array_merge($vacios, collect(old())->only($editables->keys())->all())),
        nuevo() { this.editando = null; this.datos = @js($vacios); this.abierto = true; },
        editar(registro) { this.editando = registro.id; this.datos = { ...@js($vacios), ...registro }; this.abierto = true; },
    }"
    @keydown.escape.window="abierto = false">

    {{-- Otros catálogos --}}
    <nav class="flex gap-2 overflow-x-auto pb-1 -mx-2 px-2" aria-label="Catálogos">
        @foreach ($catalogos as $clave => $c)
        <a href="{{ route('admin.catalogos.index', $clave) }}"
            class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold border transition {{ $clave === $config['clave'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            <i class="{{ $c['icono'] }}"></i> {{ $c['titulo'] }}
        </a>
        @endforeach
    </nav>

    <div class="bg-white border border-slate-200 rounded-3xl p-4 md:p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 shrink-0">
                    <i class="{{ $config['icono'] }} text-lg"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 leading-tight">{{ $config['titulo'] }}</h2>
                    <p class="text-xs text-slate-500">{{ $config['descripcion'] ?? 'Los registros se desactivan, nunca se borran.' }}</p>
                </div>
            </div>
            @if ($config['crear'])
            <button type="button" @click="nuevo()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl text-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-plus"></i> Nuevo {{ $config['singular'] }}
            </button>
            @endif
        </div>

        @if (session('success'))
        <div class="p-3 rounded-2xl text-sm font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">{{ session('success') }}</div>
        @endif

        <form method="GET" class="flex flex-col sm:flex-row gap-2">
            <input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar…" class="{{ $input }} sm:flex-1">
            <select name="estado" class="{{ $input }} sm:w-40">
                <option value="">Todos</option>
                <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
                <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
            </select>
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold">Buscar</button>
        </form>

        @if ($registros->isEmpty())
        <p class="text-center text-sm text-slate-500 py-8">No hay registros.</p>
        @else
        <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 overflow-hidden">
            @foreach ($registros as $registro)
            <div class="p-3 sm:px-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 {{ $registro->activo ? '' : 'bg-slate-50 opacity-70' }}">
                <div class="flex-1 min-w-0 grid grid-cols-2 {{ $gridColumnas }} gap-x-4 gap-y-1 text-sm">
                    @foreach ($columnas as $nombre => $campo)
                    <div class="min-w-0 {{ $loop->first ? 'col-span-2 sm:col-span-1' : '' }}">
                        <span class="block text-[11px] text-slate-400 sm:hidden">{{ $campo['etiqueta'] }}</span>
                        <span class="block truncate {{ $loop->first ? 'font-bold text-slate-900' : 'text-slate-600' }}">{{ $mostrar($registro, $nombre, $campo) }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $registro->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $registro->activo ? 'Activo' : 'Inactivo' }}</span>
                    <button type="button" @click="editar(@js(['id' => $registro->id] + $registro->only($editables->keys()->all())))"
                        class="px-3 py-2 rounded-lg border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50">Editar</button>
                    <form method="POST" action="{{ route('admin.catalogos.estado', [$config['clave'], $registro->id]) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-3 py-2 rounded-lg text-xs font-bold {{ $registro->activo ? 'text-rose-700 hover:bg-rose-50' : 'text-emerald-700 hover:bg-emerald-50' }}">
                            {{ $registro->activo ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        {{ $registros->links() }}
        @endif
    </div>

    {{-- Formulario de alta y edición --}}
    <div x-show="abierto" x-cloak class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="catalogo-form-titulo">
        <div class="absolute inset-0 bg-slate-900/60" @click="abierto = false"></div>
        <form method="POST" :action="editando ? '{{ $base }}/' + editando : '{{ $base }}'"
            class="relative w-full sm:max-w-lg bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl p-5 space-y-3 max-h-[92dvh] overflow-y-auto pb-[calc(1.25rem+env(safe-area-inset-bottom))]">
            @csrf
            <input type="hidden" name="_method" value="PUT" :disabled="!editando">
            <input type="hidden" name="_id" :value="editando">

            <h3 id="catalogo-form-titulo" class="text-lg font-black text-slate-900" x-text="editando ? 'Editar {{ $config['singular'] }}' : 'Nuevo {{ $config['singular'] }}'"></h3>

            @if ($errors->any())
            <div class="p-3 rounded-2xl text-sm font-semibold bg-rose-100 text-rose-800 border border-rose-200">
                <ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
            @endif

            @foreach ($editables as $nombre => $campo)
            <div @isset($campo['visible_si']) x-show="datos.{{ $campo['visible_si'] }}" @endisset>
                @if ($campo['tipo'] === 'checkbox')
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer">
                    <input type="checkbox" name="{{ $nombre }}" value="1" x-model="datos.{{ $nombre }}" class="w-5 h-5 rounded accent-blue-600">
                    <span class="text-sm font-bold text-slate-800">{{ $campo['etiqueta'] }}</span>
                </label>
                @else
                <label for="campo-{{ $nombre }}" class="block text-xs font-bold text-slate-600 mb-1">
                    {{ $campo['etiqueta'] }}@if (in_array('required', (array) (is_array($campo['reglas']) ? $campo['reglas'] : ['required']), true)) *@endif
                </label>
                @if ($campo['tipo'] === 'select')
                <select id="campo-{{ $nombre }}" name="{{ $nombre }}" x-model="datos.{{ $nombre }}" class="{{ $input }}">
                    <option value="">Selecciona…</option>
                    @foreach ($opciones[$nombre] as $valor => $texto)
                    <option value="{{ $valor }}">{{ $texto }}</option>
                    @endforeach
                </select>
                @else
                <input id="campo-{{ $nombre }}" type="{{ $campo['tipo'] }}" name="{{ $nombre }}" x-model="datos.{{ $nombre }}"
                    @if ($campo['tipo'] === 'number') inputmode="numeric" @endif class="{{ $input }}">
                @endif
                @endif
            </div>
            @endforeach

            <div class="flex gap-2 pt-2">
                <button type="button" @click="abierto = false" class="flex-1 py-3 rounded-2xl border border-slate-300 font-bold text-slate-700">Cancelar</button>
                <button type="submit" class="flex-1 py-3 rounded-2xl bg-blue-600 text-white font-black">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
