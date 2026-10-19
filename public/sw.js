/*
 * Service worker de la app de caseta (Industrial Hefesto).
 *
 * - Archivos de /build (CSS/JS con hash en el nombre): caché primero; nunca cambian.
 * - Iconos, manifest y favicon: se sirven de caché y se actualizan en segundo plano.
 * - Páginas de caseta (menú, entrada, salida, QR) y el asistente de intercambios: red primero;
 *   sin señal se muestra la última copia guardada. Ninguna otra página (admin, historial, etc.)
 *   se guarda. El asistente no lleva datos en el HTML: los toma de IndexedDB.
 * - Al llegar a /login (cierre de sesión o sesión expirada) se borran las páginas guardadas.
 * - Peticiones POST, JSON y de otros dominios pasan directo a la red.
 * - Registros hechos sin conexión (IndexedDB "hefesto-caseta", ver resources/js/cola-offline.js):
 *   se envían al volver el internet, en orden, por Background Sync o a petición de la página.
 *   Un intercambio sube primero sus fotos, una por una, y después los datos.
 *
 * Al cambiar este archivo, sube VERSION para que los celulares descarguen la nueva versión.
 */
const VERSION = 'v12';
const CACHE_ESTATICOS = `hefesto-estaticos-${VERSION}`;
const CACHE_PAGINAS = `hefesto-paginas-${VERSION}`;
const MAX_ESTATICOS = 60;

const PRECACHE = [
    '/offline.html',
    '/manifest.json',
    '/favicon.ico',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png',
    '/icons/favicon-32.png',
];

const PAGINAS_CASETA = ['/caseta', '/caseta/entrada', '/caseta/salida', '/caseta/qr'];

// Páginas que se pueden abrir sin conexión (se guardan al visitarlas con señal).
const PAGINAS_SIN_CONEXION = [...PAGINAS_CASETA, '/admin/intercambios/nuevo'];

// install prepara los recursos esenciales antes de activar esta versión.
// Si falla el precache, la instalación también falla para evitar publicar una versión incompleta.
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_ESTATICOS)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

// activate elimina cachés de versiones anteriores, habilita la precarga de navegaciones
// y toma control de las pestañas abiertas para que usen inmediatamente este worker.
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const vigentes = [CACHE_ESTATICOS, CACHE_PAGINAS];
        const nombres = await caches.keys();
        await Promise.all(nombres.filter((n) => !vigentes.includes(n)).map((n) => caches.delete(n)));

        if (self.registration.navigationPreload) {
            await self.registration.navigationPreload.enable();
        }

        await self.clients.claim();
    })());
});

// El worker solo intercepta GET del mismo origen. El resto de solicitudes (en especial
// POST, APIs externas y otros dominios) siguen su flujo normal y no entran en caché.
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(navegacion(event, url));
        return;
    }

    // Los archivos compilados de Vite suelen llevar hash y son inmutables: se prefieren
    // desde caché; si no existen aún, se descargan y se guardan.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cachePrimero(request));
        return;
    }

    if (PRECACHE.includes(url.pathname) || url.pathname.startsWith('/icons/')) {
        event.respondWith(cacheYActualizar(event, request));
    }
});

// Para documentos HTML se intenta primero la red (incluida la respuesta precargada).
// Solo se conserva una respuesta válida de las pantallas permitidas de caseta; las demás
// páginas, como administración, nunca se guardan. Sin conexión se usa la copia permitida
// o, como último recurso, la página estática de modo sin conexión.
async function navegacion(event, url) {
    const esPaginaCaseta = PAGINAS_SIN_CONEXION.includes(url.pathname);

    if (url.pathname === '/login') {
        event.waitUntil(caches.delete(CACHE_PAGINAS));
    }

    try {
        const respuesta = (await event.preloadResponse) || (await fetch(event.request));

        // Solo se guardan páginas de caseta que respondieron bien (no redirecciones al login).
        if (esPaginaCaseta && respuesta.ok && respuesta.type === 'basic' && !respuesta.redirected) {
            const copia = respuesta.clone();
            event.waitUntil(caches.open(CACHE_PAGINAS).then((cache) => cache.put(url.pathname, copia)));
        }

        return respuesta;
    } catch {
        if (esPaginaCaseta) {
            const guardada = await caches.match(url.pathname, { cacheName: CACHE_PAGINAS });
            if (guardada) return guardada;
        }

        return (await caches.match('/offline.html')) || Response.error();
    }
}

