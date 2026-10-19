<?php

/*
 * Escrito por: Ivan Lopez
 * Fecha:
 */

namespace App\Http\Controllers;

use App\Models\BitacoraVisita;
use App\Models\EmpresaExterno;
use App\Models\FotoVisitaExterno;
use App\Models\RequerimientoFotoVisitaExterno;
use App\Models\User;
use App\Models\VisitaExterno;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class VisitaExternoController extends Controller
{
    /** Una salida de la misma empresa sin nadie dentro en este lapso se considera repetida. */
    private const MINUTOS_SALIDA_REPETIDA = 15;

    /** Si alguien de la empresa salió en este lapso, se pide confirmar antes de cerrar otra visita. */
    private const MINUTOS_CONFIRMAR_SALIDA = 2;

    /** Fotos opcionales de la entrada: campo del formulario => [tipo de foto, descripción para mensajes]. */
    private const FOTOS_ENTRADA = [
        'foto_placas' => [RequerimientoFotoVisitaExterno::PLACAS, 'las placas'],
        'foto_vehiculo' => [RequerimientoFotoVisitaExterno::VEHICULO, 'el vehículo'],
    ];

    /** Tamaño máximo de cada foto ya decodificada (el celular las comprime antes de enviarlas). */
    private const MAX_BYTES_FOTO = 2 * 1024 * 1024;

    /**
     * Muestra el historial real de entradas y salidas registradas, con búsqueda y filtros básicos.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    public function historial(Request $request)
    {
        $query = VisitaExterno::query()->with('fotoVisitas.requerimientoFotoVisita');

        // Cada filtro se agrega solo cuando el usuario lo envía, para poder combinar
        // búsqueda, empresa, estado y fecha en una sola consulta.
        if ($request->filled('buscar')) {
            $buscar = $this->patronLike($request->input('buscar'));
            $query->where(function ($q) use ($buscar) {
                $q->whereRaw("nombre_persona LIKE ? ESCAPE '!'", [$buscar])
                    ->orWhereRaw("empresa LIKE ? ESCAPE '!'", [$buscar])
                    ->orWhereRaw("persona_a_visitar LIKE ? ESCAPE '!'", [$buscar]);
            });
        }

        if ($request->filled('empresa')) {
            $query->whereRaw("empresa LIKE ? ESCAPE '!'", [$this->patronLike($request->input('empresa'))]);
        }

        $estatus = $request->input('estatus');

        if ($estatus === 'dentro') {
            $query->whereNull('fecha_salida');
        } elseif ($estatus === 'salio') {
            $query->whereNotNull('fecha_salida');
        }

        if ($request->filled('fecha')) {
            $query->whereDate('fecha_entrada', $request->date('fecha'));
        }

        // Se pagina para limitar los resultados y se conservan los filtros al cambiar de página.
        $visitas = $query->latest('fecha_entrada')->paginate(15)->withQueryString();

        // Quién registró cada entrada y salida (bitácora en la base local).
        $autores = BitacoraVisita::autoresDe($visitas->pluck('id'));

        return view('admin.entradas-salidas.historial', compact('visitas', 'autores'));
    }

    /**
     * Registra la entrada de un visitante. El código QR corresponde al ID de la empresa
     * a la que pertenece (catálogo de empresas), y el resto de los datos vienen del formulario.
     * Si la visita es de una empresa que aún no existe en el catálogo, se puede indicar
     * su nombre y la empresa se crea automáticamente al registrar la entrada (si ya existe
     * una con ese nombre, se usa esa; pasa cuando dos entradas sin conexión la dieron de alta).
     *
     * Los registros hechos sin conexión llegan después con `id_cliente` y `registrado_en`.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    public function store(Request $request): JsonResponse
    {
        return $this->procesarUnaVez($request, fn () => $this->registrarEntrada($request));
    }

    /**
     * Valida los datos de entrada, guarda la firma y crea la visita.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function registrarEntrada(Request $request): JsonResponse
    {
        $esEmpresaNueva = $request->boolean('empresa_nueva');

        $reglas = [
            'nombre_persona' => 'required|string|max:255',
            'persona_dirigida' => 'required|string|max:255',
            'motivo' => 'required|string|max:255',
            'firma' => 'required|string|max:700000',
            'foto_placas' => 'nullable|string|max:3000000',
            'foto_vehiculo' => 'nullable|string|max:3000000',
            'id_cliente' => 'nullable|uuid',
            'registrado_en' => 'nullable|date',
            'registrado_por' => 'nullable|string|max:1000',
        ];

        // La entrada usa un QR de una empresa existente o el nombre de una empresa nueva,
        // pero no requiere ambos datos a la vez.
        if ($esEmpresaNueva) {
            $reglas['nombre_empresa'] = 'required|string|max:255';
        } else {
            $reglas['codigo_qr'] = 'required|integer|exists:db_externa.empresas,id';
        }

        $validated = $request->validate($reglas, [
            'codigo_qr.required' => 'Debes escanear o ingresar un código QR.',
            'codigo_qr.exists' => 'El código QR no corresponde a ninguna empresa registrada.',
            'nombre_empresa.required' => 'Debes escribir el nombre de la nueva empresa.',
            'firma.required' => 'La firma del visitante es obligatoria.',
        ]);

        // Las fotos (opcionales) se revisan antes de subir cualquier archivo, para no dejar
        // archivos sueltos en R2 si alguna no es una imagen válida.
        $fotos = [];

        foreach (self::FOTOS_ENTRADA as $campo => [$parte, $descripcion]) {
            if (empty($validated[$campo])) {
                continue;
            }

            $imagen = $this->decodificarFoto($validated[$campo]);

            if ($imagen === null) {
                return response()->json([
                    'success' => false,
                    'message' => "La foto de {$descripcion} no es una imagen válida o es demasiado grande.",
                ], 422);
            }

            $fotos[$parte] = $imagen;
        }

        // La firma se guarda primero en el almacenamiento privado; si la validación del
        // contenido falla, se responde sin intentar crear la visita.
        $firmaRuta = $this->guardarFirma($validated['firma']);

        if ($firmaRuta === null) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo procesar la firma proporcionada.',
            ], 422);
        }

        $fechaEntrada = $this->fechaDelRegistro($request);
        $archivosSubidos = [$firmaRuta];

        // La empresa (si es nueva), la visita y sus fotos se guardan en una transacción conjunta.
        // Si algo falla, se borran la firma y las fotos para no dejar archivos huérfanos en R2.
        try {
            $rutasFotos = [];

            foreach ($fotos as $parte => $imagen) {
                $rutasFotos[$parte] = $archivosSubidos[] = $this->subirFoto($imagen);
            }

            $visita = DB::connection('db_externa')->transaction(function () use ($esEmpresaNueva, $validated, $firmaRuta, $fechaEntrada, $rutasFotos) {
                $empresa = $esEmpresaNueva
                    ? EmpresaExterno::firstOrCreate(['nombre' => $validated['nombre_empresa']], [
                        'razon_social' => '',
                        'categoria' => '',
                        'logo_url' => '',
                        'sitio_web' => '',
                    ])
                    : EmpresaExterno::findOrFail($validated['codigo_qr']);

                $visita = VisitaExterno::create([
                    'nombre_persona' => $validated['nombre_persona'],
                    'empresa' => $empresa->nombre,
                    'motivo_visita' => $validated['motivo'],
                    'persona_a_visitar' => $validated['persona_dirigida'],
                    'firma_url' => $firmaRuta,
                    'fecha_entrada' => $fechaEntrada,
                ]);

                // Cada foto se enlaza con su tipo (Placas / Vehículo) del catálogo de requerimientos.
                foreach ($rutasFotos as $parte => $ruta) {
                    FotoVisitaExterno::create([
                        'foto_url' => $ruta,
                        'requerimiento_foto_visita_id' => RequerimientoFotoVisitaExterno::firstOrCreate(['parte' => $parte])->id,
                        'visita_id' => $visita->id,
                    ]);
                }

                return $visita;
            });
        } catch (Throwable $e) {
            Storage::disk('r2')->delete($archivosSubidos);

            throw $e;
        }

        $this->anotarEnBitacora($visita, 'entrada', $request, $fechaEntrada);

        return response()->json([
            'success' => true,
            'message' => '¡Entrada registrada correctamente!',
            'data' => $this->resumenVisita($visita),
        ], 201);
    }

    /**
     * Personas que están dentro (visitas sin salida), para darles salida desde la lista.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    public function activas(): JsonResponse
    {
        // Limita la respuesta a las 300 visitas más recientes que aún no tienen salida.
        $visitas = VisitaExterno::whereNull('fecha_salida')
            ->latest('fecha_entrada')
            ->limit(300)
            ->get(['id', 'nombre_persona', 'empresa', 'persona_a_visitar', 'motivo_visita', 'fecha_entrada']);

        $autores = BitacoraVisita::autoresDe($visitas->pluck('id'));

        return response()->json([
            'visitas' => $visitas->map(fn (VisitaExterno $v) => [
                ...$this->resumenVisita($v),
                'empresa' => $v->empresa,
                'motivo' => $v->motivo_visita,
                'entrada_por' => $autores[$v->id]['entrada'] ?? null,
            ])->values(),
            'actualizado_en' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Registra una salida. Puede venir de dos formas:
     * - Con `visita_id` (botón "Dar salida" de la lista de personas dentro, o selección).
     * - Con `entrada_id_cliente`: salida dada sin conexión a alguien cuya entrada también se
     *   registró sin conexión; se enlaza con la visita que creó esa entrada.
     * - Con `codigo_qr` de la empresa. Si hay varias personas de esa empresa dentro, responde
     *   con la lista para que el guardia elija quién sale y reenvíe con `visita_id`.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    public function registrarSalida(Request $request): JsonResponse
    {
        return $this->procesarUnaVez($request, fn () => $this->cerrarVisita($request));
    }

    /**
     * Valida la solicitud, identifica la visita activa y registra su salida.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function cerrarVisita(Request $request): JsonResponse
    {
        // Se permite identificar la salida por visita o por empresa; el QR es opcional
        // cuando el usuario ya seleccionó una visita concreta.
        $validated = $request->validate([
            'codigo_qr' => 'required_without_all:visita_id,entrada_id_cliente|nullable|integer|exists:db_externa.empresas,id',
            'visita_id' => 'nullable|integer',
            'entrada_id_cliente' => 'nullable|uuid',
            'id_cliente' => 'nullable|uuid',
            'registrado_en' => 'nullable|date',
            'registrado_por' => 'nullable|string|max:1000',
        ], [
            'codigo_qr.required_without_all' => 'Debes escanear o ingresar un código QR.',
            'codigo_qr.exists' => 'El código QR no corresponde a ninguna empresa registrada.',
        ]);

        // Si se proporcionó empresa junto con visita_id, luego se comprueba que coincidan.
        $empresa = empty($validated['codigo_qr']) ? null : EmpresaExterno::findOrFail($validated['codigo_qr']);
        $fechaSalida = $this->fechaDelRegistro($request);

        // La entrada se hizo sin conexión: su visita se identifica por el id que generó el celular.
        if (empty($validated['visita_id']) && ! empty($validated['entrada_id_cliente'])) {
            $validated['visita_id'] = BitacoraVisita::where('tipo', 'entrada')
                ->where('id_cliente', $validated['entrada_id_cliente'])
                ->value('visita_id');

            if (! $validated['visita_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'La entrada de esta persona todavía no llega al servidor.',
                ], 404);
            }
        }

        if (! empty($validated['visita_id'])) {
            // Evita cerrar una visita distinta de la empresa que se escaneó.
            $visita = VisitaExterno::query()
                ->when($empresa, fn ($q) => $q->where('empresa', $empresa->nombre))
                ->find((int) $validated['visita_id']);

            if (! $visita) {
                return response()->json([
                    'success' => false,
                    'message' => $empresa
                        ? 'Esa visita no pertenece a la empresa escaneada.'
                        : 'No se encontró esa visita.',
                ], 404);
            }

            if ($visita->fecha_salida) {
                return $this->salidaRepetida($visita);
            }
        } else {
            // Con solo el QR, se localizan las visitas todavía abiertas de esa empresa.
            $visitasActivas = VisitaExterno::where('empresa', $empresa->nombre)
                ->whereNull('fecha_salida')
                ->latest('fecha_entrada')
                ->get(['id', 'nombre_persona', 'persona_a_visitar', 'fecha_entrada']);

            $salidaReciente = $this->salidaReciente($empresa, $fechaSalida, self::MINUTOS_SALIDA_REPETIDA);

            if ($visitasActivas->isEmpty()) {
                return $salidaReciente
                    ? $this->salidaRepetida($salidaReciente)
                    : response()->json([
                        'success' => false,
                        'message' => 'No se encontró una entrada activa para esa empresa.',
                    ], 404);
            }

            // Si alguien de la empresa acaba de salir, este escaneo puede ser una repetición:
            // no se cierra a nadie automáticamente y se pide confirmar quién sale.
            $posibleRepeticion = $this->salidaReciente($empresa, $fechaSalida, self::MINUTOS_CONFIRMAR_SALIDA);

            if ($visitasActivas->count() === 1 && ! $posibleRepeticion) {
                $visita = $visitasActivas->first();
            } else {
                $mensaje = $posibleRepeticion
                    ? "Hace un momento se registró la salida de {$posibleRepeticion->nombre_persona}. Si fue un escaneo repetido, cancela; si sale alguien más, selecciónalo."
                    : "Hay {$visitasActivas->count()} personas de {$empresa->nombre} dentro. Selecciona quién sale.";

                return response()->json([
                    'success' => false,
                    'requiere_seleccion' => true,
                    'message' => $mensaje,
                    'visitas' => $visitasActivas->map(fn (VisitaExterno $v) => $this->resumenVisita($v))->values(),
                ], 409);
            }
        }

        // Con registros sin conexión, la salida nunca puede quedar antes que la entrada.
        $visita->update([
            'fecha_salida' => $fechaSalida->max($visita->fecha_entrada),
        ]);

        $this->anotarEnBitacora($visita, 'salida', $request, $visita->fecha_salida);

        return response()->json([
            'success' => true,
            'message' => "¡Salida de {$visita->nombre_persona} registrada correctamente!",
            'data' => $this->resumenVisita($visita),
        ]);
    }

    /**
     * Anota en la bitácora local quién registró la entrada o salida. Si la bitácora falla,
     * no se interrumpe el registro (la visita ya quedó guardada en la base externa).
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function anotarEnBitacora(VisitaExterno $visita, string $tipo, Request $request, Carbon $fecha): void
    {
        try {
            $autor = $this->autorDelRegistro($request);
            $idCliente = $request->input('id_cliente');

            BitacoraVisita::firstOrCreate(
                ['visita_id' => $visita->id, 'tipo' => $tipo],
                [
                    'user_id' => $autor?->id,
                    'usuario_nombre' => $autor?->name ?? 'Desconocido',
                    'id_cliente' => is_string($idCliente) && Str::isUuid($idCliente) ? $idCliente : null,
                    'registrado_en' => $fecha,
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Usuario que hizo el registro. Los registros guardados sin conexión traen en
     * `registrado_por` el id cifrado por el servidor de quien tenía la sesión en ese momento
     * (no se puede falsificar); así se respeta aunque se envíen con la sesión de otra persona.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function autorDelRegistro(Request $request): ?User
    {
        $firma = $request->input('registrado_por');

        if (is_string($firma) && $firma !== '') {
            try {
                $autor = User::find((int) Crypt::decryptString($firma));

                if ($autor) {
                    return $autor;
                }
            } catch (DecryptException) {
                // Firma inválida: se usa el usuario de la sesión actual.
            }
        }

        return $request->user();
    }

    /**
     * Salida de la empresa registrada cerca de la hora indicada (antes o después, porque los
     * registros sin conexión pueden llegar después), o null si no hay ninguna.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function salidaReciente(EmpresaExterno $empresa, Carbon $fecha, int $minutos): ?VisitaExterno
    {
        return VisitaExterno::where('empresa', $empresa->nombre)
            ->whereBetween('fecha_salida', [$fecha->copy()->subMinutes($minutos), $fecha->copy()->addMinutes($minutos)])
            ->latest('fecha_salida')
            ->first();
    }

    /**
     * Respuesta para una salida repetida: se conserva la primera y esta se cancela sin cambios.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function salidaRepetida(VisitaExterno $primera): JsonResponse
    {
        return response()->json([
            'success' => true,
            'cancelado' => true,
            'message' => "Salida repetida: la salida de {$primera->nombre_persona} ya se había registrado a las {$primera->fecha_salida->format('H:i')}. Se conservó la primera y esta se canceló.",
            'data' => $this->resumenVisita($primera),
        ]);
    }

    /**
     * Evita registros duplicados cuando el celular reenvía un registro guardado sin conexión
     * (por ejemplo, si la señal se cortó después de que el servidor lo guardó). La respuesta
     * exitosa se recuerda 7 días por `id_cliente`; un reenvío recibe la misma respuesta.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function procesarUnaVez(Request $request, Closure $accion): JsonResponse
    {
        $idCliente = $request->input('id_cliente');

        if (! is_string($idCliente) || ! Str::isUuid($idCliente)) {
            return $accion();
        }

        $clave = "caseta:registro:{$idCliente}";

        // Si ya se guardó una respuesta para este UUID, se devuelve sin repetir la operación.
        if ($previa = Cache::get($clave)) {
            return response()->json([...$previa, 'duplicado' => true]);
        }

        // El candado serializa solicitudes concurrentes que intenten procesar el mismo registro.
        $candado = Cache::lock("{$clave}:candado", 60);

        if (! $candado->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Este registro ya se está procesando.',
            ], 423);
        }

        try {
            $respuesta = $accion();

            // Solo se recuerda una respuesta exitosa; los errores pueden corregirse y reenviarse.
            if ($respuesta->isSuccessful()) {
                Cache::put($clave, $respuesta->getData(true), now()->addDays(7));
            }

            return $respuesta;
        } finally {
            $candado->release();
        }
    }

    /**
     * Hora en que el guardia hizo el registro. Para registros enviados después de estar sin
     * conexión se usa la hora del celular, siempre que sea razonable (no futura ni de hace
     * más de 7 días); si no, la hora del servidor.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function fechaDelRegistro(Request $request): Carbon
    {
        $ahora = Carbon::now();
        $valor = $request->input('registrado_en');

        if (! is_string($valor) || $valor === '') {
            return $ahora;
        }

        // Un valor de fecha malformado no impide el registro: se usa la hora del servidor.
        try {
            $fecha = Carbon::parse($valor)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return $ahora;
        }

        return $fecha->between($ahora->copy()->subDays(7), $ahora) ? $fecha : $ahora;
    }

    /**
     * Datos de una visita que se devuelven al celular de caseta (sin la firma).
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function resumenVisita(VisitaExterno $visita): array
    {
        // La respuesta contiene datos operativos y fechas legibles, pero nunca la firma.
        return [
            'id' => $visita->id,
            'nombre_persona' => $visita->nombre_persona,
            'persona_a_visitar' => $visita->persona_a_visitar,
            'fecha_entrada' => $visita->fecha_entrada?->format('d/m/Y H:i'),
            'fecha_salida' => $visita->fecha_salida?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Patrón "contiene" para LIKE donde % y _ escritos por el usuario se buscan como texto literal.
     * Se usa '!' como carácter de escape porque funciona igual en MySQL/MariaDB y SQLite.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function patronLike(string $valor): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor).'%';
    }

    /**
     * Decodifica una foto en Base64 (JPEG, PNG o WebP) y comprueba que sea una imagen real
     * y no exceda el tamaño máximo. Devuelve los bytes y su tipo, o null si no es válida.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function decodificarFoto(string $fotoBase64): ?array
    {
        if (! preg_match('/^data:image\/(jpeg|png|webp);base64,/', $fotoBase64)) {
            return null;
        }

        $bytes = base64_decode(substr($fotoBase64, strpos($fotoBase64, ',') + 1), true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES_FOTO) {
            return null;
        }

        $imagen = getimagesizefromstring($bytes);
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if ($imagen === false || ! isset($extensiones[$imagen['mime']])) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => $imagen['mime'], 'extension' => $extensiones[$imagen['mime']]];
    }

    /**
     * Sube una foto ya validada como objeto privado a R2 y devuelve su ruta.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function subirFoto(array $imagen): string
    {
        $ruta = 'fotos/visitas/'.Carbon::now()->format('Y/m').'/foto_'.bin2hex(random_bytes(16)).'.'.$imagen['extension'];

        if (! Storage::disk('r2')->put($ruta, $imagen['bytes'], [
            'visibility' => 'private',
            'ContentType' => $imagen['mime'],
        ])) {
            throw new RuntimeException('No se pudo guardar la foto en el almacenamiento configurado.');
        }

        return $ruta;
    }

    /**
     * Decodifica una firma en Base64 y la sube como objeto privado a Cloudflare R2,
     * devolviendo la ruta del objeto.
     * Escrito por: Ivan Lopez
     * Fecha:
     */
    private function guardarFirma(string $firmaBase64): ?string
    {
        // Acepta únicamente un Data URL PNG; no se admiten otros formatos de imagen.
        if (! preg_match('/^data:image\/png;base64,/', $firmaBase64)) {
            return null;
        }

        // La decodificación estricta y el límite de tamaño rechazan contenido inválido o excesivo.
        $bytes = base64_decode(substr($firmaBase64, strpos($firmaBase64, ',') + 1), true);

        if (
            $bytes === false
            || strlen($bytes) > 512 * 1024
            || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
        ) {
            return null;
        }

        // Además de revisar la firma del archivo, PHP verifica que los bytes formen un PNG válido.
        $imagen = getimagesizefromstring($bytes);

        if ($imagen === false || $imagen['mime'] !== 'image/png') {
            return null;
        }

        // La ruta aleatoria reduce colisiones y evita exponer identificadores secuenciales;
        // la visibilidad privada impide servir la imagen públicamente desde el bucket.
        $ruta = 'firmas/visitas/'.Carbon::now()->format('Y/m').'/firma_'.bin2hex(random_bytes(16)).'.png';

        if (! Storage::disk('r2')->put($ruta, $bytes, [
            'visibility' => 'private',
            'ContentType' => 'image/png',
        ])) {
            throw new RuntimeException('No se pudo guardar la firma en el almacenamiento configurado.');
        }

        return $ruta;
    }
}
