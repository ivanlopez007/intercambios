@extends('caseta.layout.layout')

@section('title', 'Registro de Entrada / Intercambio CTPAT')

@section('contenido')
<div class="max-w-5xl mx-auto py-8 px-4" x-data="intercambioForm()" x-init="init()">

    <!-- ENCABEZADO -->
    <div class="mb-8 text-center">
        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold rounded-full uppercase tracking-wider">Seguridad & Logística</span>
        <h1 class="text-3xl font-extrabold text-slate-900 mt-2">Inspección de Intercambio CTPAT</h1>
        <p class="text-sm text-slate-500 mt-1">Control de acceso, condición física de unidad y verificación agrícola</p>
    </div>

    <!-- PASOS DE NAVEGACIÓN -->
    <div class="mb-8">
        <div class="flex items-center justify-between relative">
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-slate-200 -z-10 rounded-full"></div>
            <div class="absolute left-0 top-1/2 transform -translate-y-1/2 h-1 bg-indigo-600 transition-all duration-300 -z-10 rounded-full"
                :style="'width: ' + ((step - 1) / 4 * 100) + '%'"></div>

            <template x-for="(stepName, index) in steps" :key="index">
                <div class="flex flex-col items-center">
                    <button type="button"
                        @click="if(index + 1 < step) step = index + 1"
                        class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all shadow-sm"
                        :class="step > index + 1 ? 'bg-indigo-600 text-white cursor-pointer' : (step === index + 1 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-slate-200 text-slate-500 cursor-not-allowed')">
                        <span x-show="step <= index + 1" x-text="index + 1"></span>
                        <svg x-show="step > index + 1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </button>
                    <span class="text-xs font-semibold mt-2 text-slate-600 hidden sm:block" x-text="stepName"></span>
                </div>
            </template>
        </div>
    </div>

    <!-- FORMULARIO PRINCIPAL -->
    <form action="#" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-xl border border-slate-100 p-6 sm:p-8">
        @csrf

        <!-- PASO 1: DATOS OPERATIVOS -->
        <div x-show="step === 1" x-transition.opacity>
            <div class="border-b border-slate-100 pb-4 mb-6">
                <h2 class="text-lg font-bold text-slate-900">1. Datos Operativos y del Viaje</h2>
                <p class="text-xs text-slate-500">Ingrese la información general sobre el movimiento y ruta de la unidad.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tipo de Movimiento</label>
                    <select name="tipo_movimiento" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="import">Importación</option>
                        <option value="export">Exportación</option>
                        <option value="local">Local</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Estado de la Caja</label>
                    <select name="estado_caja" x-model="estadoCaja" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        <option value="cargada">Cargada</option>
                        <option value="vacia">Vacía</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Cliente</label>
                    <input type="text" name="cliente" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Razón social del cliente">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Referencia / Carta Porte</label>
                    <input type="text" name="carta_porte" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Folio o referencia de envío">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Origen</label>
                    <input type="text" name="origen" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Ciudad o planta origen">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Destino</label>
                    <input type="text" name="destino" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Ciudad o destino final">
                </div>
            </div>
        </div>

        <!-- PASO 2: DATOS DE UNIDAD, OPERADOR Y SELLOS -->
        <div x-show="step === 2" x-transition.opacity x-cloak>
            <div class="border-b border-slate-100 pb-4 mb-6">
                <h2 class="text-lg font-bold text-slate-900">2. Identificación de Unidad, Operador y Sellos</h2>
                <p class="text-xs text-slate-500">Registre la información del tractocamión, remolque y sellos de seguridad ISO 17712.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Línea Transportista</label>
                    <input type="text" name="linea_transporte" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Código SCAC / CAAT</label>
                    <input type="text" name="scac_caat" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Económico Tractor</label>
                    <input type="text" name="eco_tractor" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Marca Remolque</label>
                    <input type="text" name="marca_remolque" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Placas Remolque</label>
                    <input type="text" name="placas_remolque" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Serie / VIN Remolque</label>
                    <input type="text" name="vin_remolque" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
            </div>

            <!-- SECCIÓN DE OPERADOR -->
            <div class="mb-6 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nombre del Operador</label>
                <input type="text" name="operador_nombre" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-white" placeholder="Nombre completo según identificación">
            </div>

            <!-- SECCIÓN DE VERIFICACIÓN DE SELLOS ISO 17712 -->
            <div class="p-5 bg-amber-50/60 rounded-2xl border border-amber-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-amber-900 uppercase tracking-wide">Control de Sellos de Seguridad (ISO 17712)</h3>
                        <p class="text-xs text-amber-800/80">Indique si la unidad porta sellos de seguridad al ingresar.</p>
                    </div>

                    <!-- SELECTOR / TOGGLE: VIENE CON SELLOS -->
                    <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl border border-amber-200 shadow-sm self-start sm:self-auto">
                        <label class="text-xs font-bold text-slate-700 cursor-pointer" for="tiene_sellos_select">¿Trae sellos?</label>
                        <select id="tiene_sellos_select" name="tiene_sellos" x-model="tieneSellos" class="text-xs border-0 py-0.5 pl-2 pr-7 font-bold text-amber-900 focus:ring-0 cursor-pointer">
                            <option value="si">Sí</option>
                            <option value="no">No</option>
                        </select>
                    </div>
                </div>

                <!-- DETALLE DE SELLOS (SOLO SE MUESTRA SI VIENE CON SELLOS) -->
                <div x-show="tieneSellos === 'si'" x-transition.opacity class="pt-2 border-t border-amber-200/60">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Folio / Número Sello Principal</label>
                            <input type="text" name="numero_sello_1" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Prueba VVTT (Ver, Verificar, Jalar, Girar)</label>
                            <select name="prueba_vvtt" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm bg-white">
                                <option value="si">Sí - Inspección VVTT Válida</option>
                                <option value="no">No - Anormalidad o Sello Violado</option>
                            </select>
                        </div>
                    </div>

                    <!-- CAPTURA DE FOTOS DE SELLOS -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div class="bg-white p-3 rounded-xl border border-amber-200">
                            <label class="block text-xs font-semibold text-slate-700 mb-2">Foto Sello Principal</label>
                            <input type="file" name="foto_sello_principal" accept="image/*" capture="environment" @change="previewFotoSello($event, 'sello1')" class="text-xs w-full text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200">
                            <div class="mt-2 h-24 rounded-lg border border-dashed border-slate-200 flex items-center justify-center overflow-hidden bg-slate-50">
                                <template x-if="fotoSello1">
                                    <img :src="fotoSello1" class="h-full w-full object-contain">
                                </template>
                                <template x-if="!fotoSello1">
                                    <span class="text-[11px] text-slate-400">Sin captura</span>
                                </template>
                            </div>
                        </div>

                        <div class="bg-white p-3 rounded-xl border border-amber-200">
                            <label class="block text-xs font-semibold text-slate-700 mb-2">Foto Sello Secundario (Opcional)</label>
                            <input type="file" name="foto_sello_secundario" accept="image/*" capture="environment" @change="previewFotoSello($event, 'sello2')" class="text-xs w-full text-slate-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200">
                            <div class="mt-2 h-24 rounded-lg border border-dashed border-slate-200 flex items-center justify-center overflow-hidden bg-slate-50">
                                <template x-if="fotoSello2">
                                    <img :src="fotoSello2" class="h-full w-full object-contain">
                                </template>
                                <template x-if="!fotoSello2">
                                    <span class="text-[11px] text-slate-400">Sin captura</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MENSAJE DE UNIDAD SIN SELLOS -->
                <div x-show="tieneSellos === 'no'" x-transition.opacity class="py-3 px-4 bg-white/80 rounded-xl border border-amber-200 text-xs font-medium text-amber-800 flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>La unidad registrará que ingresó sin sellos colocados (ej. unidad vacía o traslado local sin sello).</span>
                </div>
            </div>
        </div>

        <!-- PASO 3: INSPECCIÓN CTPAT Y AGRÍCOLA -->
        <div x-show="step === 3" x-transition.opacity x-cloak>
            <div class="flex items-center justify-between mb-4 pb-2 border-b">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">3. Inspección CTPAT, Mecánica & Agrícola</h2>
                    <p class="text-xs text-slate-500">Puntos de inspección física en tractocamión y contenedor.</p>
                </div>
                <button type="button" @click="marcarTodoBien()" class="text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100 font-semibold transition">
                    ✓ Aprobar Todos
                </button>
            </div>

            <div class="space-y-2.5 max-h-[55vh] overflow-y-auto pr-1">
                <template x-for="(punto, key) in puntosCtpat" :key="key">
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="text-xs font-semibold text-slate-700" x-text="punto.label"></span>

                        <div class="inline-flex rounded-lg shadow-sm" role="group">
                            <button type="button" @click="punto.val = 'OK'"
                                :class="punto.val === 'OK' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                                class="px-3 py-1 text-xs font-bold rounded-l-lg border border-slate-300 transition">
                                OK
                            </button>
                            <button type="button" @click="punto.val = 'FALLO'"
                                :class="punto.val === 'FALLO' ? 'bg-rose-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                                class="px-3 py-1 text-xs font-bold rounded-r-lg border-t border-b border-r border-slate-300 transition">
                                Fallo
                            </button>
                        </div>
                        <input type="hidden" :name="'ctpat[' + key + ']'" :value="punto.val">
                    </div>
                </template>
            </div>
        </div>

        <!-- PASO 4: EVIDENCIA FOTOGRÁFICA EN ORDEN OBLIGATORIO -->
        <div x-show="step === 4" x-transition.opacity x-cloak>
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h2 class="text-lg font-bold text-slate-900">4. Evidencia Fotográfica Secuencial</h2>
                <p class="text-xs text-slate-500">Capture las fotografías en el orden establecido. Las casillas siguientes se habilitarán gradualmente.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="(foto, index) in camposFotos" :key="index">
                    <div class="border-2 rounded-2xl p-3 transition-all relative"
                        :class="fotosHabilitadas[index] ? 'border-indigo-200 bg-white shadow-sm' : 'border-slate-200 bg-slate-100 opacity-60'">

                        <div class="flex items-center justify-between mb-2">
                            <span class="block text-xs font-bold text-slate-700" x-text="(index + 1) + '. ' + foto.titulo"></span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                :class="fotosPreviews[index] ? 'bg-emerald-100 text-emerald-700' : (fotosHabilitadas[index] ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-500')">
                                <span x-text="fotosPreviews[index] ? 'Capturada' : (fotosHabilitadas[index] ? 'Disponible' : 'Bloqueada')"></span>
                            </span>
                        </div>

                        <input type="file"
                            :name="foto.campo"
                            accept="image/*"
                            capture="environment"
                            :disabled="!fotosHabilitadas[index]"
                            @change="actualizarPreviewFoto(index, $event)"
                            class="text-xs w-full text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 disabled:cursor-not-allowed">

                        <div class="mt-3 h-28 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center overflow-hidden">
                            <template x-if="fotosPreviews[index]">
                                <img :src="fotosPreviews[index]" class="h-full w-full object-cover">
                            </template>
                            <template x-if="!fotosPreviews[index]">
                                <span class="text-[11px] text-slate-400 font-medium">Esperando fotografía</span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- PASO 5: OBSERVACIONES Y FIRMA DIGITAL -->
        <div x-show="step === 5" x-transition.opacity x-cloak>
            <div class="border-b border-slate-100 pb-4 mb-6">
                <h2 class="text-lg font-bold text-slate-900">5. Observaciones y Firma del Operador</h2>
                <p class="text-xs text-slate-500">Cierre del registro de inspección e integración de firma digital.</p>
            </div>

            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Observaciones / Daños Preexistentes</label>
                <textarea name="observaciones" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Detalle cualquier raspadura, golpe o hallazgo menor..."></textarea>
            </div>

            <!-- PANEL DE FIRMA -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                <div class="flex justify-between items-center mb-3">
                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Firma de Conformidad del Operador</label>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="limpiarFirma()" class="text-xs text-rose-600 hover:text-rose-700 font-bold px-2 py-1 rounded bg-rose-50">Borrar</button>
                        <button type="button" @click="abrirModalFirma()" class="text-xs text-indigo-700 font-bold bg-indigo-100 px-3 py-1 rounded-lg border border-indigo-200 hover:bg-indigo-200 transition">Firma Pantalla Completa</button>
                    </div>
                </div>

                <div class="border border-slate-300 bg-white rounded-xl overflow-hidden touch-none relative shadow-inner">
                    <canvas id="signature-pad" class="w-full h-40 cursor-crosshair block"></canvas>
                    <div id="firma-placeholder" class="absolute inset-0 flex items-center justify-center pointer-events-none text-slate-400 text-xs font-medium">
                        Trace su firma aquí o utilice el botón de Pantalla Completa
                    </div>
                </div>
                <input type="hidden" name="firma_base64" id="firma_input" x-model="firmaBase64">
            </div>
        </div>

        <!-- BARRA DE NAVEGACIÓN DE BOTONES -->
        <div class="mt-8 pt-4 border-t border-slate-100 flex justify-between items-center">
            <button type="button" x-show="step > 1" @click="step--" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold text-sm transition">
                ← Regresar
            </button>
            <div x-show="step === 1"></div>

            <button type="button" x-show="step < 5" @click="step++" class="px-6 py-2.5 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 font-bold text-sm transition shadow-md">
                Continuar →
            </button>

            <button type="submit" x-show="step === 5" @click="prepararEnvio($event)" class="px-6 py-2.5 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 font-bold text-sm transition shadow-md">
                ✓ Guardar e Imprimir
            </button>
        </div>
    </form>
