@extends('admin.layout.layout')

@php
$editando = $usuario->exists;
$esYo = $editando && $usuario->is(auth()->user());
$rolActual = old('rol', $usuario->rol);
$activoActual = (bool) old('activo', $usuario->activo);
// Perfil de un empleado de RH: nombre, correo y contraseña se administran desde RH.
$vinculado = $editando && $usuario->esVinculado();
@endphp

@section('title', $editando ? 'Editar usuario' : 'Nuevo usuario')
@section('page-title', $editando ? 'Editar Usuario' : 'Nuevo Usuario')

@section('contenido')
<div class="max-w-2xl mx-auto py-6 px-2">
    <a href="{{ route('admin.usuarios.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-900 mb-4">
        <i class="fa-solid fa-arrow-left"></i> Volver a usuarios
    </a>

    <form method="POST"
        action="{{ $editando ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}"
        x-data="{ rol: @js($rolActual), verPassword: false, generada: '' }"
        class="bg-white border border-slate-200 rounded-3xl p-5 md:p-7 shadow-sm space-y-6" novalidate>
        @csrf
        @if ($editando)
        @method('PUT')
        @endif

        <div class="flex items-center gap-3 border-b border-slate-200 pb-4">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600">
                <i class="fa-solid {{ $editando ? 'fa-user-pen' : 'fa-user-plus' }} text-xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-slate-900 leading-tight">{{ $editando ? $usuario->nombre_mostrado : 'Nueva cuenta genérica' }}</h2>
                <p class="text-xs text-slate-500">{{ $vinculado ? 'Empleado de RH: aquí solo se cambian el rol y el estado.' : ($editando ? 'Modifica los datos, el rol o la contraseña.' : 'Crea una cuenta que no es de una persona (p. ej. el celular de la caseta).') }}</p>
            </div>
        </div>

        @if ($errors->any())
        <div role="alert" class="p-4 rounded-2xl text-sm font-semibold bg-rose-100 text-rose-800 border border-rose-200">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @unless ($editando)
        <div class="p-4 rounded-2xl text-sm bg-blue-50 text-blue-900 border border-blue-200">
            <i class="fa-solid fa-circle-info"></i>
            ¿Es un empleado? No lo crees aquí: en la app de RH captura su correo real y márcale <strong>Intercambios</strong>.
            Su perfil se crea solo en su primer inicio de sesión, con rol Caseta.
        </div>
        @endunless

        <!-- Datos -->
        @if ($vinculado)
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Datos del empleado</span>
                <span class="text-[11px] font-semibold text-slate-600 bg-white border border-slate-200 px-2 py-0.5 rounded">
                    <i class="fa-solid fa-lock"></i> Se administra desde RH
                </span>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="sm:col-span-2">
                    <dt class="text-xs text-slate-500">Nombre completo</dt>
                    <dd class="font-semibold text-slate-900">{{ $usuario->nombre_mostrado }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Correo (para iniciar sesión)</dt>
                    <dd class="font-semibold text-slate-900 break-all">{{ $usuario->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Número de empleado</dt>
                    <dd class="font-semibold text-slate-900">{{ $usuario->empleado?->numero_empleado ?? '—' }}</dd>
                </div>
            </dl>
            @if ($usuario->empleado?->trashed())
            <p class="text-xs font-semibold text-rose-700"><i class="fa-solid fa-triangle-exclamation"></i> Este empleado está dado de baja en RH: no puede iniciar sesión.</p>
            @endif
        </div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label for="name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre completo</label>
                <input type="text" id="name" name="name" value="{{ old('name', $usuario->name) }}" required maxlength="255" autocomplete="off"
                    class="w-full px-4 py-3 bg-white border rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 {{ $errors->has('name') ? 'border-rose-400' : 'border-slate-300' }}">
            </div>

            <div class="sm:col-span-2">
                <label for="email" class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Correo electrónico (para iniciar sesión)</label>
                <input type="email" id="email" name="email" value="{{ old('email', $usuario->email) }}" required maxlength="255" autocomplete="off"
                    class="w-full px-4 py-3 bg-white border rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 {{ $errors->has('email') ? 'border-rose-400' : 'border-slate-300' }}">
            </div>
        </div>
        @endif

        <!-- Rol -->
        <fieldset>
            <legend class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Rol</legend>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="relative flex gap-3 p-4 rounded-2xl border-2 transition {{ $esYo ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}"
                    :class="rol === 'caseta' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'">
                    <input type="radio" name="rol" value="caseta" x-model="rol" class="mt-1 text-blue-600 focus:ring-blue-600" @disabled($esYo)>
                    <span>
                        <span class="block font-bold text-slate-900"><i class="fa-solid fa-mobile-screen text-blue-600"></i> Caseta</span>
                        <span class="block text-xs text-slate-500 mt-0.5">Registra entradas y salidas desde el celular y genera QR. No ve el panel de administración.</span>
                    </span>
                </label>

                <label class="relative flex gap-3 p-4 rounded-2xl border-2 cursor-pointer transition"
                    :class="rol === 'admin' ? 'border-amber-500 bg-amber-50' : 'border-slate-200 hover:border-slate-300'">
                    <input type="radio" name="rol" value="admin" x-model="rol" class="mt-1 text-amber-600 focus:ring-amber-600">
                    <span>
                        <span class="block font-bold text-slate-900"><i class="fa-solid fa-gauge text-amber-600"></i> Administrador</span>
                        <span class="block text-xs text-slate-500 mt-0.5">Acceso completo: historial, empresas, usuarios y también la caseta.</span>
                    </span>
                </label>
            </div>
            @if ($esYo)
            <input type="hidden" name="rol" value="admin">
            <p class="mt-2 text-xs text-slate-500"><i class="fa-solid fa-circle-info"></i> No puedes quitarte el rol de administrador a ti mismo.</p>
            @endif
        </fieldset>

        <!-- Contraseña -->
        @if ($vinculado)
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Contraseña</p>
            <p class="text-sm text-slate-700">
                <i class="fa-solid fa-lock text-slate-400"></i>
                {{ $esYo ? 'Tu contraseña es la misma de la app de RH. Cámbiala desde RH.' : 'Es la misma de la app de RH. Se administra desde RH.' }}
            </p>
        </div>
        @else
        <fieldset class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <legend class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                    {{ $editando ? 'Cambiar contraseña (opcional)' : 'Contraseña' }}
                </legend>
                <button type="button"
                    @click="generada = generarPassword(); $refs.password.value = generada; $refs.confirmacion.value = generada; verPassword = true"
                    class="text-xs font-bold text-blue-700 bg-blue-100 hover:bg-blue-200 px-2.5 py-1.5 rounded-lg border border-blue-200">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Generar segura
                </button>
            </div>

            @if ($editando)
            <p class="text-xs text-slate-500">Déjala vacía para no cambiarla. Si la cambias, el usuario tendrá que volver a iniciar sesión en sus otros dispositivos.</p>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-600 mb-1">Nueva contraseña</label>
                    <input :type="verPassword ? 'text' : 'password'" id="password" name="password" x-ref="password" minlength="8" autocomplete="new-password"
                        @if (! $editando) required @endif
                        class="w-full px-4 py-3 bg-white border rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 font-mono {{ $errors->has('password') ? 'border-rose-400' : 'border-slate-300' }}">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-slate-600 mb-1">Confirmar contraseña</label>
                    <input :type="verPassword ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" x-ref="confirmacion" minlength="8" autocomplete="new-password"
                        class="w-full px-4 py-3 bg-white border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 outline-none text-slate-900 font-mono">
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600 cursor-pointer select-none">
                    <input type="checkbox" x-model="verPassword" class="rounded border-slate-300 text-blue-600 focus:ring-blue-600">
                    Mostrar contraseña
                </label>
                <p class="text-xs text-slate-500">Mínimo 8 caracteres.</p>
            </div>

            <div x-show="generada" x-cloak class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Copia la contraseña generada y entrégasela al usuario de forma segura; no se volverá a mostrar.
                <button type="button" @click="navigator.clipboard?.writeText(generada)" class="ml-1 font-bold underline">Copiar</button>
            </div>
        </fieldset>
        @endif

        <!-- Estado -->
        @if ($editando)
        <div>
            <input type="hidden" name="activo" value="{{ $esYo ? '1' : '0' }}">
            <label class="flex items-start gap-3 p-4 rounded-2xl border border-slate-200 {{ $esYo ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
                <input type="checkbox" name="activo" value="1" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                    @checked($activoActual) @disabled($esYo)>
                <span>
                    <span class="block font-bold text-slate-900 text-sm">Cuenta activa</span>
                    <span class="block text-xs text-slate-500">Si la desactivas, el usuario no podrá entrar y su sesión abierta se cerrará de inmediato.@if ($vinculado) Solo bloquea su perfil en Caseta; para quitarle el acceso por completo, desmárcale <strong>Intercambios</strong> en RH.@endif</span>
                </span>
            </label>
        </div>
        @else
        <input type="hidden" name="activo" value="1">
        @endif

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2 border-t border-slate-200">
            <a href="{{ route('admin.usuarios.index') }}"
                class="px-5 py-3 text-center bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-bold">
                Cancelar
            </a>
            <button type="submit" class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-bold shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                {{ $editando ? 'Guardar cambios' : 'Crear cuenta' }}
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Contraseña aleatoria de 14 caracteres sin caracteres confusos (0/O, 1/l/I).
    function generarPassword() {
        const caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%*?';
        const valores = crypto.getRandomValues(new Uint32Array(14));
        return Array.from(valores, (v) => caracteres[v % caracteres.length]).join('');
    }
</script>
@endpush
