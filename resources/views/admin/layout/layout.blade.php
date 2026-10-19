<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Administración') | Industrial Hefesto</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">

    @stack('vite')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="bg-slate-100 font-sans antialiased text-slate-800" x-data="{ sidebarOpen: false }">

    <div class="flex h-dvh overflow-hidden relative">

        <!-- OVERLAY PARA MÓVILES -->
        <div x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak
            x-transition.opacity
            class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm md:hidden"></div>

        <!-- MENÚ LATERAL -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed md:static inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 flex flex-col justify-between shrink-0 -translate-x-full md:translate-x-0 transform transition-transform duration-300 ease-in-out border-r border-slate-800">
            <div class="p-4 overflow-y-auto">
                <div class="flex items-center justify-between mb-8 px-2">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3">
                        <img src="/icons/icon-192.png" alt="" class="w-8 h-8 rounded-lg">
                        <span class="text-base font-bold text-white tracking-wide">Industrial Hefesto</span>
                    </a>

                    <button @click="sidebarOpen = false" type="button" class="md:hidden text-slate-400 hover:text-white p-1" aria-label="Cerrar menú">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <x-menu-lateral :items="config('menus.admin')" tema="oscuro" />
            </div>

            <div class="p-4 border-t border-slate-800 text-[11px] text-slate-500 text-center">
                &copy; {{ date('Y') }} Industrial Hefesto
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto overflow-x-hidden">

            <header class="bg-white/90 border-b border-slate-200 backdrop-blur h-16 flex items-center justify-between px-4 sm:px-6 shrink-0 sticky top-0 z-20 shadow-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <button @click="sidebarOpen = !sidebarOpen" type="button" class="md:hidden text-slate-600 hover:text-amber-500 p-2 rounded-lg bg-slate-100" aria-label="Abrir menú">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>

                    <h1 class="text-base sm:text-lg font-bold text-slate-900 truncate">
                        @yield('page-title', 'Panel de Administración')
                    </h1>
                </div>

                <div class="flex items-center space-x-3 shrink-0">
                    <span class="text-xs sm:text-sm font-medium text-slate-700 hidden sm:inline-block">
                        {{ auth()->user()->name }}
                    </span>
                    <i class="fa-solid fa-circle-user text-xl sm:text-2xl text-amber-500"></i>
                    <form method="POST" action="{{ route('logout') }}" data-logout>
                        @csrf
                        <button type="submit" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </header>

            <main class="p-2 sm:p-6 flex-1">
                @yield('contenido')
            </main>

        </div>

    </div>

    @stack('scripts')
</body>

</html>
