/*
 * Almacén local del asistente de intercambios (IndexedDB "hefesto-intercambios").
 *
 * - datos: catálogos, operadores e historial descargados del servidor, para trabajar sin señal.
 * - borradores: intercambios en captura, con sus fotos (Blob). Viven solo en este dispositivo
 *   hasta que se completan y pasan a la cola de envío (resources/js/cola-offline.js).
 */
const BASE = 'hefesto-intercambios';
const VERSION = 1;

function abrir() {
    return new Promise((resolve, reject) => {
        const solicitud = indexedDB.open(BASE, VERSION);
        solicitud.onupgradeneeded = () => {
            const db = solicitud.result;
            if (!db.objectStoreNames.contains('datos')) db.createObjectStore('datos');
            if (!db.objectStoreNames.contains('borradores')) db.createObjectStore('borradores', { keyPath: 'id' });
        };
        solicitud.onsuccess = () => resolve(solicitud.result);
        solicitud.onerror = () => reject(solicitud.error);
    });
}

async function operacion(almacen, modo, accion) {
    const db = await abrir();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(almacen, modo);
        const peticion = accion(tx.objectStore(almacen));
        tx.oncomplete = () => {
            db.close();
            resolve(peticion?.result);
        };
        tx.onerror = () => reject(tx.error);
    });
}

export const almacen = {
    leerPaquete: () => operacion('datos', 'readonly', (s) => s.get('paquete')),
    guardarPaquete: (paquete) => operacion('datos', 'readwrite', (s) => s.put(paquete, 'paquete')),
    borrarPaquete: () => operacion('datos', 'readwrite', (s) => s.delete('paquete')),

    async listarBorradores() {
        const borradores = (await operacion('borradores', 'readonly', (s) => s.getAll())) || [];
        return borradores.sort((a, b) => (b.actualizado_en || '').localeCompare(a.actualizado_en || ''));
    },
    obtenerBorrador: (id) => operacion('borradores', 'readonly', (s) => s.get(id)),
    guardarBorrador: (borrador) => operacion('borradores', 'readwrite', (s) => s.put(borrador)),
    eliminarBorrador: (id) => operacion('borradores', 'readwrite', (s) => s.delete(id)),
};
