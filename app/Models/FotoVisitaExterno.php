<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FotoVisitaExterno extends Model
{
    use HasFactory;

    protected $connection = 'db_externa';

    protected $table = 'foto_visitas';

    protected $fillable = [
        'foto_url',
        'requerimiento_foto_visita_id',
        'visita_id',
    ];

    public function requerimientoFotoVisita()
    {
        return $this->belongsTo(RequerimientoFotoVisitaExterno::class, 'requerimiento_foto_visita_id');
    }

    public function visita()
    {
        return $this->belongsTo(VisitaExterno::class, 'visita_id');
    }

    /**
     * Enlace temporal (5 minutos) a la foto privada en R2, o null si la ruta no es válida.
     */
    public function urlTemporal(): ?string
    {
        $ruta = $this->foto_url;

        if (! $ruta || ! str_starts_with($ruta, 'fotos/visitas/')) {
            return null;
        }

        return Storage::disk('r2')->temporaryUrl($ruta, Carbon::now()->addMinutes(5));
    }
}
