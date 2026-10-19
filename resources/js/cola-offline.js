/*
 * Cola de registros de caseta hechos sin conexión.
 *
 * Los registros se guardan en IndexedDB (base "hefesto-caseta", la misma que lee public/sw.js)
 * y el service worker los envía cuando vuelve el internet: con Background Sync en Android
 * (aunque la app esté cerrada) y, en cualquier navegador, cada vez que la app está abierta
 * y hay señal. Cada registro lleva un id único para que el servidor no lo duplique.
 */
import { almacen as almacenIntercambios } from './intercambio-almacen.js';

const BASE = 'hefesto-caseta';
const ALMACEN = 'registros';
const ETIQUETA_SYNC = 'enviar-registros';

function abrirBase() {
    return new Promise((resolve, reject) => {
        const solicitud = indexedDB.open(BASE, 1);
        solicitud.onupgradeneeded = () => solicitud.result.createObjectStore(ALMACEN, { keyPath: 'id' });
        solicitud.onsuccess = () => resolve(solicitud.result);
        solicitud.onerror = () => reject(solicitud.error);
    });
}

async function operacion(modo, accion) {
    const db = await abrirBase();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(ALMACEN, modo);
        const peticion = accion(tx.objectStore(ALMACEN));
        tx.oncomplete = () => {
            db.close();
            resolve(peticion?.result);
        };
        tx.onerror = () => reject(tx.error);
    });
}

async function listar() {
    const registros = (await operacion('readonly', (almacen) => almacen.getAll())) || [];
    return registros.sort((a, b) => a.creado_en.localeCompare(b.creado_en));
}

const guardar = (registro) => operacion('readwrite', (almacen) => almacen.put(registro));
const eliminar = (id) => operacion('readwrite', (almacen) => almacen.delete(id));

function nuevoId() {
    if (crypto.randomUUID) return crypto.randomUUID();

    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)
    );
}

async function pedirEnvio() {
    if (!('serviceWorker' in navigator)) return;

    const registro = await navigator.serviceWorker.ready;

    try {
        if ('sync' in registro) await registro.sync.register(ETIQUETA_SYNC);
    } catch {
        // Background Sync no disponible (iPhone, Firefox): se envía mientras la app esté abierta.
    }

    if (navigator.onLine && registro.active) {
        registro.active.postMessage({ tipo: 'sincronizar' });
    }
}

// Copia de un registro de la cola. structuredClone conserva los Blob de las fotos de intercambios
// (JSON.stringify los perdería); Alpine.raw quita el proxy reactivo, que no se puede clonar.
function copiar(registro) {
    return structuredClone(window.Alpine?.raw(registro) ?? registro);
}

/**
 * Guarda un registro para enviarlo después. `id` y `creado_en` deben ser los mismos que se
 * usaron en el intento de envío en línea, para que el servidor detecte duplicados.
 * `extra` agrega campos fuera de `datos` (por ejemplo, las fotos de un intercambio y su resumen).
 */
async function encolar(tipo, datos, { id = nuevoId(), creadoEn = new Date().toISOString(), extra = {} } = {}) {
    // Quién hizo el registro (id cifrado por el servidor), por si se envía con la sesión de otra persona.
    const registradoPor = document.querySelector('meta[name="registrado-por"]')?.content;
    const registro = {
        id,
        tipo,
        datos: { ...datos, registrado_por: datos.registrado_por ?? registradoPor },
        creado_en: creadoEn,
        estado: 'pendiente',
        mensaje: null,
        visitas: null,
        ...extra,
    };
    await guardar(registro);
    await window.Alpine?.store('cola')?.refrescar();
    pedirEnvio();

    return registro;
}

/**
 * Un intercambio descartado pudo dejar fotos en el servidor (se suben antes que los datos).
 * Se encola la orden de borrarlas, para que se cumpla aunque ahora no haya señal.
 */
function limpiarIntercambio(idIntercambio) {
    return encolar('descartar-intercambio', { intercambio: idIntercambio }, {
        extra: { titulo: 'Intercambio descartado', resumen: 'Borrar sus fotos del servidor' },
    });
}

window.colaOffline = { encolar, nuevoId, pedirEnvio, quitar: eliminar, limpiarIntercambio };

