<?php

namespace App\Models;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Licencia y apto médico de un operador (tabla `documento_operadors` de RH). Solo lectura. */
class DocumentoOperador extends Model
{
    use SoloLectura;

    protected $connection = 'db_externa';

    protected $table = 'documento_operadors';

    protected $casts = [
        'fecha_vencimiento_licencia' => 'date',
        'fecha_vencimiento_apto' => 'date',
    ];

    public function tipoLicencia(): BelongsTo
    {
        return $this->belongsTo(TipoLicencia::class, 'tipo_licencia_id');
    }
}