// Estrategia cache-first: entrega inmediatamente una copia local de los recursos compilados.
// Un recurso nuevo se descarga, se almacena y luego se limita el tamaño de esa caché.
async function cachePrimero(request) {
    const cache = await caches.open(CACHE_ESTATICOS);
    const guardada = await cache.match(request);
    if (guardada) return guardada;

    const respuesta = await fetch(request);
    if (respuesta.ok) {
        await cache.put(request, respuesta.clone());
        recortarCache(cache);
    }

    return respuesta;
}

// Estrategia stale-while-revalidate para recursos pequeños estáticos: responde desde caché
// si es posible y, en paralelo, intenta actualizar la copia para la próxima visita.
async function cacheYActualizar(event, request) {
    const cache = await caches.open(CACHE_ESTATICOS);
    const guardada = await cache.match(request);

    const actualizacion = fetch(request).then((respuesta) => {
        if (respuesta.ok) cache.put(request, respuesta.clone());
        return respuesta;
    });

    if (guardada) {
        event.waitUntil(actualizacion.catch(() => {}));
        return guardada;
    }

    return actualizacion;
}

// Evita que se acumulen versiones viejas de los archivos de /build entre despliegues.
async function recortarCache(cache) {
    const claves = await cache.keys();
    const builds = claves.filter((r) => new URL(r.url).pathname.startsWith('/build/'));
    const sobrantes = builds.length - MAX_ESTATICOS;

    for (let i = 0; i < sobrantes; i++) {
        await cache.delete(builds[i]);
    }
}

/* ------------------------------------------------------------------------------------------
 * Envío de registros guardados sin conexión
 * ---------------------------------------------------------------------------------------- */

const ETIQUETA_SYNC = 'enviar-registros';
const URL_ENVIO = {
    entrada: '/caseta/visitas/entrada',
    salida: '/caseta/visitas/salida',
    intercambio: '/admin/intercambios',
    'descartar-intercambio': '/admin/intercambios/descartar',
};
const URL_FOTOS_INTERCAMBIO = '/admin/intercambios/fotos';

// Folios asignados en el envío actual, para avisar a la página del asistente.
let foliosEnviados = [];

class SinConexion extends Error {}

// Background Sync permite reanudar el envío cuando el navegador detecta que vuelve la red.
self.addEventListener('sync', (event) => {
    if (event.tag === ETIQUETA_SYNC) {
        // Si no hay señal se lanza el error para que el navegador vuelva a intentar más tarde.
        event.waitUntil(enviarRegistros());
    }
});

// La página también puede pedir un envío inmediato o solicitar que se precarguen
// las pantallas de caseta para poder abrirlas posteriormente sin conexión.
self.addEventListener('message', (event) => {
    if (event.data?.tipo === 'sincronizar') {
        event.waitUntil(enviarRegistros().catch(() => {}));
    }

    if (event.data?.tipo === 'guardar-paginas') {
        event.waitUntil(guardarPaginasCaseta());
    }
});

// Guarda las pantallas de caseta para poder abrirlas sin señal aunque no se hayan visitado.
async function guardarPaginasCaseta() {
    const cache = await caches.open(CACHE_PAGINAS);

    await Promise.all(PAGINAS_CASETA.map(async (ruta) => {
        try {
            const respuesta = await fetch(ruta, { credentials: 'same-origin', cache: 'no-store' });
            if (respuesta.ok && !respuesta.redirected) await cache.put(ruta, respuesta);
        } catch {
            // Sin señal: se intentará la próxima vez.
        }
    }));
}

