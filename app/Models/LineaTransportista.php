<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Línea de los tractocamiones de permisionarios. */
class LineaTransportista extends Model
{
    protected $connection = 'db_externa';

    protected $table = 'lineas_transportistas';

    protected $fillable = ['nombre', 'rfc', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function tractocamiones(): HasMany
    {
        return $this->hasMany(Tractocamion::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
