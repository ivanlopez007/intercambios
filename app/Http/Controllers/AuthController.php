<?php

/*
 * ************************************************************
 * Login y control de acceso por rol
 * Archivos necesarios:
 *   AuthController.php, login.blade.php, EnsureUserHasRole.php, VerificarAccesoCaseta.php,
 *   AccesoCaseta.php, User.php, EmpleadoRh.php, routes/web.php
 * Fecha: 14 jul 2026 - 25 jul 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

namespace App\Http\Controllers;

use App\Services\AccesoCaseta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

/**
 * Login con correo + contraseña. Los empleados de RH usan la misma contraseña de la app de RH;
 * las cuentas genéricas, la suya. El algoritmo completo está en AccesoCaseta.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('auth.login');
    }

    public function login(Request $request, AccesoCaseta $acceso)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        try {
            $user = $acceso->autenticar((string) $request->input('email'), (string) $request->input('password'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->onlyInput('email');
        }

        $recordar = $request->boolean('remember');
        Auth::login($user, $recordar);
        $request->session()->regenerate();

        // Huella de la contraseña de RH: si la cambian en RH, la siguiente petición cierra la sesión.
        // Con "recordarme" también va en una cookie cifrada, para validar las sesiones que se
        // restauran desde la cookie de recordar.
        if ($user->esVinculado()) {
            $huella = $acceso->huella($user->empleado);
            $request->session()->put(AccesoCaseta::HUELLA, $huella);

            $recordar
                ? Cookie::queue(AccesoCaseta::HUELLA, $user->id.'|'.$huella, 60 * 24 * 400)
                : Cookie::queue(Cookie::forget(AccesoCaseta::HUELLA));
        }

        $destination = $user->rol === 'admin'
            ? route('admin.dashboard')
            : route('caseta.menu');

        // Un usuario de caseta no debe volver a una página de administración que intentó abrir antes.
        $intended = $request->session()->pull('url.intended');
        if ($intended && $user->rol !== 'admin' && str_starts_with((string) parse_url($intended, PHP_URL_PATH), '/admin')) {
            $intended = null;
        }

        return redirect()->to($intended ?? $destination)
            ->with('status', '¡Bienvenido de nuevo, '.$user->nombre_mostrado.'!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(AccesoCaseta::HUELLA));

        return redirect()->route('login')
            ->with('status', 'Has cerrado sesión correctamente.');
    }
}
