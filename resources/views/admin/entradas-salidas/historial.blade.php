@extends('admin.layout.layout')
@section('title', 'Historial de Entradas y Salidas de Visitas')
@section('page-title', 'Historial de Entradas y Salidas de Visitas')

@section('contenido')
<div x-data="{
    modalFirmaOpen: false,
    firmaModalUrl: '',
    firmaModalTitulo: '',
    modalDetalleOpen: false,
    registroSeleccionado: null,
    
    // Función para abrir modal con firma ampliada
    verFirma(url, titulo) {
        this.firmaModalUrl = url;
        this.firmaModalTitulo = titulo;
        this.modalFirmaOpen = true;
    },

    // Función para abrir la ficha de detalle de la visita
    verDetalle(registro) {
        this.registroSeleccionado = registro;
        this.modalDetalleOpen = true;
    },

    // Esc cierra primero el visor de foto/firma y después la ficha.
    cerrarVentana() {
        if (this.modalFirmaOpen) {
            this.modalFirmaOpen = false;
        } else {
            this.modalDetalleOpen = false;
        }
    }
}" @keydown.escape.window="cerrarVentana()" class="space-y-6">

    <!-- ========================================================= -->
    <!-- BARRA SUPERIOR: RESUMEN Y CONTROLES                       -->
    <!-- ========================================================= -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Registro de Visitas</h2>
            <p class="text-xs text-gray-500">Consulta y auditoría de ingresos y salidas de visitantes e invitados.</p>
        </div>
        <div class="flex items-center space-x-2">
            <button class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-50 transition shadow-sm flex items-center space-x-2">
                <i class="fa-solid fa-file-excel text-emerald-600 text-sm"></i>
                <span>Exportar Excel</span>
            </button>
            <button class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 transition shadow-sm flex items-center space-x-2">
                <i class="fa-solid fa-file-pdf text-sm"></i>
                <span>Descargar PDF</span>
            </button>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- FILTROS DE BÚSQUEDA                                       -->
    <!-- ========================================================= -->
    <form method="GET" action="{{ route('admin.entradas-salidas.historial') }}" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

            <!-- Búsqueda General -->
            <div class="lg:col-span-2 relative">
                <label for="filtro-buscar" class="block text-xs font-semibold text-gray-600 mb-1">Buscar por Visitante, Empresa o Persona A Visitar</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                    <input type="text" id="filtro-buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Ej. Paco Torres, Walmart..." class="w-full pl-9 pr-3 py-2 text-xs text-gray-800 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                </div>
            </div>

            <!-- Empresa -->
            <div>
                <label for="filtro-empresa" class="block text-xs font-semibold text-gray-600 mb-1">Empresa</label>
                <input type="text" id="filtro-empresa" name="empresa" value="{{ request('empresa') }}" placeholder="Ej. Walmart..." class="w-full py-2 px-3 text-xs text-gray-800 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            </div>

            <!-- Estatus / Estado -->
            <div>
                <label for="filtro-estatus" class="block text-xs font-semibold text-gray-600 mb-1">Estatus</label>
                <select id="filtro-estatus" name="estatus" class="w-full py-2 px-3 text-xs text-gray-800 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    <option value="">Todos</option>
                    <option value="dentro" @selected(request('estatus') === 'dentro')>Dentro de Instalaciones</option>
                    <option value="salio" @selected(request('estatus') === 'salio')>Salida Registrada</option>
                </select>
            </div>

            <!-- Fecha -->
            <div>
                <label for="filtro-fecha" class="block text-xs font-semibold text-gray-600 mb-1">Fecha</label>
                <input type="date" id="filtro-fecha" name="fecha" value="{{ request('fecha') }}" class="w-full py-2 px-3 text-xs text-gray-800 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            </div>

        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.entradas-salidas.historial') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-50 transition">
                Limpiar
            </a>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-semibold rounded-xl hover:bg-indigo-700 transition flex items-center gap-2">
                <i class="fa-solid fa-filter"></i>
                <span>Filtrar</span>
            </button>
        </div>
    </form>

    <!-- ========================================================= -->
    <!-- TABLA DE HISTORIAL                                        -->
    <!-- ========================================================= -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="py-3.5 px-4 font-semibold">Visitante / Empresa</th>
                        <th class="py-3.5 px-4 font-semibold">Motivo</th>
                        <th class="py-3.5 px-4 font-semibold">Persona A Visitar</th>
                        <th class="py-3.5 px-4 font-semibold">Entrada</th>
                        <th class="py-3.5 px-4 font-semibold">Salida</th>
                        <th class="py-3.5 px-4 font-semibold text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">

                    @forelse ($visitas as $item)
                    <tr class="hover:bg-gray-50/80 transition">
                        <!-- Visitante / Empresa -->
                        <td class="py-3 px-4">
                            <div class="font-bold text-slate-800">{{ $item->nombre_persona }}</div>
                            <div class="text-xs text-gray-500">Empresa: <span class="font-semibold text-gray-700">{{ $item->empresa ?: 'N/A' }}</span></div>
                        </td>
                        <!-- Motivo -->
                        <td class="py-3 px-4">
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-semibold border border-indigo-200 inline-flex items-center">
                                <i class="fa-solid fa-qrcode mr-1.5 text-xs"></i> {{ $item->motivo_visita }}
                            </span>
                        </td>
                        <!-- Persona a Visitar -->
                        <td class="py-3 px-4">
                            <span class="text-xs text-gray-600 font-medium">{{ $item->persona_a_visitar ?: 'N/A' }}</span>
                        </td>
                        <!-- Hora Entrada -->
                        <td class="py-3 px-4 whitespace-nowrap">
                            <div class="text-xs font-bold text-slate-800">
                                {{ $item->fecha_entrada?->format('d/m/Y') ?? 'Sin fecha' }}
                            </div>
                            <div class="text-xs text-emerald-600 font-semibold">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                {{ $item->fecha_entrada?->format('h:i A') ?? 'Sin hora' }}
                            </div>
                            @isset($autores[$item->id]['entrada'])
                            <div class="text-[11px] text-gray-500"><i class="fa-solid fa-user-shield"></i> {{ $autores[$item->id]['entrada'] }}</div>
                            @endisset
                        </td>
                        <!-- Hora Salida -->
                        <td class="py-3 px-4 whitespace-nowrap">
                            @if ($item->fecha_salida)
                            <div class="text-xs font-bold text-slate-800">{{ $item->fecha_salida->format('d/m/Y') }}</div>
                            <div class="text-xs text-blue-600 font-semibold">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                {{ $item->fecha_salida->format('h:i A') }}
                            </div>
                            @isset($autores[$item->id]['salida'])
                            <div class="text-[11px] text-gray-500"><i class="fa-solid fa-user-shield"></i> {{ $autores[$item->id]['salida'] }}</div>
                            @endisset
                            @else
                            <span class="px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-md border border-emerald-200 inline-flex items-center">
                                <i class="fa-solid fa-building-user mr-1 text-xs"></i> Dentro
                            </span>
                            @endif
                        </td>
                        <!-- Acciones -->
                        <td class="py-3 px-4 text-center">
                            <button @click="verDetalle(@js([
                                'id' => $item->id,
                                'nombre' => $item->nombre_persona,
                                'empresa' => $item->empresa ?: 'N/A',
                                'motivo' => $item->motivo_visita,
                                'persona_visitar' => $item->persona_a_visitar ?: 'N/A',
                                'entrada' => $item->fecha_entrada?->format('Y-m-d H:i:s'),
                                'salida' => $item->fecha_salida?->format('Y-m-d H:i:s') ?: 'Pendiente',
                                'firma_url' => $item->firma_url,
                                'entrada_por' => $autores[$item->id]['entrada'] ?? 'Sin registro',
                                'salida_por' => $autores[$item->id]['salida'] ?? ($item->fecha_salida ? 'Sin registro' : '—'),
                                'fotos' => $item->fotoVisitas->map(fn ($foto) => [
                                    'parte' => $foto->requerimientoFotoVisita?->parte ?? 'Foto',
                                    'url' => $foto->urlTemporal(),
                                ])->filter(fn ($foto) => $foto['url'])->values(),
                            ]))" class="px-3 py-1.5 bg-gray-100 hover:bg-indigo-50 text-gray-700 hover:text-indigo-600 text-xs font-semibold rounded-lg border border-gray-200 transition">
                                <i class="fa-solid fa-eye mr-1"></i> Detalle
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 px-4 text-center text-sm text-gray-500">No hay registros de visitas.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginador -->
        @if ($visitas->hasPages())
        <div class="p-4 border-t border-gray-200 bg-gray-50/50 text-xs text-gray-500">
            {{ $visitas->links() }}
        </div>
        @endif
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 1: FICHA DETALLADA DE LA VISITA                     -->
    <!-- Se cierra con ✕, "Cerrar", tocando fuera o con Esc.       -->
    <!-- ========================================================= -->
    <div x-show="modalDetalleOpen" x-transition.opacity x-cloak
        @click.self="modalDetalleOpen = false"
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4"
        role="dialog" aria-modal="true" aria-labelledby="titulo-ficha">
        <div class="bg-white w-full sm:max-w-2xl max-h-[92dvh] sm:max-h-[90dvh] flex flex-col rounded-t-2xl sm:rounded-2xl overflow-hidden shadow-2xl border border-gray-200">

            <!-- Encabezado fijo -->
            <div class="shrink-0 px-5 py-4 bg-slate-900 text-white flex justify-between items-center gap-3">
                <div class="min-w-0">
                    <h3 id="titulo-ficha" class="text-base font-bold flex items-center gap-2">
                        <i class="fa-solid fa-address-card text-indigo-400"></i>
                        <span class="truncate" x-text="registroSeleccionado ? registroSeleccionado.nombre : 'Ficha del Visitante'"></span>
                    </h3>
                    <p class="text-xs text-gray-400">Ficha del visitante</p>
                </div>
                <button type="button" @click="modalDetalleOpen = false"
                    class="shrink-0 w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center" aria-label="Cerrar ficha">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Contenido con scroll -->
            <div class="flex-1 overflow-y-auto overscroll-contain">
                <template x-if="registroSeleccionado">
                    <div class="p-5 space-y-5">

                        <!-- Información General -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-200/80">
                            <div>
                                <span class="text-[11px] text-gray-400 font-semibold uppercase block">Empresa</span>
                                <span class="text-sm font-bold text-indigo-600" x-text="registroSeleccionado.empresa"></span>
                            </div>
                            <div>
                                <span class="text-[11px] text-gray-400 font-semibold uppercase block">Persona a visitar</span>
                                <span class="text-sm font-semibold text-gray-700" x-text="registroSeleccionado.persona_visitar"></span>
                            </div>
                            <div class="sm:col-span-2">
                                <span class="text-[11px] text-gray-400 font-semibold uppercase block">Motivo de visita</span>
                                <span class="text-sm font-semibold text-gray-700" x-text="registroSeleccionado.motivo"></span>
                            </div>
                        </div>

                        <!-- Tiempos -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-100">
                                <span class="text-[10px] text-emerald-800 font-bold uppercase block">Entrada</span>
                                <span class="text-xs font-bold text-gray-800 block mt-1" x-text="registroSeleccionado.entrada"></span>
                                <span class="text-[11px] text-gray-600 block mt-1"><i class="fa-solid fa-user-shield"></i> <span class="font-semibold" x-text="registroSeleccionado.entrada_por"></span></span>
                            </div>
                            <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-100">
                                <span class="text-[10px] text-blue-800 font-bold uppercase block">Salida</span>
                                <span class="text-xs font-bold text-gray-800 block mt-1" x-text="registroSeleccionado.salida"></span>
                                <span class="text-[11px] text-gray-600 block mt-1"><i class="fa-solid fa-user-shield"></i> <span class="font-semibold" x-text="registroSeleccionado.salida_por"></span></span>
                            </div>
                        </div>

                        <!-- Fotos del vehículo -->
                        <div class="space-y-2" x-show="registroSeleccionado.fotos.length">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Fotos</span>
                            <div class="grid grid-cols-2 gap-3">
                                <template x-for="foto in registroSeleccionado.fotos" :key="foto.url">
                                    <button type="button" @click="verFirma(foto.url, foto.parte + ' · ' + registroSeleccionado.nombre)"
                                        class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50 hover:border-indigo-400 transition text-left">
                                        <img :src="foto.url" :alt="foto.parte" class="w-full h-32 object-cover" loading="lazy">
                                        <span class="flex items-center justify-between px-2 py-1.5 text-xs font-semibold text-gray-700">
                                            <span x-text="foto.parte"></span>
                                            <i class="fa-solid fa-magnifying-glass-plus text-gray-400"></i>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Firma -->
                        <div class="space-y-2">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">Firma</span>
                            <template x-if="registroSeleccionado.firma_url">
                                <button type="button" @click="verFirma(registroSeleccionado.firma_url, 'Firma · ' + registroSeleccionado.nombre)"
                                    class="w-full p-3 rounded-xl border border-gray-200 bg-gray-50 hover:border-indigo-400 transition flex justify-center">
                                    <img :src="registroSeleccionado.firma_url" alt="Firma del visitante" class="h-28 object-contain">
                                </button>
                            </template>
                            <p x-show="!registroSeleccionado.firma_url" class="text-xs text-gray-500">Sin firma disponible.</p>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Pie fijo -->
            <div class="shrink-0 p-3 bg-gray-50 border-t border-gray-200 flex justify-end pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
                <button type="button" @click="modalDetalleOpen = false"
                    class="w-full sm:w-auto px-6 py-3 bg-slate-900 text-white text-sm font-bold rounded-xl hover:bg-slate-800 transition">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- MODAL 2: VISOR DE FOTO / FIRMA (por encima de la ficha)   -->
    <!-- ========================================================= -->
    <div x-show="modalFirmaOpen" x-transition.opacity x-cloak
        @click.self="modalFirmaOpen = false"
        class="fixed inset-0 z-[60] bg-black/90 flex flex-col items-center justify-center p-3"
        role="dialog" aria-modal="true" :aria-label="firmaModalTitulo">
        <div class="w-full max-w-3xl flex items-center justify-between gap-3 text-white mb-3">
            <span class="text-sm font-bold truncate" x-text="firmaModalTitulo"></span>
            <button type="button" @click="modalFirmaOpen = false"
                class="shrink-0 w-11 h-11 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center" aria-label="Cerrar">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <img :src="firmaModalUrl" :alt="firmaModalTitulo" @click="modalFirmaOpen = false"
            class="max-h-[80dvh] max-w-full object-contain rounded-lg bg-white">
        <p class="mt-3 text-xs text-white/60">Toca fuera de la imagen o presiona Esc para volver</p>
    </div>

</div>
@endsection