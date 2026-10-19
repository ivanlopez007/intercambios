@extends('caseta.layout.layout')

@section('title', 'Registro de Salida')
@section('page-title', 'Salida de Visitantes')

@section('contenido')
<div class="max-w-xl mx-auto py-3 sm:py-6 px-1 sm:px-2">

    <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-7 shadow-sm space-y-5">

        <!-- Aviso de errores (se desplaza a la vista automáticamente) -->
        <div id="feedback-alert" class="hidden" aria-live="assertive"></div>

        <!-- Formulario -->
        <form id="form-salida" onsubmit="event.preventDefault(); registrarSalida();" class="space-y-5">
            @csrf

            <!-- Campo QR / Escáner -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                <label for="empresa_busqueda" class="block text-sm font-bold text-slate-500 uppercase tracking-wider">
                    Empresa (QR o nombre)
                </label>

                <div class="flex gap-2">
                    <x-selector-empresa class="focus:ring-rose-500 focus:border-rose-500" />

                    <button type="button" id="btn-escaneo" onclick="escaner?.alternar()"
                        class="bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold px-4 py-3.5 rounded-xl transition flex items-center gap-2 shrink-0 shadow-sm"
                        aria-label="Escanear QR con la cámara">
                        <i id="btn-escaneo-icono" class="fa-solid fa-camera text-lg"></i>
                        <span id="btn-escaneo-text" class="hidden sm:inline">Cámara</span>
                    </button>

                    <button type="button" onclick="limpiarLectura()"
                        class="bg-slate-200 hover:bg-slate-300 text-slate-600 hover:text-slate-800 font-bold px-3 py-3.5 rounded-xl transition border border-slate-300 shrink-0" aria-label="Limpiar">
                        <i class="fa-solid fa-eraser text-lg"></i>
                    </button>
                </div>

                <!-- Visor de la cámara -->
                <div id="reader" class="hidden w-full rounded-xl overflow-hidden bg-black"></div>
            </div>

            <!-- Botón de Acción -->
            <button type="submit" id="btn-submit"
                class="w-full bg-rose-600 hover:bg-rose-700 text-white font-black py-4 px-4 rounded-2xl transition shadow-md shadow-rose-200 flex items-center justify-center gap-2 text-lg uppercase tracking-wider disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-door-open text-xl"></i>
                <span id="btn-submit-text">Registrar Salida</span>
                <i id="btn-submit-spinner" class="fa-solid fa-circle-notch animate-spin hidden"></i>
            </button>
        </form>

    </div>

    <!-- PERSONAS DENTRO: dar salida con un botón -->
    <section x-data="personasDentro()" x-init="iniciar()" @salida-registrada.window="cargar()"
        class="mt-6 bg-white border border-slate-200 rounded-3xl p-5 md:p-7 shadow-sm space-y-4" aria-labelledby="titulo-dentro">

        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 id="titulo-dentro" class="text-lg font-bold text-slate-900">
                    Personas dentro
                    <span class="ml-1 text-sm font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700" x-text="todas().length"></span>
                </h2>
                <p class="text-xs text-slate-500" x-show="actualizadoEn" x-text="desdeCache ? 'Sin conexión · lista de las ' + actualizadoEn : 'Actualizado a las ' + actualizadoEn"></p>
            </div>
            <button type="button" @click="cargar()" :disabled="cargando"
                class="p-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 disabled:opacity-50" aria-label="Actualizar lista">
                <i class="fa-solid fa-rotate" :class="cargando && 'animate-spin'"></i>
            </button>
        </div>

        <div class="relative" x-show="todas().length > 5">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
            <input type="search" x-model="busqueda" placeholder="Buscar por nombre, empresa o a quién visita..."
                class="w-full pl-10 pr-3 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none text-base">
        </div>

        <p x-show="error" x-text="error" class="text-sm font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-3"></p>

        <p x-show="!cargando && !error && todas().length === 0" class="text-center text-sm text-slate-500 py-6">
            No hay nadie dentro en este momento.
        </p>

        <ul class="space-y-2">
            <template x-for="visita in filtradas()" :key="visita.id">
                <li class="flex items-center gap-3 p-3 rounded-2xl border"
                    :class="visita.local ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-slate-50'">
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-900 truncate" x-text="visita.nombre_persona"></p>
                        <p x-show="visita.local" class="text-[11px] font-bold text-amber-800">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Entrada sin enviar
                        </p>
                        <p class="text-xs text-slate-600 truncate">
                            <i class="fa-solid fa-building text-slate-400"></i>
                            <span x-text="visita.empresa"></span>
                            <span class="text-slate-400">·</span>
                            <span x-text="visita.persona_a_visitar"></span>
                        </p>
                        <p class="text-xs text-slate-500">
                            <i class="fa-solid fa-right-to-bracket text-emerald-600"></i>
                            <span x-text="'Entró: ' + visita.fecha_entrada"></span>
                            <span x-show="visita.entrada_por" x-text="'· Registró: ' + visita.entrada_por"></span>
                        </p>
                    </div>

                    <template x-if="salidaPendiente(visita)">
                        <span class="shrink-0 text-xs font-semibold text-amber-800 bg-amber-100 px-2.5 py-1.5 rounded-xl text-center">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Salida<br>sin enviar
                        </span>
                    </template>

                    <template x-if="!salidaPendiente(visita)">
                        <button type="button" @click="darSalida(visita)" :disabled="enviando === visita.id"
                            class="shrink-0 min-w-[6.5rem] px-3 py-3 rounded-xl text-sm font-bold text-white transition disabled:opacity-60"
                            :class="confirmando === visita.id ? 'bg-slate-900 hover:bg-slate-800' : 'bg-rose-600 hover:bg-rose-700 active:bg-rose-800'">
                            <i class="fa-solid" :class="enviando === visita.id ? 'fa-circle-notch animate-spin' : 'fa-door-open'"></i>
                            <span x-text="confirmando === visita.id ? '¿Confirmar?' : 'Dar salida'"></span>
                        </button>
                    </template>
                </li>
            </template>
        </ul>
    </section>
