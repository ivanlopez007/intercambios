@props(['permitirNueva' => false, 'placeholder' => 'Escanea el QR o escribe el nombre de la empresa...'])

{{-- Buscador de empresa: QR, número o nombre. El id elegido queda en el campo oculto #codigo_qr. --}}
<div x-data="selectorEmpresa({ permitirNueva: @js($permitirNueva) })" @click.outside="abierto = false" class="relative flex-1 min-w-0">
    <input type="hidden" id="codigo_qr" name="codigo_qr" x-ref="codigo">

    <label for="empresa_busqueda" class="sr-only">Empresa</label>
    <input type="text" id="empresa_busqueda" x-model="texto" @input="escribir()" @focus="abierto = texto.length > 0" @keydown="tecla($event)"
        placeholder="{{ $placeholder }}" autocomplete="off" autocapitalize="words" spellcheck="false"
        role="combobox" aria-autocomplete="list" aria-controls="empresa_sugerencias" :aria-expanded="abierto && sugerencias.length > 0"
        {{ $attributes->merge(['class' => 'w-full pl-4 pr-10 py-3.5 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 text-base shadow-sm']) }}>

    <!-- Indicador de empresa reconocida -->
    <span x-show="seleccionada" x-cloak class="absolute right-3 top-3.5 text-emerald-600" title="Empresa reconocida">
        <i class="fa-solid fa-circle-check text-lg"></i>
    </span>

    <!-- Sugerencias -->
    <ul id="empresa_sugerencias" role="listbox" x-show="abierto && texto.trim().length > 0 && (sugerencias.length || !esNumero)" x-cloak
        class="absolute z-40 mt-1 w-full max-h-72 overflow-y-auto bg-white border border-slate-200 rounded-xl shadow-xl py-1">
        <template x-for="(empresa, i) in sugerencias" :key="empresa.id">
            <li role="option" :aria-selected="i === indice">
                <button type="button" @click="elegir(empresa)" @mouseenter="indice = i"
                    class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left transition"
                    :class="i === indice ? 'bg-blue-50' : 'hover:bg-slate-50'">
                    <span class="font-semibold text-slate-900 truncate" x-text="empresa.nombre"></span>
                    <span class="shrink-0 text-[11px] font-mono text-slate-400" x-text="'QR ' + empresa.id"></span>
                </button>
            </li>
        </template>

        <li x-show="!sugerencias.length && !ofrecerNueva" class="px-4 py-3 text-sm text-slate-500">
            No se encontraron empresas.
        </li>

        <li x-show="ofrecerNueva" class="border-t border-slate-100">
            <button type="button" @click="registrarNueva()"
                class="w-full flex items-center gap-2 px-4 py-3 text-left text-sm font-semibold text-blue-700 hover:bg-blue-50">
                <i class="fa-solid fa-plus"></i>
                <span>Registrar «<span x-text="texto.trim()"></span>» como empresa nueva</span>
            </button>
        </li>
    </ul>
</div>
