{{-- Estado de subida de una foto (variable `foto` del x-for): ya en el servidor, subiendo, esperando señal o con error. --}}
<span class="absolute bottom-1 left-1 w-6 h-6 rounded-full text-[11px] flex items-center justify-center shadow"
    :class="{
        lista: 'bg-emerald-500 text-white',
        subiendo: 'bg-white text-blue-600',
        pendiente: 'bg-slate-900/60 text-white',
        error: 'bg-amber-400 text-slate-900',
    }[estadoSubida(foto)]"
    :title="{ lista: 'Ya está en el servidor', subiendo: 'Subiendo…', pendiente: 'Se subirá cuando haya señal', error: 'No se pudo subir; se reintentará' }[estadoSubida(foto)]">
    <i class="fa-solid" :class="{ lista: 'fa-cloud', subiendo: 'fa-spinner fa-spin', pendiente: 'fa-clock', error: 'fa-rotate' }[estadoSubida(foto)]"></i>
</span>