</div>

<!-- MODAL PARA ELEGIR QUIÉN SALE (VARIAS PERSONAS DE LA MISMA EMPRESA DENTRO) -->
<div id="modal-seleccion" class="fixed inset-0 z-[60] bg-slate-900/60 backdrop-blur-sm hidden items-end sm:items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="modal-seleccion-titulo"
    onclick="if (event.target === this) cerrarModalSeleccion()">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-md w-full p-6 space-y-4 animate-pop">
        <div>
            <h3 id="modal-seleccion-titulo" class="text-xl font-black text-slate-900">¿Quién sale?</h3>
            <p id="modal-seleccion-mensaje" class="text-sm text-slate-500 mt-1"></p>
        </div>
        <div id="modal-seleccion-lista" class="space-y-2 max-h-[50vh] overflow-y-auto"></div>
        <button type="button" onclick="cerrarModalSeleccion()"
            class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-3 rounded-2xl transition">
            Cancelar
        </button>
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
            alLeer: (texto) => {
                window.dispatchEvent(new CustomEvent('empresa-escaneada', { detail: texto }));
                registrarSalida(); // Al leer el QR, la salida se procesa sola
            },
            alCambiar: (activo) => {
                document.getElementById('btn-escaneo-text').innerText = activo ? 'Cerrar' : 'Cámara';
                document.getElementById('btn-escaneo-icono').className = `fa-solid ${activo ? 'fa-xmark' : 'fa-camera'} text-lg`;
            },
            alError: (mensaje) => mostrarAlerta(mensaje, 'error'),
        });
        casetaUI.precargarLector();
        casetaUI.enfocarSiHayTeclado('empresa_busqueda');
    });

    function limpiarLectura({ enfocar = true } = {}) {
        window.dispatchEvent(new CustomEvent('empresa-limpiar'));
        casetaUI.ocultarAlerta();
        if (enfocar) casetaUI.enfocarSiHayTeclado('empresa_busqueda');
    }

    // --- REGISTRAR SALIDA ---
    // Id y hora del escaneo actual. Se conservan si hay que elegir quién sale o si el registro
    // termina guardándose sin conexión, para que el servidor no lo duplique.
    let intentoSalida = null;

    async function registrarSalida(visitaId = null) {
        const inputQr = document.getElementById('empresa_busqueda');
        const codigo = document.getElementById('codigo_qr').value.trim();
        const empresaNombre = inputQr.value.trim();

        if (!codigo) {
            mostrarAlerta(empresaNombre
                ? `No encontramos la empresa «${empresaNombre}». Elígela de la lista de sugerencias.`
                : "Escanea el QR o escribe el nombre de la empresa.", "error");
            inputQr.focus();
            return;
        }

        const btn = document.getElementById('btn-submit');
        if (btn.disabled) return; // Evita registros dobles por lecturas repetidas del escáner

        if (visitaId === null || !intentoSalida) {
            intentoSalida = { id: window.colaOffline.nuevoId(), creadoEn: new Date().toISOString() };
        }

        // empresa_nombre solo se usa para mostrarlo en el celular; el servidor lo ignora.
        const payload = { codigo_qr: codigo, visita_id: visitaId, empresa_nombre: empresaNombre };

        if (!navigator.onLine) {
            await guardarSinConexion(payload);
            return;
        }

        const spinner = document.getElementById('btn-submit-spinner');
        const tokenInput = document.querySelector('input[name="_token"]');

        btn.disabled = true;
        spinner.classList.remove('hidden');

        try {
            const response = await fetch("{{ route('caseta.visitas.salida') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": tokenInput ? tokenInput.value : '',
                    "Accept": "application/json"
                },
                body: JSON.stringify({ ...payload, id_cliente: intentoSalida.id, registrado_en: intentoSalida.creadoEn })
            });

            // Sesión expirada o servidor/túnel caído: se guarda para enviarlo después.
            if ([401, 419, 502, 503, 504].includes(response.status)) {
                await guardarSinConexion(payload);
                return;
            }

            const responseBody = await response.text();
            let data;

            try {
                data = responseBody ? JSON.parse(responseBody) : {};
            } catch {
                mostrarAlerta(`El servidor devolvió una respuesta inesperada (HTTP ${response.status}).`, "error");
                return;
            }

            if (response.ok && data.cancelado) {
                cerrarModalSeleccion();
                limpiarLectura();
                intentoSalida = null;
                mostrarModalResultado("pendiente", "Salida repetida", data.message);
                window.dispatchEvent(new CustomEvent('salida-registrada'));
            } else if (response.ok && data.success !== false) {
                cerrarModalSeleccion();
                limpiarLectura();
                intentoSalida = null;
                mostrarModalResultado("exito", "¡Salida Exitosa!", data.message || "La salida quedó registrada correctamente.");
                window.dispatchEvent(new CustomEvent('salida-registrada'));
            } else if (response.status === 409 && data.requiere_seleccion) {
                mostrarModalSeleccion(data.message, data.visitas || []);
            } else {
                const erroresValidacion = data.errors ? Object.values(data.errors).flat() : [];
                mostrarAlerta(erroresValidacion[0] || data.message || "No se pudo registrar la salida del código especificado.", "error");
                inputQr.select();
            }
        } catch (error) {
            // Se cayó la señal durante el envío.
            console.warn("Salida guardada sin conexión:", error);
            await guardarSinConexion(payload);
        } finally {
            btn.disabled = false;
            spinner.classList.add('hidden');
        }
    }

    async function guardarSinConexion(payload) {
        try {
            await window.colaOffline.encolar('salida', payload, { id: intentoSalida.id, creadoEn: intentoSalida.creadoEn });
        } catch (error) {
            console.error("No se pudo guardar el registro en el celular:", error);
            mostrarAlerta("No hay conexión y no se pudo guardar en el celular. Inténtalo de nuevo.", "error");
            return;
        }

        cerrarModalSeleccion();
        limpiarLectura();
        intentoSalida = null;
        mostrarModalResultado("pendiente", "Guardado sin conexión", "La salida se guardó con la hora actual y se enviará sola cuando vuelva el internet. Si hay varias personas de esa empresa dentro, te pediremos elegir quién salió.");
    }


    // --- SELECCIÓN DE VISITA CUANDO HAY VARIAS PERSONAS DE LA EMPRESA DENTRO ---
    function mostrarModalSeleccion(mensaje, visitas) {
        const lista = document.getElementById('modal-seleccion-lista');
        document.getElementById('modal-seleccion-mensaje').innerText = mensaje;
        lista.replaceChildren();

        visitas.forEach((visita) => {
            const opcion = document.createElement('button');
            opcion.type = 'button';
            opcion.className = 'w-full text-left p-4 rounded-2xl border border-slate-200 bg-slate-50 hover:bg-rose-50 hover:border-rose-300 active:bg-rose-100 transition';
            opcion.addEventListener('click', () => registrarSalida(visita.id));

            const nombre = document.createElement('span');
            nombre.className = 'block font-bold text-slate-900';
            nombre.innerText = visita.nombre_persona;

            const detalle = document.createElement('span');
            detalle.className = 'block text-xs text-slate-500 mt-0.5';
            detalle.innerText = `Entró: ${visita.fecha_entrada ?? '—'} · Visita a: ${visita.persona_a_visitar ?? '—'}`;

            opcion.append(nombre, detalle);
            lista.append(opcion);
        });

        const modal = document.getElementById('modal-seleccion');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function cerrarModalSeleccion() {
        const modal = document.getElementById('modal-seleccion');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarModalSeleccion();
    });

    // --- PERSONAS DENTRO: SALIDA CON UN BOTÓN ---
    const URL_ACTIVAS = "{{ route('caseta.visitas.activas') }}";
    const URL_SALIDA = "{{ route('caseta.visitas.salida') }}";
    const CLAVE_LISTA = 'caseta-personas-dentro';

    const normalizar = (texto) => (texto || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const hora = (iso) => new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

    function personasDentro() {
        return {
            visitas: [],
            busqueda: '',
            cargando: false,
            error: null,
            desdeCache: false,
            actualizadoEn: null,
            confirmando: null,
            enviando: null,
            temporizador: null,

            iniciar() {
                this.cargar();
                window.addEventListener('online', () => this.cargar());
                window.addEventListener('cola-sincronizada', () => this.cargar());
                setInterval(() => {
                    if (document.visibilityState === 'visible' && navigator.onLine) this.cargar();
                }, 60000);
            },

            async cargar() {
                this.cargando = true;
                this.error = null;

                try {
                    const respuesta = await fetch(URL_ACTIVAS, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                    if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);

                    const datos = await respuesta.json();
                    this.visitas = datos.visitas;
                    this.desdeCache = false;
                    this.actualizadoEn = hora(datos.actualizado_en);

                    try {
                        localStorage.setItem(CLAVE_LISTA, JSON.stringify(datos));
                    } catch {
                        // Sin almacenamiento local: solo no habrá lista sin conexión.
                    }
                } catch {
                    // Sin señal: se muestra la última lista descargada.
                    let guardada = null;
                    try {
                        guardada = JSON.parse(localStorage.getItem(CLAVE_LISTA));
                    } catch {
                        guardada = null;
                    }

                    if (guardada) {
                        this.visitas = guardada.visitas;
                        this.desdeCache = true;
                        this.actualizadoEn = hora(guardada.actualizado_en);
                    } else {
                        this.error = 'Sin conexión: la lista aparecerá cuando haya internet. Puedes registrar la salida con el QR.';
                    }
                } finally {
                    this.cargando = false;
                }
            },

            // Entradas hechas sin conexión que aún no llegan al servidor: también están dentro.
            locales() {
                return Alpine.store('cola').registros
                    .filter((r) => r.tipo === 'entrada')
                    .map((r) => ({
                        id: `local-${r.id}`,
                        local: true,
                        id_cliente: r.id,
                        nombre_persona: r.datos.nombre_persona,
                        empresa: r.datos.nombre_empresa || r.datos.empresa_nombre || `Empresa QR ${r.datos.codigo_qr}`,
                        persona_a_visitar: r.datos.persona_dirigida,
                        fecha_entrada: new Date(r.creado_en).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }),
                        entrada_por: null,
                    }));
            },

            todas() {
                return [...this.locales(), ...this.visitas];
            },

            filtradas() {
                const q = normalizar(this.busqueda);
                if (!q) return this.todas();

                return this.todas().filter((v) => normalizar(`${v.nombre_persona} ${v.empresa} ${v.persona_a_visitar}`).includes(q));
            },

            salidaPendiente(visita) {
                return Alpine.store('cola').registros.some((r) => r.tipo === 'salida' && (visita.local
                    ? r.datos.entrada_id_cliente === visita.id_cliente
                    : Number(r.datos.visita_id) === visita.id));
            },

            // Primer toque: pide confirmar. Segundo toque (en 4 s): registra la salida.
            async darSalida(visita) {
                if (this.confirmando !== visita.id) {
                    this.confirmando = visita.id;
                    clearTimeout(this.temporizador);
                    this.temporizador = setTimeout(() => (this.confirmando = null), 4000);
                    return;
                }

                this.confirmando = null;
                this.enviando = visita.id;

                try {
                    const resultado = await enviarSalidaDeVisita(visita);

                    if (resultado === 'ok' || resultado === 'cancelado') {
                        this.visitas = this.visitas.filter((v) => v.id !== visita.id);
                    }
                } finally {
                    this.enviando = null;
                }
            },
        };
    }

    /**
     * Registra la salida de una visita concreta. Devuelve 'ok', 'cancelado' (ya había salido),
     * 'pendiente' (guardada sin conexión) o 'error'.
     */
    async function enviarSalidaDeVisita(visita) {
        // Si la entrada también está sin enviar, la salida se enlaza con ella y se envía después.
        const payload = visita.local
            ? { entrada_id_cliente: visita.id_cliente, nombre_persona: visita.nombre_persona }
            : { visita_id: visita.id, nombre_persona: visita.nombre_persona };
        const intento = { id: window.colaOffline.nuevoId(), creadoEn: new Date().toISOString() };

        const guardarEnCola = async () => {
            try {
                await window.colaOffline.encolar('salida', payload, intento);
            } catch {
                mostrarModalResultado("error", "No se pudo guardar", "No se pudo guardar en el celular. Inténtalo de nuevo.");
                return 'error';
            }

            mostrarModalResultado("pendiente", "Salida guardada", visita.local
                ? `La salida de ${visita.nombre_persona} se guardó con la hora actual. Se enviará justo después de su entrada, cuando haya internet.`
                : `La salida de ${visita.nombre_persona} se guardó con la hora actual y se enviará sola cuando vuelva el internet.`);
            return 'pendiente';
        };

        if (visita.local || !navigator.onLine) return guardarEnCola();

        try {
            const tokenInput = document.querySelector('input[name="_token"]');
            const response = await fetch(URL_SALIDA, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": tokenInput ? tokenInput.value : '',
                    "Accept": "application/json"
                },
                body: JSON.stringify({ ...payload, id_cliente: intento.id, registrado_en: intento.creadoEn })
            });

            if ([401, 419, 502, 503, 504].includes(response.status)) return guardarEnCola();

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.cancelado) {
                mostrarModalResultado("pendiente", "Salida repetida", data.message);
                window.dispatchEvent(new CustomEvent('salida-registrada'));
                return 'cancelado';
            }

            if (response.ok) {
                mostrarModalResultado("exito", "¡Salida Exitosa!", data.message || `Salida de ${visita.nombre_persona} registrada.`);
                return 'ok';
            }

            const errores = data.errors ? Object.values(data.errors).flat() : [];
            mostrarModalResultado("error", "No se pudo dar salida", errores[0] || data.message || "Inténtalo de nuevo.");
            return 'error';
        } catch {
            return guardarEnCola();
        }
    }
</script>
@endpush