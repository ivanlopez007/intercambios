<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Punto de la revisión C-TPAT (precargado por la app de RH). Se desactiva o reordena, nunca se borra. */
class PuntoInspeccion extends Model
{
    public const SECCION_CTPAT = 'ctpat';

    public const SECCION_OLORES = 'olores';

    public const SECCION_RESIDUOS = 'residuos';

    /** Checklist del remolque del contrato de intercambio (luces, birlos, conector…). */
    public const SECCION_REMOLQUE = 'remolque';

    /** Daños de la caja por lado, marcados con G (golpe), C (corte) o A (agujero). */
    public const SECCION_DANOS_CAJA = 'danos_caja';

    public const SECCIONES = [
        self::SECCION_CTPAT => 'Revisión C-TPAT',
        self::SECCION_OLORES => 'Olores',
        self::SECCION_RESIDUOS => 'Residuos',
        self::SECCION_REMOLQUE => 'Revisión del remolque',
        self::SECCION_DANOS_CAJA => 'Daños de la caja',
    ];

    public const RESPUESTA_OK_FALLA = 'ok_falla';

    public const RESPUESTA_DETECCION = 'deteccion';

    public const RESPUESTA_DANO = 'dano';

    /**
     * Puntos que agrega esta app (contrato de intercambio de remolque). Los de C-TPAT, olores y
     * residuos los precarga la app de RH. Los inserta PuntosInspeccionContratoSeeder.
     * `clave` es la abreviatura del lado en el diagrama del contrato.
     */
    public const PUNTOS_CONTRATO = [
        self::SECCION_REMOLQUE => [
            'Luces', 'Marcadores', 'Reflectores', 'Loderas', 'Manivela', 'Birlos', 'Conector 7 pines',
            'Cableado', 'Topes', 'Fuga de aire', 'Manguera', 'Conexiones', 'Tubería',
        ],
        self::SECCION_DANOS_CAJA => [
            'LS' => 'Lado izquierdo', 'F' => 'Frente', 'T' => 'Techo',
            'FL' => 'Piso', 'RS' => 'Lado derecho', 'R' => 'Trasera (puertas)',
        ],
    ];

    protected $connection = 'db_externa';

    protected $table = 'puntos_inspeccion';

    protected $fillable = ['nombre', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function subpuntos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden');
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
