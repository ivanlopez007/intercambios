<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Remolque. El número económico es único por dueño (cliente_id + numero_economico). */
class Caja extends Model
{
    protected $connection = 'db_externa';

    protected $table = 'cajas';

    protected $fillable = [
        'cliente_id',
        'tipo_caja_id',
        'numero_economico',
        'placa',
        'estado_placa',
        'marca',
        'modelo',
        'vin',
        'anio',
        'tamano_pies',
        'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tipoCaja(): BelongsTo
    {
        return $this->belongsTo(TipoCaja::class);
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