document.addEventListener('alpine:init', () => {
    window.Alpine.store('cola', {
        registros: [],
        sesionExpirada: false,
        aviso: null,
        abierta: false,

        get pendientes() {
            return this.registros.filter((r) => r.estado === 'pendiente').length;
        },

        get conProblema() {
            return this.registros.filter((r) => r.estado !== 'pendiente').length;
        },

        async refrescar() {
            try {
                this.registros = await listar();
            } catch {
                this.registros = [];
            }
        },

        avisar(mensaje) {
            this.aviso = mensaje;
            clearTimeout(this._temporizador);
            this._temporizador = setTimeout(() => (this.aviso = null), 5000);
        },

        // Salidas sin enviar que dependen de una entrada que tampoco se ha enviado.
        salidasDeEntrada(idEntrada) {
            return this.registros.filter((r) => r.tipo === 'salida' && r.datos.entrada_id_cliente === idEntrada);
        },

        async descartar(id) {
            const registro = this.registros.find((r) => r.id === id);
            const dependientes = this.salidasDeEntrada(id);
            const mensaje = registro?.tipo === 'intercambio'
                ? '¿Descartar este intercambio? Se borrarán sus datos y fotos; no se registrará en el sistema.'
                : dependientes.length
                    ? '¿Descartar esta entrada? También se descartará su salida sin enviar. No se enviarán al sistema.'
                    : '¿Descartar este registro? No se enviará al sistema.';

            if (!confirm(mensaje)) return false;

            await eliminar(id);
            for (const salida of dependientes) await eliminar(salida.id);

            if (registro?.tipo === 'intercambio') {
                try {
                    await almacenIntercambios.eliminarBorrador(id);
                } catch {
                    // Sin borrador guardado: no importa.
                }
                await limpiarIntercambio(id);
            }

            await this.refrescar();
            window.dispatchEvent(new CustomEvent('cola-sincronizada'));

            return true;
        },

        async reintentar(registro) {
            await guardar({ ...copiar(registro), estado: 'pendiente', mensaje: null });
            await this.refrescar();
            pedirEnvio();
        },

        // Salida que encontró varias personas de la empresa dentro: el guardia elige quién salió.
        async elegirVisita(registro, visitaId) {
            const copia = copiar(registro);
            copia.datos.visita_id = visitaId;
            copia.estado = 'pendiente';
            copia.mensaje = null;
            copia.visitas = null;
            await guardar(copia);
            await this.refrescar();
            pedirEnvio();
        },
    });
});

// Resultado de cada envío hecho por el service worker.
navigator.serviceWorker?.addEventListener('message', async (evento) => {
    if (evento.data?.tipo !== 'cola') return;

    const cola = window.Alpine?.store('cola');
    if (!cola) return;

    await cola.refrescar();
    cola.sesionExpirada = Boolean(evento.data.sesionExpirada);

    const partes = [];
    const n = evento.data.enviados || 0;
    const c = evento.data.cancelados || 0;

    if (n > 0) partes.push(n === 1 ? 'Se envió 1 registro guardado sin conexión.' : `Se enviaron ${n} registros guardados sin conexión.`);
    if (c > 0) partes.push(c === 1 ? '1 salida repetida se canceló (se conservó la primera).' : `${c} salidas repetidas se cancelaron (se conservó la primera).`);
    if (partes.length) {
        cola.avisar(partes.join(' '));
        window.dispatchEvent(new CustomEvent('cola-sincronizada'));
    }

    // Intercambios que ya recibieron folio en el servidor.
    if (evento.data.folios?.length) {
        window.dispatchEvent(new CustomEvent('intercambios-enviados', { detail: evento.data.folios }));
    }
});

window.addEventListener('online', pedirEnvio);

window.addEventListener('load', async () => {
    await window.Alpine?.store('cola')?.refrescar();
    if (window.Alpine?.store('cola')?.registros.length) pedirEnvio();
});

// Mientras la app está abierta, reintenta cada 30 s si queda algo pendiente.
setInterval(() => {
    if (navigator.onLine && window.Alpine?.store('cola')?.pendientes > 0) pedirEnvio();
}, 30000);

// Una vez por sesión del navegador, con señal: guarda las pantallas de caseta para uso sin conexión.
window.addEventListener('load', async () => {
    if (!navigator.onLine || !('serviceWorker' in navigator)) return;

    try {
        if (sessionStorage.getItem('caseta-paginas-guardadas')) return;
        const registro = await navigator.serviceWorker.ready;
        registro.active?.postMessage({ tipo: 'guardar-paginas' });
        sessionStorage.setItem('caseta-paginas-guardadas', '1');
    } catch {
        // sessionStorage no disponible: no es crítico.
    }
});