let envioEnCurso = null;

function enviarRegistros() {
    // Un solo envío a la vez, aunque lo pidan varias pestañas o el sync y la página juntos.
    envioEnCurso ??= procesarCola().finally(() => {
        envioEnCurso = null;
    });

    return envioEnCurso;
}

// Lee la cola local en orden cronológico, obtiene un token CSRF vigente y procesa
// cada registro secuencialmente para conservar el orden de entradas y salidas.
async function procesarCola() {
    const todos = await colaListar();
    const pendientes = todos.filter((r) => r.estado === 'pendiente');
    // Ids que siguen en el celular; una salida cuya entrada aún no se envía debe esperar.
    const enCola = new Set(todos.map((r) => r.id));

    if (pendientes.length === 0) {
        await avisarPaginas({ enviados: 0 });
        return;
    }

    let enviados = 0;
    let cancelados = 0;
    let sesionExpirada = false;
    foliosEnviados = [];

    try {
        const token = await obtenerToken();

        if (!token) {
            sesionExpirada = true;
            return;
        }

        for (const registro of pendientes) {
            if (registro.tipo === 'salida' && enCola.has(registro.datos.entrada_id_cliente)) {
                continue;
            }

            const resultado = await enviarRegistro(registro, token);

            if (resultado === 'enviado' || resultado === 'cancelado') {
                enCola.delete(registro.id);
            }

            if (resultado === 'enviado') {
                enviados++;
            } else if (resultado === 'cancelado') {
                cancelados++;
            } else if (resultado === 'sesion') {
                sesionExpirada = true;
                break;
            } else if (resultado === 'reintentar') {
                throw new SinConexion();
            }
        }
    } finally {
        await avisarPaginas({ enviados, cancelados, sesionExpirada, folios: foliosEnviados });
    }
}

async function obtenerToken() {
    let respuesta;

    try {
        respuesta = await fetch('/caseta/csrf', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
    } catch {
        throw new SinConexion();
    }

    if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) return null;
    if (!respuesta.ok) throw new SinConexion();

    return (await respuesta.json()).token;
}

/**
 * Envía un registro y actualiza la cola según la respuesta.
 * Devuelve 'enviado', 'cancelado' (repetido), 'atencion' (el guardia debe revisarlo), 'sesion' o 'reintentar'.
 */
async function enviarRegistro(registro, token) {
    if (registro.tipo === 'intercambio') {
        const resultadoFotos = await subirFotosIntercambio(registro, token);
        if (resultadoFotos) return resultadoFotos;
    }

    let respuesta;

    try {
        respuesta = await fetch(URL_ENVIO[registro.tipo], {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({
                ...registro.datos,
                id_cliente: registro.id,
                registrado_en: registro.creado_en,
            }),
        });
    } catch {
        return 'reintentar';
    }

    if (respuesta.ok) {
        await colaEliminar(registro.id);
        const cuerpo = await respuesta.json().catch(() => ({}));

        if (cuerpo.folio) foliosEnviados.push({ id: registro.id, folio: cuerpo.folio });

        // Registro repetido (por ejemplo, una salida escaneada dos veces): se conservó el primero.
        return cuerpo.cancelado ? 'cancelado' : 'enviado';
    }

    return resultadoDeError(registro, respuesta);
}

/**
 * Sube las fotos de un intercambio que falten. Cada foto subida se marca en la cola, para no
 * repetirla si se corta la señal a la mitad. Devuelve null si todas quedaron arriba, o el
 * resultado del error (igual que enviarRegistro).
 */
