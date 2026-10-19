<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequerimientoFotoVisitaExterno extends Model
{
    use HasFactory;

    protected $connection = 'db_externa';

    protected $table = 'requerimiento_foto_visitas';

    protected $fillable = [
        'parte',
    ];

    /** Tipos de foto que se toman en la entrada de caseta. */
    public const PLACAS = 'Placas';

    public const VEHICULO = 'Vehículo';

    public function fotoVisitas()
    {
        return $this->hasMany(FotoVisitaExterno::class, 'requerimiento_foto_visita_id');
    }
}
