<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VisitaExterno extends Model
{
    use HasFactory;

    protected $connection = 'db_externa';

    protected $table = 'visitas';

    protected $fillable = [
        'nombre_persona',
        'empresa',
        'motivo_visita',
        'persona_a_visitar',
        'firma_url',
        'fecha_entrada',
        'fecha_salida',
    ];

    protected $casts = [
        'fecha_entrada' => 'datetime',
        'fecha_salida' => 'datetime',
    ];

    public function fotoVisitas()
    {
        return $this->hasMany(FotoVisitaExterno::class, 'visita_id');
    }

    public function getFirmaFullUrlAttribute()
    {
        return $this->firma_url;
    }

    public function getFirmaUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $key = $path === null || $path === false
            ? $value
            : ltrim(rawurldecode($path), '/');

        if (! str_starts_with($key, 'firmas/visitas/')) {
            return null;
        }

        return Storage::disk('r2')->temporaryUrl($key, Carbon::now()->addMinutes(5));
    }
}
