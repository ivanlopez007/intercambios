<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Modelos de la base de RH que esta app solo puede leer (operadores y sus documentos).
 * Cualquier intento de guardar o borrar lanza una excepción.
 */
trait SoloLectura
{
    public static function bootSoloLectura(): void
    {
        $bloquear = fn () => throw new LogicException(static::class.' es de solo lectura: lo administra la app de RH.');

        static::saving($bloquear);
        static::deleting($bloquear);
    }
}
