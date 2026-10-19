{{--
    Lista de revisión de una sección (ctpat, olores o residuos), dibujada desde puntos_inspeccion.
    $seccion, $titulo, $descripcion y $columnas (dos columnas en pantallas anchas).
--}}
<section class="space-y-3">
    <div>
        <h3 class="text-base font-black text-slate-900">{{ $titulo }}</h3>
        @isset($descripcion)
        <p class="text-xs text-slate-500">{{ $descripcion }}</p>
        @endisset
    </div>

    <div class="grid gap-3 {{ ($columnas ?? false) ? 'lg:grid-cols-2' : 'sm:grid-cols-2' }}">
        <template x-for="punto in puntosDe('{{ $seccion }}')" :key="punto.id">
            <div class="rounded-2xl border p-3 transition"
                :class="tieneHallazgo(punto.id) || subpuntosDe(punto.id).some((s) => tieneHallazgo(s.id)) ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-white'">
                @include('admin.intercambio.partials.punto')

                <template x-if="subpuntosDe(punto.id).length">
                    <div class="mt-3 pl-3 border-l-2 border-slate-200 space-y-3">
                        <template x-for="punto in subpuntosDe(punto.id)" :key="punto.id">
                            <div>
                                @include('admin.intercambio.partials.punto')
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</section>
