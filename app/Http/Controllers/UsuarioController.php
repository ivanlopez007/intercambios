<?php

/*
 * ************************************************************
 * Administración de usuarios
 * Archivos necesarios:
 *   UsuarioController.php, UsuarioRequest.php, usuarios/index.blade.php,
 *   usuarios/form.blade.php
 * Fecha: 28 jul 2026 - 07 ago 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Administración de los perfiles de Caseta (tabla usuarios_caseta). Los usuarios no se eliminan:
 * se desactivan, y su sesión se cierra en la siguiente petición.
 *
 * - Empleados de RH (vinculados): aquí solo se cambian rol y activo. Se dan de alta solos en su
 *   primer login, después de que RH les marca "Intercambios"; su nombre, correo y contraseña
 *   se administran desde RH.
 * - Cuentas genéricas: se crean y editan completas aquí.
 */
class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::query()
            ->with('empleado.info')
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->input('buscar')).'%';
                $like = fn (string $columna) => ["{$columna} LIKE ? ESCAPE '!'", [$buscar]];

                $q->where(fn ($q) => $q->whereRaw(...$like('name'))
                    ->orWhereRaw(...$like('email'))
                    // Empleados de RH: también por su nombre o número de empleado en RH.
                    ->orWhereHas('empleado', fn ($e) => $e->where(fn ($e) => $e->whereRaw(...$like('numero_empleado'))
                        ->orWhereHas('info', fn ($i) => $i->where(fn ($i) => $i->whereRaw(...$like('nombre'))
                            ->orWhereRaw(...$like('apellido_p'))
                            ->orWhereRaw(...$like('apellido_m')))))));
            })
            ->when(in_array($request->input('rol'), UsuarioRequest::ROLES, true), fn ($q) => $q->where('rol', $request->input('rol')))
            ->orderByDesc('activo')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.usuarios.index', compact('usuarios'));
    }

    public function create()
    {
        return view('admin.usuarios.form', ['usuario' => new User(['rol' => 'caseta', 'activo' => true])]);
    }

    public function store(UsuarioRequest $request)
    {
        // Desde aquí solo se crean cuentas genéricas (user_id NULL). A un empleado se le da
        // acceso en RH y su perfil se crea en su primer login.
        $usuario = User::create($request->safe()->only(['name', 'email', 'rol', 'activo', 'password']));

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario {$usuario->name} creado como ".$this->nombreRol($usuario->rol).'.');
    }

    public function edit(User $usuario)
    {
        $usuario->load('empleado.info');

        return view('admin.usuarios.form', compact('usuario'));
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        if ($usuario->esVinculado()) {
            // Nombre, correo y contraseña los administra RH: nunca se escriben desde aquí.
            $datos = $request->safe()->only(['rol', 'activo']);
        } else {
            $datos = $request->safe()->only(['name', 'email', 'rol', 'activo']);

            if ($request->filled('password')) {
                $datos['password'] = $request->input('password');
            }
        }

        $usuario->fill($datos);

        // Al cambiar la contraseña o desactivar, se invalida el "recordarme" en otros dispositivos.
        if ($usuario->isDirty('password') || ($usuario->isDirty('activo') && ! $usuario->activo)) {
            $usuario->setRememberToken(Str::random(60));
        }

        $usuario->save();

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario {$usuario->nombre_mostrado} actualizado.");
    }

    /**
     * Activa o desactiva una cuenta desde la lista.
     */
    public function estado(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $usuario->activo = ! $usuario->activo;

        if (! $usuario->activo) {
            $usuario->setRememberToken(Str::random(60));
        }

        $usuario->save();

        return back()->with('success', $usuario->activo
            ? "Usuario {$usuario->nombre_mostrado} activado."
            : "Usuario {$usuario->nombre_mostrado} desactivado. Su sesión se cerrará de inmediato.");
    }

    private function nombreRol(string $rol): string
    {
        return $rol === 'admin' ? 'administrador' : 'usuario de caseta';
    }
}
