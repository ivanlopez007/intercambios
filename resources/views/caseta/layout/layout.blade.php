@php
// Barra inferior para celulares: accesos directos a las tareas más frecuentes de caseta.
$barraInferior = [
    ['title' => 'Inicio', 'icon' => 'fa-solid fa-house', 'route' => 'caseta.menu'],
    ['title' => 'Entrada', 'icon' => 'fa-solid fa-right-to-bracket', 'route' => 'caseta.entrada'],
    ['title' => 'Salida', 'icon' => 'fa-solid fa-right-from-bracket', 'route' => 'caseta.salida'],
    ['title' => 'QR', 'icon' => 'fa-solid fa-qrcode', 'route' => 'caseta.qr'],
];
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Identifica a quien hace un registro sin conexión, aunque se envíe después con otra sesión. --}}
    <meta name="registrado-por" content="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) auth()->id()) }}">

    <title>@yield('title', 'Caseta') | Industrial Hefesto</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563eb">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Caseta Hefesto">

    @stack('vite')
    @vite(['resources/css/app.css', 'resources/js/caseta.js', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="bg-slate-100 font-sans antialiased text-slate-800"
    x-data="{ sidebarOpen: false, enLinea: navigator.onLine }"
    @online.window="enLinea = true" @offline.window="enLinea = false">

    <div class="flex h-dvh overflow-hidden relative">

        <!-- OVERLAY PARA MÓVILES -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak
            x-transition.opacity
            class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm md:hidden"></div>

        <!-- MENÚ LATERAL -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-white text-slate-700 flex flex-col justify-between shrink-0 -translate-x-full md:translate-x-0 transform transition-transform duration-300 ease-in-out border-r border-slate-200 shadow-sm">
            <div class="p-4 overflow-y-auto">
                <div class="flex items-center justify-between mb-8 px-2">
                    <a href="{{ route('caseta.menu') }}" class="flex items-center space-x-3">
                        <img src="/icons/icon-192.png" alt="" class="w-8 h-8 rounded-lg">
                        <span class="text-base font-bold text-slate-900 tracking-wide">Caseta Hefesto</span>
                    </a>

                    <button @click="sidebarOpen = false" type="button" class="md:hidden text-slate-400 hover:text-slate-600 p-1" aria-label="Cerrar menú">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <x-menu-lateral :items="config('menus.caseta')" tema="claro" />

                @if (auth()->user()->rol === 'admin')
                <a href="{{ route('admin.dashboard') }}"
                    class="mt-6 flex items-center px-4 py-3 text-sm font-semibold rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition">
                    <i class="fa-solid fa-gauge w-6 text-slate-400"></i>
                    <span>Panel de administración</span>
                </a>
                @endif
            </div>

            <div class="p-4 border-t border-slate-200 text-[11px] text-slate-400 text-center">
                &copy; {{ date('Y') }} Industrial Hefesto
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto overflow-x-hidden bg-slate-100">

            <header class="bg-white/90 border-b border-slate-200 backdrop-blur h-16 flex items-center justify-between px-4 sm:px-6 shrink-0 sticky top-0 z-20 shadow-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <button @click="sidebarOpen = !sidebarOpen" type="button" class="md:hidden text-slate-600 hover:text-blue-600 p-2 rounded-lg bg-slate-100" aria-label="Abrir menú">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>

                    <h1 class="text-base sm:text-lg font-bold text-slate-900 truncate">
                        @yield('page-title', 'Caseta')
                    </h1>
                </div>

                <div class="flex items-center space-x-3 shrink-0">
                    <!-- Registros guardados sin conexión -->
                    <button type="button" x-show="$store.cola.registros.length" x-cloak @click="$store.cola.abierta = true"
                        class="relative flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-bold transition"
                        :class="$store.cola.conProblema ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800'"
                        aria-label="Ver registros sin enviar">
                        <i class="fa-solid" :class="$store.cola.conProblema ? 'fa-triangle-exclamation' : 'fa-cloud-arrow-up'"></i>
                        <span x-text="$store.cola.registros.length"></span>
                        <span class="hidden sm:inline">sin enviar</span>
                    </button>

                    <span class="text-xs sm:text-sm font-medium text-slate-700 hidden sm:inline-block">
                        {{ auth()->user()->name }}
                    </span>
                    <i class="fa-solid fa-circle-user text-xl sm:text-2xl text-blue-600"></i>
                    <form method="POST" action="{{ route('logout') }}" data-logout>
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                            Salir
                        </button>
                    </form>
                </div>
            </header>

            <!-- Aviso sin conexión -->
            <div x-show="!enLinea" x-cloak role="status"
                class="bg-amber-100 border-b border-amber-200 text-amber-900 text-sm font-semibold px-4 py-2 flex items-center gap-2">
                <i class="fa-solid fa-wifi"></i>
                <span>Sin conexión. Los registros se guardan en el celular y se enviarán solos cuando vuelva la señal.</span>
            </div>

            <!-- Sesión expirada con registros pendientes -->
            <div x-show="$store.cola.sesionExpirada && $store.cola.pendientes" x-cloak role="alert"
                class="bg-rose-100 border-b border-rose-200 text-rose-900 text-sm font-semibold px-4 py-2 flex flex-wrap items-center gap-2">
                <i class="fa-solid fa-lock"></i>
                <span>Tu sesión expiró y hay registros sin enviar. Vuelve a iniciar sesión para enviarlos; no se perderán.</span>
                <a href="{{ route('login') }}" class="underline">Iniciar sesión</a>
            </div>

            <main class="p-2 sm:p-6 flex-1 pb-24 md:pb-6">
                @yield('contenido')
            </main>

        </div>

    </div>

    <!-- BARRA INFERIOR (CELULARES) -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 shadow-[0_-2px_10px_rgba(0,0,0,0.05)] pb-[env(safe-area-inset-bottom)]"
        aria-label="Navegación principal">
        <div class="grid grid-cols-4">
            @foreach ($barraInferior as $opcion)
            @php($activa = request()->routeIs($opcion['route']))
            <a href="{{ route($opcion['route']) }}" @if ($activa) aria-current="page" @endif
                class="flex flex-col items-center justify-center gap-1 py-2.5 text-[11px] font-semibold transition {{ $activa ? 'text-blue-600' : 'text-slate-500 active:text-blue-600' }}">
                <i class="{{ $opcion['icon'] }} text-lg"></i>
                <span>{{ $opcion['title'] }}</span>
            </a>
            @endforeach
        </div>
    </nav>

    <!-- PANEL DE REGISTROS SIN ENVIAR -->
    <div x-show="$store.cola.abierta" x-cloak class="fixed inset-0 z-[70] flex justify-end" role="dialog" aria-modal="true" aria-labelledby="cola-titulo">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="$store.cola.abierta = false"></div>

        <div class="relative w-full max-w-md h-full bg-white shadow-2xl flex flex-col">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 id="cola-titulo" class="text-lg font-black text-slate-900">Registros sin enviar</h2>
                    <p class="text-xs text-slate-500">Se envían solos cuando hay internet.</p>
                </div>
                <button type="button" @click="$store.cola.abierta = false" class="p-2 text-slate-400 hover:text-slate-700" aria-label="Cerrar">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                <template x-if="!$store.cola.registros.length">
                    <p class="text-center text-sm text-slate-500 py-8">No hay registros pendientes.</p>
                </template>

                <template x-for="registro in $store.cola.registros" :key="registro.id">
                    <div class="rounded-2xl border p-4 space-y-2"
                        :class="registro.estado === 'pendiente' ? 'border-slate-200 bg-slate-50' : 'border-rose-200 bg-rose-50'">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <span class="text-[11px] font-bold uppercase tracking-wider"
                                    :class="registro.tipo === 'entrada' ? 'text-emerald-700' : (registro.tipo === 'intercambio' ? 'text-blue-700' : 'text-rose-700')"
                                    x-text="registro.titulo || (registro.tipo === 'entrada' ? 'Entrada' : 'Salida')"></span>
                                <p class="font-bold text-slate-900 truncate"
                                    x-text="registro.resumen || registro.datos.nombre_persona || registro.datos.empresa_nombre || ('Empresa QR ' + registro.datos.codigo_qr)"></p>
                                <p class="text-xs text-slate-500" x-text="new Date(registro.creado_en).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' })"></p>
                            </div>
                            <span x-show="registro.estado === 'pendiente'" class="shrink-0 text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-1 rounded-full">
                                <i class="fa-solid fa-clock"></i> En espera
                            </span>
                        </div>

                        <p x-show="registro.mensaje" class="text-sm font-semibold text-rose-800" x-text="registro.mensaje"></p>

                        <!-- Salida con varias personas de la empresa dentro -->
                        <template x-if="registro.estado === 'requiere_seleccion'">
                            <div class="space-y-2">
                                <template x-for="visita in registro.visitas" :key="visita.id">
                                    <button type="button" @click="$store.cola.elegirVisita(registro, visita.id)"
                                        class="w-full text-left p-3 rounded-xl border border-slate-200 bg-white hover:border-rose-300 active:bg-rose-100 transition">
                                        <span class="block font-bold text-slate-900" x-text="visita.nombre_persona"></span>
                                        <span class="block text-xs text-slate-500" x-text="'Entró: ' + (visita.fecha_entrada ?? '—')"></span>
                                    </button>
                                </template>
                            </div>
                        </template>

                        <div x-show="registro.estado !== 'pendiente'" class="flex gap-2 pt-1">
                            <button type="button" x-show="registro.estado === 'error'" @click="$store.cola.reintentar(registro)"
                                class="flex-1 py-2 rounded-xl bg-slate-900 text-white text-sm font-bold">Reintentar</button>
                            <button type="button" @click="$store.cola.descartar(registro.id)"
                                class="flex-1 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 text-sm font-bold"
                                x-text="registro.estado === 'requiere_seleccion' ? 'Cancelar (fue repetida)' : 'Descartar'"></button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- AVISO DE ENVÍO -->
    <div x-show="$store.cola.aviso" x-cloak x-transition.opacity role="status"
        class="fixed left-1/2 -translate-x-1/2 bottom-24 md:bottom-6 z-[80] max-w-sm w-[calc(100%-2rem)] bg-emerald-600 text-white text-sm font-bold px-4 py-3 rounded-2xl shadow-lg flex items-center gap-2">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <span x-text="$store.cola.aviso"></span>
    </div>

    @stack('scripts')
</body>

</html>
