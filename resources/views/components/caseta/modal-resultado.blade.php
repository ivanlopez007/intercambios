{{-- Ventana de resultado de Entrada/Salida. Se controla con casetaUI.mostrarResultado() / cerrarResultado(). --}}
<div id="modal-resultado" class="fixed inset-0 z-[60] bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4"
    role="dialog" aria-modal="true" aria-labelledby="modal-resultado-titulo"
    onclick="if (event.target === this) casetaUI.cerrarResultado()">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-sm w-full p-8 text-center space-y-4 animate-pop">
        <div id="modal-resultado-icono" class="mx-auto w-20 h-20 rounded-full flex items-center justify-center text-4xl"></div>
        <h3 id="modal-resultado-titulo" class="text-2xl font-black text-slate-900"></h3>
        <p id="modal-resultado-mensaje" class="text-sm text-slate-500"></p>
        <button type="button" id="modal-resultado-aceptar" onclick="casetaUI.cerrarResultado()"
            class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 rounded-2xl transition">
            Aceptar
        </button>
    </div>
</div>
