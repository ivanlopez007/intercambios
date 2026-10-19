// Registra el service worker de la aplicación de caseta (uso en celulares).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.warn('No se pudo registrar el service worker:', error);
        });
    });
}
