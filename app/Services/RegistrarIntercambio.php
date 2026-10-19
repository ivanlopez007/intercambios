<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\FotoIntercambio;
use App\Models\Intercambio;
use App\Models\LineaTransportista;
use App\Models\Operador;
use App\Models\RevisionPunto;
use App\Models\Tractocamion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Crea un intercambio completado con todo su detalle en una sola transacción sobre la base de RH:
 * folio, catálogos dados de alta en el asistente, llantas, revisión, fotos, firmas y la copia de
 * datos del operador, la caja y el tracto.
 *
 * Las fotos ya están en R2 (se suben una por una antes); aquí solo se enlazan. Las firmas se
 * suben aquí y se borran si la transacción falla.
 */
class RegistrarIntercambio
{
    public const DISCO = 'r2';

    /** Carpeta de un intercambio en R2. Usa el UUID del dispositivo porque el id aún no existe. */
    public static function carpeta(string $idCliente): string
    {
        return "intercambios/{$idCliente}";
    }

    /** Las fotos del recorrido llevan su vista como subcarpeta: recorrido/{vista}/{uuid}.jpg */
    public static function rutaFoto(string $idCliente, string $tipo, string $uuid, string $extension, ?string $vista = null): string
    {
        $carpeta = $tipo === FotoIntercambio::RECORRIDO ? "{$tipo}/{$vista}" : $tipo;

        return self::carpeta($idCliente)."/{$carpeta}/{$uuid}.{$extension}";
    }

    /**
     * @param  array  $datos  Datos ya validados por GuardarIntercambioRequest.
     */
    public function ejecutar(array $datos, User $guardia): Intercambio
    {
        $idCliente = $datos['id_cliente'];
        // Una sola consulta a R2 con todo lo subido para este intercambio.
        $subidas = Storage::disk(self::DISCO)->allFiles(self::carpeta($idCliente));
        $rutasFotos = $this->localizarFotos($idCliente, $subidas, $datos['fotos'] ?? []);
        $firmas = [];

        try {
            foreach (['operador', 'guardia'] as $quien) {
                $firmas[$quien] = $this->guardarFirma($idCliente, $quien, $datos["firma_{$quien}"]);
            }

            $intercambio = DB::connection('db_externa')->transaction(
                fn () => $this->crear($datos, $guardia, $rutasFotos, $firmas)
            );
        } catch (Throwable $e) {
            Storage::disk(self::DISCO)->delete(array_values($firmas));

            throw $e;
        }

        $this->borrarNoUsadas($subidas, [...array_values($rutasFotos), ...array_values($firmas)]);

        return $intercambio;
    }

