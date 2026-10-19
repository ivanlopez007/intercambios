<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de un intercambio. En la base solo va la ruta del objeto privado en R2. */
class FotoIntercambio extends Model
{
    public const SELLO = 'sello';

    public const PUNTO = 'punto';

    public const LLANTA = 'llanta';

    public const PROBLEMA = 'problema';

    /** Fotos obligatorias de la unidad (config intercambios.recorrido). */
    public const RECORRIDO = 'recorrido';

    public const TIPOS = [self::SELLO, self::PUNTO, self::LLANTA, self::PROBLEMA, self::RECORRIDO];

    public const UNIDADES = ['caja', 'tracto'];

    protected $connection = 'db_externa';

    protected $table = 'fotos_intercambio';

    protected $guarded = ['id'];

    public function intercambio(): BelongsTo
    {
        return $this->belongsTo(Intercambio::class);
    }

    public function revisionPunto(): BelongsTo
    {
        return $this->belongsTo(RevisionPunto::class);
    }

    public function llanta(): BelongsTo
    {
        return $this->belongsTo(LlantaIntercambio::class, 'llanta_intercambio_id');
    }

    /** Claves válidas del recorrido de fotos, en orden. */
    public static function vistas(): array
    {
        return array_column(config('intercambios.recorrido'), 'clave');
    }

    /**
     * Vista del recorrido (por ejemplo, trc_frontal). Se guarda como carpeta en la ruta:
     * intercambios/{uuid}/recorrido/{vista}/{foto}.jpg
     */
    public function getVistaAttribute(): ?string
    {
        return $this->tipo === self::RECORRIDO ? basename(dirname($this->path)) : null;
    }

    public function getEtiquetaVistaAttribute(): ?string
    {
        $vista = collect(config('intercambios.recorrido'))->firstWhere('clave', $this->vista);

        return $vista['etiqueta'] ?? $this->vista;
    }
}
