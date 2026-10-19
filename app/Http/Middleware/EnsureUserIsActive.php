<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario desactivado en su siguiente petición,
 * aunque haya iniciado sesión antes o tenga la cookie de "recordarme".
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu cuenta se encuentra desactivada. Contacta al administrador.',
                ], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta se encuentra desactivada. Contacta al administrador.',
            ]);
        }

        return $next($request);
    }
}