</div>

<!-- MODAL FIRMA PANTALLA COMPLETA -->
<div id="firma-modal" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-sm hidden flex-col p-4">
    <div class="bg-white p-4 rounded-2xl max-w-4xl w-full mx-auto flex flex-col h-full shadow-2xl">
        <div class="flex justify-between items-center mb-3">
            <div>
                <h3 class="text-slate-900 font-extrabold text-base">Firma Digital del Operador</h3>
                <p class="text-xs text-slate-500">Firme utilizando su dedo o stylus sobre el área punteada.</p>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="limpiarFirmaModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-rose-600 rounded-xl font-bold text-xs transition">Limpiar</button>
                <button type="button" @click="guardarFirmaModal()" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs transition shadow-sm">Aceptar Firma</button>
            </div>
        </div>
        <div class="flex-1 border-2 border-dashed border-indigo-300 rounded-2xl bg-slate-50 touch-none overflow-hidden relative">
            <canvas id="signature-modal" class="w-full h-full cursor-crosshair block"></canvas>
        </div>
    </div>
</div>

<script>
    let firmaModalCanvas = null;
    let firmaModalCtx = null;
    let firmaInlineCanvas = null;
    let firmaInlineCtx = null;
    let firmaPlaceholder = null;
    let firmaInlineDrawing = false;
    let firmaModalDrawing = false;

    function inicializarCanvas(canvas, ctx) {
        if (!canvas || !ctx) return;
        const rect = canvas.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);
        ctx.strokeStyle = '#0f172a';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }

    function configurarEventosCanvas(canvas, ctx, esModal, alTrazar) {
        if (!canvas || !ctx) return;

        const obtenerPos = (e) => {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        };

        const iniciar = (e) => {
            if (esModal) firmaModalDrawing = true;
            else firmaInlineDrawing = true;

            const pos = obtenerPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            if (alTrazar) alTrazar();
        };

        const mover = (e) => {
            const activo = esModal ? firmaModalDrawing : firmaInlineDrawing;
            if (!activo) return;
            e.preventDefault();
            const pos = obtenerPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        };

        const detener = () => {
            if (esModal) firmaModalDrawing = false;
            else firmaInlineDrawing = false;
        };

        canvas.addEventListener('mousedown', iniciar);
        canvas.addEventListener('mousemove', mover);
        canvas.addEventListener('mouseup', detener);
        canvas.addEventListener('mouseleave', detener);

        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            iniciar(e);
        }, {
            passive: false
        });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            mover(e);
        }, {
            passive: false
        });
        canvas.addEventListener('touchend', detener);
    }

    function intercambioForm() {
        return {
            step: 1,
            estadoCaja: 'cargada',
            tieneSellos: 'si', // Control para mostrar/ocultar los datos de sellos
            steps: ['Operación', 'Unidad / Sellos', 'CTPAT', 'Fotos', 'Firma'],
            fotoSello1: null,
            fotoSello2: null,
            puntosCtpat: {
                defensa: {
                    label: '1. Defensa del Tractor',
                    val: 'OK'
                },
                motor: {
                    label: '2. Compartimento del Motor',
                    val: 'OK'
                },
                llantas_trc: {
                    label: '3. Llantas y Rines del Tractor',
                    val: 'OK'
                },
                piso_cabina: {
                    label: '4. Piso / Cabina',
                    val: 'OK'
                },
                tanque_diesel: {
                    label: '5. Depósito de Diésel',
                    val: 'OK'
                },
                camarote: {
                    label: '6. Camarote y Literas',
                    val: 'OK'
                },
                tanques_aire: {
                    label: '7. Tanques de Aire',
                    val: 'OK'
                },
                diferenciales: {
                    label: '8. Diferenciales',
                    val: 'OK'
                },
                quinta_rueda: {
                    label: '9. Quinta Rueda y Perno Rey',
                    val: 'OK'
                },
                cargadores: {
                    label: '10. Cargadores y Chasis',
                    val: 'OK'
                },
                piso_interior: {
                    label: '11. Piso Interior / Exterior Caja',
                    val: 'OK'
                },
                puertas_mecanismos: {
                    label: '12. Puertas y Bisagras',
                    val: 'OK'
                },
                paredes_laterales: {
                    label: '13. Paredes Laterales',
                    val: 'OK'
                },
                techo_caja: {
                    label: '14. Techo (Sin dobles fondos)',
                    val: 'OK'
                },
                escape: {
                    label: '15. Tubo de Escape',
                    val: 'OK'
                },
                pared_delantera: {
                    label: '16. Pared Delantera / Thermo',
                    val: 'OK'
                },
                frenos_fugas: {
                    label: '17. Frenos / Fugas de Aire',
                    val: 'OK'
                },
                luces_reflectores: {
                    label: '18. Luces y Reflectores',
                    val: 'OK'
                },
                conectores: {
                    label: '19. Conectores y Cableado',
                    val: 'OK'
                },
                olores: {
                    label: '20. Olores (Sin contaminantes)',
                    val: 'OK'
                },
                residuos_agricola: {
                    label: '21. Inspección Agrícola (Plagas/Tierra)',
                    val: 'OK'
                },
            },
            camposFotos: [{
                    titulo: 'Tractor Frontal',
                    campo: 'foto_trc_frontal'
                },
                {
                    titulo: 'Tractor Llanta Frontal Izquierda',
                    campo: 'foto_trc_llanta_front_izq'
                },
                {
                    titulo: 'Tractor Lado Izquierdo',
                    campo: 'foto_trc_lado_izq'
                },
                {
                    titulo: 'Tractor Llantas Traseras Izquierdas',
                    campo: 'foto_trc_llantas_izq_trasera'
                },
                {
                    titulo: 'Caja Frontal',
                    campo: 'foto_caja_frontal'
                },
                {
                    titulo: 'Caja Izquierda Frontal',
                    campo: 'foto_caja_izq_frontal'
                },
                {
                    titulo: 'Caja Izquierda Trasera',
                    campo: 'foto_caja_izq_trasera'
                },
                {
                    titulo: 'Caja Llantas Izquierda Trasera',
                    campo: 'foto_caja_llantas_izq_tras'
                },
                {
                    titulo: 'Caja Puertas Traseras',
                    campo: 'foto_caja_trasera'
                },
                {
                    titulo: 'Caja Derecha Trasera',
                    campo: 'foto_caja_der_trasera'
                },
                {
                    titulo: 'Caja Llantas Derecha Trasera',
                    campo: 'foto_caja_llantas_der_tras'
                },
                {
                    titulo: 'Caja Frontal Derecha',
                    campo: 'foto_caja_front_derecha'
                }
            ],
            fotosPreviews: [],
            fotosHabilitadas: [],
            firmaBase64: '',
            init() {
                this.fotosPreviews = Array(this.camposFotos.length).fill(null);
                this.fotosHabilitadas = this.camposFotos.map((_, i) => i === 0);

                // Auto deshabilitar sellos si la caja se cambia a Vacía
                this.$watch('estadoCaja', (val) => {
                    if (val === 'vacia') {
                        this.tieneSellos = 'no';
                    } else {
                        this.tieneSellos = 'si';
                    }
                });

                this.$nextTick(() => {
                    firmaPlaceholder = document.getElementById('firma-placeholder');
                    firmaInlineCanvas = document.getElementById('signature-pad');
                    firmaModalCanvas = document.getElementById('signature-modal');

                    if (firmaInlineCanvas) {
                        firmaInlineCtx = firmaInlineCanvas.getContext('2d');
                        inicializarCanvas(firmaInlineCanvas, firmaInlineCtx);
                        configurarEventosCanvas(firmaInlineCanvas, firmaInlineCtx, false, () => {
                            if (firmaPlaceholder) firmaPlaceholder.classList.add('hidden');
                        });
                    }

                    if (firmaModalCanvas) {
                        firmaModalCtx = firmaModalCanvas.getContext('2d');
                    }
                });

                this.$watch('step', (val) => {
                    if (val === 5) {
                        this.$nextTick(() => {
                            if (firmaInlineCanvas && firmaInlineCtx) {
                                inicializarCanvas(firmaInlineCanvas, firmaInlineCtx);
                            }
                        });
                    }
                });
            },
            marcarTodoBien() {
                Object.keys(this.puntosCtpat).forEach(k => this.puntosCtpat[k].val = 'OK');
            },
            actualizarPreviewFoto(index, event) {
                const file = event.target.files && event.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    this.fotosPreviews[index] = e.target.result;
                    this.fotosHabilitadas = this.camposFotos.map((_, i) => i <= index + 1);
                };
                reader.readAsDataURL(file);
            },
            previewFotoSello(e, tipo) {
                const file = e.target.files && e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (ev) => {
                    if (tipo === 'sello1') this.fotoSello1 = ev.target.result;
                    if (tipo === 'sello2') this.fotoSello2 = ev.target.result;
                };
                reader.readAsDataURL(file);
            },
            abrirModalFirma() {
                const modal = document.getElementById('firma-modal');
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');

                this.$nextTick(() => {
                    if (firmaModalCanvas && firmaModalCtx) {
                        inicializarCanvas(firmaModalCanvas, firmaModalCtx);
                        configurarEventosCanvas(firmaModalCanvas, firmaModalCtx, true, null);
                    }
                });
            },
            guardarFirmaModal() {
                const modal = document.getElementById('firma-modal');
                if (!modal || !firmaModalCanvas || !firmaInlineCanvas || !firmaInlineCtx) return;

                this.firmaBase64 = firmaModalCanvas.toDataURL();
                document.getElementById('firma_input').value = this.firmaBase64;

                const img = new Image();
                img.onload = () => {
                    firmaInlineCtx.clearRect(0, 0, firmaInlineCanvas.width, firmaInlineCanvas.height);
                    const rect = firmaInlineCanvas.getBoundingClientRect();
                    firmaInlineCtx.drawImage(img, 0, 0, rect.width, rect.height);
                    if (firmaPlaceholder) firmaPlaceholder.classList.add('hidden');
                };
                img.src = this.firmaBase64;

                modal.classList.add('hidden');
                modal.classList.remove('flex');
            },
            limpiarFirma() {
                if (firmaInlineCanvas && firmaInlineCtx) {
                    const rect = firmaInlineCanvas.getBoundingClientRect();
                    firmaInlineCtx.clearRect(0, 0, rect.width, rect.height);
                }
                if (firmaPlaceholder) firmaPlaceholder.classList.remove('hidden');
                this.firmaBase64 = '';
                document.getElementById('firma_input').value = '';
            },
            limpiarFirmaModal() {
                if (firmaModalCanvas && firmaModalCtx) {
                    const rect = firmaModalCanvas.getBoundingClientRect();
                    firmaModalCtx.clearRect(0, 0, rect.width, rect.height);
                }
            },
            prepararEnvio(e) {
                if (firmaInlineCanvas) {
                    this.firmaBase64 = firmaInlineCanvas.toDataURL();
                }
                if (!this.firmaBase64 || this.firmaBase64.length < 100) {
                    e.preventDefault();
                    alert('La firma del operador es obligatoria para finalizar.');
                    return;
                }
                document.getElementById('firma_input').value = this.firmaBase64;
            }
        }
    }
</script>
@endsection