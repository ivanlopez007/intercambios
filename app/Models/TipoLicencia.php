<?php

namespace App\Models;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;

/** Catálogo de tipos de licencia de RH. Solo lectura. */
class TipoLicencia extends Model
{
    use SoloLectura;

    protected $connection = 'db_externa';

    protected $table = 'tipo_licencias';
}
