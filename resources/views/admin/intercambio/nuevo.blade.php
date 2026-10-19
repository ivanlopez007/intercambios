@extends('admin.layout.layout')

@section('title', 'Nuevo intercambio')
@section('page-title', 'Intercambio de caja y tracto')

@push('vite')
{{-- Identifica a quien captura, aunque el intercambio se envíe después con otra sesión. --}}
<meta name="registrado-por" content="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) auth()->id()) }}">
<link rel="manifest" href="/manifest.json">
@vite(['resources/js/intercambio.js'])
@endpush

@php
$campo = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base text-slate-900 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none';
$etiqueta = 'block text-xs font-bold text-slate-600 mb-1';
$tarjeta = 'bg-white border border-slate-200 rounded-2xl p-4 sm:p-5 shadow-sm';
$opcion = 'flex-1 rounded-2xl border-2 px-3 py-4 text-sm font-bold transition flex flex-col items-center gap-1';
$opcionActiva = 'border-blue-600 bg-blue-50 text-blue-700';
$opcionInactiva = 'border-slate-200 bg-white text-slate-600';
$coloresLlanta = "{
    'bg-rose-500 border-rose-700 text-white': colorLlanta(p) === 'rojo',
    'bg-amber-400 border-amber-600 text-slate-900': colorLlanta(p) === 'amarillo',
    'bg-emerald-500 border-emerald-700 text-white': colorLlanta(p) === 'verde',
    'bg-white border-slate-400 text-slate-600': !colorLlanta(p),
}";
@endphp

