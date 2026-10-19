<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro del estado de una caja y su tractocamión al entrar o salir del patio.
 * El guardia (`guardia_id`) es un usuario de la base local: no hay relación Eloquent.
 */
class Intercambio extends Model
{
    public const STATUS_BORRADOR = 'borrador';

    public const STATUS_COMPLETADO = 'completado';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS = [self::STATUS_BORRADOR, self::STATUS_COMPLETADO, self::STATUS_CANCELADO];

    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    public const MOVIMIENTOS = [self::ENTRADA, self::SALIDA];

    public const CARGADA = 'cargada';

    public const VACIA = 'vacia';

    public const ESTADOS_CARGA = [self::CARGADA, self::VACIA];

    public const TIPOS_INTERCAMBIO = ['impo', 'expo', 'local'];

    protected $connection = 'db_externa';

    protected $table = 'intercambios';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'operador_licencia_vence' => 'date',
        'operador_apto_medico_vence' => 'date',
        'completado_at' => 'datetime',
        'cancelado_at' => 'datetime',
    ];

    public function operador(): BelongsTo
    {
        return $this->belongsTo(Operador::class, 'operador_id')->withTrashed();
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function tractocamion(): BelongsTo
    {
        return $this->belongsTo(Tractocamion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Destino::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Destino::class, 'destino_id');
    }

    public function relacionado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'intercambio_relacionado_id');
    }

    public function llantas(): HasMany
    {
        return $this->hasMany(LlantaIntercambio::class)->orderBy('posicion');
    }

    public function revisionPuntos(): HasMany
    {
        return $this->hasMany(RevisionPunto::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoIntercambio::class);
    }

    public function scopeCompletados(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETADO);
    }

    /** Un intercambio completado o cancelado ya no se modifica. */
    public function esSoloLectura(): bool
    {
        return $this->status !== self::STATUS_BORRADOR;
    }

    public static function movimientoContrario(string $movimiento): string
    {
        return $movimiento === self::ENTRADA ? self::SALIDA : self::ENTRADA;
    }
}
