<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Intercambios recibidos de los dispositivos (base local). `id_cliente` es el UUID que generó
 * el dispositivo: evita duplicados cuando reenvía un intercambio guardado sin conexión.
 * `intercambio_id` apunta a la tabla `intercambios` de la base de RH.
 */
class BitacoraIntercambio extends Model
{
    protected $table = 'bitacora_intercambios';

    protected $fillable = ['id_cliente', 'intercambio_id', 'user_id', 'usuario_nombre'];
}
