@extends('admin.layout.layout')

@section('title', 'Usuarios')
@section('page-title', 'Usuarios del Sistema')

@section('contenido')
<div class="max-w-6xl mx-auto py-6 px-2">
    <div class="bg-white border border-slate-200 rounded-3xl p-5 md:p-7 shadow-sm space-y-6">

        <!-- Encabezado -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600">
                    <i class="fa-solid fa-users-gear text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 leading-tight">Usuarios</h2>
                    <p class="text-xs text-slate-500">Cuentas de acceso para caseta y administración</p>
                </div>
            </div>

            <a href="{{ route('admin.usuarios.create') }}"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-2 text-sm shadow-sm">
                <i class="fa-solid fa-user-plus text-base"></i>
                <span>Nuevo usuario</span>
            </a>
        </div>

        @if (session('success'))
        <div role="status" class="p-4 rounded-2xl text-sm font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
            {{ session('success') }}
        </div>
        @endif

        @if (session('error'))
        <div role="alert" class="p-4 rounded-2xl text-sm font-semibold bg-rose-100 text-rose-800 border border-rose-200">
            {{ session('error') }}
        </div>
        @endif

        <!-- Filtros -->
        <form method="GET" action="{{ route('admin.usuarios.index') }}" class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-sm"></i>
                <label for="buscar" class="sr-only">Buscar</label>
                <input type="search" id="buscar" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre o correo..."
                    class="w-full pl-9 pr-3 py-2.5 text-sm text-slate-800 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>
            <label for="filtro-rol" class="sr-only">Rol</label>
            <select id="filtro-rol" name="rol" class="py-2.5 px-3 text-sm text-slate-800 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Todos los roles</option>
                <option value="caseta" @selected(request('rol') === 'caseta')>Caseta</option>
                <option value="admin" @selected(request('rol') === 'admin')>Administrador</option>
            </select>
            <button type="submit" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl">
                Filtrar
            </button>
        </form>

        <!-- Tabla -->
        <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th scope="col" class="px-4 py-3.5">Usuario</th>
                        <th scope="col" class="px-4 py-3.5">Rol</th>
                        <th scope="col" class="px-4 py-3.5">Estado</th>
                        <th scope="col" class="px-4 py-3.5">Alta</th>
                        <th scope="col" class="px-4 py-3.5 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($usuarios as $usuario)
                    @php($esYo = $usuario->is(auth()->user()))
                    <tr class="hover:bg-slate-50/80 transition-colors {{ $usuario->activo ? '' : 'opacity-60' }}">
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-900">
                                {{ $usuario->name }}
                                @if ($esYo)
                                <span class="ml-1 text-[11px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded">Tú</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-500">{{ $usuario->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($usuario->rol === 'admin')
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                <i class="fa-solid fa-gauge"></i> Administrador
                            </span>
                            @else
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                <i class="fa-solid fa-mobile-screen"></i> Caseta
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($usuario->activo)
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Activo</span>
                            @else
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">
                            {{ $usuario->created_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1.5">
                                <a href="{{ route('admin.usuarios.edit', $usuario) }}" title="Editar"
                                    class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-bold transition border border-blue-200">
                                    <i class="fa-solid fa-pen-to-square"></i> Editar
                                </a>

                                @unless ($esYo)
                                <form method="POST" action="{{ route('admin.usuarios.estado', $usuario) }}"
                                    onsubmit="return confirm(@js($usuario->activo ? "¿Desactivar a {$usuario->name}? Su sesión se cerrará de inmediato." : "¿Activar a {$usuario->name}?"))">
                                    @csrf
                                    @method('PATCH')
                                    @if ($usuario->activo)
                                    <button type="submit" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-bold transition border border-rose-200">
                                        <i class="fa-solid fa-user-slash"></i> Desactivar
                                    </button>
                                    @else
                                    <button type="submit" class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold transition border border-emerald-200">
                                        <i class="fa-solid fa-user-check"></i> Activar
                                    </button>
                                    @endif
                                </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-slate-400 font-medium">No se encontraron usuarios.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($usuarios->hasPages())
        <div class="pt-2">{{ $usuarios->links() }}</div>
        @endif
    </div>
</div>
@endsection