    /**
     * Las fotos se suben mientras se captura; si después se reemplazaron o se quitaron, quedan en
     * R2 sin usarse. Se borran al registrar el intercambio. Un error aquí no afecta el registro.
     */
    private function borrarNoUsadas(array $subidas, array $usadas): void
    {
        $sobrantes = array_values(array_diff($subidas, $usadas));

        if ($sobrantes === []) {
            return;
        }

        try {
            Storage::disk(self::DISCO)->delete($sobrantes);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function crear(array $datos, User $guardia, array $rutasFotos, array $firmas): Intercambio
    {
        $operador = Operador::with(['info', 'documento.tipoLicencia'])->findOrFail($datos['operador_id']);
        $caja = $this->resolverCaja($datos['caja']);
        $tracto = $this->resolverTracto($datos['tracto']);
        $fecha = Carbon::parse($datos['fecha_hora'], config('app.timezone'));

        $clienteId = $this->resolverCatalogo(Cliente::class, $datos['cliente_id'] ?? null, $datos['cliente_nuevo'] ?? null)
            ?? $caja->cliente_id;

        $relacionado = Intercambio::completados()
            ->where('caja_id', $caja->id)
            ->where('tipo_movimiento', Intercambio::movimientoContrario($datos['tipo_movimiento']))
            ->latest('fecha_hora')
            ->latest('id')
            ->value('id');

        $documento = $operador->documento;
        $cargada = $datos['estado_carga'] === Intercambio::CARGADA;

        $intercambio = Intercambio::create([
            'folio' => $this->siguienteFolio($fecha->year),
            'status' => Intercambio::STATUS_COMPLETADO,
            'paso_actual' => 10,
            'tipo_movimiento' => $datos['tipo_movimiento'],
            'fecha_hora' => $fecha,
            'operador_id' => $operador->id,
            'guardia_id' => $guardia->id,
            'guardia_nombre' => $guardia->name,
            'caja_id' => $caja->id,
            'intercambio_relacionado_id' => $relacionado,
            'tractocamion_id' => $tracto->id,
            'odometro' => $datos['odometro'] ?? null,
            'estado_carga' => $datos['estado_carga'],
            'tipo_intercambio' => $datos['tipo_intercambio'],
            'cliente_id' => $clienteId,
            'origen_id' => $this->resolverDestino($datos['origen_id'] ?? null, $datos['origen_nuevo'] ?? null),
            'destino_id' => $this->resolverDestino($datos['destino_id'] ?? null, $datos['destino_nuevo'] ?? null),
            'referencia_carta_porte' => $datos['referencia_carta_porte'] ?? null,
            'numero_sello' => $cargada ? $datos['numero_sello'] : null,
            'comentarios_caja' => $datos['comentarios_caja'] ?? null,
            'comentarios_tracto' => $datos['comentarios_tracto'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'firma_operador_path' => $firmas['operador'],
            'firma_guardia_path' => $firmas['guardia'],

            // Copia de datos al completar: el intercambio conserva lo que había en ese momento.
            'operador_nombre' => $operador->nombre_completo,
            'operador_numero_empleado' => $operador->numero_empleado,
            'operador_licencia' => $documento ? trim($documento->numero_licencia.' '.($documento->tipoLicencia?->tipo_licencia ? "({$documento->tipoLicencia->tipo_licencia})" : '')) : null,
            'operador_licencia_vence' => $documento?->fecha_vencimiento_licencia,
            'operador_apto_medico_vence' => $documento?->fecha_vencimiento_apto,
            'caja_numero_economico' => $caja->numero_economico,
            'caja_placa' => $caja->placa,
            'caja_cliente' => $caja->cliente?->nombre,
            'tracto_numero_economico' => $tracto->numero_economico,
            'tracto_placa' => $tracto->placa,
            'tracto_linea' => $tracto->lineaTransportista?->nombre,
            'completado_at' => now(),
        ]);

        $llantas = [];
        foreach ($datos['llantas'] as $llanta) {
            $llantas[(int) $llanta['posicion']] = $intercambio->llantas()->create([
                'posicion' => $llanta['posicion'],
                'marca' => $llanta['marca'] ?? null,
                'profundidad_32' => $llanta['profundidad_32'],
                'psi' => $llanta['psi'],
                'tipo' => $llanta['tipo'] ?? null,
                'observaciones' => $llanta['observaciones'] ?? null,
            ])->id;
        }

        $revisiones = [];
        foreach ($datos['revision'] as $fila) {
            $hallazgo = in_array($fila['resultado'], RevisionPunto::CON_HALLAZGO, true);

            $revisiones[(int) $fila['punto_inspeccion_id']] = $intercambio->revisionPuntos()->create([
                'punto_inspeccion_id' => $fila['punto_inspeccion_id'],
                'resultado' => $fila['resultado'],
                'observaciones' => $hallazgo ? $fila['observaciones'] : ($fila['observaciones'] ?? null),
            ])->id;
        }

        foreach ($datos['fotos'] ?? [] as $foto) {
            $intercambio->fotos()->create([
                'tipo' => $foto['tipo'],
                'revision_punto_id' => $foto['tipo'] === FotoIntercambio::PUNTO ? $revisiones[(int) $foto['punto_inspeccion_id']] : null,
                'llanta_intercambio_id' => $foto['tipo'] === FotoIntercambio::LLANTA ? ($llantas[(int) $foto['posicion']] ?? null) : null,
                'unidad' => match ($foto['tipo']) {
                    FotoIntercambio::PROBLEMA => $foto['unidad'],
                    FotoIntercambio::RECORRIDO => collect(config('intercambios.recorrido'))->firstWhere('clave', $foto['vista'])['unidad'],
                    default => null,
                },
                'path' => $rutasFotos[$foto['uuid']],
            ]);
        }

        return $intercambio;
    }

    /**
     * Folio INT-AAAA-000001, consecutivo por año. El bloqueo de la fila evita que dos
     * dispositivos obtengan el mismo folio al mismo tiempo.
     */
    private function siguienteFolio(int $anio): string
    {
        $tabla = fn () => DB::connection('db_externa')->table('folios_intercambio');

        $tabla()->insertOrIgnore(['anio' => $anio, 'ultimo' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $fila = $tabla()->where('anio', $anio)->lockForUpdate()->first();
        $siguiente = $fila->ultimo + 1;
        $tabla()->where('anio', $anio)->update(['ultimo' => $siguiente, 'updated_at' => now()]);

        return sprintf('INT-%d-%06d', $anio, $siguiente);
    }

    /** Caja existente (con corrección opcional de sus datos) o caja nueva con su dueño. */
    private function resolverCaja(array $datos): Caja
    {
        if (! empty($datos['id'])) {
            $caja = Caja::with('cliente')->findOrFail($datos['id']);

            if (! empty($datos['correccion'])) {
                $caja->update($this->soloCampos($datos['correccion'], ['tipo_caja_id', 'placa', 'estado_placa', 'marca', 'modelo', 'vin', 'anio', 'tamano_pies']));
            }

            return $caja;
        }

        $nuevo = $datos['nuevo'];
        $clienteId = $this->resolverCatalogo(Cliente::class, $nuevo['cliente_id'] ?? null, $nuevo['cliente_nuevo'] ?? null);

        // Si otro dispositivo ya registró la misma caja sin conexión, se usa esa.
        return Caja::with('cliente')->firstOrCreate(
            ['cliente_id' => $clienteId, 'numero_economico' => trim($nuevo['numero_economico'])],
            $this->soloCampos($nuevo, ['tipo_caja_id', 'placa', 'estado_placa', 'marca', 'modelo', 'vin', 'anio', 'tamano_pies']),
        )->load('cliente');
    }

    private function resolverTracto(array $datos): Tractocamion
    {
        if (! empty($datos['id'])) {
            $tracto = Tractocamion::with('lineaTransportista')->findOrFail($datos['id']);

            if (! empty($datos['correccion'])) {
                $tracto->update($this->soloCampos($datos['correccion'], ['placa', 'estado_placa', 'marca', 'modelo', 'vin', 'anio']));
            }

            return $tracto;
        }

        $nuevo = $datos['nuevo'];
        $permisionario = (bool) ($nuevo['es_permisionario'] ?? false);
        $numero = trim($nuevo['numero_economico']);

        $existente = Tractocamion::activos()->where('numero_economico', $numero)->where('placa', $nuevo['placa'])->first();

        if ($existente) {
            return $existente->load('lineaTransportista');
        }

        return Tractocamion::create([
            ...$this->soloCampos($nuevo, ['placa', 'estado_placa', 'marca', 'modelo', 'vin', 'anio']),
            'numero_economico' => $numero,
            'es_permisionario' => $permisionario,
            'linea_transportista_id' => $permisionario
                ? $this->resolverCatalogo(LineaTransportista::class, $nuevo['linea_id'] ?? null, $nuevo['linea_nueva'] ?? null)
                : null,
        ])->load('lineaTransportista');
    }

    /**
     * Id de un cliente o línea: el seleccionado, o uno nuevo dado de alta en el asistente.
     * Si ya existe uno activo con el mismo nombre (alta repetida sin conexión), se usa ese.
     */
    private function resolverCatalogo(string $modelo, mixed $id, ?array $nuevo): ?int
    {
        if ($id) {
            return (int) $id;
        }

        $nombre = trim($nuevo['nombre'] ?? '');

        if ($nombre === '') {
            return null;
        }

        return $modelo::activos()->where('nombre', $nombre)->value('id')
            ?? $modelo::create(['nombre' => $nombre, 'rfc' => $nuevo['rfc'] ?? null])->id;
    }

    private function resolverDestino(mixed $id, ?string $nuevo): ?int
    {
        if ($id) {
            return (int) $id;
        }

        $nombre = trim($nuevo ?? '');

        if ($nombre === '') {
            return null;
        }

        return Destino::activos()->where('nombre', $nombre)->value('id') ?? Destino::create(['nombre' => $nombre])->id;
    }

    private function soloCampos(array $datos, array $campos): array
    {
        return array_map(
            fn ($valor) => is_string($valor) ? (trim($valor) === '' ? null : trim($valor)) : $valor,
            array_intersect_key($datos, array_flip($campos)),
        );
    }

    /**
     * Ruta en R2 de cada foto declarada en el intercambio. Todas deben haberse subido antes.
     *
     * @return array<string, string> uuid => ruta
     */
    private function localizarFotos(string $idCliente, array $subidas, array $fotos): array
    {
        $rutas = [];
        // Archivos subidos por carpeta y nombre (sin extensión): [carpeta][uuid] => ruta.
        $porCarpeta = [];
        foreach ($subidas as $ruta) {
            $porCarpeta[dirname($ruta)][pathinfo($ruta, PATHINFO_FILENAME)] = $ruta;
        }

        foreach ($fotos as $foto) {
            // Carpeta donde subirFoto() la guardó (las del recorrido van en una subcarpeta por vista).
            $carpeta = dirname(self::rutaFoto($idCliente, $foto['tipo'], 'x', 'jpg', $foto['vista'] ?? null));
            $ruta = $porCarpeta[$carpeta][$foto['uuid']] ?? null;

            if (! $ruta) {
                throw ValidationException::withMessages([
                    'fotos' => 'Todavía no terminan de subirse todas las fotos. Se volverá a intentar.',
                ])->status(409);
            }

            $rutas[$foto['uuid']] = $ruta;
        }

        return $rutas;
    }

    /** Guarda una firma (PNG en Base64) en R2 y devuelve su ruta. */
    private function guardarFirma(string $idCliente, string $quien, string $firmaBase64): string
    {
        $bytes = preg_match('/^data:image\/png;base64,/', $firmaBase64)
            ? base64_decode(substr($firmaBase64, strpos($firmaBase64, ',') + 1), true)
            : false;

        $imagen = $bytes ? getimagesizefromstring($bytes) : false;

        if (! $bytes || strlen($bytes) > 512 * 1024 || $imagen === false || $imagen['mime'] !== 'image/png') {
            throw ValidationException::withMessages(["firma_{$quien}" => "La firma del {$quien} no es válida. Vuelve a firmar."]);
        }

        $ruta = self::carpeta($idCliente)."/firmas/{$quien}.png";

        if (! Storage::disk(self::DISCO)->put($ruta, $bytes, ['visibility' => 'private', 'ContentType' => 'image/png'])) {
            throw new RuntimeException('No se pudo guardar la firma en el almacenamiento configurado.');
        }

        return $ruta;
    }
}