@section('contenido')
<div x-data="asistenteIntercambio" class="max-w-3xl mx-auto pb-6" x-cloak>

    {{-- Aviso de conexión --}}
    <div x-show="!enLinea" role="status"
        class="mb-3 rounded-2xl bg-amber-100 border border-amber-200 text-amber-900 text-sm font-semibold px-4 py-3 flex items-start gap-2">
        <i class="fa-solid fa-wifi mt-0.5"></i>
        <span>Sin conexión. Puedes seguir capturando: todo se guarda en este dispositivo y se enviará solo cuando vuelva la señal.</span>
    </div>

    {{-- ======================= INICIO ======================= --}}
    <div x-show="vista === 'inicio'" class="space-y-4">

        <div class="{{ $tarjeta }} space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 shrink-0">
                    <i class="fa-solid fa-truck-ramp-box text-xl"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-lg font-black text-slate-900 leading-tight">Intercambios</h2>
                    <p class="text-xs text-slate-500">Estado de la caja y el tracto al entrar o salir, con revisión C-TPAT.</p>
                </div>
            </div>

            <button type="button" @click="nuevo()" :disabled="!datosListos"
                class="w-full rounded-2xl bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white font-black py-4 text-base flex items-center justify-center gap-2 shadow-sm">
                <i class="fa-solid fa-plus"></i> Nuevo intercambio
            </button>

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <span class="text-slate-500" x-show="paquete">
                    <i class="fa-solid fa-database"></i>
                    Datos del <span x-text="fechaCorta(paquete?.generado_en)"></span>
                    · <span x-text="(paquete?.operadores ?? []).length"></span> operadores
                </span>
                <button type="button" @click="descargarDatos()" x-show="enLinea" :disabled="cargandoDatos"
                    class="font-bold text-blue-700 px-2 py-1 rounded-lg hover:bg-blue-50">
                    <i class="fa-solid fa-rotate" :class="cargandoDatos && 'fa-spin'"></i> Actualizar datos
                </button>
            </div>
            <p x-show="errorDatos" class="text-sm font-semibold text-rose-700" x-text="errorDatos"></p>
        </div>

        {{-- Enviados en esta sesión --}}
        <template x-if="enviadosRecientes.length">
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-900">
                <p class="font-bold mb-1"><i class="fa-solid fa-circle-check"></i> Registrados en el sistema</p>
                <p x-text="enviadosRecientes.join(', ')"></p>
            </div>
        </template>

        {{-- Pendientes de enviar --}}
        <template x-if="pendientesEnvio.length">
            <div class="{{ $tarjeta }} space-y-3">
                <h3 class="font-black text-slate-900">Por enviar <span class="text-slate-400 font-semibold" x-text="'(' + pendientesEnvio.length + ')'"></span></h3>
                <template x-for="registro in pendientesEnvio" :key="registro.id">
                    <div class="rounded-xl border p-3 space-y-2" :class="registro.estado === 'pendiente' ? 'border-slate-200 bg-slate-50' : 'border-rose-200 bg-rose-50'">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wider text-blue-700" x-text="registro.titulo"></p>
                                <p class="font-bold text-slate-900 truncate" x-text="registro.resumen"></p>
                                <p class="text-xs text-slate-500" x-text="fechaCorta(registro.creado_en)"></p>
                            </div>
                            <span x-show="registro.estado === 'pendiente'" class="shrink-0 text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-1 rounded-full">
                                <i class="fa-solid fa-clock"></i>
                                <span x-text="(registro.archivos ?? []).filter((a) => a.subida).length + '/' + (registro.archivos ?? []).length + ' fotos'"></span>
                            </span>
                        </div>
                        <p x-show="registro.mensaje" class="text-sm font-semibold text-rose-800" x-text="registro.mensaje"></p>
                        <div x-show="registro.estado !== 'pendiente'" class="flex gap-2">
                            <button type="button" @click="corregirRechazado(registro)" class="flex-1 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold">Corregir</button>
                            <button type="button" @click="$store.cola.reintentar(registro)" class="flex-1 py-2.5 rounded-xl bg-white border border-slate-300 text-slate-700 text-sm font-bold">Reintentar</button>
                            <button type="button" @click="descartarEnviado(registro)" class="w-11 rounded-xl bg-white border border-rose-300 text-rose-700" aria-label="Descartar intercambio">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </template>
                <p x-show="$store.cola.sesionExpirada" class="text-sm font-semibold text-rose-700">
                    Tu sesión expiró. <a href="{{ route('login') }}" class="underline">Inicia sesión</a> para enviarlos; no se perderán.
                </p>
            </div>
        </template>

        {{-- Borradores --}}
        <div class="{{ $tarjeta }} space-y-3">
            <h3 class="font-black text-slate-900">Borradores en este dispositivo</h3>
            <p x-show="!borradoresEditando.length" class="text-sm text-slate-500">No hay borradores.</p>
            <template x-for="b in borradoresEditando" :key="b.id">
                <div class="rounded-xl border border-slate-200 p-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-bold text-slate-900 truncate">
                            <span x-text="b.tipo_movimiento === 'entrada' ? 'Entrada' : (b.tipo_movimiento === 'salida' ? 'Salida' : 'Sin movimiento')"></span>
                            · Paso <span x-text="b.paso"></span> de <span x-text="PASOS.length"></span>
                        </p>
                        <p class="text-xs text-slate-500">Guardado <span x-text="fechaCorta(b.actualizado_en)"></span></p>
                        <p x-show="b.rechazo" class="text-xs font-semibold text-rose-700" x-text="'Rechazado: ' + b.rechazo"></p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" @click="descartar(b.id)" class="w-10 h-10 rounded-xl border border-slate-300 text-slate-500" aria-label="Descartar borrador">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        <button type="button" @click="continuar(b.id)" :disabled="!datosListos" class="px-4 h-10 rounded-xl bg-blue-600 text-white text-sm font-bold disabled:bg-slate-300">Continuar</button>
                    </div>
                </div>
            </template>
        </div>

        <a href="{{ route('admin.intercambios.historial') }}" class="block text-center text-sm font-bold text-slate-600 py-2">
            Ver historial de intercambios <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    {{-- ======================= ASISTENTE ======================= --}}
    <template x-if="vista === 'asistente' && borrador">
        <div id="asistente-inicio" class="scroll-mt-20">

            {{-- Encabezado fijo --}}
            <div class="sticky top-16 z-10 -mx-2 sm:mx-0 px-3 sm:px-4 py-3 bg-slate-100/95 backdrop-blur border-b border-slate-200 sm:rounded-b-2xl space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <button type="button" @click="salir()" class="text-sm font-bold text-slate-600 py-1 pr-2">
                        <i class="fa-solid fa-chevron-left"></i> Salir
                    </button>
                    <div class="flex flex-wrap justify-end gap-1 text-[11px] font-bold">
                        <span class="px-2 py-1 rounded-full bg-slate-200 text-slate-700">Borrador</span>
                        <span x-show="borrador.tipo_movimiento" class="px-2 py-1 rounded-full"
                            :class="borrador.tipo_movimiento === 'entrada' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                            x-text="borrador.tipo_movimiento === 'entrada' ? 'Entrada' : 'Salida'"></span>
                        <span x-show="etiquetaCaja" class="px-2 py-1 rounded-full bg-blue-100 text-blue-800"><i class="fa-solid fa-trailer"></i> <span x-text="etiquetaCaja"></span></span>
                        <span x-show="etiquetaTracto" class="px-2 py-1 rounded-full bg-indigo-100 text-indigo-800"><i class="fa-solid fa-truck"></i> <span x-text="etiquetaTracto"></span></span>
                    </div>
                </div>

                <div>
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-lg font-black text-slate-900" x-text="PASOS[borrador.paso - 1]"></h2>
                        <span class="text-xs font-bold text-slate-500" x-text="'Paso ' + (indicePaso + 1) + ' de ' + pasosVisibles.length"></span>
                    </div>
                    <div class="flex gap-1 mt-2" role="list" aria-label="Pasos">
                        <template x-for="(p, i) in pasosVisibles" :key="p.numero">
                            <button type="button" role="listitem" @click="editar(p.numero)" :disabled="p.numero > borrador.paso_maximo"
                                :aria-label="p.nombre" :aria-current="p.numero === borrador.paso ? 'step' : null"
                                class="h-2 flex-1 rounded-full transition"
                                :class="p.numero === borrador.paso ? 'bg-blue-600' : (p.numero <= borrador.paso_maximo ? 'bg-blue-300' : 'bg-slate-300')"></button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="space-y-4 pt-4 px-1 sm:px-0">

                <div x-show="borrador.rechazo" class="rounded-2xl bg-rose-100 border border-rose-200 text-rose-900 text-sm font-semibold p-4">
                    <i class="fa-solid fa-triangle-exclamation"></i> El servidor rechazó este intercambio: <span x-text="borrador.rechazo"></span>
                </div>

                <div id="asistente-errores" x-show="errores.length" role="alert" class="rounded-2xl bg-rose-100 border border-rose-200 text-rose-900 text-sm font-semibold p-4">
                    <ul class="list-disc list-inside space-y-1">
                        <template x-for="error in errores"><li x-text="error"></li></template>
                    </ul>
                </div>

                {{-- ---------- PASO 1: OPERADOR ---------- --}}
                <template x-if="borrador.paso === 1">
                    <div class="space-y-4">
                        <div class="{{ $tarjeta }} space-y-4">
                            <div>
                                <span class="{{ $etiqueta }}">Tipo de movimiento</span>
                                <div class="flex gap-2">
                                    <button type="button" @click="borrador.tipo_movimiento = 'entrada'" class="{{ $opcion }}"
                                        :class="borrador.tipo_movimiento === 'entrada' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : '{{ $opcionInactiva }}'">
                                        <i class="fa-solid fa-right-to-bracket text-xl"></i> Entrada
                                    </button>
                                    <button type="button" @click="borrador.tipo_movimiento = 'salida'" class="{{ $opcion }}"
                                        :class="borrador.tipo_movimiento === 'salida' ? 'border-rose-600 bg-rose-50 text-rose-700' : '{{ $opcionInactiva }}'">
                                        <i class="fa-solid fa-right-from-bracket text-xl"></i> Salida
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label for="fecha_hora" class="{{ $etiqueta }}">Fecha y hora</label>
                                <input id="fecha_hora" type="datetime-local" x-model="borrador.fecha_hora" class="{{ $campo }}">
                            </div>
                        </div>

                        <div class="{{ $tarjeta }} space-y-3">
                            <span class="{{ $etiqueta }}">Operador</span>

                            <template x-if="operador">
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-black text-slate-900" x-text="operador.nombre"></p>
                                            <p class="text-sm text-slate-500" x-text="'No. empleado ' + operador.numero_empleado"></p>
                                        </div>
                                        <button type="button" @click="borrador.operador_id = null" class="text-sm font-bold text-blue-700 shrink-0">Cambiar</button>
                                    </div>

                                    <div class="grid sm:grid-cols-3 gap-2 text-sm">
                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
                                            <p class="text-xs text-slate-500">Licencia</p>
                                            <p class="font-bold text-slate-900" x-text="operador.licencia || 'Sin licencia registrada'"></p>
                                            <p class="text-xs text-slate-500" x-text="operador.tipo_licencia || ''"></p>
                                        </div>
                                        <template x-for="[titulo, fecha] in [['Vence licencia', operador.licencia_vence], ['Vence apto médico', operador.apto_vence]]" :key="titulo">
                                            <div class="rounded-xl border p-3"
                                                :class="{
                                                    'bg-rose-50 border-rose-300': estadoVencimiento(fecha) === 'vencido',
                                                    'bg-amber-50 border-amber-300': estadoVencimiento(fecha) === 'por_vencer',
                                                    'bg-emerald-50 border-emerald-300': estadoVencimiento(fecha) === 'vigente',
                                                    'bg-slate-50 border-slate-200': estadoVencimiento(fecha) === 'sin_dato',
                                                }">
                                                <p class="text-xs text-slate-500" x-text="titulo"></p>
                                                <p class="font-bold text-slate-900" x-text="fecha ? fechaCorta(fecha) : 'Sin dato'"></p>
                                                <p class="text-xs font-bold"
                                                    :class="{ 'text-rose-700': estadoVencimiento(fecha) === 'vencido', 'text-amber-700': estadoVencimiento(fecha) === 'por_vencer', 'text-emerald-700': estadoVencimiento(fecha) === 'vigente' }"
                                                    x-text="{ vencido: 'Vencido', por_vencer: 'Por vencer', vigente: 'Vigente', sin_dato: '' }[estadoVencimiento(fecha)]"></p>
                                            </div>
                                        </template>
                                    </div>

                                    <div x-show="operadorConVencidos" class="rounded-xl bg-rose-100 border border-rose-200 text-rose-900 text-sm font-semibold p-3">
                                        <i class="fa-solid fa-triangle-exclamation"></i> El operador tiene documentos vencidos. Se puede continuar, pero quedará registrado.
                                    </div>
                                </div>
                            </template>

                            <template x-if="!operador">
                                <div class="space-y-2">
                                    <input type="search" x-model="busquedaOperador" placeholder="Nombre o número de empleado" class="{{ $campo }}" autocomplete="off">
                                    <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100">
                                        <template x-for="o in operadoresFiltrados" :key="o.id">
                                            <button type="button" @click="borrador.operador_id = o.id; busquedaOperador = ''"
                                                class="w-full text-left px-3 py-3 hover:bg-blue-50 active:bg-blue-100">
                                                <span class="block font-bold text-slate-900" x-text="o.nombre"></span>
                                                <span class="block text-xs text-slate-500" x-text="o.numero_empleado + (o.licencia ? ' · Lic. ' + o.licencia : ' · Sin licencia registrada')"></span>
                                            </button>
                                        </template>
                                        <p x-show="!operadoresFiltrados.length" class="px-3 py-4 text-sm text-slate-500">No se encontró ningún operador activo.</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- ---------- PASO 2: CAJA ---------- --}}
                <template x-if="borrador.paso === 2">
                    <div class="space-y-4">
                        <template x-if="!borrador.caja.modo">
                            <div class="{{ $tarjeta }} space-y-3">
                                <label for="buscar-caja" class="{{ $etiqueta }}">Número económico o placa de la caja</label>
                                <input id="buscar-caja" type="search" x-model="busquedaCaja" placeholder="Ej. 5310" class="{{ $campo }}" autocomplete="off">
                                <div x-show="cajasFiltradas.length" class="rounded-xl border border-slate-200 divide-y divide-slate-100">
                                    <template x-for="c in cajasFiltradas" :key="c.id">
                                        <button type="button" @click="seleccionarCaja(c)" class="w-full text-left px-3 py-3 hover:bg-blue-50 active:bg-blue-100">
                                            <span class="block font-bold text-slate-900" x-text="c.numero_economico + (c.placa ? ' · ' + c.placa : '')"></span>
                                            <span class="block text-xs text-slate-500" x-text="'Dueño: ' + nombreDe('clientes', c.cliente_id) + (c.tipo_caja_id ? ' · ' + nombreDe('tipos_caja', c.tipo_caja_id) : '')"></span>
                                        </button>
                                    </template>
                                </div>
                                <p x-show="busquedaCaja && !cajasFiltradas.length" class="text-sm text-slate-500">No hay ninguna caja con ese número.</p>
                                <button type="button" @click="registrarCajaNueva()" class="w-full rounded-xl border-2 border-dashed border-blue-300 text-blue-700 font-bold py-3">
                                    <i class="fa-solid fa-plus"></i> Registrar caja nueva
                                </button>
                            </div>
                        </template>

                        {{-- Caja existente --}}
                        <template x-if="borrador.caja.modo === 'existente' && caja">
                            <div class="{{ $tarjeta }} space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-xs text-slate-500">Caja</p>
                                        <p class="text-xl font-black text-slate-900" x-text="caja.numero_economico"></p>
                                        <p class="text-sm text-slate-600" x-text="'Dueño: ' + duenoCaja"></p>
                                    </div>
                                    <button type="button" @click="cambiarCaja()" class="text-sm font-bold text-blue-700">Cambiar</button>
                                </div>

                                <dl x-show="!borrador.caja.corregir" class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-sm">
                                    <template x-for="[titulo, valor] in [
                                        ['Tipo', nombreDe('tipos_caja', caja.tipo_caja_id)], ['Placa', caja.placa], ['Estado placa', caja.estado_placa],
                                        ['Marca', caja.marca], ['Modelo', caja.modelo], ['VIN', caja.vin], ['Año', caja.anio], ['Tamaño', caja.tamano_pies ? caja.tamano_pies + ' pies' : ''],
                                    ]" :key="titulo">
                                        <div class="rounded-xl bg-slate-50 p-2">
                                            <dt class="text-xs text-slate-500" x-text="titulo"></dt>
                                            <dd class="font-semibold text-slate-900 break-all" x-text="valor || '—'"></dd>
                                        </div>
                                    </template>
                                </dl>

                                <div x-show="borrador.caja.corregir" class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $etiqueta }}">Tipo de caja</label>
                                        <select x-model="borrador.caja.correccion.tipo_caja_id" class="{{ $campo }}">
                                            <option value="">Sin tipo</option>
                                            <template x-for="t in paquete.tipos_caja" :key="t.id"><option :value="t.id" x-text="t.nombre" :selected="String(t.id) === String(borrador.caja.correccion.tipo_caja_id)"></option></template>
                                        </select>
                                    </div>
                                    <template x-for="[clave, titulo, tipo] in [['placa','Placa *','text'],['estado_placa','Estado de la placa','text'],['marca','Marca','text'],['modelo','Modelo','text'],['vin','VIN','text'],['anio','Año','number'],['tamano_pies','Tamaño (pies)','number']]" :key="clave">
                                        <div>
                                            <label class="{{ $etiqueta }}" x-text="titulo"></label>
                                            <input :type="tipo" :inputmode="tipo === 'number' ? 'numeric' : null" x-model="borrador.caja.correccion[clave]" class="{{ $campo }}">
                                        </div>
                                    </template>
                                </div>

                                <button type="button" @click="corregirCaja()" class="text-sm font-bold text-slate-600">
                                    <i class="fa-solid" :class="borrador.caja.corregir ? 'fa-xmark' : 'fa-pen'"></i>
                                    <span x-text="borrador.caja.corregir ? 'Cancelar corrección' : 'Corregir datos'"></span>
                                </button>
                            </div>
                        </template>

                        {{-- Caja nueva --}}
                        <template x-if="borrador.caja.modo === 'nueva'">
                            <div class="{{ $tarjeta }} space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="font-black text-slate-900">Caja nueva</p>
                                    <button type="button" @click="cambiarCaja()" class="text-sm font-bold text-blue-700">Buscar otra</button>
                                </div>
                                <div>
                                    <label class="{{ $etiqueta }}">Dueño (cliente) *</label>
                                    <select x-model="borrador.caja.nuevo.cliente_id" class="{{ $campo }}">
                                        <option value="">Selecciona…</option>
                                        <template x-for="c in paquete.clientes" :key="c.id"><option :value="c.id" x-text="c.nombre" :selected="String(c.id) === String(borrador.caja.nuevo.cliente_id)"></option></template>
                                        <option value="__nuevo">+ Cliente nuevo</option>
                                    </select>
                                </div>
                                <div x-show="borrador.caja.nuevo.cliente_id === '__nuevo'" class="grid sm:grid-cols-2 gap-3 rounded-xl bg-blue-50 p-3">
                                    <div><label class="{{ $etiqueta }}">Nombre del cliente *</label><input type="text" x-model="borrador.caja.nuevo.cliente_nuevo.nombre" class="{{ $campo }}"></div>
                                    <div><label class="{{ $etiqueta }}">RFC (opcional)</label><input type="text" x-model="borrador.caja.nuevo.cliente_nuevo.rfc" maxlength="13" class="{{ $campo }} uppercase"></div>
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div><label class="{{ $etiqueta }}">Número económico *</label><input type="text" x-model="borrador.caja.nuevo.numero_economico" maxlength="30" class="{{ $campo }} uppercase"></div>
                                    <div><label class="{{ $etiqueta }}">Placa *</label><input type="text" x-model="borrador.caja.nuevo.placa" maxlength="20" class="{{ $campo }} uppercase"></div>
                                    <div>
                                        <label class="{{ $etiqueta }}">Tipo de caja</label>
                                        <select x-model="borrador.caja.nuevo.tipo_caja_id" class="{{ $campo }}">
                                            <option value="">Sin tipo</option>
                                            <template x-for="t in paquete.tipos_caja" :key="t.id"><option :value="t.id" x-text="t.nombre" :selected="String(t.id) === String(borrador.caja.nuevo.tipo_caja_id)"></option></template>
                                        </select>
                                    </div>
                                    <template x-for="[clave, titulo, tipo] in [['estado_placa','Estado de la placa','text'],['marca','Marca','text'],['modelo','Modelo','text'],['vin','VIN','text'],['anio','Año','number'],['tamano_pies','Tamaño (pies)','number']]" :key="clave">
                                        <div>
                                            <label class="{{ $etiqueta }}" x-text="titulo"></label>
                                            <input :type="tipo" :inputmode="tipo === 'number' ? 'numeric' : null" x-model="borrador.caja.nuevo[clave]" class="{{ $campo }}">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-for="aviso in advertenciasCaja">
                            <div class="rounded-2xl bg-amber-100 border border-amber-200 text-amber-900 text-sm font-semibold p-3">
                                <i class="fa-solid fa-triangle-exclamation"></i> <span x-text="aviso"></span>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ---------- PASO 3: TRACTOCAMIÓN ---------- --}}
                <template x-if="borrador.paso === 3">
                    <div class="space-y-4">
                        <template x-if="!borrador.tracto.modo">
                            <div class="{{ $tarjeta }} space-y-3">
                                <label for="buscar-tracto" class="{{ $etiqueta }}">Número económico o placa del tracto</label>
                                <input id="buscar-tracto" type="search" x-model="busquedaTracto" placeholder="Ej. T-120" class="{{ $campo }}" autocomplete="off">
                                <div x-show="tractosFiltrados.length" class="rounded-xl border border-slate-200 divide-y divide-slate-100">
                                    <template x-for="t in tractosFiltrados" :key="t.id">
                                        <button type="button" @click="seleccionarTracto(t)" class="w-full text-left px-3 py-3 hover:bg-blue-50 active:bg-blue-100">
                                            <span class="block font-bold text-slate-900" x-text="t.numero_economico + (t.placa ? ' · ' + t.placa : '')"></span>
                                            <span class="block text-xs text-slate-500" x-text="t.es_permisionario ? 'Permisionario · ' + nombreDe('lineas', t.linea_transportista_id) : 'Propio'"></span>
                                        </button>
                                    </template>
                                </div>
                                <p x-show="busquedaTracto && !tractosFiltrados.length" class="text-sm text-slate-500">No hay ningún tracto con ese número.</p>
                                <button type="button" @click="registrarTractoNuevo()" class="w-full rounded-xl border-2 border-dashed border-blue-300 text-blue-700 font-bold py-3">
                                    <i class="fa-solid fa-plus"></i> Registrar tractocamión nuevo
                                </button>
                            </div>
                        </template>

                        <template x-if="borrador.tracto.modo === 'existente' && tracto">
                            <div class="{{ $tarjeta }} space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-xs text-slate-500">Tractocamión</p>
                                        <p class="text-xl font-black text-slate-900" x-text="tracto.numero_economico"></p>
                                        <p class="text-sm text-slate-600" x-text="tracto.es_permisionario ? 'Permisionario · ' + lineaTracto : 'Propio'"></p>
                                    </div>
                                    <button type="button" @click="cambiarTracto()" class="text-sm font-bold text-blue-700">Cambiar</button>
                                </div>
                                <dl x-show="!borrador.tracto.corregir" class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-sm">
                                    <template x-for="[titulo, valor] in [['Placa', tracto.placa], ['Estado placa', tracto.estado_placa], ['Marca', tracto.marca], ['Modelo', tracto.modelo], ['VIN', tracto.vin], ['Año', tracto.anio]]" :key="titulo">
                                        <div class="rounded-xl bg-slate-50 p-2">
                                            <dt class="text-xs text-slate-500" x-text="titulo"></dt>
                                            <dd class="font-semibold text-slate-900 break-all" x-text="valor || '—'"></dd>
                                        </div>
                                    </template>
                                </dl>
                                <div x-show="borrador.tracto.corregir" class="grid sm:grid-cols-2 gap-3">
                                    <template x-for="[clave, titulo, tipo] in [['placa','Placa *','text'],['estado_placa','Estado de la placa','text'],['marca','Marca','text'],['modelo','Modelo','text'],['vin','VIN','text'],['anio','Año','number']]" :key="clave">
                                        <div>
                                            <label class="{{ $etiqueta }}" x-text="titulo"></label>
                                            <input :type="tipo" :inputmode="tipo === 'number' ? 'numeric' : null" x-model="borrador.tracto.correccion[clave]" class="{{ $campo }}">
                                        </div>
                                    </template>
                                </div>
                                <button type="button" @click="corregirTracto()" class="text-sm font-bold text-slate-600">
                                    <i class="fa-solid" :class="borrador.tracto.corregir ? 'fa-xmark' : 'fa-pen'"></i>
                                    <span x-text="borrador.tracto.corregir ? 'Cancelar corrección' : 'Corregir datos'"></span>
                                </button>
                            </div>
                        </template>

                        <template x-if="borrador.tracto.modo === 'nuevo'">
                            <div class="{{ $tarjeta }} space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="font-black text-slate-900">Tractocamión nuevo</p>
                                    <button type="button" @click="cambiarTracto()" class="text-sm font-bold text-blue-700">Buscar otro</button>
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div><label class="{{ $etiqueta }}">Número económico *</label><input type="text" x-model="borrador.tracto.nuevo.numero_economico" maxlength="30" class="{{ $campo }} uppercase"></div>
                                    <div><label class="{{ $etiqueta }}">Placa *</label><input type="text" x-model="borrador.tracto.nuevo.placa" maxlength="20" class="{{ $campo }} uppercase"></div>
                                    <template x-for="[clave, titulo, tipo] in [['estado_placa','Estado de la placa','text'],['marca','Marca','text'],['modelo','Modelo','text'],['vin','VIN','text'],['anio','Año','number']]" :key="clave">
                                        <div>
                                            <label class="{{ $etiqueta }}" x-text="titulo"></label>
                                            <input :type="tipo" :inputmode="tipo === 'number' ? 'numeric' : null" x-model="borrador.tracto.nuevo[clave]" class="{{ $campo }}">
                                        </div>
                                    </template>
                                </div>
                                <label class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer">
                                    <input type="checkbox" x-model="borrador.tracto.nuevo.es_permisionario" class="w-5 h-5 rounded accent-blue-600">
                                    <span class="text-sm font-bold text-slate-800">Es de permisionario</span>
                                </label>
                                <div x-show="borrador.tracto.nuevo.es_permisionario" class="space-y-3">
                                    <div>
                                        <label class="{{ $etiqueta }}">Línea transportista *</label>
                                        <select x-model="borrador.tracto.nuevo.linea_id" class="{{ $campo }}">
                                            <option value="">Selecciona…</option>
                                            <template x-for="l in paquete.lineas" :key="l.id"><option :value="l.id" x-text="l.nombre" :selected="String(l.id) === String(borrador.tracto.nuevo.linea_id)"></option></template>
                                            <option value="__nueva">+ Línea nueva</option>
                                        </select>
                                    </div>
                                    <div x-show="borrador.tracto.nuevo.linea_id === '__nueva'" class="grid sm:grid-cols-2 gap-3 rounded-xl bg-blue-50 p-3">
                                        <div><label class="{{ $etiqueta }}">Nombre de la línea *</label><input type="text" x-model="borrador.tracto.nuevo.linea_nueva.nombre" class="{{ $campo }}"></div>
                                        <div><label class="{{ $etiqueta }}">RFC (opcional)</label><input type="text" x-model="borrador.tracto.nuevo.linea_nueva.rfc" maxlength="13" class="{{ $campo }} uppercase"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="borrador.tracto.modo" class="{{ $tarjeta }}">
                            <label for="odometro" class="{{ $etiqueta }}">Odómetro (opcional)</label>
                            <input id="odometro" type="number" inputmode="numeric" min="0" x-model="borrador.odometro" class="{{ $campo }}" placeholder="Kilómetros">
                        </div>
                    </div>
                </template>

                {{-- ---------- PASO 4: VIAJE ---------- --}}
                <template x-if="borrador.paso === 4">
                    <div class="{{ $tarjeta }} space-y-4">
                        <div>
                            <span class="{{ $etiqueta }}">Estado de carga *</span>
                            <div class="flex gap-2">
                                <button type="button" @click="elegirCarga('cargada')" class="{{ $opcion }}" :class="borrador.estado_carga === 'cargada' ? '{{ $opcionActiva }}' : '{{ $opcionInactiva }}'">
                                    <i class="fa-solid fa-boxes-stacked text-xl"></i> Cargada
                                </button>
                                <button type="button" @click="elegirCarga('vacia')" class="{{ $opcion }}" :class="borrador.estado_carga === 'vacia' ? '{{ $opcionActiva }}' : '{{ $opcionInactiva }}'">
                                    <i class="fa-regular fa-square text-xl"></i> Vacía
                                </button>
                            </div>
                        </div>
                        <div>
                            <span class="{{ $etiqueta }}">Tipo de intercambio *</span>
                            <div class="flex gap-2">
                                <template x-for="[valor, titulo] in [['impo', 'Impo'], ['expo', 'Expo'], ['local', 'Local']]" :key="valor">
                                    <button type="button" @click="borrador.tipo_intercambio = valor" class="{{ $opcion }}"
                                        :class="borrador.tipo_intercambio === valor ? '{{ $opcionActiva }}' : '{{ $opcionInactiva }}'" x-text="titulo"></button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <label class="{{ $etiqueta }}">Cliente del viaje</label>
                            <select x-model="borrador.cliente_id" class="{{ $campo }}">
                                <option value="" x-text="'El dueño de la caja' + (duenoCaja ? ' (' + duenoCaja + ')' : '')"></option>
                                <template x-for="c in paquete.clientes" :key="c.id"><option :value="c.id" x-text="c.nombre" :selected="String(c.id) === String(borrador.cliente_id)"></option></template>
                                <option value="__nuevo">+ Cliente nuevo</option>
                            </select>
                            <div x-show="borrador.cliente_id === '__nuevo'" class="grid sm:grid-cols-2 gap-3 rounded-xl bg-blue-50 p-3 mt-2">
                                <div><label class="{{ $etiqueta }}">Nombre del cliente *</label><input type="text" x-model="borrador.cliente_nuevo.nombre" class="{{ $campo }}"></div>
                                <div><label class="{{ $etiqueta }}">RFC (opcional)</label><input type="text" x-model="borrador.cliente_nuevo.rfc" maxlength="13" class="{{ $campo }} uppercase"></div>
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <template x-for="[clave, titulo] in [['origen', 'Origen'], ['destino', 'Destino']]" :key="clave">
                                <div>
                                    <label class="{{ $etiqueta }}" x-text="titulo"></label>
                                    <select x-model="borrador[clave + '_id']" class="{{ $campo }}">
                                        <option value="">Sin especificar</option>
                                        <template x-for="d in paquete.destinos" :key="d.id"><option :value="d.id" x-text="d.nombre" :selected="String(d.id) === String(borrador[clave + '_id'])"></option></template>
                                        <option value="__nuevo">+ Ubicación nueva</option>
                                    </select>
                                    <input x-show="borrador[clave + '_id'] === '__nuevo'" type="text" x-model="borrador[clave + '_nuevo']" placeholder="Nombre de la ubicación" class="{{ $campo }} mt-2">
                                </div>
                            </template>
                        </div>
                        <div>
                            <label for="carta_porte" class="{{ $etiqueta }}">Referencia o carta porte</label>
                            <input id="carta_porte" type="text" x-model="borrador.referencia_carta_porte" maxlength="100" class="{{ $campo }}">
                        </div>
                    </div>
                </template>

                {{-- ---------- PASO 5: SELLO ---------- --}}
                <template x-if="borrador.paso === 5">
                    <div class="{{ $tarjeta }} space-y-4">
                        <div>
                            <label for="sello" class="{{ $etiqueta }}">Número de sello *</label>
                            <input id="sello" type="text" x-model="borrador.numero_sello" maxlength="50" class="{{ $campo }} uppercase">
                        </div>
                        <div>
                            <span class="{{ $etiqueta }}">Fotos del sello * <span class="font-normal text-slate-500" x-text="'(1 a ' + (config.max_fotos_sello ?? 2) + ')'"></span></span>
                            <p class="text-xs text-slate-500 mb-2">Que se vea claramente el código del sello y que está colocado.</p>
                            @include('admin.intercambio.partials.fotos', [
                                'filtro' => "{ tipo: 'sello' }",
                                'meta' => "{ tipo: 'sello' }",
                                'mostrar' => "fotosDe({ tipo: 'sello' }).length < (config.max_fotos_sello ?? 2)",
                                'etiqueta' => 'Foto del sello',
                            ])
                        </div>
                    </div>
                </template>

                {{-- ---------- PASO 6: LLANTAS ---------- --}}
                <template x-if="borrador.paso === 6">
                    <div class="space-y-4">
                        <div class="{{ $tarjeta }} space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm text-slate-600">
                                    Toca una llanta en el dibujo para medirla. Lo que no captures se guarda con
                                    <span class="font-bold" x-text="llantaPorDefecto.profundidad_32 + '/32 y ' + llantaPorDefecto.psi + ' PSI'"></span>.
                                </p>
                                <span class="shrink-0 text-xs font-bold px-2 py-1 rounded-full bg-slate-100 text-slate-700" x-text="llantasCapturadas + ' de 18 medidas'"></span>
                            </div>

                            <div class="mx-auto w-full max-w-[17rem]">
                                @include('admin.intercambio.partials.diagrama-llantas')
                            </div>

                            <div class="flex flex-wrap justify-center gap-3 text-[11px] font-semibold text-slate-600 pt-1">
                                <span><span class="inline-block w-3 h-3 rounded border-2 border-slate-600 bg-white align-middle"></span> Sin medir</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-rose-500 align-middle"></span> Bajo el mínimo legal</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-amber-400 align-middle"></span> Alerta</span>
                                <span><span class="inline-block w-3 h-3 rounded bg-emerald-500 align-middle"></span> Bien</span>
                            </div>
                        </div>

                        <div x-show="llantasConAlerta.some((p) => colorLlanta(p) === 'rojo')" class="rounded-2xl bg-rose-100 border border-rose-200 text-rose-900 text-sm font-semibold p-3">
                            <i class="fa-solid fa-triangle-exclamation"></i> Hay llantas bajo el mínimo legal. Se puede continuar, pero aparecerán en el resumen.
                        </div>
                    </div>
                </template>

                {{-- ---------- PASO 7: REVISIÓN C-TPAT ---------- --}}
                <template x-if="borrador.paso === 7">
                    <div class="space-y-6">
                        @include('admin.intercambio.partials.checklist', ['seccion' => 'ctpat', 'titulo' => 'Revisión C-TPAT', 'descripcion' => 'Marca "No cumple" solo si encuentras algo; se pedirán observaciones.', 'columnas' => true])
                        @include('admin.intercambio.partials.checklist', ['seccion' => 'olores', 'titulo' => 'Olores'])
                        @include('admin.intercambio.partials.checklist', ['seccion' => 'residuos', 'titulo' => 'Residuos'])
                        <template x-if="puntosDe('remolque').length">
                            <div>
                                @include('admin.intercambio.partials.checklist', ['seccion' => 'remolque', 'titulo' => 'Revisión del remolque', 'descripcion' => 'Para el contrato de intercambio. Marca "No cumple" si tiene defecto.'])
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ---------- PASO 8: COMENTARIOS Y DAÑOS ---------- --}}
                <template x-if="borrador.paso === 8">
                    <div class="space-y-4">
                        <template x-if="relacionado">
                            <div class="rounded-2xl bg-slate-200/60 border border-slate-300 p-4 space-y-2 text-sm">
                                <p class="font-black text-slate-900">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                    <span x-text="'Última ' + (borrador.tipo_movimiento === 'entrada' ? 'salida' : 'entrada') + ' de esta caja'"></span>
                                </p>
                                <p class="text-slate-600" x-text="relacionado.folio + ' · ' + fechaCorta(relacionado.fecha_hora)"></p>
                                <template x-for="[titulo, valor] in [['Caja', relacionado.comentarios_caja], ['Tracto', relacionado.comentarios_tracto], ['Observaciones', relacionado.observaciones]]" :key="titulo">
                                    <p x-show="valor"><span class="font-bold" x-text="titulo + ': '"></span><span x-text="valor"></span></p>
                                </template>
                                <p x-show="!relacionado.comentarios_caja && !relacionado.comentarios_tracto && !relacionado.observaciones" class="text-slate-500">Sin comentarios.</p>
                                <a x-show="enLinea" :href="'/admin/intercambios/' + relacionado.id" target="_blank" class="inline-block font-bold text-blue-700">Ver fotos y detalle <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a>
                            </div>
                        </template>

                        {{-- ¿Tiene daños? Si dice que sí: descripción obligatoria y fotos si hace falta. --}}
                        @foreach (['caja' => ['la caja', 'fa-trailer'], 'tracto' => ['el tracto', 'fa-truck']] as $unidad => [$nombreUnidad, $iconoUnidad])
                        <div class="{{ $tarjeta }} space-y-3" :class="tieneDanos('{{ $unidad }}') && 'border-rose-300 bg-rose-50'">
                            <p class="font-black text-slate-900"><i class="fa-solid {{ $iconoUnidad }}"></i> ¿{{ ucfirst($nombreUnidad) }} tiene daños?</p>
                            <div class="flex gap-2">
                                <button type="button" @click="marcarDanos('{{ $unidad }}', 'no')" class="{{ $opcion }}"
                                    :class="!tieneDanos('{{ $unidad }}') ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : '{{ $opcionInactiva }}'">
                                    <i class="fa-solid fa-circle-check text-xl"></i> No
                                </button>
                                <button type="button" @click="marcarDanos('{{ $unidad }}', 'si')" class="{{ $opcion }}"
                                    :class="tieneDanos('{{ $unidad }}') ? 'border-rose-600 bg-rose-100 text-rose-700' : '{{ $opcionInactiva }}'">
                                    <i class="fa-solid fa-triangle-exclamation text-xl"></i> Sí, tiene daños
                                </button>
                            </div>
                            <div x-show="tieneDanos('{{ $unidad }}')" class="space-y-3">
                                @if ($unidad === 'caja')
                                {{-- Lado del daño para el contrato de intercambio: G golpe, C corte, A agujero --}}
                                <div x-show="puntosDe('danos_caja').length">
                                    <span class="{{ $etiqueta }}">Marca el lado dañado: G = golpe, C = corte, A = agujero</span>
                                    <div class="space-y-2 rounded-xl bg-white border border-rose-200 p-3">
                                        <template x-for="punto in puntosDe('danos_caja')" :key="punto.id">
                                            <div>@include('admin.intercambio.partials.punto')</div>
                                        </template>
                                    </div>
                                </div>
                                @endif
                                <div>
                                    <label class="{{ $etiqueta }}">Describe los daños de {{ $nombreUnidad }} *</label>
                                    <textarea x-model="borrador.comentarios_{{ $unidad }}" rows="3" class="{{ $campo }}" placeholder="¿Qué daño tiene y dónde?"></textarea>
                                </div>
                                <div>
                                    <span class="{{ $etiqueta }}">Fotos del daño (si es necesario)</span>
                                    @include('admin.intercambio.partials.fotos', ['filtro' => "{ tipo: 'problema', unidad: '{$unidad}' }", 'meta' => "{ tipo: 'problema', unidad: '{$unidad}' }", 'etiqueta' => 'Foto del daño'])
                                </div>
                            </div>
                        </div>
                        @endforeach

                        <div class="{{ $tarjeta }}">
                            <label class="{{ $etiqueta }}">Observaciones generales (opcional)</label>
                            <textarea x-model="borrador.observaciones" rows="3" class="{{ $campo }}"></textarea>
                        </div>
                    </div>
                </template>

                {{-- ---------- PASO 9: FOTOS DE LA UNIDAD ---------- --}}
                <template x-if="borrador.paso === 9">
                    <div class="space-y-4">
                        <div class="{{ $tarjeta }} flex items-start justify-between gap-3">
                            <p class="text-sm text-slate-600">Toma las fotos en orden, caminando alrededor de la unidad: cada una se habilita al tomar la anterior. Si una salió mal, tócala para tomarla de nuevo.</p>
                            <span class="shrink-0 text-xs font-black px-2 py-1 rounded-full"
                                :class="recorridoTomadas === vistasRecorrido.length ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                                x-text="recorridoTomadas + ' de ' + vistasRecorrido.length"></span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <template x-for="(v, i) in vistasRecorrido" :key="v.clave">
                                <label :id="'vista-' + v.clave" class="relative block rounded-2xl border-2 overflow-hidden bg-white transition scroll-mt-48"
                                    :class="fotoDeVista(v.clave)
                                        ? 'border-emerald-500 cursor-pointer active:scale-[0.98]'
                                        : (i === indiceSiguienteVista ? 'border-blue-600 ring-4 ring-blue-100 cursor-pointer active:scale-[0.98]' : 'border-dashed border-slate-200 opacity-50 cursor-not-allowed')"
                                    :aria-disabled="!vistaHabilitada(i)">
                                    <div class="aspect-[4/3] flex items-center justify-center"
                                        :class="i === indiceSiguienteVista && !fotoDeVista(v.clave) ? 'bg-blue-50' : 'bg-slate-100'">
                                        <template x-if="fotoDeVista(v.clave)">
                                            <div class="relative w-full h-full" x-data="{ get foto() { return fotoDeVista(v.clave) } }">
                                                <img :src="urlFoto(foto)" :alt="v.etiqueta" class="w-full h-full object-cover">
                                                @include('admin.intercambio.partials.estado-subida')
                                            </div>
                                        </template>
                                        <template x-if="!fotoDeVista(v.clave) && i === indiceSiguienteVista">
                                            <span class="flex flex-col items-center gap-1 text-blue-700">
                                                <i class="fa-solid text-2xl" :class="procesandoFoto ? 'fa-spinner fa-spin' : 'fa-camera'"></i>
                                                <span class="text-[11px] font-black">Tomar esta foto</span>
                                            </span>
                                        </template>
                                        <template x-if="!vistaHabilitada(i)">
                                            <span class="flex flex-col items-center gap-1 text-slate-400">
                                                <i class="fa-solid fa-lock text-xl"></i>
                                                <span class="text-[11px] font-bold">Después</span>
                                            </span>
                                        </template>
                                    </div>
                                    <div class="px-2 py-2 flex items-start gap-1.5">
                                        <span class="shrink-0 w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center"
                                            :class="fotoDeVista(v.clave) ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600'">
                                            <i x-show="fotoDeVista(v.clave)" class="fa-solid fa-check"></i>
                                            <span x-show="!fotoDeVista(v.clave)" x-text="i + 1"></span>
                                        </span>
                                        <span class="text-xs font-bold text-slate-800 leading-tight" x-text="v.etiqueta"></span>
                                    </div>
                                    <input type="file" accept="image/*" capture="environment" class="hidden" :disabled="!vistaHabilitada(i) || procesandoFoto"
                                        @change="vistaHabilitada(i) && tomarFoto($event, { tipo: 'recorrido', vista: v.clave, unidad: v.unidad })">
                                </label>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- ---------- PASO 10: RESUMEN Y FIRMAS ---------- --}}
                <template x-if="borrador.paso === 10">
                    <div class="space-y-4">
                        @php
                        $bloque = 'bg-white border border-slate-200 rounded-2xl p-4 space-y-1 text-sm';
                        $cabecera = 'flex items-center justify-between gap-2 mb-1';
                        $editar = 'text-xs font-bold text-blue-700 px-2 py-1 rounded-lg hover:bg-blue-50';
                        @endphp

                        <div class="{{ $bloque }}">
                            <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Operador</h3><button type="button" @click="editar(1)" class="{{ $editar }}">Editar</button></div>
                            <p><span x-text="borrador.tipo_movimiento === 'entrada' ? 'Entrada' : 'Salida'"></span> · <span x-text="fechaCorta(borrador.fecha_hora)"></span></p>
                            <p class="font-bold" x-text="operador?.nombre"></p>
                            <p x-show="operadorConVencidos" class="font-bold text-rose-700"><i class="fa-solid fa-triangle-exclamation"></i> Documentos vencidos</p>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="{{ $bloque }}">
                                <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Caja</h3><button type="button" @click="editar(2)" class="{{ $editar }}">Editar</button></div>
                                <p class="font-bold" x-text="etiquetaCaja"></p>
                                <p x-text="'Dueño: ' + duenoCaja"></p>
                                <p x-show="borrador.caja.modo === 'nueva'" class="text-xs font-bold text-blue-700">Se registrará como caja nueva</p>
                            </div>
                            <div class="{{ $bloque }}">
                                <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Tracto</h3><button type="button" @click="editar(3)" class="{{ $editar }}">Editar</button></div>
                                <p class="font-bold" x-text="etiquetaTracto"></p>
                                <p x-show="lineaTracto" x-text="'Línea: ' + lineaTracto"></p>
                                <p x-show="borrador.odometro" x-text="'Odómetro: ' + borrador.odometro"></p>
                            </div>
                        </div>

                        <div class="{{ $bloque }}">
                            <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Viaje</h3><button type="button" @click="editar(4)" class="{{ $editar }}">Editar</button></div>
                            <p><span x-text="borrador.estado_carga === 'cargada' ? 'Cargada' : 'Vacía'"></span> · <span class="uppercase" x-text="borrador.tipo_intercambio"></span></p>
                            <p x-text="'Cliente: ' + clienteViaje"></p>
                            <p x-show="lugar('origen') || lugar('destino')" x-text="(lugar('origen') || '—') + ' → ' + (lugar('destino') || '—')"></p>
                            <p x-show="borrador.referencia_carta_porte" x-text="'Carta porte: ' + borrador.referencia_carta_porte"></p>
                        </div>

                        <div x-show="aplicaSello" class="{{ $bloque }}">
                            <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Sello</h3><button type="button" @click="editar(5)" class="{{ $editar }}">Editar</button></div>
                            <p class="font-bold" x-text="borrador.numero_sello"></p>
                            <div class="flex gap-2 pt-1">
                                <template x-for="foto in fotosDe({ tipo: 'sello' })" :key="foto.uuid">
                                    <img :src="urlFoto(foto)" alt="Foto del sello" class="w-16 h-16 rounded-lg object-cover border border-slate-200">
                                </template>
                            </div>
                        </div>

                        <div class="{{ $bloque }}">
                            <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Llantas</h3><button type="button" @click="editar(6)" class="{{ $editar }}">Editar</button></div>
                            <p x-show="!llantasConAlerta.length" class="text-emerald-700 font-semibold">Todas en buen estado.</p>
                            <template x-for="p in llantasConAlerta" :key="p">
                                <p :class="colorLlanta(p) === 'rojo' ? 'text-rose-700 font-bold' : 'text-amber-700 font-semibold'"
                                    x-text="'Llanta ' + p + ' (' + descripcionLlanta(p).toLowerCase() + '): ' + borrador.llantas[p].profundidad_32 + '/32' + (colorLlanta(p) === 'rojo' ? ' · bajo el mínimo legal' : ' · alerta')"></p>
                            </template>
                            <p x-show="llantasCapturadas < 18" class="text-slate-500"
                                x-text="(18 - llantasCapturadas) + (18 - llantasCapturadas === 1 ? ' llanta sin medir se guarda' : ' llantas sin medir se guardan') + ' (y los datos que falten)' + ' con ' + llantaPorDefecto.profundidad_32 + '/32 y ' + llantaPorDefecto.psi + ' PSI.'"></p>
                        </div>

                        <div class="{{ $bloque }}">
                            <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Revisión C-TPAT</h3><button type="button" @click="editar(7)" class="{{ $editar }}">Editar</button></div>
                            <p x-show="!hallazgos.length" class="text-emerald-700 font-semibold">Sin hallazgos.</p>
                            <template x-for="punto in hallazgos" :key="punto.id">
                                <div class="rounded-xl bg-rose-50 border border-rose-200 p-2">
                                    <p class="font-bold text-rose-800" x-text="punto.nombre + ': ' + (borrador.revision[punto.id].resultado === 'detectado' ? 'Detectado' : 'No cumple')"></p>
                                    <p class="text-slate-700" x-text="borrador.revision[punto.id].observaciones"></p>
                                    <div class="flex gap-2 pt-1">
                                        <template x-for="foto in fotosDe({ tipo: 'punto', punto_inspeccion_id: punto.id })" :key="foto.uuid">
                                            <img :src="urlFoto(foto)" alt="Evidencia" class="w-14 h-14 rounded-lg object-cover border border-slate-200">
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="{{ $bloque }}">
                            <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Daños</h3><button type="button" @click="editar(8)" class="{{ $editar }}">Editar</button></div>
                            <p x-show="!tieneDanos('caja') && !tieneDanos('tracto')" class="text-emerald-700 font-semibold">Sin daños en la caja ni en el tracto.</p>
                            <p x-show="tieneDanos('caja')" class="text-rose-800"><span class="font-bold">Caja: </span><span x-text="borrador.comentarios_caja"></span></p>
                            <p x-show="danosMarcados.length" class="text-rose-800 text-xs font-semibold"
                                x-text="danosMarcados.map((p) => p.nombre + ': ' + { golpe: 'G', corte: 'C', agujero: 'A' }[borrador.revision[p.id].resultado]).join(' · ')"></p>
                            <p x-show="tieneDanos('tracto')" class="text-rose-800"><span class="font-bold">Tracto: </span><span x-text="borrador.comentarios_tracto"></span></p>
                            <p x-show="borrador.observaciones"><span class="font-bold">Observaciones: </span><span x-text="borrador.observaciones"></span></p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <template x-for="foto in fotosDe({ tipo: 'problema' })" :key="foto.uuid">
                                    <img :src="urlFoto(foto)" alt="Daño" class="w-14 h-14 rounded-lg object-cover border border-slate-200">
                                </template>
                            </div>
                        </div>

                        <div class="{{ $bloque }}">
                            <div class="{{ $cabecera }}"><h3 class="font-black text-slate-900">Fotos de la unidad</h3><button type="button" @click="editar(9)" class="{{ $editar }}">Editar</button></div>
                            <p class="font-semibold" :class="recorridoTomadas === vistasRecorrido.length ? 'text-emerald-700' : 'text-rose-700'"
                                x-text="recorridoTomadas + ' de ' + vistasRecorrido.length + ' fotos tomadas'"></p>
                            <div class="grid grid-cols-6 gap-1 pt-1">
                                <template x-for="v in vistasRecorrido.filter((v) => fotoDeVista(v.clave))" :key="v.clave">
                                    <img :src="urlFoto(fotoDeVista(v.clave))" :alt="v.etiqueta" :title="v.etiqueta" class="w-full aspect-square rounded-md object-cover border border-slate-200">
                                </template>
                            </div>
                        </div>

                        {{-- Firmas --}}
                        <div class="grid sm:grid-cols-2 gap-4">
                            @foreach (['firma_operador' => 'Firma del operador', 'firma_guardia' => 'Firma del guardia de caseta'] as $campoFirma => $tituloFirma)
                            <div class="{{ $tarjeta }} space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-black text-slate-900">{{ $tituloFirma }}</span>
                                    <button type="button" @click="limpiarFirma('{{ $campoFirma }}')" class="text-xs font-bold text-slate-600 px-2 py-1 rounded-lg border border-slate-300">Limpiar</button>
                                </div>
                                <div class="relative rounded-xl border-2 border-dashed border-slate-300 overflow-hidden">
                                    <canvas x-ref="{{ $campoFirma }}" class="w-full h-40 block touch-none bg-white" aria-label="{{ $tituloFirma }}"></canvas>
                                    <span x-show="!borrador.{{ $campoFirma }}" class="absolute inset-0 flex items-center justify-center text-xs text-slate-400 pointer-events-none">Firme aquí con el dedo</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </template>

                {{-- Botones --}}
                <div class="sticky bottom-0 -mx-2 sm:mx-0 px-3 sm:px-0 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] bg-gradient-to-t from-slate-100 via-slate-100 to-slate-100/0 flex gap-2">
                    <button type="button" @click="anterior()" x-show="indicePaso > 0"
                        class="px-5 py-4 rounded-2xl bg-white border border-slate-300 text-slate-700 font-bold">
                        <i class="fa-solid fa-chevron-left"></i> <span class="hidden sm:inline">Anterior</span>
                    </button>
                    <button type="button" x-show="borrador.paso !== PASOS.length" @click="siguiente()"
                        class="flex-1 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black shadow-sm">
                        Guardar y continuar <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button type="button" x-show="borrador.paso === PASOS.length" @click="completar()"
                        class="flex-1 py-4 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black shadow-sm">
                        <i class="fa-solid fa-check"></i> Completar intercambio
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Ventana de una llanta --}}
    <template x-if="llantaAbierta && borrador">
        <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="llanta-titulo"
            @keydown.escape.window="llantaAbierta = null">
            <div class="absolute inset-0 bg-slate-900/60" @click="llantaAbierta = null"></div>
            <div class="relative w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl p-5 space-y-4 max-h-[92dvh] overflow-y-auto pb-[calc(1.25rem+env(safe-area-inset-bottom))]">
                {{-- Qué llanta es: dibujo pequeño con la llanta resaltada y su ubicación --}}
                <div class="flex items-center gap-4">
                    <div class="w-24 shrink-0 rounded-xl bg-slate-50 border border-slate-200 p-1">
                        @include('admin.intercambio.partials.diagrama-llantas', ['mini' => true])
                    </div>
                    <div class="min-w-0 space-y-1">
                        <h3 id="llanta-titulo" class="text-2xl font-black text-slate-900" x-text="'Llanta ' + llantaAbierta"></h3>
                        <p class="text-sm font-bold text-blue-700" x-text="ubicacionLlanta(llantaAbierta).unidad + ' · ' + ubicacionLlanta(llantaAbierta).eje"></p>
                        <p class="text-sm text-slate-600" x-text="ubicacionLlanta(llantaAbierta).lado"></p>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-black border-2" x-data="{ get p() { return llantaAbierta } }" :class="{{ $coloresLlanta }}"
                            x-text="{ rojo: 'Bajo el mínimo legal', amarillo: 'Alerta', verde: 'Bien' }[colorLlanta(llantaAbierta)] || 'Sin medir'"></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $etiqueta }}">Profundidad (/32)</label>
                        <input type="number" inputmode="numeric" min="0" max="32" x-model="borrador.llantas[llantaAbierta].profundidad_32"
                            :placeholder="llantaPorDefecto.profundidad_32" class="{{ $campo }} text-center text-xl font-black placeholder:font-normal">
                    </div>
                    <div>
                        <label class="{{ $etiqueta }}">PSI</label>
                        <input type="number" inputmode="numeric" min="0" :max="config.llantas?.psi_maximo ?? 150" x-model="borrador.llantas[llantaAbierta].psi"
                            :placeholder="llantaPorDefecto.psi" class="{{ $campo }} text-center text-xl font-black placeholder:font-normal">
                    </div>
                </div>
                <p class="text-xs text-slate-500 -mt-2">Si lo dejas vacío se guarda con el valor gris.</p>
                <div>
                    <label class="{{ $etiqueta }}">Marca</label>
                    <input type="text" x-model="borrador.llantas[llantaAbierta].marca" maxlength="50" class="{{ $campo }}">
                </div>
                <div>
                    <span class="{{ $etiqueta }}">Tipo</span>
                    <div class="flex gap-2">
                        <template x-for="[valor, titulo] in [['original', 'Original'], ['renovada', 'Renovada']]" :key="valor">
                            <button type="button" @click="borrador.llantas[llantaAbierta].tipo = borrador.llantas[llantaAbierta].tipo === valor ? '' : valor"
                                class="flex-1 rounded-xl border-2 py-3 text-sm font-bold"
                                :class="borrador.llantas[llantaAbierta].tipo === valor ? '{{ $opcionActiva }}' : '{{ $opcionInactiva }}'" x-text="titulo"></button>
                        </template>
                    </div>
                </div>
                <div>
                    <label class="{{ $etiqueta }}">Observaciones</label>
                    <input type="text" x-model="borrador.llantas[llantaAbierta].observaciones" maxlength="255" class="{{ $campo }}">
                </div>
                @include('admin.intercambio.partials.fotos', [
                    'filtro' => "{ tipo: 'llanta', posicion: llantaAbierta }",
                    'meta' => "{ tipo: 'llanta', posicion: llantaAbierta }",
                    'etiqueta' => 'Foto (opcional)',
                ])
                <div class="flex gap-2 pt-1">
                    <button type="button" @click="llantaAbierta = null" class="flex-1 py-3.5 rounded-2xl border border-slate-300 font-bold text-slate-700">Cerrar</button>
                    <button type="button" @click="siguienteLlanta()" class="flex-1 py-3.5 rounded-2xl bg-blue-600 text-white font-black">
                        <span x-text="llantaAbierta < 18 ? 'Siguiente llanta' : 'Listo'"></span> <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- Aviso flotante --}}
    <div x-show="avisoFlotante || $store.cola.aviso" x-transition.opacity role="status"
        class="fixed left-1/2 -translate-x-1/2 bottom-6 z-[80] max-w-sm w-[calc(100%-2rem)] bg-slate-900 text-white text-sm font-bold px-4 py-3 rounded-2xl shadow-lg flex items-center gap-2">
        <i class="fa-solid fa-circle-info"></i>
        <span x-text="avisoFlotante || $store.cola.aviso"></span>
    </div>
</div>
@endsection
