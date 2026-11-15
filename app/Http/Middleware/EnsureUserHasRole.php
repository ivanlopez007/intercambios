<?php

/*
 * ************************************************************
 * Login y control de acceso por rol
 * Archivos necesarios:
 *   AuthController.php, login.blade.php, EnsureUserHasRole.php, EnsureUserIsActive.php,
 *   User.php, create_users_table.php, routes/web.php
 * Fecha: 14 jul 2026 - 25 jul 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a los roles indicados. Uso: ->middleware('rol:admin') o 'rol:caseta,admin'.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->rol, $roles, true)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
