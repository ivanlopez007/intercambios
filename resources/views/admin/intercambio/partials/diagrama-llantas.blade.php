{{--
    Dibujo de la unidad vista desde arriba (tracto adelante, caja atrás) con sus 18 llantas.
    Cada llanta se toca para capturarla y toma el color de su profundidad.
    $mini: versión pequeña de la ventana de una llanta; resalta la llanta abierta.
--}}
@php
$mini ??= false;
// Centro de cada llanta en el dibujo: [x, y]. Lado izquierdo (chofer) a la izquierda.
$llantasSvg = [
    1 => [46, 92], 2 => [194, 92],
    3 => [26, 182], 4 => [54, 182], 5 => [186, 182], 6 => [214, 182],
    7 => [26, 232], 8 => [54, 232], 9 => [186, 232], 10 => [214, 232],
    11 => [26, 468], 12 => [54, 468], 13 => [186, 468], 14 => [214, 468],
    15 => [26, 518], 16 => [54, 518], 17 => [186, 518], 18 => [214, 518],
];
$ejesSvg = [[92, 46, 194], [182, 26, 214], [232, 26, 214], [468, 26, 214], [518, 26, 214]];
@endphp
<svg viewBox="0 0 240 572" class="w-full h-auto select-none" role="group" aria-label="Llantas de la unidad vista desde arriba" style="-webkit-tap-highlight-color: transparent">
    {{-- Frente --}}
    <path d="M120 4 l9 11 h-6 v7 h-6 v-7 h-6 z" class="fill-slate-400" />
    @unless ($mini)
    <text x="120" y="34" text-anchor="middle" font-size="9" font-weight="800" class="fill-slate-400" letter-spacing="1">FRENTE</text>
    @endunless

    {{-- Tracto: cabina y chasis --}}
    <rect x="98" y="104" width="44" height="150" rx="4" class="fill-slate-300" />
    <rect x="80" y="42" width="80" height="72" rx="14" class="fill-slate-700" />
    <rect x="88" y="48" width="64" height="16" rx="4" class="fill-sky-200" />
    <circle cx="120" cy="244" r="9" class="fill-slate-400" />
    @unless ($mini)
    <text x="120" y="150" text-anchor="middle" font-size="10" font-weight="800" class="fill-slate-600">TRACTO</text>
    @endunless

    {{-- Caja --}}
    <rect x="66" y="262" width="108" height="292" rx="6" class="fill-slate-100 stroke-slate-400" stroke-width="2" />
    @unless ($mini)
    <text x="120" y="370" text-anchor="middle" font-size="12" font-weight="800" class="fill-slate-500">CAJA</text>
    @endunless

    {{-- Ejes --}}
    @foreach ($ejesSvg as [$y, $x1, $x2])
    <line x1="{{ $x1 }}" y1="{{ $y }}" x2="{{ $x2 }}" y2="{{ $y }}" class="stroke-slate-500" stroke-width="4" stroke-linecap="round" />
    @endforeach

    {{-- Llantas --}}
    @foreach ($llantasSvg as $p => [$cx, $cy])
    <g role="button" tabindex="0" class="cursor-pointer outline-none"
        :aria-label="'Llanta {{ $p }}: ' + descripcionLlanta({{ $p }})"
        @click="abrirLlanta({{ $p }})" @keydown.enter.prevent="abrirLlanta({{ $p }})"
        @if ($mini) :class="llantaAbierta === {{ $p }} ? '' : 'opacity-40'" @endif>
        @if ($mini)
        <rect x="{{ $cx - 16 }}" y="{{ $cy - 25 }}" width="32" height="50" rx="8" fill="none" class="stroke-blue-600" stroke-width="4" x-show="llantaAbierta === {{ $p }}" />
        @endif
        <rect x="{{ $cx - 12 }}" y="{{ $cy - 21 }}" width="24" height="42" rx="5" stroke-width="2"
            :class="{
                'fill-rose-500 stroke-rose-700': colorLlanta({{ $p }}) === 'rojo',
                'fill-amber-400 stroke-amber-600': colorLlanta({{ $p }}) === 'amarillo',
                'fill-emerald-500 stroke-emerald-700': colorLlanta({{ $p }}) === 'verde',
                'fill-white stroke-slate-600': !colorLlanta({{ $p }}),
            }" />
        <text x="{{ $cx }}" y="{{ $cy + 4 }}" text-anchor="middle" font-size="12" font-weight="800" pointer-events="none"
            :class="['rojo', 'verde'].includes(colorLlanta({{ $p }})) ? 'fill-white' : 'fill-slate-800'">{{ $p }}</text>
    </g>
    @endforeach

    @unless ($mini)
    <text x="6" y="568" font-size="9" font-weight="700" class="fill-slate-500">◀ IZQUIERDO (CHOFER)</text>
    <text x="234" y="568" text-anchor="end" font-size="9" font-weight="700" class="fill-slate-500">DERECHO ▶</text>
    @endunless
</svg>
