/*
 * Asistente de intercambios (admin/intercambios/nuevo). Funciona sin conexión:
 *
 * - Catálogos, operadores e historial se descargan de /admin/intercambios/datos cuando hay señal
 *   y se guardan en IndexedDB (intercambio-almacen.js).
 * - El borrador se guarda en el dispositivo en cada cambio, con sus fotos (Blob). Se puede
 *   cerrar la app y continuarlo después, también en otro turno con el mismo dispositivo.
 * - Al completarlo pasa a la cola de envío (cola-offline.js). El service worker sube las fotos
 *   y después el intercambio; el folio se asigna al llegar al servidor.
 *
 * Debe cargarse antes de app.js (Alpine).
 */
import './pwa.js';
import './cola-offline.js';
import { almacen } from './intercambio-almacen.js';

const PASOS = ['Operador', 'Caja', 'Tractocamión', 'Viaje', 'Sello', 'Llantas', 'Revisión C-TPAT', 'Comentarios y daños', 'Fotos de la unidad', 'Resumen y firmas'];
const PASO_SELLO = 5;
const PASO_DANOS = 8;
const PASO_RECORRIDO = 9;
const PASO_FINAL = PASOS.length;
const URL_DATOS = '/admin/intercambios/datos';

// Distribución de las 18 llantas vistas desde arriba: [izquierda exterior, izquierda interior, derecha interior, derecha exterior].
const EJES = [
    { unidad: 'Tracto', nombre: 'Direccional', posiciones: [1, null, null, 2] },
    { unidad: 'Tracto', nombre: 'Tracción 1', posiciones: [3, 4, 5, 6] },
    { unidad: 'Tracto', nombre: 'Tracción 2', posiciones: [7, 8, 9, 10] },
    { unidad: 'Caja', nombre: 'Eje 1', posiciones: [11, 12, 13, 14] },
    { unidad: 'Caja', nombre: 'Eje 2', posiciones: [15, 16, 17, 18] },
];

const nuevoId = () => window.colaOffline.nuevoId();
const texto = (valor) => (valor ?? '').toString().trim();
const normalizar = (valor) => texto(valor).toLocaleLowerCase('es-MX');
const esEntero = (valor, min, max) => /^\d+$/.test(texto(valor)) && Number(valor) >= min && Number(valor) <= max;

function ahoraLocal() {
    const fecha = new Date();
    fecha.setSeconds(0, 0);
    return new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

function fechaCorta(valor) {
    if (!valor) return '—';
    const fecha = new Date(valor.length === 10 ? `${valor}T00:00:00` : valor);
    return Number.isNaN(fecha.getTime())
        ? valor
        : fecha.toLocaleString('es-MX', valor.length === 10 ? { dateStyle: 'medium' } : { dateStyle: 'short', timeStyle: 'short' });
}

// Nombre de cada eje para ubicar la llanta.
const NOMBRES_EJE = {
    Direccional: 'Eje direccional',
    'Tracción 1': 'Eje de tracción delantero',
    'Tracción 2': 'Eje de tracción trasero',
    'Eje 1': 'Eje delantero',
    'Eje 2': 'Eje trasero',
};

function borradorVacio() {
    const ahora = new Date().toISOString();
    // Las llantas empiezan vacías; las que no se midan se guardan con los valores por defecto.
    const llantas = {};
    for (let posicion = 1; posicion <= 18; posicion++) {
        llantas[posicion] = { marca: '', profundidad_32: '', psi: '', tipo: '', observaciones: '' };
    }

    return {
        id: nuevoId(),
        creado_en: ahora,
        actualizado_en: ahora,
        estado: 'editando',
        paso: 1,
        paso_maximo: 1,

        tipo_movimiento: null,
        fecha_hora: ahoraLocal(),
        operador_id: null,

        caja: {
            modo: null, // 'existente' | 'nueva'
            id: null,
            corregir: false,
            correccion: {},
            nuevo: {
                cliente_id: '', cliente_nuevo: { nombre: '', rfc: '' },
                tipo_caja_id: '', numero_economico: '', placa: '', estado_placa: '',
                marca: '', modelo: '', vin: '', anio: '', tamano_pies: '',
            },
        },
        tracto: {
            modo: null,
            id: null,
            corregir: false,
            correccion: {},
            nuevo: {
                numero_economico: '', placa: '', estado_placa: '', marca: '', modelo: '', vin: '', anio: '',
                es_permisionario: false, linea_id: '', linea_nueva: { nombre: '', rfc: '' },
            },
        },
        odometro: '',

        estado_carga: null,
        tipo_intercambio: null,
        cliente_id: '', // '' = el dueño de la caja; '__nuevo' = alta rápida
        cliente_nuevo: { nombre: '', rfc: '' },
        origen_id: '',
        origen_nuevo: '',
        destino_id: '',
        destino_nuevo: '',
        referencia_carta_porte: '',

        numero_sello: '',
        llantas,
        llantas_precarga: { caja: null, tracto: null },
        revision: {},
        danos_caja: 'no', // ¿La caja tiene daños? Si es 'si' se piden descripción y, si hace falta, fotos.
        danos_tracto: 'no',
        comentarios_caja: '',
        comentarios_tracto: '',
        observaciones: '',
        fotos: [], // { uuid, tipo, punto_inspeccion_id, posicion, unidad, vista, blob }
        firma_operador: null,
        firma_guardia: null,
    };
}

// Copia guardable en IndexedDB: sin proxies de Alpine y conservando los Blob de las fotos.
function serializar(borrador) {
    const crudo = window.Alpine.raw(borrador);
    const { fotos, ...resto } = crudo;
    const copia = JSON.parse(JSON.stringify(resto));
    copia.fotos = (fotos || []).map(({ blob, subiendo, error_subida, ...meta }) => ({ ...JSON.parse(JSON.stringify(meta)), blob }));
    return copia;
}

function comprimirFoto(archivo, ladoMaximo, calidad) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(archivo);
        const imagen = new Image();

        imagen.onload = () => {
            const escala = Math.min(1, ladoMaximo / Math.max(imagen.naturalWidth, imagen.naturalHeight));
            const lienzo = document.createElement('canvas');
            lienzo.width = Math.round(imagen.naturalWidth * escala);
            lienzo.height = Math.round(imagen.naturalHeight * escala);
            lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
            URL.revokeObjectURL(url);
            lienzo.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('No se pudo procesar la foto.'))), 'image/jpeg', calidad);
        };
        imagen.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('No se pudo leer la foto.'));
        };
        imagen.src = url;
    });
}

