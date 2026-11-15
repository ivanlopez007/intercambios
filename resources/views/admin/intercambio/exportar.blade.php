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
{{--
    Botón y ventana para exportar un reporte (historial o patio).
    Recibe: $accion (URL de exportación), $columnas (catálogo de ReporteIntercambios),
    $filtrosActuales (parámetros de filtro en pantalla).
--}}
@php
$filtrosActuales = array_filter($filtrosActuales, fn ($v) => is_string($v) && trim($v) !== '');
$todas = array_keys($columnas);
$basicas = array_keys(array_filter($columnas, fn ($c) => $c[2]));
$atajo = 'px-2.5 py-1 rounded-lg border border-gray-300 text-xs font-bold text-gray-600 hover:bg-gray-50';
@endphp

<div x-data="{ abierto: false, columnas: @js($todas) }" @keydown.escape.window="abierto = false">
    <button type="button" @click="abierto = true"
        class="w-full sm:w-auto px-4 py-3 bg-white border border-gray-300 hover:bg-gray-50 text-slate-800 text-sm font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
        <i class="fa-solid fa-file-export"></i> Exportar reporte
    </button>

    <div x-show="abierto" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="exportar-titulo">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="abierto = false"></div>

        <form method="GET" action="{{ $accion }}" x-transition
            class="relative w-full sm:max-w-2xl max-h-[90dvh] flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl">
            <div class="p-4 sm:p-5 border-b border-gray-200 flex items-center justify-between">
                <h2 id="exportar-titulo" class="text-lg font-black text-slate-900"><i class="fa-solid fa-file-export"></i> Exportar reporte</h2>
                <button type="button" @click="abierto = false" class="p-2 text-gray-400 hover:text-gray-700" aria-label="Cerrar"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>

            <div class="p-4 sm:p-5 space-y-5 overflow-y-auto">
                @foreach ($filtrosActuales as $clave => $valor)
                <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                @endforeach

                {{-- Alcance --}}
                <fieldset>
                    <legend class="text-xs font-bold text-gray-600 mb-2">¿Qué registros?</legend>
                    <div class="grid sm:grid-cols-2 gap-2">
                        <label class="flex items-start gap-2 p-3 rounded-xl border border-gray-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 cursor-pointer">
                            <input type="radio" name="alcance" value="filtros" @checked($filtrosActuales) @disabled(! $filtrosActuales) class="mt-0.5">
                            <span>
                                <span class="block text-sm font-bold text-slate-800">Personalizado</span>
                                <span class="block text-xs text-gray-500">{{ $filtrosActuales ? 'Con los filtros que tienes en pantalla.' : 'Aplica filtros en la pantalla para usarlo.' }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 p-3 rounded-xl border border-gray-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 cursor-pointer">
                            <input type="radio" name="alcance" value="todo" @checked(! $filtrosActuales) class="mt-0.5">
                            <span>
                                <span class="block text-sm font-bold text-slate-800">General</span>
                                <span class="block text-xs text-gray-500">Todos los registros, sin filtros.</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                {{-- Columnas --}}
                <fieldset>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <legend class="text-xs font-bold text-gray-600">Columnas <span class="font-normal text-gray-400" x-text="'(' + columnas.length + ')'"></span></legend>
                        <div class="flex gap-1.5">
                            <button type="button" @click="columnas = @js($todas)" class="{{ $atajo }}">Todas</button>
                            <button type="button" @click="columnas = @js($basicas)" class="{{ $atajo }}">Básicas</button>
                            <button type="button" @click="columnas = []" class="{{ $atajo }}">Ninguna</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-3 gap-y-1.5">
                        @foreach ($columnas as $clave => [$titulo])
                        <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                            <input type="checkbox" name="columnas[]" value="{{ $clave }}" x-model="columnas" class="rounded">
                            <span>{{ $titulo }}</span>
                        </label>
                        @endforeach
                    </div>
                    <p x-show="!columnas.length" class="mt-2 text-xs font-semibold text-amber-700">Elige al menos una columna.</p>
                </fieldset>
            </div>

            <div class="p-4 sm:p-5 border-t border-gray-200 grid grid-cols-2 gap-2 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:pb-5">
                <button type="submit" name="formato" value="pdf" formtarget="_blank" :disabled="!columnas.length"
                    class="px-4 py-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold disabled:bg-slate-300 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-pdf"></i> PDF
                </button>
                <button type="submit" name="formato" value="excel" :disabled="!columnas.length"
                    class="px-4 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold disabled:bg-slate-300 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-excel"></i> Excel
                </button>
            </div>
        </form>
    </div>
</div>
