<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Tu cuenta se encuentra desactivada. Contacta al administrador.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $destination = $user->rol === 'admin'
            ? route('admin.dashboard')
            : route('caseta.menu');

        // Un usuario de caseta no debe volver a una página de administración que intentó abrir antes.
        $intended = $request->session()->pull('url.intended');
        if ($intended && $user->rol !== 'admin' && str_starts_with((string) parse_url($intended, PHP_URL_PATH), '/admin')) {
            $intended = null;
        }

        return redirect()->to($intended ?? $destination)
            ->with('status', '¡Bienvenido de nuevo, '.$user->name.'!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Has cerrado sesión correctamente.');
    }
}
