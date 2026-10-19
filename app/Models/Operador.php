<?php

namespace App\Models;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Operador: usuario activo de RH cuya área es una de las de operadores (config intercambios.areas_operadores).
 * Solo lectura; los administra la app de RH.
 */
class Operador extends Model
{
    use SoftDeletes, SoloLectura;

    protected $connection = 'db_externa';

    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::addGlobalScope('operadores', function (Builder $query) {
            $query->whereHas('area', fn (Builder $area) => $area->whereIn('area', config('intercambios.areas_operadores')));
        });
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaExterno::class, 'area_id');
    }

    // Nunca se seleccionan rfc ni curp: están cifrados con la llave de la app de RH.
    public function info(): HasOne
    {
        return $this->hasOne(InfoOperador::class, 'usuario_id')
            ->select(['id', 'usuario_id', 'nombre', 'apellido_p', 'apellido_m']);
    }

    public function documento(): HasOne
    {
        return $this->hasOne(DocumentoOperador::class, 'operador_id')->latestOfMany();
    }

    public function getNombreCompletoAttribute(): string
    {
        $info = $this->info;

        return $info
            ? trim(preg_replace('/\s+/', ' ', "{$info->nombre} {$info->apellido_p} {$info->apellido_m}"))
            : "Empleado {$this->numero_empleado}";
    }
}
