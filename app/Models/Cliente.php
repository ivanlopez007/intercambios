<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Dueño de cajas y cliente de los viajes. Se desactiva, nunca se borra. */
class Cliente extends Model
{
    protected $connection = 'db_externa';

    protected $table = 'clientes';

    protected $fillable = ['nombre', 'rfc', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
