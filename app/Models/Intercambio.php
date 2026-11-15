<?php

/*
 * ************************************************************
 * Captura de intercambios (asistente: checklist, llantas, fotos, firmas)
 * Archivos necesarios:
 *   IntercambioController.php, RegistrarIntercambio.php, GuardarIntercambioRequest.php,
 *   Intercambio.php, BitacoraIntercambio.php, FotoIntercambio.php, LlantaIntercambio.php,
 *   PuntoInspeccion.php, RevisionPunto.php, intercambios/nuevo.blade.php,
 *   partials/checklist.blade.php, partials/diagrama-llantas.blade.php,
 *   partials/fotos.blade.php, partials/punto.blade.php, partials/estado-subida.blade.php,
 *   intercambio.js, intercambio-almacen.js, config/intercambios.php,
 *   create_bitacora_intercambios_table.php
 * Fecha: 08 oct 2026 - 19 oct 2026
 * Realizado por: Ivan Lopez
 * ************************************************************
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro del estado de una caja y su tractocamión al entrar o salir del patio.
 * El guardia (`guardia_id`) es el id de usuarios_caseta (modelo User), no de `users` de RH.
 * No tiene llave foránea ni relación Eloquent; el nombre queda copiado en `guardia_nombre`.
 */
class Intercambio extends Model
{
    public const STATUS_BORRADOR = 'borrador';

    public const STATUS_COMPLETADO = 'completado';

    public const STATUS_CANCELADO = 'cancelado';

    public const STATUS = [self::STATUS_BORRADOR, self::STATUS_COMPLETADO, self::STATUS_CANCELADO];

    public const ENTRADA = 'entrada';

    public const SALIDA = 'salida';

    public const MOVIMIENTOS = [self::ENTRADA, self::SALIDA];

    public const CARGADA = 'cargada';

    public const VACIA = 'vacia';

    public const ESTADOS_CARGA = [self::CARGADA, self::VACIA];

    public const TIPOS_INTERCAMBIO = ['impo', 'expo', 'local'];

    /** Parámetros de filtro del historial (ver scopeFiltrar). */
    public const FILTROS = ['folio', 'caja', 'tracto', 'cliente', 'movimiento', 'status', 'desde', 'hasta'];

    protected $table = 'intercambios';

    protected $guarded = ['id'];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'operador_licencia_vence' => 'date',
        'operador_apto_medico_vence' => 'date',
        'completado_at' => 'datetime',
        'cancelado_at' => 'datetime',
    ];

    public function operador(): BelongsTo
    {
        return $this->belongsTo(Operador::class, 'operador_id')->withTrashed();
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function tractocamion(): BelongsTo
    {
        return $this->belongsTo(Tractocamion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(Destino::class, 'origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Destino::class, 'destino_id');
    }

    public function relacionado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'intercambio_relacionado_id');
    }

    public function llantas(): HasMany
    {
        return $this->hasMany(LlantaIntercambio::class)->orderBy('posicion');
    }

    public function revisionPuntos(): HasMany
    {
        return $this->hasMany(RevisionPunto::class);
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(FotoIntercambio::class);
    }

    public function scopeCompletados(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETADO);
    }

    /**
     * Filtros del historial (también los usa su reporte). Los vacíos se ignoran.
     *
     * @param  array<string, mixed>  $filtros  claves de self::FILTROS
     */
    public function scopeFiltrar(Builder $query, array $filtros): void
    {
        $filtros = array_filter($filtros, fn ($valor) => is_string($valor) && trim($valor) !== '');
        $patron = fn (string $valor) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($valor)).'%';

        if (isset($filtros['folio'])) {
            $query->whereRaw("folio LIKE ? ESCAPE '!'", [$patron($filtros['folio'])]);
        }

        if (isset($filtros['caja'])) {
            $caja = $patron($filtros['caja']);
            $query->where(fn ($q) => $q->whereRaw("caja_numero_economico LIKE ? ESCAPE '!'", [$caja])
                ->orWhereHas('caja', fn ($c) => $c->whereRaw("numero_economico LIKE ? ESCAPE '!'", [$caja])));
        }

        // Número económico copiado al registrar o el actual del catálogo de tractocamiones.
        if (isset($filtros['tracto'])) {
            $tracto = $patron($filtros['tracto']);
            $query->where(fn ($q) => $q->whereRaw("tracto_numero_economico LIKE ? ESCAPE '!'", [$tracto])
                ->orWhereHas('tractocamion', fn ($t) => $t->whereRaw("numero_economico LIKE ? ESCAPE '!'", [$tracto])));
        }

        // Cliente del viaje o dueño de la caja.
        if (isset($filtros['cliente'])) {
            $cliente = $patron($filtros['cliente']);
            $query->where(fn ($q) => $q->whereRaw("caja_cliente LIKE ? ESCAPE '!'", [$cliente])
                ->orWhereHas('cliente', fn ($c) => $c->whereRaw("nombre LIKE ? ESCAPE '!'", [$cliente])));
        }

        if (in_array($filtros['movimiento'] ?? null, self::MOVIMIENTOS, true)) {
            $query->where('tipo_movimiento', $filtros['movimiento']);
        }

        if (in_array($filtros['status'] ?? null, self::STATUS, true)) {
            $query->where('status', $filtros['status']);
        }

        foreach (['desde' => ['>=', 'startOfDay'], 'hasta' => ['<=', 'endOfDay']] as $clave => [$operador, $limite]) {
            if (isset($filtros[$clave]) && ($fecha = rescue(fn () => Carbon::parse($filtros[$clave]), report: false))) {
                $query->where('fecha_hora', $operador, $fecha->{$limite}());
            }
        }
    }

    /** Un intercambio completado o cancelado ya no se modifica. */
    public function esSoloLectura(): bool
    {
        return $this->status !== self::STATUS_BORRADOR;
    }

    public static function movimientoContrario(string $movimiento): string
    {
        return $movimiento === self::ENTRADA ? self::SALIDA : self::ENTRADA;
    }
}
