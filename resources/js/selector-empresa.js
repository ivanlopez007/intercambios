/*
 * Buscador de empresas para caseta: el mismo campo acepta el QR escaneado, el número de la
 * empresa o su nombre, y va sugiriendo empresas mientras se escribe.
 *
 * El catálogo (id y nombre) se descarga una vez y se guarda en el celular para funcionar sin
 * conexión. El id elegido se escribe en el campo oculto #codigo_qr, que es el que leen las
 * pantallas de entrada y salida.
 *
 * Eventos (window):
 *   empresa-escaneada (detail: código)  → selecciona la empresa de ese QR.
 *   empresa-limpiar                     → vacía el campo.
 *   empresa-nueva (detail: nombre)      → lo emite el componente al elegir "registrar como nueva".
 */
const URL_CATALOGO = '/caseta/empresas';
const CLAVE_CATALOGO = 'caseta-empresas';
const MAX_SUGERENCIAS = 8;

const normalizar = (texto) =>
    (texto ?? '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

let catalogoEnCurso = null;

function leerGuardado() {
    try {
        return JSON.parse(localStorage.getItem(CLAVE_CATALOGO)) || [];
    } catch {
        return [];
    }
}

// Una sola descarga por página, aunque haya varios buscadores.
function obtenerCatalogo() {
    catalogoEnCurso ??= (async () => {
        try {
            const respuesta = await fetch(URL_CATALOGO, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);

            const { empresas } = await respuesta.json();
            try {
                localStorage.setItem(CLAVE_CATALOGO, JSON.stringify(empresas));
            } catch {
                // Sin almacenamiento local: solo no habrá catálogo sin conexión.
            }
            return empresas;
        } catch {
            return leerGuardado();
        }
    })();

    return catalogoEnCurso;
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('selectorEmpresa', ({ permitirNueva = false } = {}) => ({
        texto: '',
        id: '',
        abierto: false,
        indice: 0,
        empresas: [],
        permitirNueva,

        async init() {
            window.addEventListener('empresa-escaneada', (evento) => this.desdeCodigo(evento.detail));
            window.addEventListener('empresa-limpiar', () => this.limpiar());

            this.empresas = (await obtenerCatalogo()).map((e) => ({ ...e, clave: normalizar(e.nombre) }));

            // Si ya se había escaneado un código, ahora se muestra el nombre de su empresa.
            if (this.id && this.texto === this.id) this.desdeCodigo(this.id);
        },

        // Se escribe directo en el campo oculto (sin esperar a Alpine) porque el escáner de
        // salida envía el formulario inmediatamente después de leer el QR.
        fijar(id) {
            this.id = id;
            this.$refs.codigo.value = id;
        },

        get esNumero() {
            return /^\d+$/.test(this.texto.trim());
        },

        get sugerencias() {
            const q = normalizar(this.texto);
            if (!q) return [];

            const coincidencias = this.empresas.filter((e) => e.clave.includes(q) || String(e.id) === q);

            // Primero las que empiezan con lo escrito, luego las que lo contienen.
            return coincidencias
                .sort((a, b) => (b.clave.startsWith(q) - a.clave.startsWith(q)) || a.clave.localeCompare(b.clave))
                .slice(0, MAX_SUGERENCIAS);
        },

        get seleccionada() {
            return this.empresas.find((e) => String(e.id) === String(this.id)) ?? null;
        },

        // Hay texto que no corresponde a ninguna empresa: se ofrece darla de alta (solo en entrada).
        get ofrecerNueva() {
            return this.permitirNueva && this.texto.trim().length > 1 && !this.esNumero && !this.id;
        },

        escribir() {
            this.abierto = true;
            this.indice = 0;

            const q = this.texto.trim();
            if (/^\d+$/.test(q)) {
                // Número escrito a mano o lector QR tipo teclado.
                this.fijar(q);
                return;
            }

            const exacta = this.empresas.find((e) => e.clave === normalizar(q));
            this.fijar(exacta ? String(exacta.id) : '');
        },

        elegir(empresa) {
            this.texto = empresa.nombre;
            this.fijar(String(empresa.id));
            this.abierto = false;
        },

        desdeCodigo(codigo) {
            const limpio = String(codigo ?? '').trim();
            const empresa = this.empresas.find((e) => String(e.id) === limpio);

            this.texto = empresa ? empresa.nombre : limpio;
            this.fijar(limpio);
            this.abierto = false;
        },

        limpiar() {
            this.texto = '';
            this.fijar('');
            this.abierto = false;
        },

        registrarNueva() {
            const nombre = this.texto.trim();
            this.limpiar();
            window.dispatchEvent(new CustomEvent('empresa-nueva', { detail: nombre }));
        },

        tecla(evento) {
            const total = this.sugerencias.length;

            if (evento.key === 'ArrowDown' && total) {
                evento.preventDefault();
                this.abierto = true;
                this.indice = (this.indice + 1) % total;
            } else if (evento.key === 'ArrowUp' && total) {
                evento.preventDefault();
                this.indice = (this.indice - 1 + total) % total;
            } else if (evento.key === 'Escape') {
                this.abierto = false;
            } else if (evento.key === 'Enter' && this.abierto && total && !this.esNumero) {
                // Con un número (lector QR tipo teclado) Enter envía el formulario directamente.
                evento.preventDefault();
                this.elegir(this.sugerencias[this.indice]);
            }
        },
    }));
});
