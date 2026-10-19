@props(['items', 'tema' => 'claro'])

@php
$estilos = [
    'claro' => [
        'simpleActivo' => 'bg-blue-600 text-white shadow-sm',
        'simple' => 'hover:bg-slate-100 text-slate-600',
        'iconoActivo' => 'text-white',
        'icono' => 'text-slate-400',
        'padreActivo' => 'text-blue-600 bg-blue-50',
        'iconoPadreActivo' => 'text-blue-600',
        'submenu' => 'bg-slate-50 border-slate-100',
        'subActivo' => 'bg-blue-100 text-blue-700 font-bold',
        'sub' => 'text-slate-500 hover:text-slate-900 hover:bg-slate-200/60',
    ],
    'oscuro' => [
        'simpleActivo' => 'bg-amber-400 text-slate-950',
        'simple' => 'hover:bg-slate-800 text-slate-300',
        'iconoActivo' => 'text-slate-950',
        'icono' => 'text-slate-400',
        'padreActivo' => 'text-white bg-slate-800',
        'iconoPadreActivo' => 'text-amber-400',
        'submenu' => 'bg-slate-950/60 border-slate-800',
        'subActivo' => 'bg-amber-400/20 text-amber-300 font-bold',
        'sub' => 'text-slate-400 hover:text-white hover:bg-slate-800',
    ],
][$tema];
@endphp

<nav class="space-y-1">
    @foreach ($items as $item)
        @php
        $submenu = $item['submenu'] ?? [];
        $activo = request()->routeIs(...($submenu
            ? array_column($submenu, 'active')
            : [$item['active'] ?? $item['route']]));
        @endphp

        @if (! $submenu)
            <a href="{{ route($item['route']) }}" @if ($activo) aria-current="page" @endif
                class="flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition {{ $activo ? $estilos['simpleActivo'] : $estilos['simple'] }}">
                <i class="{{ $item['icon'] }} w-6 {{ $activo ? $estilos['iconoActivo'] : $estilos['icono'] }}"></i>
                <span>{{ $item['title'] }}</span>
            </a>
        @else
            <div x-data="{ open: @js($activo) }">
                <button @click="open = !open" type="button" :aria-expanded="open"
                    class="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold rounded-xl transition {{ $activo ? $estilos['padreActivo'] : $estilos['simple'] }}">
                    <span class="flex items-center">
                        <i class="{{ $item['icon'] }} w-6 {{ $activo ? $estilos['iconoPadreActivo'] : $estilos['icono'] }}"></i>
                        <span>{{ $item['title'] }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-right text-xs transition-transform duration-200" :class="open && 'rotate-90'"></i>
                </button>

                <div x-show="open" x-cloak class="pl-9 pr-2 py-1.5 space-y-1 rounded-xl my-1 border {{ $estilos['submenu'] }}">
                    @foreach ($submenu as $sub)
                        @php
                        // Con 'params', la opción solo está activa si la ruta actual lleva esos mismos parámetros.
                        $subActivo = request()->routeIs($sub['active'])
                            && collect($sub['params'] ?? [])->every(fn ($valor, $clave) => request()->route($clave) == $valor);
                        @endphp
                        <a href="{{ route($sub['route'], $sub['params'] ?? []) }}" @if ($subActivo) aria-current="page" @endif
                            class="flex items-center gap-2 px-3 py-2.5 text-sm rounded-lg transition {{ $subActivo ? $estilos['subActivo'] : $estilos['sub'] }}">
                            @isset($sub['icon'])
                                <i class="{{ $sub['icon'] }} w-4 text-xs"></i>
                            @endisset
                            <span>{{ $sub['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</nav>
