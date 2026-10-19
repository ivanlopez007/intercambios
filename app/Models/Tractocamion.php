<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tractocamión. Solo los de permisionarios llevan línea transportista. */
class Tractocamion extends Model
{
    protected $connection = 'db_externa';

    protected $table = 'tractocamiones';

    protected $fillable = [
        'numero_economico',
        'placa',
        'estado_placa',
        'marca',
        'modelo',
        'vin',
        'anio',
        'es_permisionario',
        'linea_transportista_id',
        'activo',
    ];

    protected $casts = [
        'es_permisionario' => 'boolean',
        'activo' => 'boolean',
    ];

    public function lineaTransportista(): BelongsTo
    {
        return $this->belongsTo(LineaTransportista::class);
    }

    public function intercambios(): HasMany
    {
        return $this->hasMany(Intercambio::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