// Lienzo de firma que funciona con el dedo. alTerminar recibe la firma como PNG en Base64.
function habilitarFirma(lienzo, alTerminar) {
    const ctx = () => lienzo.getContext('2d');
    let dibujando = false;
    let trazo = false;

    const punto = (e) => {
        const area = lienzo.getBoundingClientRect();
        return [e.clientX - area.left, e.clientY - area.top];
    };

    lienzo.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        lienzo.setPointerCapture(e.pointerId);
        dibujando = true;
        trazo = false;
        ctx().beginPath();
        ctx().moveTo(...punto(e));
    });
    lienzo.addEventListener('pointermove', (e) => {
        if (!dibujando) return;
        e.preventDefault();
        ctx().lineTo(...punto(e));
        ctx().stroke();
        trazo = true;
    });
    const terminar = () => {
        if (!dibujando) return;
        dibujando = false;
        if (trazo) alTerminar(lienzo.toDataURL('image/png'));
    };
    lienzo.addEventListener('pointerup', terminar);
    lienzo.addEventListener('pointercancel', terminar);
}

function prepararLienzo(lienzo, firma) {
    const densidad = window.devicePixelRatio || 1;
    const area = lienzo.getBoundingClientRect();
    if (!area.width) return;

    lienzo.width = Math.round(area.width * densidad);
    lienzo.height = Math.round(area.height * densidad);
    const ctx = lienzo.getContext('2d');
    ctx.scale(densidad, densidad);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, area.width, area.height);
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0f172a';

    if (firma) {
        const imagen = new Image();
        imagen.onload = () => ctx.drawImage(imagen, 0, 0, area.width, area.height);
        imagen.src = firma;
    }
}

