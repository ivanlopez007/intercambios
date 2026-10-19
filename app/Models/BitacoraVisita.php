<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quién registró la entrada o la salida de una visita (base local).
 * `visita_id` apunta a la tabla `visitas` de la base externa.
 */
class BitacoraVisita extends Model
{
    protected $table = 'bitacora_visitas';

    protected $fillable = [
        'visita_id',
        'tipo',
        'user_id',
        'usuario_nombre',
        'id_cliente',
        'registrado_en',
    ];

    protected $casts = [
        'registrado_en' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Nombres de quién registró entrada y salida, por visita:
     * [visita_id => ['entrada' => 'Juan', 'salida' => 'Ana']].
     */
    public static function autoresDe(iterable $visitaIds): array
    {
        $autores = [];

        static::whereIn('visita_id', collect($visitaIds)->all())
            ->get(['visita_id', 'tipo', 'usuario_nombre'])
            ->each(function (self $registro) use (&$autores) {
                $autores[$registro->visita_id][$registro->tipo] = $registro->usuario_nombre;
            });

        return $autores;
    }
}
