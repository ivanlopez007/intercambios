/*
 * ************************************************************
 * Modo sin conexión (PWA y cola offline)
 * Archivos necesarios:
 *   public/sw.js, pwa.js, cola-offline.js
 * Fecha: 14 sep 2026 - 25 sep 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

// Registra el service worker de la aplicación de caseta (uso en celulares).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.warn('No se pudo registrar el service worker:', error);
        });
    });
}
