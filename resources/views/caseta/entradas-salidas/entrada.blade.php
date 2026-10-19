@extends('caseta.layout.layout')

@section('title', 'Registro de Entrada')
@section('page-title', 'Entrada de Visitantes')

@section('contenido')
<div class="max-w-xl mx-auto py-3 sm:py-6 px-1 sm:px-2">

    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-7 shadow-sm space-y-5">

        <!-- Aviso de errores (se desplaza a la vista automáticamente) -->
        <div id="feedback-alert" class="hidden" aria-live="assertive"></div>

        <form id="form-entrada" onsubmit="event.preventDefault(); registrarEntrada();" class="space-y-5" novalidate>
            @csrf

            <!-- 1. Empresa -->
            <fieldset class="bg-slate-50 p-3 sm:p-4 rounded-2xl border border-slate-200 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <legend class="text-xs font-bold text-slate-500 uppercase tracking-wider">1. Empresa (QR o nombre)</legend>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" id="empresa_nueva" onchange="toggleEmpresaNueva()"
                            class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600">
                        Empresa nueva
                    </label>
                </div>

                <div id="grupo-codigo-qr" class="flex gap-2">
                    <x-selector-empresa :permitir-nueva="true" />
                    <button type="button" id="btn-escaneo" onclick="escaner.alternar()"
                        class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold px-4 py-3.5 rounded-xl transition flex items-center gap-2 shrink-0 shadow-sm"
                        aria-label="Escanear QR con la cámara">
                        <i id="btn-escaneo-icono" class="fa-solid fa-camera text-lg"></i>
                        <span id="btn-escaneo-text" class="hidden sm:inline">Escanear</span>
                    </button>
                </div>
                <div id="reader" class="hidden w-full rounded-xl overflow-hidden bg-black"></div>

                <div id="grupo-nombre-empresa" class="hidden">
                    <label for="nombre_empresa" class="sr-only">Nombre de la nueva empresa</label>
                    <input type="text" id="nombre_empresa" name="nombre_empresa" placeholder="Nombre de la nueva empresa"
                        autocomplete="off" autocapitalize="words" enterkeyhint="next"
                        class="w-full px-4 py-3.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-base shadow-sm">
                    <p class="mt-1.5 text-xs text-slate-500">
                        <i class="fa-solid fa-circle-info mr-1"></i>La empresa se dará de alta al registrar la entrada.
                    </p>
                </div>
            </fieldset>

            <!-- 2. Visitante -->
            <div class="space-y-3">
                <div>
                    <label for="nombre_persona" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">2. ¿Quién entra?</label>
                    <input type="text" id="nombre_persona" name="nombre_persona" placeholder="Nombre completo del visitante" required
                        autocomplete="off" autocapitalize="words" enterkeyhint="next" maxlength="255"
                        class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-base shadow-sm">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="persona_dirigida" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">¿A quién visita?</label>
                        <input type="text" id="persona_dirigida" name="persona_dirigida" placeholder="Persona o área" required
                            autocomplete="off" autocapitalize="words" enterkeyhint="next" maxlength="255"
                            class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-base shadow-sm">
                    </div>
                    <div>
                        <label for="motivo" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Motivo</label>
                        <input type="text" id="motivo" name="motivo" placeholder="Ej. Junta, Entrega, Trabajo" required
                            autocomplete="off" autocapitalize="sentences" enterkeyhint="done" maxlength="255"
                            class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-base shadow-sm">
                    </div>
                </div>
            </div>

            <!-- 3. Fotos del vehículo (opcionales) -->
            <fieldset class="bg-slate-50 p-3 rounded-2xl border border-slate-200 space-y-2">
                <legend class="sr-only">Fotos del vehículo</legend>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    3. Fotos del vehículo <span class="normal-case font-semibold text-slate-400">(opcional)</span>
                </p>

                <div class="grid grid-cols-2 gap-3">
                    @foreach (['placas' => ['Placas', 'fa-rectangle-list'], 'vehiculo' => ['Vehículo', 'fa-car-side']] as $tipo => [$etiqueta, $icono])
                    <div>
                        <input type="file" id="foto-{{ $tipo }}" accept="image/*" capture="environment" class="hidden"
                            onchange="seleccionarFoto('{{ $tipo }}', this)">

                        <!-- Sin foto: botón para abrir la cámara -->
                        <label for="foto-{{ $tipo }}" id="foto-{{ $tipo }}-vacio"
                            class="flex flex-col items-center justify-center gap-1.5 h-28 sm:h-32 rounded-xl border-2 border-dashed border-slate-300 bg-white text-slate-500 hover:border-blue-400 hover:text-blue-600 active:bg-blue-50 cursor-pointer transition">
                            <i class="fa-solid {{ $icono }} text-2xl"></i>
                            <span class="text-sm font-bold">{{ $etiqueta }}</span>
                            <span class="text-[11px]"><i class="fa-solid fa-camera"></i> Tomar foto</span>
                        </label>

                        <!-- Con foto: vista previa -->
                        <div id="foto-{{ $tipo }}-lista" class="hidden relative h-28 sm:h-32 rounded-xl overflow-hidden border border-slate-300 bg-white">
                            <img id="foto-{{ $tipo }}-img" alt="Foto de {{ mb_strtolower($etiqueta) }}" class="w-full h-full object-cover">
                            <span class="absolute top-1.5 left-1.5 text-[11px] font-bold bg-slate-900/70 text-white px-2 py-0.5 rounded-md">{{ $etiqueta }}</span>
                            <div class="absolute bottom-1.5 inset-x-1.5 flex gap-1.5">
                                <label for="foto-{{ $tipo }}" class="flex-1 text-center text-xs font-bold bg-white/90 text-slate-800 py-1.5 rounded-lg cursor-pointer">
                                    <i class="fa-solid fa-rotate"></i> Cambiar
                                </label>
                                <button type="button" onclick="quitarFoto('{{ $tipo }}')" class="px-2.5 text-xs font-bold bg-rose-600/90 text-white py-1.5 rounded-lg" aria-label="Quitar foto de {{ mb_strtolower($etiqueta) }}">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>

                        <p id="foto-{{ $tipo }}-procesando" class="hidden mt-1 text-[11px] text-slate-500">
                            <i class="fa-solid fa-circle-notch animate-spin"></i> Preparando foto...
                        </p>
                    </div>
                    @endforeach
                </div>
            </fieldset>

            <!-- 4. Firma -->
            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 space-y-2">
                <div class="flex justify-between items-center gap-2">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">4. Firma</p>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="borrarFirma()" class="px-2.5 py-1.5 text-xs text-rose-600 hover:text-rose-700 font-semibold rounded-lg hover:bg-rose-50">
                            <i class="fa-solid fa-eraser"></i> Borrar
                        </button>
                        <button type="button" onclick="abrirFirmaGrande()" class="text-xs text-blue-700 hover:text-blue-800 font-bold bg-blue-100/80 px-2.5 py-1.5 rounded-lg border border-blue-200">
                            <i class="fa-solid fa-expand mr-1"></i> Firma grande
                        </button>
                    </div>
                </div>

                <div class="border border-slate-300 bg-white rounded-xl overflow-hidden relative shadow-inner">
                    <canvas id="canvas-inline" class="w-full h-36 cursor-crosshair block touch-none" aria-label="Área para firmar"></canvas>
                    <div id="firma-placeholder" class="absolute inset-0 flex items-center justify-center pointer-events-none text-slate-400 text-xs font-medium">
                        Firme aquí o use "Firma grande"
                    </div>
                </div>
            </div>

            <!-- Enviar -->
            <button type="submit" id="btn-submit"
                class="w-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-black py-4 px-4 rounded-2xl transition shadow-md shadow-emerald-200 flex items-center justify-center gap-2 text-lg uppercase tracking-wider disabled:opacity-60">
                <i class="fa-solid fa-circle-check text-xl"></i>
                <span>Registrar entrada</span>
                <i id="btn-submit-spinner" class="fa-solid fa-circle-notch animate-spin hidden"></i>
            </button>
        </form>
    </div>
