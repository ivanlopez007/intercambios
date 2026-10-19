import '@fortawesome/fontawesome-free/css/fontawesome.min.css';
import '@fortawesome/fontawesome-free/css/solid.min.css';
import Alpine from 'alpinejs';
import { almacen as almacenIntercambios } from './intercambio-almacen.js';

window.Alpine = Alpine;

// Al cerrar sesión se borran las páginas de caseta guardadas por el service worker,
// para que el siguiente usuario del celular no vea información de la sesión anterior.
document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!form.matches('form[data-logout]') || !('caches' in window) || form.dataset.limpiado) {
        return;
    }

    event.preventDefault();

    try {
        sessionStorage.removeItem('caseta-paginas-guardadas');
        localStorage.removeItem('caseta-personas-dentro');
    } catch {
        // sessionStorage no disponible.
    }

    try {
        const nombres = await caches.keys();
        await Promise.all(nombres.filter((nombre) => nombre.startsWith('hefesto-paginas')).map((nombre) => caches.delete(nombre)));
    } catch {
        // Si el navegador no permite acceder a la caché, se cierra sesión de todas formas.
    }

    // Operadores y catálogos descargados para intercambios. Los borradores no se borran:
    // pertenecen al dispositivo y el siguiente turno puede continuarlos.
    try {
        await almacenIntercambios.borrarPaquete();
    } catch {
        // IndexedDB no disponible.
    }

    form.dataset.limpiado = '1';
    form.submit();
});

Alpine.start();
