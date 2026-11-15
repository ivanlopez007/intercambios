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

namespace App\Http\Middleware;

use App\Services\AccesoCaseta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalida la sesión en cada petición, aunque venga de la cookie de "recordarme":
 * - perfil desactivado en Caseta;
 * - empleado de RH dado de baja, sin "Intercambios", con cambio de contraseña pendiente
 *   o cuya contraseña cambió en RH después de iniciar sesión.
 * Si algo falla, cierra la sesión y manda al login con el motivo.
 */
class VerificarAccesoCaseta
{
    public function __construct(private AccesoCaseta $acceso) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $motivo = $this->acceso->motivoParaCerrar($user, $this->huellaDe($request, $user->id));

        if ($motivo === null) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(AccesoCaseta::HUELLA));

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $motivo], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $motivo]);
    }

    /**
     * Huella guardada al iniciar sesión. Si la sesión se restauró desde la cookie de
     * "recordarme", se toma de la cookie cifrada (solo si es del mismo perfil).
     */
    private function huellaDe(Request $request, int $perfilId): ?string
    {
        if ($huella = $request->session()->get(AccesoCaseta::HUELLA)) {
            return $huella;
        }

        [$id, $huella] = array_pad(explode('|', (string) $request->cookie(AccesoCaseta::HUELLA), 2), 2, null);

        if ((int) $id !== $perfilId || ! $huella) {
            return null;
        }

        $request->session()->put(AccesoCaseta::HUELLA, $huella);

        return $huella;
    }
}