</div>

<!-- FIRMA GRANDE (pantalla completa) -->
<div id="modal-firma" class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm hidden flex-col p-2 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="modal-firma-titulo">
    <div class="bg-white p-3 sm:p-4 rounded-2xl max-w-3xl w-full mx-auto flex flex-col h-full shadow-2xl">
        <div class="flex justify-between items-center gap-2 mb-3">
            <button type="button" onclick="cerrarFirmaGrande()" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center" aria-label="Cancelar">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <span id="modal-firma-titulo" class="text-slate-900 font-bold">Firme dentro del recuadro</span>
            <div class="flex gap-2">
                <button type="button" onclick="limpiarFirmaGrande()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-rose-600 rounded-xl font-bold text-sm">
                    Limpiar
                </button>
                <button type="button" onclick="aceptarFirmaGrande()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-sm shadow-sm">
                    Aceptar
                </button>
            </div>
        </div>
        <div class="flex-1 border-2 border-dashed border-blue-300 rounded-2xl bg-slate-50 overflow-hidden">
            <canvas id="canvas-modal" class="w-full h-full cursor-crosshair block touch-none" aria-label="Área grande para firmar"></canvas>
        </div>
    </div>
</div>

<x-caseta.modal-resultado />
@endsection

@push('scripts')
<script>
    const mostrarAlerta = (mensaje, tipo) => casetaUI.mostrarAlerta(mensaje, tipo);
    const mostrarModalResultado = (tipo, titulo, mensaje) => casetaUI.mostrarResultado(tipo, titulo, mensaje);

    // --- ESCÁNER QR ---
    // casetaUI viene de un módulo de Vite, que se ejecuta después de este script:
    // por eso el escáner se crea en DOMContentLoaded.
    let escaner = null;
    document.addEventListener('DOMContentLoaded', () => {
        escaner = new casetaUI.Escaner('reader', {
            alLeer: (texto) => window.dispatchEvent(new CustomEvent('empresa-escaneada', { detail: texto })),
            alCambiar: (activo) => {
                document.getElementById('btn-escaneo-text').innerText = activo ? 'Cerrar' : 'Escanear';
                document.getElementById('btn-escaneo-icono').className = `fa-solid ${activo ? 'fa-xmark' : 'fa-camera'} text-lg`;
            },
            alError: (mensaje) => mostrarAlerta(mensaje, 'error'),
        });
        casetaUI.precargarLector();
    });

    // --- FIRMA ---
    // El lienzo usa la densidad real de la pantalla (máx. 2x) para que el trazo se vea nítido,
    // y eventos "pointer" para funcionar igual con dedo, mouse o lápiz.
    let firmaBase64Global = null;
    const canvasInline = document.getElementById('canvas-inline');
    const canvasModal = document.getElementById('canvas-modal');
    const placeholder = document.getElementById('firma-placeholder');

    function prepararLienzo(canvas) {
        const densidad = Math.min(window.devicePixelRatio || 1, 2);
        const area = canvas.getBoundingClientRect();
        canvas.width = Math.round(area.width * densidad);
        canvas.height = Math.round(area.height * densidad);

        const ctx = canvas.getContext('2d');
        ctx.setTransform(densidad, 0, 0, densidad, 0, 0);
        ctx.strokeStyle = '#1e3a8a';
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }

    function habilitarDibujo(canvas, { alEmpezar = () => {}, alTerminar = () => {} } = {}) {
        let dibujando = false;
        const ctx = canvas.getContext('2d');
        const punto = (e) => {
            const area = canvas.getBoundingClientRect();
            return { x: e.clientX - area.left, y: e.clientY - area.top };
        };

        canvas.addEventListener('pointerdown', (e) => {
            dibujando = true;
            canvas.setPointerCapture(e.pointerId);
            const p = punto(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.lineTo(p.x + 0.1, p.y + 0.1); // un toque deja un punto
            ctx.stroke();
            alEmpezar();
        });

        canvas.addEventListener('pointermove', (e) => {
            if (!dibujando) return;
            for (const evento of e.getCoalescedEvents?.() ?? [e]) {
                const p = punto(evento);
                ctx.lineTo(p.x, p.y);
            }
            ctx.stroke();
        });

        const terminar = () => {
            if (!dibujando) return;
            dibujando = false;
            alTerminar();
        };
        canvas.addEventListener('pointerup', terminar);
        canvas.addEventListener('pointercancel', terminar);
    }

    // Dibuja una firma guardada en el lienzo pequeño, conservando su proporción.
    function mostrarFirmaEnRecuadro(dataUrl) {
        const imagen = new Image();
        imagen.onload = () => {
            const ctx = canvasInline.getContext('2d');
            const area = canvasInline.getBoundingClientRect();
            const escala = Math.min(area.width / imagen.width, area.height / imagen.height);
            const ancho = imagen.width * escala;
            const alto = imagen.height * escala;
            ctx.clearRect(0, 0, area.width, area.height);
            ctx.drawImage(imagen, (area.width - ancho) / 2, (area.height - alto) / 2, ancho, alto);
            placeholder.classList.add('hidden');
        };
        imagen.src = dataUrl;
    }

    habilitarDibujo(canvasInline, {
        alEmpezar: () => placeholder.classList.add('hidden'),
        alTerminar: () => (firmaBase64Global = canvasInline.toDataURL('image/png')),
    });
    habilitarDibujo(canvasModal);

    function borrarFirma() {
        prepararLienzo(canvasInline);
        placeholder.classList.remove('hidden');
        firmaBase64Global = null;
    }

    function abrirFirmaGrande() {
        const modal = document.getElementById('modal-firma');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        prepararLienzo(canvasModal);
    }

    function cerrarFirmaGrande() {
        const modal = document.getElementById('modal-firma');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function limpiarFirmaGrande() {
        prepararLienzo(canvasModal);
    }

    function aceptarFirmaGrande() {
        firmaBase64Global = canvasModal.toDataURL('image/png');
        prepararLienzo(canvasInline);
        mostrarFirmaEnRecuadro(firmaBase64Global);
        cerrarFirmaGrande();
    }

    // Al girar el celular o cambiar el tamaño, el recuadro se ajusta sin perder la firma.
    let anchoRecuadro = 0;
    function ajustarRecuadro() {
        const ancho = Math.round(canvasInline.getBoundingClientRect().width);
        if (ancho === anchoRecuadro) return;
        anchoRecuadro = ancho;
        prepararLienzo(canvasInline);
        if (firmaBase64Global) mostrarFirmaEnRecuadro(firmaBase64Global);
    }
    document.addEventListener('DOMContentLoaded', ajustarRecuadro);
    window.addEventListener('load', ajustarRecuadro);
    window.addEventListener('resize', () => requestAnimationFrame(ajustarRecuadro));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarFirmaGrande();
    });

    // --- FOTOS DEL VEHÍCULO (OPCIONALES) ---
    // Se comprimen en el celular (máx. 1280 px, JPEG) para que pesen ~150-300 KB y se envíen
    // rápido aunque la señal sea débil; también se guardan así en la cola sin conexión.
    const fotosEntrada = { placas: null, vehiculo: null };

    function comprimirFoto(archivo, ladoMaximo = 1280, calidad = 0.72) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(archivo);
            const imagen = new Image();

            imagen.onload = () => {
                const escala = Math.min(1, ladoMaximo / Math.max(imagen.naturalWidth, imagen.naturalHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(imagen.naturalWidth * escala);
                canvas.height = Math.round(imagen.naturalHeight * escala);
                canvas.getContext('2d').drawImage(imagen, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                resolve(canvas.toDataURL('image/jpeg', calidad));
            };
            imagen.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('No se pudo leer la imagen'));
            };
            imagen.src = url;
        });
    }

    async function seleccionarFoto(tipo, input) {
        const archivo = input.files && input.files[0];
        if (!archivo) return;

        const procesando = document.getElementById(`foto-${tipo}-procesando`);
        procesando.classList.remove('hidden');

        try {
            fotosEntrada[tipo] = await comprimirFoto(archivo);
            document.getElementById(`foto-${tipo}-img`).src = fotosEntrada[tipo];
            document.getElementById(`foto-${tipo}-vacio`).classList.add('hidden');
            document.getElementById(`foto-${tipo}-lista`).classList.remove('hidden');
        } catch {
            mostrarAlerta("No se pudo procesar la foto. Intenta tomarla de nuevo.", "error");
        } finally {
            procesando.classList.add('hidden');
            input.value = ''; // Permite volver a elegir la misma foto
        }
    }

    function quitarFoto(tipo) {
        fotosEntrada[tipo] = null;
        document.getElementById(`foto-${tipo}-img`).removeAttribute('src');
        document.getElementById(`foto-${tipo}-lista`).classList.add('hidden');
        document.getElementById(`foto-${tipo}-vacio`).classList.remove('hidden');
    }

    // --- EMPRESA NUEVA ---
    function toggleEmpresaNueva() {
        const esNueva = document.getElementById('empresa_nueva').checked;
        const grupoQr = document.getElementById('grupo-codigo-qr');
        const grupoNombreEmpresa = document.getElementById('grupo-nombre-empresa');
        const inputNombreEmpresa = document.getElementById('nombre_empresa');

        if (esNueva) {
            grupoQr.classList.add('hidden');
            grupoNombreEmpresa.classList.remove('hidden');
            escaner?.detener();
            window.dispatchEvent(new CustomEvent('empresa-limpiar'));
            inputNombreEmpresa.focus();
        } else {
            grupoQr.classList.remove('hidden');
            grupoNombreEmpresa.classList.add('hidden');
            inputNombreEmpresa.value = '';
        }
    }

    // El buscador ofrece "Registrar «X» como empresa nueva" cuando el nombre no existe.
    window.addEventListener('empresa-nueva', (evento) => {
        document.getElementById('empresa_nueva').checked = true;
        toggleEmpresaNueva();
        document.getElementById('nombre_empresa').value = evento.detail;
        document.getElementById('nombre_persona').focus();
    });

    // --- VALIDACIÓN ---
    // Revisa en orden y lleva al guardia al primer dato que falta.
    function validarFormulario(esEmpresaNueva) {
        const faltante = (id, mensaje) => {
            mostrarAlerta(mensaje, "error");
            document.getElementById(id)?.focus({ preventScroll: true });
            return false;
        };

        if (esEmpresaNueva && !document.getElementById('nombre_empresa').value.trim()) {
            return faltante('nombre_empresa', "Escribe el nombre de la nueva empresa.");
        }

        if (!esEmpresaNueva && !document.getElementById('codigo_qr').value.trim()) {
            const escrito = document.getElementById('empresa_busqueda').value.trim();
            return faltante('empresa_busqueda', escrito
                ? `No encontramos la empresa «${escrito}». Elígela de la lista o regístrala como empresa nueva.`
                : "Escanea el QR o escribe el nombre de la empresa.");
        }

        const campos = [
            ['nombre_persona', "Escribe el nombre de quien entra."],
            ['persona_dirigida', "Escribe a quién visita."],
            ['motivo', "Escribe el motivo de la visita."],
        ];

        for (const [id, mensaje] of campos) {
            if (!document.getElementById(id).value.trim()) return faltante(id, mensaje);
        }

        if (!firmaBase64Global) {
            mostrarAlerta("Falta la firma del visitante.", "error");
            return false;
        }

        return true;
    }

    // --- ENVÍO ---
    async function registrarEntrada() {
        const esEmpresaNueva = document.getElementById('empresa_nueva').checked;
        if (!validarFormulario(esEmpresaNueva)) return;

        const btn = document.getElementById('btn-submit');
        if (btn.disabled) return; // Evita registros dobles por toques repetidos

        const spinner = document.getElementById('btn-submit-spinner');

        const payload = {
            empresa_nueva: esEmpresaNueva,
            nombre_persona: document.getElementById('nombre_persona').value.trim(),
            persona_dirigida: document.getElementById('persona_dirigida').value.trim(),
            motivo: document.getElementById('motivo').value.trim(),
            firma: firmaBase64Global
        };

        if (fotosEntrada.placas) payload.foto_placas = fotosEntrada.placas;
        if (fotosEntrada.vehiculo) payload.foto_vehiculo = fotosEntrada.vehiculo;

        if (esEmpresaNueva) {
            payload.nombre_empresa = document.getElementById('nombre_empresa').value.trim();
        } else {
            payload.codigo_qr = document.getElementById('codigo_qr').value;
            // Solo para mostrar en el celular (lista de personas dentro y pendientes); el servidor lo ignora.
            payload.empresa_nombre = document.getElementById('empresa_busqueda').value.trim();
        }

        // El mismo id y hora se usan si el registro termina guardándose sin conexión,
        // para que el servidor no lo duplique si el primer intento sí había llegado.
        const idRegistro = window.colaOffline.nuevoId();
        const creadoEn = new Date().toISOString();

        if (!navigator.onLine) {
            await guardarSinConexion(payload, idRegistro, creadoEn);
            return;
        }

        btn.disabled = true;
        spinner.classList.remove('hidden');

        try {
            const response = await fetch("{{ route('caseta.visitas.entrada') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('input[name="_token"]').value,
                    "Accept": "application/json"
                },
                body: JSON.stringify({ ...payload, id_cliente: idRegistro, registrado_en: creadoEn })
            });

            // Sesión expirada o servidor/túnel caído: se guarda para enviarlo después.
            if ([401, 419, 502, 503, 504].includes(response.status)) {
                await guardarSinConexion(payload, idRegistro, creadoEn);
                return;
            }

            const data = await response.json().catch(() => null);

            if (!data) {
                mostrarAlerta(`El servidor devolvió una respuesta inesperada (HTTP ${response.status}).`, "error");
            } else if (response.ok && data.success !== false) {
                limpiarFormulario();
                mostrarModalResultado("exito", "¡Ingreso correcto!", data.message || "El visitante quedó registrado correctamente.");
            } else {
                const erroresValidacion = data.errors ? Object.values(data.errors).flat() : [];
                mostrarAlerta(erroresValidacion[0] || data.message || "Error al guardar el registro.", "error");
            }
        } catch (error) {
            // Se cayó la señal durante el envío.
            console.warn("Entrada guardada sin conexión:", error);
            await guardarSinConexion(payload, idRegistro, creadoEn);
        } finally {
            btn.disabled = false;
            spinner.classList.add('hidden');
        }
    }

    async function guardarSinConexion(payload, idRegistro, creadoEn) {
        try {
            await window.colaOffline.encolar('entrada', payload, { id: idRegistro, creadoEn });
        } catch (error) {
            console.error("No se pudo guardar el registro en el celular:", error);
            mostrarAlerta("No hay conexión y no se pudo guardar en el celular. Los datos siguen en el formulario.", "error");
            return;
        }

        limpiarFormulario();
        mostrarModalResultado("pendiente", "Guardado sin conexión", "La entrada se guardó en el celular con la hora actual y se enviará sola cuando vuelva el internet.");
    }

    function limpiarFormulario() {
        document.getElementById('form-entrada').reset();
        borrarFirma();
        quitarFoto('placas');
        quitarFoto('vehiculo');
        toggleEmpresaNueva();
        window.dispatchEvent(new CustomEvent('empresa-limpiar'));
        casetaUI.ocultarAlerta();
        window.scrollTo({ top: 0 });
        document.querySelector('main')?.parentElement?.scrollTo({ top: 0 });
    }
</script>
@endpush
