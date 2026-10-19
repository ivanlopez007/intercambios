<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Resultado de un punto revisado. Se guarda una fila por cada punto, no solo los que fallan. */
class RevisionPunto extends Model
{
    public const CUMPLE = 'cumple';

    public const NO_CUMPLE = 'no_cumple';

    public const NO_APLICA = 'no_aplica';

    public const NO_DETECTADO = 'no_detectado';

    public const DETECTADO = 'detectado';

    // Daños de la caja por lado (contrato de intercambio).
    public const SIN_DANO = 'sin_dano';

    public const GOLPE = 'golpe';

    public const CORTE = 'corte';

    public const AGUJERO = 'agujero';

    /** Daños marcados en el diagrama de la caja, con su letra. */
    public const DANOS = [self::GOLPE => 'G', self::CORTE => 'C', self::AGUJERO => 'A'];

    /** Respuestas válidas por tipo de respuesta del punto. */
    public const RESULTADOS = [
        PuntoInspeccion::RESPUESTA_OK_FALLA => [self::CUMPLE, self::NO_CUMPLE, self::NO_APLICA],
        PuntoInspeccion::RESPUESTA_DETECCION => [self::NO_DETECTADO, self::DETECTADO],
        PuntoInspeccion::RESPUESTA_DANO => [self::SIN_DANO, self::GOLPE, self::CORTE, self::AGUJERO],
    ];

    /** Resultados que exigen observaciones. */
    public const CON_HALLAZGO = [self::NO_CUMPLE, self::DETECTADO];

    public const ETIQUETAS = [
        self::CUMPLE => 'Cumple',
        self::NO_CUMPLE => 'No cumple',
        self::NO_APLICA => 'No aplica',
        self::NO_DETECTADO => 'No detectado',
        self::DETECTADO => 'Detectado',
        self::SIN_DANO => 'Sin daño',
        self::GOLPE => 'Golpe (G)',
        self::CORTE => 'Corte (C)',
        self::AGUJERO => 'Agujero (A)',
    ];

    protected $connection = 'db_externa';

    protected $table = 'revision_puntos';

    protected $guarded = ['id'];

    public function intercambio(): BelongsTo
    {
        return $this->belongsTo(Intercambio::class);
    }

    public function punto(): BelongsTo
    {
        return $this->belongsTo(PuntoInspeccion::class, 'punto_inspeccion_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoIntercambio::class);
    }

    public function tieneHallazgo(): bool
    {
        return in_array($this->resultado, self::CON_HALLAZGO, true);
    }

    /** Hallazgo o daño marcado: se muestra en rojo. */
    public function esNegativo(): bool
    {
        return $this->tieneHallazgo() || array_key_exists($this->resultado, self::DANOS);
    }
}
