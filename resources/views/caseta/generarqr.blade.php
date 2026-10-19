@extends('caseta.layout.layout')

@section('title', 'Generar QR Empresa')

@section('contenido')
<div class="max-w-xl mx-auto py-6 px-2">

    <!-- Tarjeta Principal -->
    <div class="bg-white border border-slate-200 rounded-3xl p-5 md:p-7 shadow-sm space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-600">
                    <i class="fa-solid fa-qrcode text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 leading-tight">Acceso Empresarial</h2>
                    <p class="text-xs text-slate-500">Generador de Código QR</p>
                </div>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200">
                ● PERMANENTE
            </span>
        </div>

        <!-- Alerta Dinámica -->
        <div id="feedback-alert" class="hidden p-4 rounded-2xl text-sm font-semibold transition-all"></div>

        <!-- Formulario Simplificado -->
        <form id="form-generar-qr" onsubmit="event.preventDefault(); generarQREmpresa();" class="space-y-5">
            @csrf

            <!-- Selección Única de Empresa -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">
                    Seleccionar Empresa
                </label>
                <select id="empresa_id" name="empresa_id" required
                    class="w-full px-4 py-3.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 outline-none text-slate-900 text-base font-medium shadow-sm">
                    <option value="" disabled selected>-- Selecciona una empresa --</option>
                    @foreach ($empresas as $empresa)
                    <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Botón Generar -->
            <button type="submit" id="btn-submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 px-4 rounded-2xl transition shadow-md shadow-indigo-200 flex items-center justify-center gap-2 text-lg uppercase tracking-wider">
                <i class="fa-solid fa-wand-magic-sparkles text-xl"></i>
                <span id="btn-submit-text">Generar QR</span>
                <i id="btn-submit-spinner" class="fa-solid fa-circle-notch animate-spin hidden"></i>
            </button>
        </form>

        <!-- SECCIÓN DE RESULTADO Y COMPARTIR -->
        <div id="resultado-qr" class="hidden border-t border-slate-200 pt-6 space-y-5">
            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-center space-y-4">

                <span class="text-xs font-bold tracking-widest text-slate-400 uppercase">Acceso Permanente</span>
                <h3 id="qr-empresa-nombre" class="text-lg font-bold text-slate-800"></h3>

                <!-- Contenedor Imagen QR -->
                <div class="bg-white p-4 rounded-2xl border border-slate-300 inline-block shadow-inner mx-auto">
                    <img id="img-qr" src="" alt="Código QR Empresa" class="w-48 h-48 object-contain mx-auto">
                </div>

                <div>
                    <p id="qr-codigo-texto" class="text-xs font-mono font-bold text-indigo-600 bg-indigo-50 py-1.5 px-3 rounded-lg border border-indigo-100 inline-block">
                        ID de empresa
                    </p>
                </div>

                <!-- Botones de Acción (Compartir / Descargar) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                    <button type="button" onclick="compartirQR()"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl transition shadow-sm flex items-center justify-center gap-2 text-sm">
                        <i class="fa-solid fa-share-nodes text-base"></i>
                        <span>Compartir imagen</span>
                    </button>

                    <button type="button" onclick="descargarQR()"
                        class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-3 px-4 rounded-xl transition border border-slate-300 flex items-center justify-center gap-2 text-sm">
                        <i class="fa-solid fa-download text-base"></i>
                        <span>Descargar</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    const urlQrBase = @js(route('caseta.qr.show', ['empresa' => '__ID__']));
    let currentEmpresaId = null;
    let currentEmpresaNombre = '';

    function generarQREmpresa() {
        const select = document.getElementById('empresa_id');
        const empresaId = select.value;

        if (!empresaId) {
            mostrarAlerta("Por favor selecciona una empresa.", "error");
            return;
        }

        currentEmpresaId = empresaId;
        currentEmpresaNombre = select.options[select.selectedIndex].text;

        const btn = document.getElementById('btn-submit');
        const spinner = document.getElementById('btn-submit-spinner');
        const img = document.getElementById('img-qr');
        btn.disabled = true;
        spinner.classList.remove('hidden');

        img.onload = () => {
            document.getElementById('qr-empresa-nombre').innerText = currentEmpresaNombre;
            document.getElementById('qr-codigo-texto').innerText = `ID: ${empresaId}`;
            document.getElementById('resultado-qr').classList.remove('hidden');
            mostrarAlerta("¡Código QR permanente generado!", "exito");
            btn.disabled = false;
            spinner.classList.add('hidden');
        };
        img.onerror = () => {
            mostrarAlerta("No se pudo generar el QR. Verifica tu conexión e inténtalo de nuevo.", "error");
            btn.disabled = false;
            spinner.classList.add('hidden');
        };
        img.src = urlQrBase.replace('__ID__', encodeURIComponent(empresaId));
    }

    // Convierte el QR (SVG) en una imagen PNG con el nombre de la empresa, lista para compartir o imprimir.
    async function crearImagenQR() {
        const img = document.getElementById('img-qr');
        const tamano = 600;
        const canvas = document.createElement('canvas');
        canvas.width = tamano;
        canvas.height = tamano + 90;

        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 20, 20, tamano - 40, tamano - 40);
        ctx.fillStyle = '#0f172a';
        ctx.font = 'bold 30px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(currentEmpresaNombre, tamano / 2, tamano + 25, tamano - 40);
        ctx.font = '22px system-ui, sans-serif';
        ctx.fillStyle = '#64748b';
        ctx.fillText(`ID: ${currentEmpresaId}`, tamano / 2, tamano + 60);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        const nombreArchivo = `QR-${currentEmpresaNombre.replace(/[^\w\-]+/g, '_')}.png`;

        return new File([blob], nombreArchivo, { type: 'image/png' });
    }

    async function compartirQR() {
        const archivo = await crearImagenQR();

        if (navigator.canShare && navigator.canShare({ files: [archivo] })) {
            try {
                await navigator.share({
                    title: `Acceso QR - ${currentEmpresaNombre}`,
                    text: `Código de acceso permanente para ${currentEmpresaNombre}`,
                    files: [archivo]
                });
            } catch (err) {
                // Cancelado por el usuario
            }
        } else {
            descargarArchivo(archivo);
            mostrarAlerta("Tu navegador no permite compartir imágenes; se descargó el QR.", "exito");
        }
    }

    async function descargarQR() {
        descargarArchivo(await crearImagenQR());
    }

    function descargarArchivo(archivo) {
        const url = URL.createObjectURL(archivo);
        const enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = archivo.name;
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function mostrarAlerta(msg, tipo) {
        const box = document.getElementById('feedback-alert');
        box.className = 'p-4 rounded-2xl text-sm font-semibold transition-all border';

        if (tipo === 'exito') {
            box.classList.add('bg-emerald-100', 'text-emerald-800', 'border-emerald-200');
        } else {
            box.classList.add('bg-rose-100', 'text-rose-800', 'border-rose-200');
        }

        box.innerText = msg;
    }
</script>
@endpush