async function subirFotosIntercambio(registro, token) {
    // Casi siempre ya se subieron mientras se capturaba; aquí van las que falten, de 3 en 3.
    const pendientes = (registro.archivos ?? []).filter((archivo) => !archivo.subida);
    let fallo = null;

    const subirUna = async (archivo) => {
        const formulario = new FormData();
        formulario.append('intercambio', registro.id);
        formulario.append('uuid', archivo.uuid);
        formulario.append('tipo', archivo.tipo);
        if (archivo.vista) formulario.append('vista', archivo.vista);
        formulario.append('foto', archivo.blob, `${archivo.uuid}.jpg`);

        let respuesta;

        try {
            respuesta = await fetch(URL_FOTOS_INTERCAMBIO, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: formulario,
            });
        } catch {
            fallo ??= 'reintentar';
            return;
        }

        if (!respuesta.ok) {
            fallo ??= await resultadoDeError(registro, respuesta);
            return;
        }

        archivo.subida = true;
        await colaGuardar(registro);
    };

    const trabajador = async () => {
        while (pendientes.length && !fallo) await subirUna(pendientes.shift());
    };
    await Promise.all([trabajador(), trabajador(), trabajador()]);

    return fallo;
}

// Interpreta una respuesta de error del servidor y actualiza el registro en la cola.
async function resultadoDeError(registro, respuesta) {
    if (respuesta.status === 401 || respuesta.status === 419) return 'sesion';

    let datos = {};
    try {
        datos = await respuesta.json();
    } catch {
        // Respuesta no JSON (por ejemplo, la página de error de un proxy).
    }

    // Intercambio cuyas fotos no aparecen en el servidor: se vuelven a subir todas en el siguiente intento.
    if (respuesta.status === 409 && registro.tipo === 'intercambio') {
        (registro.archivos ?? []).forEach((archivo) => (archivo.subida = false));
        await colaGuardar(registro);
        return 'reintentar';
    }

    if (respuesta.status === 409 && datos.requiere_seleccion) {
        await colaGuardar({ ...registro, estado: 'requiere_seleccion', mensaje: datos.message, visitas: datos.visitas });
        return 'atencion';
    }

    if (respuesta.status === 422 || respuesta.status === 404 || respuesta.status === 403) {
        const errores = datos.errors ? Object.values(datos.errors).flat() : [];
        await colaGuardar({ ...registro, estado: 'error', mensaje: errores[0] || datos.message || 'El servidor rechazó el registro.' });
        return 'atencion';
    }

    // 423 (se está procesando), 5xx o errores del proxy: se reintenta después.
    return 'reintentar';
}

async function avisarPaginas(resumen) {
    const paginas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    paginas.forEach((pagina) => pagina.postMessage({ tipo: 'cola', ...resumen }));
}

// Persistencia local de la cola en IndexedDB. La estructura debe coincidir con
// resources/js/cola-offline.js, que agrega los registros cuando la página está sin red.

// Abre la base compartida por la página y el service worker; crea el almacén al inicializarla.
function colaAbrir() {
    return new Promise((resolve, reject) => {
        const solicitud = indexedDB.open('hefesto-caseta', 1);
        solicitud.onupgradeneeded = () => solicitud.result.createObjectStore('registros', { keyPath: 'id' });
        solicitud.onsuccess = () => resolve(solicitud.result);
        solicitud.onerror = () => reject(solicitud.error);
    });
}

// Ejecuta una operación y espera a que la transacción termine antes de cerrar la conexión.
async function colaOperacion(modo, accion) {
    const db = await colaAbrir();

    return new Promise((resolve, reject) => {
        const tx = db.transaction('registros', modo);
        const peticion = accion(tx.objectStore('registros'));
        tx.oncomplete = () => {
            db.close();
            resolve(peticion?.result);
        };
        tx.onerror = () => reject(tx.error);
    });
}

// Recupera los registros pendientes y los ordena por hora de creación para enviarlos en secuencia.
async function colaListar() {
    const registros = (await colaOperacion('readonly', (almacen) => almacen.getAll())) || [];
    return registros.sort((a, b) => a.creado_en.localeCompare(b.creado_en));
}

const colaGuardar = (registro) => colaOperacion('readwrite', (almacen) => almacen.put(registro));
const colaEliminar = (id) => colaOperacion('readwrite', (almacen) => almacen.delete(id));