function asistenteIntercambio() {
    // URL temporales de las miniaturas. Fuera del estado de Alpine: se llenan al dibujar.
    const urls = new Map();
    // Fotos que se están subiendo ahora y token CSRF para subirlas (no se guardan en el borrador).
    const subiendo = new Set();
    let tokenCsrf = null;

    return {
        PASOS,
        EJES,
        vista: 'inicio', // 'inicio' | 'asistente'
        enLinea: navigator.onLine,
        paquete: null,
        cargandoDatos: false,
        errorDatos: null,
        borradores: [],
        borrador: null,
        errores: [],
        avisoFlotante: null,
        enviadosRecientes: [],
        llantaAbierta: null,
        busquedaOperador: '',
        busquedaCaja: '',
        busquedaTracto: '',
        procesandoFoto: false,
        _guardado: null,

        async init() {
            window.addEventListener('online', () => {
                this.enLinea = true;
                this.descargarDatos();
                this.subirPendientes();
            });
            window.addEventListener('offline', () => (this.enLinea = false));
            // Al girar el celular, las firmas se redibujan con el nuevo tamaño del recuadro.
            let anchoPrevio = window.innerWidth;
            window.addEventListener('resize', () => {
                if (window.innerWidth === anchoPrevio) return;
                anchoPrevio = window.innerWidth;
                if (this.borrador?.paso === PASO_FINAL) this.prepararFirmas();
            });
            window.addEventListener('intercambios-enviados', (e) => this.alEnviar(e.detail));
            window.addEventListener('cola-sincronizada', () => this.conciliar());
            navigator.serviceWorker?.addEventListener('message', (e) => {
                if (e.data?.tipo === 'cola') this.conciliar();
            });

            try {
                this.paquete = (await almacen.leerPaquete()) ?? null;
            } catch {
                this.paquete = null;
            }

            await this.conciliar();
            this.descargarDatos();

            // Guarda el borrador en el dispositivo en cada cambio (con una pequeña espera).
            this.$watch('borrador', () => {
                if (!this.borrador || this.borrador.estado !== 'editando') return;
                clearTimeout(this._guardado);
                this._guardado = setTimeout(() => this.guardarBorrador(), 600);
            });
        },

        // ---------- Datos descargados ----------

        async descargarDatos() {
            if (!navigator.onLine || this.cargandoDatos) return;
            this.cargandoDatos = true;

            try {
                const respuesta = await fetch(URL_DATOS, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });

                if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) {
                    this.errorDatos = 'Tu sesión expiró. Vuelve a iniciar sesión para actualizar los datos.';
                    return;
                }
                if (!respuesta.ok) throw new Error();

                const paquete = await respuesta.json();
                await almacen.guardarPaquete(paquete);
                this.paquete = paquete;
                this.errorDatos = null;
            } catch {
                this.errorDatos = this.paquete
                    ? 'No se pudieron actualizar los datos. Se usan los guardados en el dispositivo.'
                    : 'No se pudieron descargar los datos. Abre esta pantalla con internet al menos una vez.';
            } finally {
                this.cargandoDatos = false;
            }
        },

        get datosListos() {
            return Boolean(this.paquete);
        },

        get config() {
            return this.paquete?.config ?? {};
        },

        porId(lista, id) {
            return (this.paquete?.[lista] ?? []).find((x) => String(x.id) === String(id)) ?? null;
        },

        nombreDe(lista, id) {
            return this.porId(lista, id)?.nombre ?? '';
        },

        // ---------- Borradores y cola ----------

        get pendientesEnvio() {
            return (window.Alpine.store('cola')?.registros ?? []).filter((r) => r.tipo === 'intercambio');
        },

        get borradoresEditando() {
            return this.borradores.filter((b) => b.estado === 'editando');
        },

        // Un borrador en cola cuyo registro ya no está en la cola se envió: se borra del dispositivo.
        async conciliar() {
            const cola = window.Alpine.store('cola');
            await cola?.refrescar();
            const enCola = new Set((cola?.registros ?? []).map((r) => r.id));

            try {
                const borradores = await almacen.listarBorradores();
                for (const b of borradores) {
                    if (b.estado === 'en_cola' && !enCola.has(b.id)) await almacen.eliminarBorrador(b.id);
                }
                this.borradores = await almacen.listarBorradores();
            } catch {
                this.borradores = [];
            }
        },

        alEnviar(folios) {
            const nuevos = folios.map((f) => f.folio);
            this.enviadosRecientes = [...nuevos, ...this.enviadosRecientes].slice(0, 10);
            this.avisar(nuevos.length === 1 ? `Intercambio ${nuevos[0]} registrado.` : `${nuevos.length} intercambios registrados.`);
            this.conciliar();
        },

        avisar(mensaje) {
            this.avisoFlotante = mensaje;
            clearTimeout(this._aviso);
            this._aviso = setTimeout(() => (this.avisoFlotante = null), 5000);
        },

        async guardarBorrador() {
            if (!this.borrador) return;
            this.borrador.actualizado_en = new Date().toISOString();

            try {
                await almacen.guardarBorrador(serializar(this.borrador));
            } catch {
                this.avisar('No se pudo guardar el borrador en el dispositivo. Revisa el espacio disponible.');
            }
        },

        nuevo() {
            if (!this.datosListos) return;
            this.abrir(borradorVacio());
        },

        async continuar(id) {
            const guardado = await almacen.obtenerBorrador(id);
            if (guardado) this.abrir(guardado);
        },

        abrir(borrador) {
            this.liberarUrls();
            this.borrador = borrador;
            this.errores = [];
            this.busquedaOperador = '';
            this.busquedaCaja = '';
            this.busquedaTracto = '';
            this.vista = 'asistente';
            this.irA(borrador.paso);
            this.subirPendientes();
        },

        async salir() {
            await this.guardarBorrador();
            this.liberarUrls();
            this.borrador = null;
            this.vista = 'inicio';
            await this.conciliar();
            window.scrollTo({ top: 0 });
        },

        async descartar(id) {
            if (!confirm('¿Descartar este borrador? Se borrarán sus datos y fotos.')) return;
            const guardado = await almacen.obtenerBorrador(id);
            await almacen.eliminarBorrador(id);

            // Si ya se intentó enviar, sus fotos pueden estar en el servidor: se piden borrar.
            if (guardado?.se_envio) await window.colaOffline.limpiarIntercambio(id);

            if (this.borrador?.id === id) {
                this.borrador = null;
                this.vista = 'inicio';
            }
            await this.conciliar();
        },

        // Descarta un intercambio de la cola: borra su borrador y pide borrar sus fotos del servidor.
        async descartarEnviado(registro) {
            if (await window.Alpine.store('cola').descartar(registro.id)) await this.conciliar();
        },

        // Un intercambio rechazado por el servidor vuelve a ser borrador para corregirlo.
        async corregirRechazado(registro) {
            const borrador = await almacen.obtenerBorrador(registro.id);
            if (!borrador) {
                alert('El borrador de este intercambio ya no está en el dispositivo.');
                return;
            }
            await window.colaOffline.quitar(registro.id);
            borrador.estado = 'editando';
            borrador.rechazo = registro.mensaje;
            await almacen.guardarBorrador(borrador);
            await this.conciliar();
            this.abrir(borrador);
        },

        // ---------- Navegación ----------

        get aplicaSello() {
            return this.borrador?.estado_carga !== 'vacia';
        },

        pasoVisible(numero) {
            return numero !== PASO_SELLO || this.aplicaSello;
        },

        get pasosVisibles() {
            return PASOS.map((nombre, i) => ({ numero: i + 1, nombre })).filter((p) => this.pasoVisible(p.numero));
        },

        get indicePaso() {
            return this.pasosVisibles.findIndex((p) => p.numero === this.borrador.paso);
        },

        irA(numero) {
            if (!this.pasoVisible(numero)) numero = numero === PASO_SELLO ? PASO_SELLO + 1 : numero;
            this.borrador.paso = numero;
            this.errores = [];
            this.alEntrarPaso(numero);
            this.$nextTick(() => document.getElementById('asistente-inicio')?.scrollIntoView({ block: 'start' }));
        },

        editar(numero) {
            if (numero <= this.borrador.paso_maximo) this.irA(numero);
        },

        anterior() {
            const visibles = this.pasosVisibles;
            const i = this.indicePaso;
            if (i > 0) this.irA(visibles[i - 1].numero);
        },

        siguiente() {
            this.errores = this.validarPaso(this.borrador.paso);
            if (this.errores.length) {
                this.$nextTick(() => document.getElementById('asistente-errores')?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                return;
            }

            const visibles = this.pasosVisibles;
            const siguiente = visibles[this.indicePaso + 1];
            if (!siguiente) return;

            this.borrador.paso_maximo = Math.max(this.borrador.paso_maximo, siguiente.numero);
            this.irA(siguiente.numero);
            this.guardarBorrador();
        },

        alEntrarPaso(numero) {
            if (numero === 6) this.precargarLlantas();
            if (numero === 7 || numero === PASO_DANOS) this.prepararRevision();
            if (numero === PASO_FINAL) this.$nextTick(() => this.prepararFirmas());
        },

        // ---------- Paso 1: operador ----------

        get operador() {
            return this.porId('operadores', this.borrador?.operador_id);
        },

        get operadoresFiltrados() {
            const q = normalizar(this.busquedaOperador);
            const lista = this.paquete?.operadores ?? [];
            if (!q) return lista.slice(0, 30);
            return lista.filter((o) => normalizar(o.nombre).includes(q) || normalizar(o.numero_empleado).includes(q)).slice(0, 30);
        },

        estadoVencimiento(fecha) {
            if (!fecha) return 'sin_dato';
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);
            const vence = new Date(`${fecha}T00:00:00`);
            const dias = Math.round((vence - hoy) / 86400000);
            if (dias < 0) return 'vencido';
            if (dias <= (this.config.dias_aviso_vencimiento ?? 30)) return 'por_vencer';
            return 'vigente';
        },

        get operadorConVencidos() {
            const o = this.operador;
            return Boolean(o) && [o.licencia_vence, o.apto_vence].some((f) => this.estadoVencimiento(f) === 'vencido');
        },

        // ---------- Paso 2: caja ----------

        get caja() {
            return this.borrador?.caja.modo === 'existente' ? this.porId('cajas', this.borrador.caja.id) : null;
        },

        get cajasFiltradas() {
            const q = normalizar(this.busquedaCaja);
            if (!q) return [];
            return (this.paquete?.cajas ?? [])
                .filter((c) => normalizar(c.numero_economico).includes(q) || normalizar(c.placa).includes(q))
                .slice(0, 20);
        },

        seleccionarCaja(caja) {
            const b = this.borrador;
            b.caja.modo = 'existente';
            b.caja.id = caja.id;
            b.caja.corregir = false;
            b.caja.correccion = {};
            this.busquedaCaja = '';
        },

        registrarCajaNueva() {
            const b = this.borrador;
            b.caja.modo = 'nueva';
            b.caja.id = null;
            b.caja.nuevo.numero_economico ||= texto(this.busquedaCaja).toUpperCase();
            this.busquedaCaja = '';
        },

        cambiarCaja() {
            this.borrador.caja.modo = null;
            this.borrador.caja.id = null;
        },

        corregirCaja() {
            const c = this.caja;
            const b = this.borrador.caja;
            b.corregir = !b.corregir;
            b.correccion = b.corregir
                ? {
                    tipo_caja_id: c.tipo_caja_id ?? '', placa: c.placa ?? '', estado_placa: c.estado_placa ?? '',
                    marca: c.marca ?? '', modelo: c.modelo ?? '', vin: c.vin ?? '', anio: c.anio ?? '', tamano_pies: c.tamano_pies ?? '',
                }
                : {};
        },

        get duenoCaja() {
            const b = this.borrador;
            if (b.caja.modo === 'existente') return this.nombreDe('clientes', this.caja?.cliente_id);
            if (b.caja.modo === 'nueva') {
                return b.caja.nuevo.cliente_id === '__nuevo' ? texto(b.caja.nuevo.cliente_nuevo.nombre) : this.nombreDe('clientes', b.caja.nuevo.cliente_id);
            }
            return '';
        },

        get etiquetaCaja() {
            const b = this.borrador;
            if (b.caja.modo === 'existente' && this.caja) return this.caja.numero_economico;
            if (b.caja.modo === 'nueva') return texto(b.caja.nuevo.numero_economico) || 'Caja nueva';
            return '';
        },

        // Último intercambio completado de la caja con cada movimiento.
        get historialCaja() {
            return this.borrador?.caja.modo === 'existente' ? this.paquete?.historial_cajas?.[this.borrador.caja.id] ?? {} : {};
        },

        get advertenciasCaja() {
            const avisos = [];
            const historial = this.historialCaja;
            const ultimo = [historial.entrada, historial.salida].filter(Boolean)
                .sort((a, b) => b.fecha_hora.localeCompare(a.fecha_hora))[0];

            if (ultimo && ((historial.entrada === ultimo && this.borrador.tipo_movimiento === 'entrada') || (historial.salida === ultimo && this.borrador.tipo_movimiento === 'salida'))) {
                avisos.push(`El último movimiento de esta caja también fue una ${this.borrador.tipo_movimiento} (${ultimo.folio}, ${fechaCorta(ultimo.fecha_hora)}).`);
            }

            if (this.borrador.caja.modo === 'existente') {
                const otros = this.borradores.filter((b) => b.id !== this.borrador.id && b.estado === 'editando' && b.caja?.modo === 'existente' && b.caja.id === this.borrador.caja.id);
                if (otros.length) avisos.push('Esta caja tiene otro borrador abierto en este dispositivo.');
            }

            return avisos;
        },

        get relacionado() {
            const contrario = this.borrador?.tipo_movimiento === 'entrada' ? 'salida' : 'entrada';
            return this.historialCaja[contrario] ?? null;
        },

        // ---------- Paso 3: tractocamión ----------

        get tracto() {
            return this.borrador?.tracto.modo === 'existente' ? this.porId('tractos', this.borrador.tracto.id) : null;
        },

        get tractosFiltrados() {
            const q = normalizar(this.busquedaTracto);
            if (!q) return [];
            return (this.paquete?.tractos ?? [])
                .filter((t) => normalizar(t.numero_economico).includes(q) || normalizar(t.placa).includes(q))
                .slice(0, 20);
        },

        seleccionarTracto(tracto) {
            const b = this.borrador;
            b.tracto.modo = 'existente';
            b.tracto.id = tracto.id;
            b.tracto.corregir = false;
            b.tracto.correccion = {};
            this.busquedaTracto = '';
        },

        registrarTractoNuevo() {
            const b = this.borrador;
            b.tracto.modo = 'nuevo';
            b.tracto.id = null;
            b.tracto.nuevo.numero_economico ||= texto(this.busquedaTracto).toUpperCase();
            this.busquedaTracto = '';
        },

        cambiarTracto() {
            this.borrador.tracto.modo = null;
            this.borrador.tracto.id = null;
        },

        corregirTracto() {
            const t = this.tracto;
            const b = this.borrador.tracto;
            b.corregir = !b.corregir;
            b.correccion = b.corregir
                ? { placa: t.placa ?? '', estado_placa: t.estado_placa ?? '', marca: t.marca ?? '', modelo: t.modelo ?? '', vin: t.vin ?? '', anio: t.anio ?? '' }
                : {};
        },

        get etiquetaTracto() {
            const b = this.borrador;
            if (b.tracto.modo === 'existente' && this.tracto) return this.tracto.numero_economico;
            if (b.tracto.modo === 'nuevo') return texto(b.tracto.nuevo.numero_economico) || 'Tracto nuevo';
            return '';
        },

        get lineaTracto() {
            const b = this.borrador;
            if (b.tracto.modo === 'existente') return this.nombreDe('lineas', this.tracto?.linea_transportista_id);
            if (b.tracto.modo === 'nuevo' && b.tracto.nuevo.es_permisionario) {
                return b.tracto.nuevo.linea_id === '__nueva' ? texto(b.tracto.nuevo.linea_nueva.nombre) : this.nombreDe('lineas', b.tracto.nuevo.linea_id);
            }
            return '';
        },

        // ---------- Paso 4 y 5: viaje y sello ----------

        elegirCarga(estado) {
            const b = this.borrador;
            b.estado_carga = estado;

            // Si la caja pasa a vacía se limpia el sello y se borran sus fotos.
            if (estado === 'vacia') {
                b.numero_sello = '';
                b.fotos.filter((f) => f.tipo === 'sello').forEach((f) => this.quitarFoto(f.uuid, false));
            }
        },

        get clienteViaje() {
            const b = this.borrador;
            if (b.cliente_id === '__nuevo') return texto(b.cliente_nuevo.nombre);
            if (b.cliente_id) return this.nombreDe('clientes', b.cliente_id);
            return this.duenoCaja ? `${this.duenoCaja} (dueño de la caja)` : '';
        },

        lugar(campo) {
            const b = this.borrador;
            return b[`${campo}_id`] === '__nuevo' ? texto(b[`${campo}_nuevo`]) : this.nombreDe('destinos', b[`${campo}_id`]);
        },

        // ---------- Fotos ----------

        fotosDe(filtro) {
            return (this.borrador?.fotos ?? []).filter((f) => Object.entries(filtro).every(([k, v]) => String(f[k] ?? '') === String(v ?? '')));
        },

        urlFoto(foto) {
            if (!urls.has(foto.uuid) && foto.blob) urls.set(foto.uuid, URL.createObjectURL(window.Alpine.raw(foto).blob));
            return urls.get(foto.uuid);
        },

        liberarUrls() {
            urls.forEach((url) => URL.revokeObjectURL(url));
            urls.clear();
        },

        async tomarFoto(evento, meta) {
            const archivos = [...(evento.target.files ?? [])];
            evento.target.value = '';
            if (!archivos.length) return;

            this.procesandoFoto = true;
            try {
                for (const archivo of archivos) {
                    if (meta.tipo === 'sello' && this.fotosDe({ tipo: 'sello' }).length >= (this.config.max_fotos_sello ?? 2)) break;

                    // Cada vista de la unidad lleva una sola foto: tomarla de nuevo reemplaza la anterior.
                    if (meta.tipo === 'recorrido') {
                        const previa = this.fotoDeVista(meta.vista);
                        if (previa) this.quitarFoto(previa.uuid, false);
                    }

                    const blob = await comprimirFoto(archivo, this.config.fotos?.lado_maximo ?? 1600, this.config.fotos?.calidad ?? 0.7);
                    this.borrador.fotos.push({
                        uuid: nuevoId(),
                        tipo: meta.tipo,
                        punto_inspeccion_id: meta.punto_inspeccion_id ?? null,
                        posicion: meta.posicion ?? null,
                        unidad: meta.unidad ?? null,
                        vista: meta.vista ?? null,
                        blob,
                    });
                }
            } catch (error) {
                this.avisar(error.message || 'No se pudo procesar la foto.');
            } finally {
                this.procesandoFoto = false;
            }

            this.subirPendientes();

            // Si había avisos de este paso, se actualizan (por ejemplo, "faltan fotos").
            if (this.errores.length) this.errores = this.validarPaso(this.borrador.paso);

            // En el recorrido, la pantalla baja a la siguiente foto pendiente.
            if (meta.tipo === 'recorrido') {
                const siguiente = this.vistasRecorrido[this.indiceSiguienteVista];
                if (siguiente) this.$nextTick(() => document.getElementById('vista-' + siguiente.clave)?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        },

        quitarFoto(uuid, preguntar = true) {
            if (preguntar && !confirm('¿Quitar esta foto?')) return;
            const url = urls.get(uuid);
            if (url) URL.revokeObjectURL(url);
            urls.delete(uuid);
            this.borrador.fotos = this.borrador.fotos.filter((f) => f.uuid !== uuid);
        },

        /*
         * Subida anticipada: con señal, cada foto se sube en cuanto se toma (de 3 en 3), mientras el
         * guardia sigue capturando. Al completar, el service worker solo sube las que falten.
         * Si después se reemplaza o quita una foto ya subida, el servidor la borra al registrar.
         */
        async subirPendientes() {
            const borrador = this.borrador;
            if (!borrador || borrador.estado !== 'editando' || !navigator.onLine) return;

            const pendientes = borrador.fotos.filter((f) => !f.subida && !subiendo.has(f.uuid));
            const trabajador = async () => {
                while (pendientes.length && navigator.onLine) {
                    const foto = pendientes.shift();
                    if (borrador.fotos.includes(foto)) await this.subirFoto(borrador, foto);
                }
            };
            await Promise.all([trabajador(), trabajador(), trabajador()]);
        },

        async subirFoto(borrador, foto) {
            subiendo.add(foto.uuid);
            foto.subiendo = true;
            foto.error_subida = false;

            try {
                const formulario = new FormData();
                formulario.append('intercambio', borrador.id);
                formulario.append('uuid', foto.uuid);
                formulario.append('tipo', foto.tipo);
                if (foto.vista) formulario.append('vista', foto.vista);
                formulario.append('foto', window.Alpine.raw(foto).blob, `${foto.uuid}.jpg`);

                let respuesta = await this.enviarFoto(formulario);
                if (respuesta.status === 419) {
                    tokenCsrf = null; // token vencido: se pide otro y se reintenta una vez
                    respuesta = await this.enviarFoto(formulario);
                }

                if (!respuesta.ok) throw new Error();

                foto.subida = true;
                borrador.se_envio = true; // si se descarta, hay que borrar sus fotos del servidor
                if (this.borrador === borrador) this.guardarBorrador();
                else await this.marcarSubidaGuardada(borrador.id, foto.uuid);
            } catch {
                foto.error_subida = true; // se reintenta al tomar otra foto, al volver la señal o al completar
            } finally {
                subiendo.delete(foto.uuid);
                foto.subiendo = false;
            }
        },

        async enviarFoto(formulario) {
            tokenCsrf ??= await fetch('/caseta/csrf', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
                .then((r) => (r.ok && !r.redirected ? r.json() : {}))
                .then((d) => d.token ?? null);

            return fetch('/admin/intercambios/fotos', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': tokenCsrf ?? '' },
                body: formulario,
            });
        },

        // La subida terminó cuando el guardia ya había salido de ese borrador: se anota en el guardado.
        async marcarSubidaGuardada(id, uuid) {
            const guardado = await almacen.obtenerBorrador(id);
            const foto = guardado?.fotos?.find((f) => f.uuid === uuid);
            if (!foto) return;
            foto.subida = true;
            guardado.se_envio = true;
            await almacen.guardarBorrador(guardado);
        },

        estadoSubida(foto) {
            if (foto.subida) return 'lista';
            if (foto.subiendo) return 'subiendo';
            return foto.error_subida ? 'error' : 'pendiente';
        },

        // ---------- Paso 6: llantas ----------

        // Marca y tipo del último intercambio del mismo tracto (1-10) y de la misma caja (11-18).
        precargarLlantas() {
            const b = this.borrador;
            const cajaId = b.caja.modo === 'existente' ? b.caja.id : null;
            const tractoId = b.tracto.modo === 'existente' ? b.tracto.id : null;
            const aplicar = (fuente) => {
                Object.entries(fuente ?? {}).forEach(([posicion, previa]) => {
                    const llanta = b.llantas[posicion];
                    if (!llanta) return;
                    if (!texto(llanta.marca) && previa.marca) llanta.marca = previa.marca;
                    if (!llanta.tipo && previa.tipo) llanta.tipo = previa.tipo;
                });
            };

            if (cajaId && b.llantas_precarga.caja !== cajaId) {
                aplicar(this.paquete?.llantas_cajas?.[cajaId]);
                b.llantas_precarga.caja = cajaId;
            }
            if (tractoId && b.llantas_precarga.tracto !== tractoId) {
                aplicar(this.paquete?.llantas_tractos?.[tractoId]);
                b.llantas_precarga.tracto = tractoId;
            }
        },

        colorLlanta(posicion) {
            const llanta = this.borrador?.llantas[posicion];
            if (!llanta || !esEntero(llanta.profundidad_32, 0, 32)) return null;

            const config = this.config.llantas ?? { direccionales: { posiciones: [1, 2], minimo: 4, alerta: 6 }, resto: { minimo: 2, alerta: 4 } };
            const umbral = config.direccionales.posiciones.includes(Number(posicion)) ? config.direccionales : config.resto;
            const profundidad = Number(llanta.profundidad_32);

            if (profundidad < umbral.minimo) return 'rojo';
            if (profundidad <= umbral.alerta) return 'amarillo';
            return 'verde';
        },

        get llantaPorDefecto() {
            return this.config.llantas?.por_defecto ?? { profundidad_32: 4, psi: 100 };
        },

        // Una llanta está medida si tiene profundidad o PSI; el dato que falte usa el valor por defecto.
        llantaMedida(posicion) {
            const llanta = this.borrador.llantas[posicion];
            return texto(llanta.profundidad_32) !== '' || texto(llanta.psi) !== '';
        },

        get llantasCapturadas() {
            return Object.keys(this.borrador?.llantas ?? {}).filter((p) => this.llantaMedida(p)).length;
        },

        // Dónde está la llanta: unidad, eje, lado y, en ejes dobles, si es la exterior o la interior.
        ubicacionLlanta(posicion) {
            const p = Number(posicion);
            const eje = EJES.find((e) => e.posiciones.includes(p));
            if (!eje) return { unidad: '', eje: '', lado: '' };

            const j = eje.posiciones.indexOf(p);
            const lado = j < 2 ? 'Lado izquierdo (chofer)' : 'Lado derecho';
            const fila = eje.nombre === 'Direccional' ? '' : (j === 0 || j === 3 ? ' · exterior' : ' · interior');

            return { unidad: eje.unidad, eje: NOMBRES_EJE[eje.nombre], lado: lado + fila };
        },

        descripcionLlanta(posicion) {
            const u = this.ubicacionLlanta(posicion);
            return `${u.unidad} · ${u.eje} · ${u.lado}`;
        },

        get llantasConAlerta() {
            return Object.keys(this.borrador?.llantas ?? {})
                .map(Number)
                .filter((p) => ['rojo', 'amarillo'].includes(this.colorLlanta(p)));
        },

        abrirLlanta(posicion) {
            this.llantaAbierta = posicion;
        },

        // Pasa a la llanta siguiente; después de la 18 se cierra la ventana.
        siguienteLlanta() {
            this.llantaAbierta = this.llantaAbierta < 18 ? this.llantaAbierta + 1 : null;
        },

        // ---------- Paso 7: revisión ----------

        puntosDe(seccion) {
            return (this.paquete?.puntos ?? []).filter((p) => p.seccion === seccion && !p.parent_id);
        },

        subpuntosDe(id) {
            return (this.paquete?.puntos ?? []).filter((p) => p.parent_id === id);
        },

        // Todos los puntos activos empiezan sin problema: "cumple", "no detectado" o "sin daño".
        prepararRevision() {
            const inicial = { deteccion: 'no_detectado', dano: 'sin_dano' };
            for (const punto of this.paquete?.puntos ?? []) {
                if (!this.borrador.revision[punto.id]) {
                    this.borrador.revision[punto.id] = { resultado: inicial[punto.tipo_respuesta] ?? 'cumple', observaciones: '' };
                }
            }
        },

        opcionesDe(punto) {
            if (punto.tipo_respuesta === 'deteccion') return [['no_detectado', 'No detectado', 'emerald'], ['detectado', 'Detectado', 'rose']];
            if (punto.tipo_respuesta === 'dano') return [['sin_dano', 'Sin daño', 'emerald'], ['golpe', 'G', 'rose'], ['corte', 'C', 'rose'], ['agujero', 'A', 'rose']];
            return [['cumple', 'Cumple', 'emerald'], ['no_cumple', 'No cumple', 'rose'], ['no_aplica', 'N/A', 'slate']];
        },

        // Lados de la caja con daño marcado (G, C o A).
        get danosMarcados() {
            return this.puntosDe('danos_caja').filter((p) => ['golpe', 'corte', 'agujero'].includes(this.borrador.revision[p.id]?.resultado));
        },

        tieneHallazgo(puntoId) {
            return ['no_cumple', 'detectado'].includes(this.borrador.revision[puntoId]?.resultado);
        },

        get hallazgos() {
            return (this.paquete?.puntos ?? []).filter((p) => this.tieneHallazgo(p.id));
        },

        // ---------- Paso 9: firmas ----------

        prepararFirmas() {
            ['firma_operador', 'firma_guardia'].forEach((campo) => {
                const lienzo = this.$refs[campo];
                if (!lienzo) return;
                prepararLienzo(lienzo, this.borrador[campo]);

                if (!lienzo.dataset.listo) {
                    habilitarFirma(lienzo, (firma) => (this.borrador[campo] = firma));
                    lienzo.dataset.listo = '1';
                }
            });
        },

        limpiarFirma(campo) {
            this.borrador[campo] = null;
            prepararLienzo(this.$refs[campo], null);
        },

        // ---------- Validación (las mismas reglas que el servidor) ----------

        validarPaso(numero) {
            const b = this.borrador;
            const e = [];

            if (numero === 1) {
                if (!b.tipo_movimiento) e.push('Elige si es entrada o salida.');
                if (!b.fecha_hora) e.push('Indica la fecha y hora.');
                else if (new Date(b.fecha_hora) > new Date(Date.now() + 10 * 60000)) e.push('La fecha y hora no puede ser futura.');
                if (!this.operador) e.push('Selecciona al operador.');
            }

            if (numero === 2) {
                if (b.caja.modo === 'existente') {
                    if (!this.caja) e.push('La caja seleccionada ya no está en el catálogo. Búscala de nuevo.');
                    if (b.caja.corregir && !texto(b.caja.correccion.placa)) e.push('La placa de la caja es obligatoria.');
                } else if (b.caja.modo === 'nueva') {
                    const n = b.caja.nuevo;
                    if (!n.cliente_id) e.push('Selecciona el dueño de la caja.');
                    if (n.cliente_id === '__nuevo' && !texto(n.cliente_nuevo.nombre)) e.push('Escribe el nombre del dueño nuevo.');
                    if (!texto(n.numero_economico)) e.push('El número económico es obligatorio.');
                    if (!texto(n.placa)) e.push('La placa es obligatoria.');
                    if (n.anio && !esEntero(n.anio, 1950, new Date().getFullYear() + 1)) e.push('El año de la caja no es válido.');
                    const repetida = n.cliente_id && n.cliente_id !== '__nuevo' && (this.paquete?.cajas ?? [])
                        .some((c) => String(c.cliente_id) === String(n.cliente_id) && normalizar(c.numero_economico) === normalizar(n.numero_economico));
                    if (repetida) e.push('Ese dueño ya tiene una caja con ese número económico. Búscala en lugar de registrarla.');
                } else {
                    e.push('Busca la caja o registra una nueva.');
                }
            }

            if (numero === 3) {
                if (b.tracto.modo === 'existente') {
                    if (!this.tracto) e.push('El tractocamión seleccionado ya no está en el catálogo. Búscalo de nuevo.');
                    if (b.tracto.corregir && !texto(b.tracto.correccion.placa)) e.push('La placa del tracto es obligatoria.');
                } else if (b.tracto.modo === 'nuevo') {
                    const n = b.tracto.nuevo;
                    if (!texto(n.numero_economico)) e.push('El número económico del tracto es obligatorio.');
                    if (!texto(n.placa)) e.push('La placa del tracto es obligatoria.');
                    if (n.es_permisionario && !n.linea_id) e.push('Selecciona la línea transportista del permisionario.');
                    if (n.es_permisionario && n.linea_id === '__nueva' && !texto(n.linea_nueva.nombre)) e.push('Escribe el nombre de la línea nueva.');
                    if (n.anio && !esEntero(n.anio, 1950, new Date().getFullYear() + 1)) e.push('El año del tracto no es válido.');
                } else {
                    e.push('Busca el tractocamión o registra uno nuevo.');
                }
                if (texto(b.odometro) && !esEntero(b.odometro, 0, 9999999)) e.push('El odómetro debe ser un número entero.');
            }

            if (numero === 4) {
                if (!b.estado_carga) e.push('Indica si la caja va cargada o vacía.');
                if (!b.tipo_intercambio) e.push('Elige el tipo de intercambio.');
                if (b.cliente_id === '__nuevo' && !texto(b.cliente_nuevo.nombre)) e.push('Escribe el nombre del cliente nuevo.');
                if (b.origen_id === '__nuevo' && !texto(b.origen_nuevo)) e.push('Escribe el nombre del origen nuevo.');
                if (b.destino_id === '__nuevo' && !texto(b.destino_nuevo)) e.push('Escribe el nombre del destino nuevo.');
            }

            if (numero === PASO_SELLO && this.aplicaSello) {
                if (!texto(b.numero_sello)) e.push('El número de sello es obligatorio.');
                const fotos = this.fotosDe({ tipo: 'sello' }).length;
                if (fotos < 1) e.push('Toma al menos una foto del sello.');
                if (fotos > (this.config.max_fotos_sello ?? 2)) e.push(`Máximo ${this.config.max_fotos_sello ?? 2} fotos del sello.`);
            }

            // Las llantas no son obligatorias; solo se revisa que lo capturado sea válido.
            if (numero === 6) {
                const psiMaximo = this.config.llantas?.psi_maximo ?? 150;
                const malas = Object.entries(b.llantas).filter(([, l]) => (texto(l.profundidad_32) !== '' && !esEntero(l.profundidad_32, 0, 32))
                    || (texto(l.psi) !== '' && !esEntero(l.psi, 0, psiMaximo))).map(([p]) => p);
                if (malas.length) e.push(`Revisa ${malas.length === 1 ? 'la llanta' : 'las llantas'} ${malas.join(', ')}: profundidad de 0 a 32 y PSI de 0 a ${psiMaximo}.`);
            }

            if (numero === 7) {
                this.prepararRevision();
                for (const punto of this.paquete?.puntos ?? []) {
                    if (this.tieneHallazgo(punto.id) && !texto(b.revision[punto.id].observaciones)) {
                        e.push(`Escribe las observaciones de «${punto.nombre}».`);
                    }
                }
            }

            // Si marcó daños, se pide describirlos (las fotos son opcionales).
            if (numero === PASO_DANOS) {
                if (this.tieneDanos('caja') && !texto(b.comentarios_caja)) e.push('Describe los daños de la caja.');
                if (this.tieneDanos('tracto') && !texto(b.comentarios_tracto)) e.push('Describe los daños del tracto.');
            }

            if (numero === PASO_RECORRIDO) {
                const faltan = this.vistasRecorrido.filter((v) => !this.fotoDeVista(v.clave)).map((v) => v.etiqueta);
                if (faltan.length > 3) e.push(`Faltan ${faltan.length} fotos de la unidad: las que no tienen palomita verde.`);
                else if (faltan.length) e.push(`Falta${faltan.length === 1 ? '' : 'n'} la${faltan.length === 1 ? '' : 's'} foto${faltan.length === 1 ? '' : 's'}: ${faltan.join(', ')}.`);
            }

            if (numero === PASO_FINAL) {
                if (!b.firma_operador) e.push('Falta la firma del operador.');
                if (!b.firma_guardia) e.push('Falta la firma del guardia.');
            }

            return e;
        },

        // ---------- Paso 8: daños ----------

        // Los borradores anteriores a esta pregunta se consideran con daños si ya tienen comentarios o fotos.
        tieneDanos(unidad) {
            const b = this.borrador;
            const respuesta = b[`danos_${unidad}`];
            if (respuesta) return respuesta === 'si';
            return Boolean(texto(b[`comentarios_${unidad}`])) || this.fotosDe({ tipo: 'problema', unidad }).length > 0
                || (unidad === 'caja' && this.danosMarcados.length > 0);
        },

        marcarDanos(unidad, valor) {
            const b = this.borrador;
            const fotos = this.fotosDe({ tipo: 'problema', unidad });

            const marcados = unidad === 'caja' ? this.danosMarcados : [];

            if (valor === 'no' && (texto(b[`comentarios_${unidad}`]) || fotos.length || marcados.length)) {
                if (!confirm(`¿Quitar la descripción, los daños marcados y las fotos ${unidad === 'caja' ? 'de la caja' : 'del tracto'}?`)) return;
                b[`comentarios_${unidad}`] = '';
                fotos.forEach((f) => this.quitarFoto(f.uuid, false));
                marcados.forEach((p) => (b.revision[p.id].resultado = 'sin_dano'));
            }

            if (unidad === 'caja') this.prepararRevision();
            b[`danos_${unidad}`] = valor;
        },

        // ---------- Paso 9: fotos de la unidad ----------

        get vistasRecorrido() {
            return this.config.recorrido ?? [];
        },

        fotoDeVista(clave) {
            return this.fotosDe({ tipo: 'recorrido', vista: clave })[0] ?? null;
        },

        get recorridoTomadas() {
            return this.vistasRecorrido.filter((v) => this.fotoDeVista(v.clave)).length;
        },

        // Las fotos se toman en orden: la siguiente es la primera que falta.
        get indiceSiguienteVista() {
            const i = this.vistasRecorrido.findIndex((v) => !this.fotoDeVista(v.clave));
            return i === -1 ? this.vistasRecorrido.length : i;
        },

        // Se puede tomar la siguiente foto pendiente o repetir una ya tomada; las demás esperan su turno.
        vistaHabilitada(indice) {
            return indice <= this.indiceSiguienteVista;
        },

        // ---------- Completar ----------

        async completar() {
            for (const paso of this.pasosVisibles) {
                const errores = this.validarPaso(paso.numero);
                if (errores.length) {
                    this.irA(paso.numero);
                    this.errores = errores;
                    return;
                }
            }

            const b = this.borrador;
            const datos = this.armarEnvio();
            const archivos = b.fotos.map((f) => ({ uuid: f.uuid, tipo: f.tipo, vista: f.vista ?? null, blob: window.Alpine.raw(f).blob, subida: f.subida === true }));

            try {
                await window.colaOffline.encolar('intercambio', datos, {
                    id: b.id,
                    extra: {
                        archivos,
                        titulo: `Intercambio · ${b.tipo_movimiento === 'entrada' ? 'Entrada' : 'Salida'}`,
                        resumen: `Caja ${this.etiquetaCaja} · Tracto ${this.etiquetaTracto}`,
                    },
                });
            } catch {
                this.errores = ['No se pudo guardar el intercambio en el dispositivo. Revisa el espacio disponible e inténtalo de nuevo.'];
                return;
            }

            b.estado = 'en_cola';
            b.se_envio = true;
            b.rechazo = null;
            await almacen.guardarBorrador(serializar(b));

            this.avisar(navigator.onLine
                ? 'Intercambio completado. Enviando…'
                : 'Intercambio completado. Se enviará solo cuando vuelva la señal.');

            this.liberarUrls();
            this.borrador = null;
            this.vista = 'inicio';
            await this.conciliar();
            window.scrollTo({ top: 0 });
        },

        armarEnvio() {
            const b = this.borrador;
            const valor = (v) => (texto(v) === '' ? null : texto(v));
            const entero = (v) => (texto(v) === '' ? null : Number(v));
            const unidad = (d) => ({
                estado_placa: valor(d.estado_placa), marca: valor(d.marca), modelo: valor(d.modelo),
                vin: valor(d.vin), anio: entero(d.anio),
            });

            const caja = b.caja.modo === 'existente'
                ? {
                    id: b.caja.id,
                    correccion: b.caja.corregir
                        ? { ...unidad(b.caja.correccion), placa: valor(b.caja.correccion.placa), tipo_caja_id: entero(b.caja.correccion.tipo_caja_id), tamano_pies: entero(b.caja.correccion.tamano_pies) }
                        : null,
                }
                : {
                    nuevo: {
                        ...unidad(b.caja.nuevo),
                        cliente_id: b.caja.nuevo.cliente_id === '__nuevo' ? null : entero(b.caja.nuevo.cliente_id),
                        cliente_nuevo: b.caja.nuevo.cliente_id === '__nuevo' ? { nombre: valor(b.caja.nuevo.cliente_nuevo.nombre), rfc: valor(b.caja.nuevo.cliente_nuevo.rfc) } : null,
                        tipo_caja_id: entero(b.caja.nuevo.tipo_caja_id),
                        numero_economico: valor(b.caja.nuevo.numero_economico),
                        placa: valor(b.caja.nuevo.placa),
                        tamano_pies: entero(b.caja.nuevo.tamano_pies),
                    },
                };

            const n = b.tracto.nuevo;
            const tracto = b.tracto.modo === 'existente'
                ? { id: b.tracto.id, correccion: b.tracto.corregir ? { ...unidad(b.tracto.correccion), placa: valor(b.tracto.correccion.placa) } : null }
                : {
                    nuevo: {
                        ...unidad(n),
                        numero_economico: valor(n.numero_economico),
                        placa: valor(n.placa),
                        es_permisionario: Boolean(n.es_permisionario),
                        linea_id: n.es_permisionario && n.linea_id !== '__nueva' ? entero(n.linea_id) : null,
                        linea_nueva: n.es_permisionario && n.linea_id === '__nueva' ? { nombre: valor(n.linea_nueva.nombre), rfc: valor(n.linea_nueva.rfc) } : null,
                    },
                };

            const lugar = (campo) => ({
                [`${campo}_id`]: b[`${campo}_id`] && b[`${campo}_id`] !== '__nuevo' ? entero(b[`${campo}_id`]) : null,
                [`${campo}_nuevo`]: b[`${campo}_id`] === '__nuevo' ? valor(b[`${campo}_nuevo`]) : null,
            });

            const idsPuntos = new Set((this.paquete?.puntos ?? []).map((p) => String(p.id)));

            return {
                tipo_movimiento: b.tipo_movimiento,
                fecha_hora: b.fecha_hora,
                operador_id: b.operador_id,
                caja,
                tracto,
                odometro: entero(b.odometro),
                estado_carga: b.estado_carga,
                tipo_intercambio: b.tipo_intercambio,
                cliente_id: b.cliente_id && b.cliente_id !== '__nuevo' ? entero(b.cliente_id) : null,
                cliente_nuevo: b.cliente_id === '__nuevo' ? { nombre: valor(b.cliente_nuevo.nombre), rfc: valor(b.cliente_nuevo.rfc) } : null,
                ...lugar('origen'),
                ...lugar('destino'),
                referencia_carta_porte: valor(b.referencia_carta_porte),
                numero_sello: this.aplicaSello ? valor(b.numero_sello) : null,
                llantas: Object.entries(b.llantas).map(([posicion, l]) => ({
                    posicion: Number(posicion),
                    marca: valor(l.marca),
                    profundidad_32: entero(l.profundidad_32) ?? this.llantaPorDefecto.profundidad_32,
                    psi: entero(l.psi) ?? this.llantaPorDefecto.psi,
                    tipo: valor(l.tipo),
                    observaciones: valor(l.observaciones),
                })),
                revision: Object.entries(b.revision)
                    .filter(([id]) => idsPuntos.has(id))
                    .map(([id, r]) => ({ punto_inspeccion_id: Number(id), resultado: r.resultado, observaciones: valor(r.observaciones) })),
                comentarios_caja: valor(b.comentarios_caja),
                comentarios_tracto: valor(b.comentarios_tracto),
                observaciones: valor(b.observaciones),
                fotos: b.fotos
                    .filter((f) => f.tipo !== 'punto' || idsPuntos.has(String(f.punto_inspeccion_id)))
                    .map((f) => ({ uuid: f.uuid, tipo: f.tipo, punto_inspeccion_id: f.punto_inspeccion_id, posicion: f.posicion, unidad: f.unidad, vista: f.vista ?? null })),
                firma_operador: b.firma_operador,
                firma_guardia: b.firma_guardia,
            };
        },

        fechaCorta,
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('asistenteIntercambio', asistenteIntercambio);
});
