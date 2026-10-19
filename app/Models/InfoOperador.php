<?php

namespace App\Models;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;

/**
 * Datos personales de un operador (tabla `info_usuarios` de RH). Solo lectura.
 * No leer `rfc` ni `curp`: están cifrados con la llave de la app de RH.
 */
class InfoOperador extends Model
{
    use SoloLectura;

    protected $connection = 'db_externa';

    protected $table = 'info_usuarios';

    protected $hidden = ['rfc', 'rfc_hash', 'curp', 'curp_hash', 'nss'];
}
