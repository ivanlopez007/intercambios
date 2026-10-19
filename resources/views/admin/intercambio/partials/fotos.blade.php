{{--
    Miniaturas de fotos del borrador y botón para tomar otra (abre la cámara trasera en el celular).
    $filtro: objeto JS para fotosDe(). $meta: objeto JS para tomarFoto(). $mostrar: expresión JS
    que decide si se puede agregar otra foto. $etiqueta: texto del botón.
--}}
<div class="flex flex-wrap gap-2">
    <template x-for="foto in fotosDe({{ $filtro }})" :key="foto.uuid">
        <div class="relative w-20 h-20 rounded-xl overflow-hidden border border-slate-200 bg-slate-100">
            <img :src="urlFoto(foto)" alt="Foto tomada" class="w-full h-full object-cover">
            @include('admin.intercambio.partials.estado-subida')
            <button type="button" @click="quitarFoto(foto.uuid)"
                class="absolute top-1 right-1 w-7 h-7 rounded-full bg-slate-900/75 text-white text-xs flex items-center justify-center"
                aria-label="Quitar foto">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </template>

    <label x-show="{{ $mostrar ?? 'true' }}"
        class="w-20 h-20 rounded-xl border-2 border-dashed border-slate-300 bg-white text-slate-500 flex flex-col items-center justify-center gap-1 text-[11px] font-semibold text-center cursor-pointer active:bg-slate-100"
        :class="procesandoFoto && 'opacity-50 pointer-events-none'">
        <i class="fa-solid" :class="procesandoFoto ? 'fa-spinner fa-spin' : 'fa-camera'"></i>
        <span>{{ $etiqueta ?? 'Foto' }}</span>
        <input type="file" accept="image/*" capture="environment" class="hidden" @change="tomarFoto($event, {{ $meta }})">
    </label>
</div>
