<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarIntercambioRequest;
use App\Models\BitacoraIntercambio;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\FotoIntercambio;
use App\Models\Intercambio;
use App\Models\LineaTransportista;
use App\Models\LlantaIntercambio;
use App\Models\Operador;
use App\Models\PuntoInspeccion;
use App\Models\TipoCaja;
use App\Models\Tractocamion;
use App\Models\User;
use App\Services\PdfIntercambio;
use App\Services\RegistrarIntercambio;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Intercambios de cajas y tractocamiones (tablas en la base de RH, conexión `db_externa`).
 *
 * El asistente funciona sin conexión: el borrador vive en el dispositivo y, al completarlo,
 * se encola. El service worker sube primero cada foto (subirFoto) y después el intercambio
 * completo (store), que recibe su folio al llegar al servidor.
 */
class IntercambioController extends Controller
{
    /**
     * Listado de intercambios con filtros.
     */
    public function index(Request $request)
    {
        $query = Intercambio::query()->with(['cliente:id,nombre']);

        if ($request->filled('folio')) {
            $query->whereRaw("folio LIKE ? ESCAPE '!'", [$this->patronLike($request->input('folio'))]);
        }

        if ($request->filled('caja')) {
            $caja = $this->patronLike($request->input('caja'));
            $query->where(function ($q) use ($caja) {
                $q->whereRaw("caja_numero_economico LIKE ? ESCAPE '!'", [$caja])
                    ->orWhereHas('caja', fn ($c) => $c->whereRaw("numero_economico LIKE ? ESCAPE '!'", [$caja]));
            });
        }

        // Cliente del viaje o dueño de la caja.
        if ($request->filled('cliente')) {
            $cliente = $this->patronLike($request->input('cliente'));
            $query->where(function ($q) use ($cliente) {
                $q->whereRaw("caja_cliente LIKE ? ESCAPE '!'", [$cliente])
                    ->orWhereHas('cliente', fn ($c) => $c->whereRaw("nombre LIKE ? ESCAPE '!'", [$cliente]));
            });
        }

        if (in_array($request->input('movimiento'), Intercambio::MOVIMIENTOS, true)) {
            $query->where('tipo_movimiento', $request->input('movimiento'));
        }

        if (in_array($request->input('status'), Intercambio::STATUS, true)) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('desde')) {
            $query->where('fecha_hora', '>=', $request->date('desde')->startOfDay());
        }

        if ($request->filled('hasta')) {
            $query->where('fecha_hora', '<=', $request->date('hasta')->endOfDay());
        }

        $intercambios = $query->latest('fecha_hora')->latest('id')->paginate(15)->withQueryString();

