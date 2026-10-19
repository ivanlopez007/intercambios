<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una llanta revisada. Posiciones: 1-2 direccionales del tracto, 3-10 tracción, 11-18 caja.
 */
class LlantaIntercambio extends Model
{
    public const ORIGINAL = 'original';

    public const RENOVADA = 'renovada';

    public const TIPOS = [self::ORIGINAL, self::RENOVADA];

    protected $connection = 'db_externa';

    protected $table = 'llantas_intercambio';

    protected $guarded = ['id'];

    public function intercambio(): BelongsTo
    {
        return $this->belongsTo(Intercambio::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoIntercambio::class);
    }

    /** rojo (bajo el mínimo legal), amarillo o verde, según config('intercambios.llantas'). */
    public static function color(int $posicion, ?int $profundidad): ?string
    {
        if ($profundidad === null) {
            return null;
        }

        $config = config('intercambios.llantas');
        $umbral = in_array($posicion, $config['direccionales']['posiciones'], true)
            ? $config['direccionales']
            : $config['resto'];

        return match (true) {
            $profundidad < $umbral['minimo'] => 'rojo',
            $profundidad <= $umbral['alerta'] => 'amarillo',
            default => 'verde',
        };
    }

    public function getColorAttribute(): ?string
    {
        return self::color($this->posicion, $this->profundidad_32);
    }
}
