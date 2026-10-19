{{-- Un punto de la revisión: respuesta, y observaciones y fotos si hay hallazgo. Usa la variable `punto` del x-for. --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
    <span class="text-sm font-semibold text-slate-800" x-text="punto.nombre"></span>

    <div class="grid gap-1 shrink-0" :class="{ deteccion: 'grid-cols-2', dano: 'grid-cols-4' }[punto.tipo_respuesta] ?? 'grid-cols-3'">
        <template x-for="[valor, etiqueta, color] in opcionesDe(punto)" :key="valor">
            <button type="button" @click="borrador.revision[punto.id].resultado = valor"
                class="px-3 py-2.5 rounded-xl text-xs font-bold border transition"
                :class="borrador.revision[punto.id].resultado !== valor
                    ? 'bg-white text-slate-500 border-slate-200'
                    : (color === 'emerald' ? 'bg-emerald-600 text-white border-emerald-600' : (color === 'rose' ? 'bg-rose-600 text-white border-rose-600' : 'bg-slate-600 text-white border-slate-600'))"
                :aria-pressed="borrador.revision[punto.id].resultado === valor"
                x-text="etiqueta"></button>
        </template>
    </div>
</div>

<div x-show="tieneHallazgo(punto.id)" x-cloak class="mt-3 space-y-2">
    <textarea x-model="borrador.revision[punto.id].observaciones" rows="2" maxlength="2000"
        placeholder="¿Qué se encontró? (obligatorio)"
        class="w-full rounded-xl border border-rose-300 bg-white px-3 py-2.5 text-base focus:border-rose-500 focus:ring-2 focus:ring-rose-200 outline-none"></textarea>
    @include('admin.intercambio.partials.fotos', [
        'filtro' => "{ tipo: 'punto', punto_inspeccion_id: punto.id }",
        'meta' => "{ tipo: 'punto', punto_inspeccion_id: punto.id }",
        'etiqueta' => 'Evidencia',
    ])
</div>
