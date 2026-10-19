/*
 * Piezas compartidas por las pantallas de Entrada y Salida de caseta:
 * escáner QR con la cámara, aviso de errores y ventana de resultado.
 * Se exponen en window.casetaUI para los scripts de cada vista.
 */

// --- ESCÁNER QR ---
// La librería (~370 KB) se descarga solo cuando hace falta. Al abrir la pantalla se precarga en
// segundo plano para que quede en la caché del service worker y funcione también sin conexión.
let claseLector = null;

async function cargarLector() {
    if (!claseLector) {
        ({ Html5Qrcode: claseLector } = await import('html5-qrcode'));
    }

    return claseLector;
}

function precargarLector() {
    const precargar = () => cargarLector().catch(() => {});

    if ('requestIdleCallback' in window) {
        requestIdleCallback(precargar, { timeout: 4000 });
    } else {
        setTimeout(precargar, 1500);
    }
}

class Escaner {
    /**
     * @param {string} idContenedor  id del div donde se muestra la cámara.
     * @param {object} opciones      alLeer(texto), alCambiar(activo), alError(mensaje).
     */
    constructor(idContenedor, { alLeer, alCambiar = () => {}, alError = () => {} }) {
        this.idContenedor = idContenedor;
        this.alLeer = alLeer;
        this.alCambiar = alCambiar;
        this.alError = alError;
        this.lector = null;
        this.activo = false;
        this.ocupado = false;

        // La cámara se apaga al salir de la pantalla o al mandar la app a segundo plano.
        const apagar = () => this.detener();
        window.addEventListener('pagehide', apagar);
        document.addEventListener('visibilitychange', () => document.hidden && apagar());
    }

    get contenedor() {
        return document.getElementById(this.idContenedor);
    }

    alternar() {
        return this.activo ? this.detener() : this.iniciar();
    }

    async iniciar() {
        if (this.activo || this.ocupado) return;
        this.ocupado = true;
        this.contenedor.classList.remove('hidden');

        try {
            const Lector = await cargarLector();
            this.lector ??= new Lector(this.idContenedor);

            await this.lector.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    // Recuadro de lectura proporcional a la pantalla del celular.
                    qrbox: (ancho, alto) => {
                        const lado = Math.floor(Math.min(ancho, alto) * 0.7);
                        return { width: lado, height: lado };
                    },
                },
                async (texto) => {
                    await this.detener();
                    this.alLeer(texto);
                }
            );

            this.activo = true;
            this.alCambiar(true);
        } catch {
            this.contenedor.classList.add('hidden');
            this.alError(navigator.onLine || claseLector
                ? 'No se pudo abrir la cámara. Revisa que la app tenga permiso de usarla.'
                : 'El lector de QR aún no está disponible sin conexión. Escribe el nombre de la empresa.');
        } finally {
            this.ocupado = false;
        }
    }

    async detener() {
        if (!this.activo) return;
        this.activo = false;

        try {
            if (this.lector?.isScanning) await this.lector.stop();
        } catch {
            // La cámara ya estaba detenida.
        }

        this.contenedor?.classList.add('hidden');
        this.alCambiar(false);
    }
}

// --- AVISO DE ERRORES (#feedback-alert) ---
const ESTILOS_ALERTA = {
    exito: ['bg-emerald-100', 'text-emerald-800', 'border-emerald-200'],
    error: ['bg-rose-100', 'text-rose-800', 'border-rose-200'],
};

function mostrarAlerta(mensaje, tipo = 'error') {
    const caja = document.getElementById('feedback-alert');
    if (!caja) return;

    caja.className = 'p-4 rounded-2xl text-sm font-semibold border';
    caja.classList.add(...(ESTILOS_ALERTA[tipo] ?? ESTILOS_ALERTA.error));
    caja.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
    caja.innerText = mensaje;

    // En el celular el botón de enviar queda lejos del aviso: se lleva el aviso a la vista.
    caja.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function ocultarAlerta() {
    const caja = document.getElementById('feedback-alert');
    if (!caja) return;

    caja.classList.add('hidden');
    caja.innerText = '';
}

// --- VENTANA DE RESULTADO (#modal-resultado) ---
const ICONOS_RESULTADO = {
    exito: ['bg-emerald-100 text-emerald-600', 'fa-circle-check'],
    pendiente: ['bg-amber-100 text-amber-600', 'fa-cloud-arrow-up'],
    error: ['bg-rose-100 text-rose-600', 'fa-circle-xmark'],
};

let temporizadorResultado = null;

function mostrarResultado(tipo, titulo, mensaje) {
    const modal = document.getElementById('modal-resultado');
    const icono = document.getElementById('modal-resultado-icono');
    const [colores, figura] = ICONOS_RESULTADO[tipo] ?? ICONOS_RESULTADO.error;

    icono.className = `mx-auto w-20 h-20 rounded-full flex items-center justify-center text-4xl ${colores}`;
    icono.innerHTML = `<i class="fa-solid ${figura}"></i>`;
    document.getElementById('modal-resultado-titulo').innerText = titulo;
    document.getElementById('modal-resultado-mensaje').innerText = mensaje;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('modal-resultado-aceptar')?.focus();

    // Los errores no se cierran solos para que el guardia alcance a leerlos.
    clearTimeout(temporizadorResultado);
    if (tipo !== 'error') {
        temporizadorResultado = setTimeout(cerrarResultado, tipo === 'pendiente' ? 4500 : 2500);
    }
}

function cerrarResultado() {
    const modal = document.getElementById('modal-resultado');
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    clearTimeout(temporizadorResultado);
}

document.addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape') cerrarResultado();
});

// El campo de búsqueda solo recibe el foco automático en equipos con mouse o lector QR físico;
// en el celular abrir el teclado al entrar tapa media pantalla.
function enfocarSiHayTeclado(id) {
    if (window.matchMedia('(pointer: fine)').matches) {
        document.getElementById(id)?.focus();
    }
}

window.casetaUI = {
    Escaner,
    precargarLector,
    mostrarAlerta,
    ocultarAlerta,
    mostrarResultado,
    cerrarResultado,
    enfocarSiHayTeclado,
};