        return view('admin.intercambio.historial', compact('intercambios'));
    }

    /**
     * Asistente de captura. La página no lleva datos: los toma de datos(), guardados en el
     * dispositivo, para poder abrirse sin conexión.
     */
    public function create()
    {
        return view('admin.intercambio.nuevo');
    }

    /**
     * Catálogos, operadores e historial reciente que el asistente guarda en el dispositivo.
     */
    public function datos(): JsonResponse
    {
        $operadores = Operador::with(['info', 'documento.tipoLicencia'])->get()
            ->map(fn (Operador $o) => [
                'id' => $o->id,
                'numero_empleado' => $o->numero_empleado,
                'nombre' => $o->nombre_completo,
                'licencia' => $o->documento?->numero_licencia,
                'tipo_licencia' => $o->documento?->tipoLicencia?->tipo_licencia,
                'licencia_vence' => $o->documento?->fecha_vencimiento_licencia?->toDateString(),
                'apto_vence' => $o->documento?->fecha_vencimiento_apto?->toDateString(),
            ])
            ->sortBy('nombre')
            ->values();

        $campos = ['id', 'nombre'];

        return response()->json([
            'generado_en' => now()->toIso8601String(),
            'operadores' => $operadores,
            'clientes' => Cliente::activos()->orderBy('nombre')->get([...$campos, 'rfc']),
            'tipos_caja' => TipoCaja::activos()->orderBy('nombre')->get($campos),
            'lineas' => LineaTransportista::activos()->orderBy('nombre')->get($campos),
            'destinos' => Destino::activos()->orderBy('nombre')->get($campos),
            'cajas' => Caja::activos()->orderBy('numero_economico')->get([
                'id', 'cliente_id', 'tipo_caja_id', 'numero_economico', 'placa', 'estado_placa',
                'marca', 'modelo', 'vin', 'anio', 'tamano_pies',
            ]),
            'tractos' => Tractocamion::activos()->orderBy('numero_economico')->get([
                'id', 'numero_economico', 'placa', 'estado_placa', 'marca', 'modelo', 'vin', 'anio',
                'es_permisionario', 'linea_transportista_id',
            ]),
            'puntos' => PuntoInspeccion::activos()->orderBy('orden')->orderBy('id')
                ->get(['id', 'seccion', 'nombre', 'parent_id', 'tipo_respuesta', 'orden']),
            ...$this->historialUnidades(),
            'config' => [
                'dias_aviso_vencimiento' => config('intercambios.dias_aviso_vencimiento'),
                'llantas' => config('intercambios.llantas'),
                'max_fotos_sello' => config('intercambios.max_fotos_sello'),
                'recorrido' => config('intercambios.recorrido'),
                'fotos' => config('intercambios.fotos'),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Último intercambio completado de cada caja (por movimiento) y llantas del último
     * intercambio de cada caja y de cada tracto, para advertencias y precarga sin conexión.
     */
    private function historialUnidades(): array
    {
        $porMovimiento = Intercambio::completados()
            ->whereIn('id', Intercambio::completados()->whereNotNull('caja_id')->selectRaw('max(id)')->groupBy('caja_id', 'tipo_movimiento'))
            ->get(['id', 'folio', 'caja_id', 'tipo_movimiento', 'fecha_hora', 'comentarios_caja', 'comentarios_tracto', 'observaciones']);

        $cajas = [];
        foreach ($porMovimiento as $i) {
            $cajas[$i->caja_id][$i->tipo_movimiento] = [
                'id' => $i->id,
                'folio' => $i->folio,
                'fecha_hora' => $i->fecha_hora->toIso8601String(),
                'comentarios_caja' => $i->comentarios_caja,
                'comentarios_tracto' => $i->comentarios_tracto,
                'observaciones' => $i->observaciones,
            ];
        }

        $ultimoPorCaja = Intercambio::completados()->whereNotNull('caja_id')->selectRaw('caja_id, max(id) as id')->groupBy('caja_id')->pluck('id', 'caja_id');
        $ultimoPorTracto = Intercambio::completados()->whereNotNull('tractocamion_id')->selectRaw('tractocamion_id, max(id) as id')->groupBy('tractocamion_id')->pluck('id', 'tractocamion_id');

        $llantas = LlantaIntercambio::whereIn('intercambio_id', $ultimoPorCaja->values()->merge($ultimoPorTracto->values())->unique())
            ->get(['intercambio_id', 'posicion', 'marca', 'tipo'])
            ->groupBy('intercambio_id');

        $extraer = fn ($intercambioId, $desde, $hasta) => ($llantas->get($intercambioId) ?? collect())
            ->filter(fn ($l) => $l->posicion >= $desde && $l->posicion <= $hasta)
            ->mapWithKeys(fn ($l) => [$l->posicion => ['marca' => $l->marca, 'tipo' => $l->tipo]]);

        return [
            'historial_cajas' => (object) $cajas,
            'llantas_cajas' => (object) $ultimoPorCaja->map(fn ($id) => $extraer($id, 11, 18))->all(),
            'llantas_tractos' => (object) $ultimoPorTracto->map(fn ($id) => $extraer($id, 1, 10))->all(),
        ];
    }

    /**
     * Sube una foto de un intercambio todavía no registrado. Se puede repetir sin problema
     * (la ruta depende de los UUID del intercambio y de la foto).
     */
    public function subirFoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'intercambio' => 'required|uuid',
            'uuid' => 'required|uuid',
            'tipo' => ['required', Rule::in(FotoIntercambio::TIPOS)],
            'vista' => ['required_if:tipo,'.FotoIntercambio::RECORRIDO, 'nullable', Rule::in(FotoIntercambio::vistas())],
            'foto' => 'required|file|mimes:jpg,jpeg,png,webp|max:'.config('intercambios.fotos.max_kb'),
        ], [
            'foto.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'foto.max' => 'La foto pesa demasiado.',
        ]);

        // Un intercambio registrado ya no se modifica.
        if (BitacoraIntercambio::where('id_cliente', $validated['intercambio'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Este intercambio ya se registró y no se puede modificar.'], 403);
        }

        $archivo = $request->file('foto');
        $extension = ['image/png' => 'png', 'image/webp' => 'webp'][$archivo->getMimeType()] ?? 'jpg';
        $ruta = RegistrarIntercambio::rutaFoto($validated['intercambio'], $validated['tipo'], $validated['uuid'], $extension, $validated['vista'] ?? null);

        if (! Storage::disk(RegistrarIntercambio::DISCO)->put($ruta, $archivo->getContent(), [
            'visibility' => 'private',
            'ContentType' => $archivo->getMimeType(),
        ])) {
            throw new RuntimeException('No se pudo guardar la foto en el almacenamiento configurado.');
        }

        return response()->json(['success' => true, 'uuid' => $validated['uuid']], 201);
    }

    /**
     * Borra de R2 las fotos de un intercambio que se descartó en el dispositivo sin registrarse.
     * Llega por la cola sin conexión, así que se puede repetir sin problema. Si el intercambio
     * sí se registró, sus fotos se conservan.
     */
    public function descartar(Request $request): JsonResponse
    {
        $idCliente = $request->validate(['intercambio' => 'required|uuid'])['intercambio'];

        // No borrar mientras el mismo intercambio se está registrando.
        $candado = Cache::lock("intercambio:{$idCliente}", 120);

        if (! $candado->get()) {
            return response()->json(['success' => false, 'message' => 'Este intercambio se está procesando.'], 423);
        }

        try {
            if (BitacoraIntercambio::where('id_cliente', $idCliente)->exists()) {
                return response()->json(['success' => true, 'message' => 'El intercambio ya estaba registrado; sus fotos se conservan.']);
            }

            Storage::disk(RegistrarIntercambio::DISCO)->deleteDirectory(RegistrarIntercambio::carpeta($idCliente));
        } finally {
            $candado->release();
        }

        return response()->json(['success' => true, 'message' => 'Fotos del intercambio descartado eliminadas.']);
    }

    /**
     * Registra un intercambio completo. Un reenvío del mismo `id_cliente` (por ejemplo, si la
     * señal se cortó después de guardarlo) devuelve el folio ya asignado sin duplicarlo.
     */
    public function store(GuardarIntercambioRequest $request, RegistrarIntercambio $registrar): JsonResponse
    {
        $idCliente = $request->input('id_cliente');

        if ($previo = $this->yaRegistrado($idCliente)) {
            return $previo;
        }

        // Serializa reenvíos simultáneos del mismo intercambio.
        $candado = Cache::lock("intercambio:{$idCliente}", 120);

        if (! $candado->get()) {
            return response()->json(['success' => false, 'message' => 'Este intercambio ya se está procesando.'], 423);
        }

        try {
            if ($previo = $this->yaRegistrado($idCliente)) {
                return $previo;
            }

            $guardia = $this->autorDelRegistro($request);
            $intercambio = $registrar->ejecutar($request->validated(), $guardia);

            BitacoraIntercambio::create([
                'id_cliente' => $idCliente,
                'intercambio_id' => $intercambio->id,
                'user_id' => $guardia->id,
                'usuario_nombre' => $guardia->name,
            ]);
        } finally {
            $candado->release();
        }

        return response()->json([
            'success' => true,
            'message' => "Intercambio {$intercambio->folio} registrado.",
            'folio' => $intercambio->folio,
            'id' => $intercambio->id,
        ], 201);
    }

    private function yaRegistrado(string $idCliente): ?JsonResponse
    {
        $bitacora = BitacoraIntercambio::where('id_cliente', $idCliente)->first();

        if (! $bitacora) {
            return null;
        }

        $folio = Intercambio::whereKey($bitacora->intercambio_id)->value('folio');

        return response()->json([
            'success' => true,
            'duplicado' => true,
            'message' => "Intercambio {$folio} registrado.",
            'folio' => $folio,
            'id' => $bitacora->intercambio_id,
        ]);
    }

    /**
     * Detalle de solo lectura.
     */
    public function show(Intercambio $intercambio)
    {
        $intercambio->load([
            'caja.tipoCaja', 'tractocamion.lineaTransportista', 'cliente', 'origen', 'destino', 'relacionado',
            'llantas', 'revisionPuntos.punto', 'fotos',
        ]);

        $revision = $intercambio->revisionPuntos->sortBy(fn ($r) => [$r->punto?->orden, $r->punto_inspeccion_id]);

        return view('admin.intercambio.detalle', [
            'intercambio' => $intercambio,
            'revisionPorSeccion' => $revision->groupBy(fn ($r) => $r->punto?->seccion),
            'fotos' => $intercambio->fotos->groupBy('tipo'),
            // Fotos de la unidad en el orden del recorrido.
            'recorrido' => $intercambio->fotos->where('tipo', FotoIntercambio::RECORRIDO)
                ->sortBy(fn (FotoIntercambio $f) => array_search($f->vista, FotoIntercambio::vistas(), true))
                ->values(),
            'diasAviso' => config('intercambios.dias_aviso_vencimiento'),
        ]);
    }

    /**
     * PDF del contrato de intercambio de remolque o de la inspección C-TPAT.
     */
    public function pdf(Intercambio $intercambio, string $documento, PdfIntercambio $pdf)
    {
        abort_unless($intercambio->status === Intercambio::STATUS_COMPLETADO, 404);

        [$contenido, $nombre] = match ($documento) {
            'contrato' => [$pdf->contrato($intercambio), "contrato-intercambio-{$intercambio->folio}.pdf"],
            'ctpat' => [$pdf->ctpat($intercambio), "inspeccion-ctpat-{$intercambio->folio}.pdf"],
        };

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Las fotos se sirven con un enlace temporal de R2 (5 minutos), nunca con una URL pública. */
    public function foto(Intercambio $intercambio, FotoIntercambio $foto): RedirectResponse
    {
        abort_unless($foto->intercambio_id === $intercambio->id, 404);

        return redirect()->away(Storage::disk(RegistrarIntercambio::DISCO)->temporaryUrl($foto->path, now()->addMinutes(5)));
    }

    public function firma(Intercambio $intercambio, string $quien): RedirectResponse
    {
        $ruta = match ($quien) {
            'operador' => $intercambio->firma_operador_path,
            'guardia' => $intercambio->firma_guardia_path,
            default => null,
        };

        abort_unless($ruta, 404);

        return redirect()->away(Storage::disk(RegistrarIntercambio::DISCO)->temporaryUrl($ruta, now()->addMinutes(5)));
    }

    /**
     * Usuario que capturó el intercambio. Los enviados después de estar sin conexión traen en
     * `registrado_por` el id cifrado por el servidor de quien tenía la sesión al capturarlo.
     */
    private function autorDelRegistro(Request $request): User
    {
        $firma = $request->input('registrado_por');

        if (is_string($firma) && $firma !== '') {
            try {
                if ($autor = User::find((int) Crypt::decryptString($firma))) {
                    return $autor;
                }
            } catch (DecryptException) {
                // Firma inválida: se usa el usuario de la sesión actual.
            }
        }

        return $request->user();
    }

    /** Patrón "contiene" para LIKE donde % y _ escritos por el usuario se buscan como texto literal. */
    private function patronLike(string $valor): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor).'%';
    }
}
