<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Administración de las cuentas de acceso (base local). Los usuarios no se eliminan:
 * se desactivan, y su sesión se cierra en la siguiente petición.
 */
class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::query()
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $buscar = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->input('buscar')).'%';
                $q->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$buscar])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$buscar]));
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
        $usuario = User::create($request->safe()->only(['name', 'email', 'rol', 'activo', 'password']));

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario {$usuario->name} creado como ".$this->nombreRol($usuario->rol).'.');
    }

    public function edit(User $usuario)
    {
        return view('admin.usuarios.form', compact('usuario'));
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        $datos = $request->safe()->only(['name', 'email', 'rol', 'activo']);

        if ($request->filled('password')) {
            $datos['password'] = $request->input('password');
        }

        $usuario->fill($datos);

        // Al cambiar la contraseña o desactivar, se invalida el "recordarme" en otros dispositivos.
        if ($usuario->isDirty('password') || ($usuario->isDirty('activo') && ! $usuario->activo)) {
            $usuario->setRememberToken(Str::random(60));
        }

        $usuario->save();

        return redirect()->route('admin.usuarios.index')
            ->with('success', "Usuario {$usuario->name} actualizado.");
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
            ? "Usuario {$usuario->name} activado."
            : "Usuario {$usuario->name} desactivado. Su sesión se cerrará de inmediato.");
    }

    private function nombreRol(string $rol): string
    {
        return $rol === 'admin' ? 'administrador' : 'usuario de caseta';
    }
}
