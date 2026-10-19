<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ubicación usada como origen o destino de un viaje. */
class Destino extends Model
{
    protected $connection = 'db_externa';

    protected $table = 'destinos';

    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
