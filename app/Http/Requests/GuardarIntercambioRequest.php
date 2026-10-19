<?php

namespace App\Http\Requests;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\FotoIntercambio;
use App\Models\Intercambio;
use App\Models\LineaTransportista;
use App\Models\LlantaIntercambio;
use App\Models\Operador;
use App\Models\PuntoInspeccion;
use App\Models\RevisionPunto;
use App\Models\Tractocamion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Intercambio completo enviado por el asistente (en línea o desde la cola sin conexión).
 * Valida todas las reglas de los pasos 1 a 9; el asistente valida lo mismo en el dispositivo.
 *
 * Los catálogos nuevos (cliente, caja, tracto, línea, origen y destino) llegan anidados con
 * la clave `nuevo`, porque se pudieron dar de alta sin conexión.
 */
class GuardarIntercambioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Las llantas no son obligatorias: las que falten o lleguen sin profundidad o PSI se
     * guardan con los valores por defecto (config intercambios.llantas.por_defecto).
     */
    protected function prepareForValidation(): void
    {
        $llantas = $this->input('llantas');

        if ($llantas !== null && ! is_array($llantas)) {
            return; // la regla `array` lo rechaza
        }

        $porDefecto = config('intercambios.llantas.por_defecto');
        $recibidas = collect($llantas ?? [])
            ->filter(fn ($l) => is_array($l))
            ->keyBy(fn ($l) => (int) ($l['posicion'] ?? 0));

        $this->merge(['llantas' => collect(range(1, config('intercambios.llantas.posiciones')))
            ->map(function (int $posicion) use ($recibidas, $porDefecto) {
                $llanta = $recibidas->get($posicion, []);

                foreach (['profundidad_32', 'psi'] as $campo) {
                    if (blank($llanta[$campo] ?? null)) {
                        $llanta[$campo] = $porDefecto[$campo];
                    }
                }

                return ['posicion' => $posicion] + $llanta;
            })->all()]);
    }

    public function rules(): array
    {
        $cargada = $this->input('estado_carga') === Intercambio::CARGADA;

        return [
            'id_cliente' => 'required|uuid',
            'registrado_por' => 'nullable|string|max:1000',

            // Paso 1
            'tipo_movimiento' => ['required', Rule::in(Intercambio::MOVIMIENTOS)],
            'fecha_hora' => 'required|date|before_or_equal:'.now()->addMinutes(10)->toDateTimeString().'|after:'.now()->subDays(60)->toDateTimeString(),
            'operador_id' => 'required|integer',

            // Paso 2
            'caja' => 'required|array',
            'caja.id' => 'required_without:caja.nuevo|nullable|integer',
            'caja.nuevo' => 'nullable|array',
            'caja.nuevo.cliente_id' => 'nullable|integer',
            'caja.nuevo.cliente_nuevo.nombre' => 'nullable|string|max:255',
            'caja.nuevo.cliente_nuevo.rfc' => 'nullable|string|max:13',
            'caja.nuevo.numero_economico' => 'required_with:caja.nuevo|nullable|string|max:30',
            'caja.nuevo.placa' => 'required_with:caja.nuevo|nullable|string|max:20',
            'caja.nuevo.tipo_caja_id' => 'nullable|integer|exists:db_externa.tipos_caja,id',
            ...$this->reglasUnidad('caja.nuevo'),
            'caja.nuevo.tamano_pies' => 'nullable|integer|min:1|max:100',
            'caja.correccion' => 'nullable|array',
            'caja.correccion.placa' => 'nullable|string|max:20',
            'caja.correccion.tipo_caja_id' => 'nullable|integer|exists:db_externa.tipos_caja,id',
            ...$this->reglasUnidad('caja.correccion'),
            'caja.correccion.tamano_pies' => 'nullable|integer|min:1|max:100',

            // Paso 3
            'tracto' => 'required|array',
            'tracto.id' => 'required_without:tracto.nuevo|nullable|integer',
            'tracto.nuevo' => 'nullable|array',
            'tracto.nuevo.numero_economico' => 'required_with:tracto.nuevo|nullable|string|max:30',
            'tracto.nuevo.placa' => 'required_with:tracto.nuevo|nullable|string|max:20',
            'tracto.nuevo.es_permisionario' => 'nullable|boolean',
            'tracto.nuevo.linea_id' => 'nullable|integer',
            'tracto.nuevo.linea_nueva.nombre' => 'nullable|string|max:255',
            'tracto.nuevo.linea_nueva.rfc' => 'nullable|string|max:13',
            ...$this->reglasUnidad('tracto.nuevo'),
            'tracto.correccion' => 'nullable|array',
            'tracto.correccion.placa' => 'nullable|string|max:20',
            ...$this->reglasUnidad('tracto.correccion'),
            'odometro' => 'nullable|integer|min:0|max:9999999',

            // Paso 4
            'estado_carga' => ['required', Rule::in(Intercambio::ESTADOS_CARGA)],
            'tipo_intercambio' => ['required', Rule::in(Intercambio::TIPOS_INTERCAMBIO)],
            'cliente_id' => 'nullable|integer',
            'cliente_nuevo.nombre' => 'nullable|string|max:255',
            'cliente_nuevo.rfc' => 'nullable|string|max:13',
            'origen_id' => 'nullable|integer',
            'origen_nuevo' => 'nullable|string|max:255',
            'destino_id' => 'nullable|integer',
            'destino_nuevo' => 'nullable|string|max:255',
            'referencia_carta_porte' => 'nullable|string|max:100',

            // Paso 5
            'numero_sello' => [Rule::requiredIf($cargada), 'nullable', 'string', 'max:50'],

            // Paso 6
            'llantas' => 'required|array|size:'.config('intercambios.llantas.posiciones'),
            'llantas.*.posicion' => 'required|integer|distinct|min:1|max:'.config('intercambios.llantas.posiciones'),
            'llantas.*.marca' => 'nullable|string|max:50',
            'llantas.*.profundidad_32' => 'required|integer|min:0|max:32',
            'llantas.*.psi' => 'required|integer|min:0|max:'.config('intercambios.llantas.psi_maximo'),
            'llantas.*.tipo' => ['nullable', Rule::in(LlantaIntercambio::TIPOS)],
            'llantas.*.observaciones' => 'nullable|string|max:255',

            // Paso 7
            'revision' => 'required|array',
            'revision.*.punto_inspeccion_id' => 'required|integer|distinct',
            'revision.*.resultado' => 'required|string',
            'revision.*.observaciones' => 'nullable|string|max:2000',

            // Paso 8
            'comentarios_caja' => 'nullable|string|max:5000',
            'comentarios_tracto' => 'nullable|string|max:5000',
            'observaciones' => 'nullable|string|max:5000',

            // Fotos (ya subidas una por una antes de enviar el intercambio)
            'fotos' => 'nullable|array|max:200',
            'fotos.*.uuid' => 'required|uuid|distinct',
            'fotos.*.tipo' => ['required', Rule::in(FotoIntercambio::TIPOS)],
            'fotos.*.punto_inspeccion_id' => 'nullable|integer',
            'fotos.*.posicion' => 'nullable|integer|min:1|max:'.config('intercambios.llantas.posiciones'),
            'fotos.*.unidad' => ['nullable', Rule::in(FotoIntercambio::UNIDADES)],
            'fotos.*.vista' => ['nullable', Rule::in(FotoIntercambio::vistas())],

            // Paso 9
            'firma_operador' => 'required|string|max:700000',
            'firma_guardia' => 'required|string|max:700000',
        ];
    }

    /** Datos opcionales comunes de cajas y tractos. */
    private function reglasUnidad(string $prefijo): array
    {
        return [
            "{$prefijo}.estado_placa" => 'nullable|string|max:50',
            "{$prefijo}.marca" => 'nullable|string|max:50',
            "{$prefijo}.modelo" => 'nullable|string|max:50',
            "{$prefijo}.vin" => 'nullable|string|max:17',
            "{$prefijo}.anio" => 'nullable|integer|min:1950|max:'.(now()->year + 1),
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_hora.before_or_equal' => 'La fecha y hora del intercambio no puede ser futura.',
            'fecha_hora.after' => 'La fecha y hora del intercambio es demasiado antigua.',
            'caja.id.required_without' => 'Selecciona o registra la caja.',
            'tracto.id.required_without' => 'Selecciona o registra el tractocamión.',
            'caja.nuevo.numero_economico.required_with' => 'El número económico de la caja es obligatorio.',
            'caja.nuevo.placa.required_with' => 'La placa de la caja es obligatoria.',
            'tracto.nuevo.numero_economico.required_with' => 'El número económico del tracto es obligatorio.',
            'tracto.nuevo.placa.required_with' => 'La placa del tracto es obligatoria.',
            'numero_sello.required' => 'El número de sello es obligatorio cuando la caja va cargada.',
            'llantas.size' => 'Captura las :size llantas.',
            'llantas.*.profundidad_32.required' => 'Captura la profundidad de todas las llantas.',
            'llantas.*.psi.required' => 'Captura el PSI de todas las llantas.',
            'firma_operador.required' => 'Falta la firma del operador.',
            'firma_guardia.required' => 'Falta la firma del guardia.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_movimiento' => 'tipo de movimiento',
            'fecha_hora' => 'fecha y hora',
            'operador_id' => 'operador',
            'estado_carga' => 'estado de carga',
            'tipo_intercambio' => 'tipo de intercambio',
            'numero_sello' => 'número de sello',
            'llantas.*.profundidad_32' => 'profundidad',
            'llantas.*.psi' => 'PSI',
        ];
    }

    /**
     * Reglas que dependen de la base de datos o cruzan varios pasos.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validarCatalogos($validator);
                $this->validarRevision($validator);
                $this->validarFotos($validator);
            },
        ];
    }

    private function validarCatalogos(Validator $validator): void
    {
        if (! Operador::whereKey($this->integer('operador_id'))->exists()) {
            $validator->errors()->add('operador_id', 'El operador no existe o ya no está activo.');
        }

        if ($this->filled('caja.id') && ! Caja::activos()->whereKey($this->integer('caja.id'))->exists()) {
            $validator->errors()->add('caja.id', 'La caja seleccionada no existe o está desactivada.');
        }

        if ($this->filled('caja.nuevo')) {
            $clienteId = $this->input('caja.nuevo.cliente_id');

            if ($clienteId && ! Cliente::activos()->whereKey($clienteId)->exists()) {
                $validator->errors()->add('caja.nuevo.cliente_id', 'El dueño seleccionado no existe o está desactivado.');
            }

            if (! $clienteId && ! $this->filled('caja.nuevo.cliente_nuevo.nombre')) {
                $validator->errors()->add('caja.nuevo.cliente_id', 'Indica el dueño de la caja nueva.');
            }
        }

        if ($this->filled('tracto.id') && ! Tractocamion::activos()->whereKey($this->integer('tracto.id'))->exists()) {
            $validator->errors()->add('tracto.id', 'El tractocamión seleccionado no existe o está desactivado.');
        }

        if ($this->filled('tracto.nuevo') && $this->boolean('tracto.nuevo.es_permisionario')) {
            $lineaId = $this->input('tracto.nuevo.linea_id');

            if ($lineaId && ! LineaTransportista::activos()->whereKey($lineaId)->exists()) {
                $validator->errors()->add('tracto.nuevo.linea_id', 'La línea transportista no existe o está desactivada.');
            }

            if (! $lineaId && ! $this->filled('tracto.nuevo.linea_nueva.nombre')) {
                $validator->errors()->add('tracto.nuevo.linea_id', 'Indica la línea transportista del permisionario.');
            }
        }

        if ($this->filled('cliente_id') && ! Cliente::activos()->whereKey($this->integer('cliente_id'))->exists()) {
            $validator->errors()->add('cliente_id', 'El cliente del viaje no existe o está desactivado.');
        }

        foreach (['origen_id' => 'origen', 'destino_id' => 'destino'] as $campo => $nombre) {
            if ($this->filled($campo) && ! Destino::activos()->whereKey($this->integer($campo))->exists()) {
                $validator->errors()->add($campo, "El {$nombre} no existe o está desactivado.");
            }
        }
    }

    /** Debe venir un resultado válido por cada punto activo, con observaciones si hay hallazgo. */
    private function validarRevision(Validator $validator): void
    {
        $puntos = PuntoInspeccion::activos()->get(['id', 'nombre', 'tipo_respuesta'])->keyBy('id');
        $revision = collect($this->input('revision'))->keyBy('punto_inspeccion_id');

        foreach ($puntos as $punto) {
            $fila = $revision->get($punto->id);

            if (! $fila) {
                $validator->errors()->add('revision', "Falta revisar el punto «{$punto->nombre}».");

                continue;
            }

            if (! in_array($fila['resultado'], RevisionPunto::RESULTADOS[$punto->tipo_respuesta] ?? [], true)) {
                $validator->errors()->add('revision', "Resultado no válido en «{$punto->nombre}».");

                continue;
            }

            if (in_array($fila['resultado'], RevisionPunto::CON_HALLAZGO, true) && blank($fila['observaciones'] ?? null)) {
                $validator->errors()->add('revision', "Escribe las observaciones de «{$punto->nombre}».");
            }
        }

        $sobrantes = $revision->keys()->diff($puntos->keys());

        if ($sobrantes->isNotEmpty()) {
            $validator->errors()->add('revision', 'La revisión incluye puntos que ya no están activos. Actualiza los datos del dispositivo.');
        }
    }

    private function validarFotos(Validator $validator): void
    {
        $fotos = collect($this->input('fotos', []));
        $sellos = $fotos->where('tipo', FotoIntercambio::SELLO)->count();

        if ($this->input('estado_carga') === Intercambio::CARGADA) {
            $maximo = config('intercambios.max_fotos_sello');

            if ($sellos < 1 || $sellos > $maximo) {
                $validator->errors()->add('fotos', "Toma de 1 a {$maximo} fotos del sello.");
            }
        } elseif ($sellos > 0) {
            $validator->errors()->add('fotos', 'Una caja vacía no lleva fotos de sello.');
        }

        // Recorrido: exactamente una foto por cada vista de config('intercambios.recorrido').
        $porVista = $fotos->where('tipo', FotoIntercambio::RECORRIDO)->countBy('vista');
        $faltan = collect(config('intercambios.recorrido'))->filter(fn ($v) => ! $porVista->has($v['clave']))->pluck('etiqueta');

        if ($faltan->isNotEmpty()) {
            $validator->errors()->add('fotos', 'Faltan fotos de la unidad: '.$faltan->join(', ').'.');
        }

        if ($porVista->contains(fn ($cantidad) => $cantidad > 1)) {
            $validator->errors()->add('fotos', 'Solo va una foto por cada vista de la unidad.');
        }

        $puntosRevisados = collect($this->input('revision'))->pluck('punto_inspeccion_id')->map(fn ($id) => (int) $id);

        foreach ($fotos as $foto) {
            $valida = match ($foto['tipo']) {
                FotoIntercambio::PUNTO => $puntosRevisados->contains((int) ($foto['punto_inspeccion_id'] ?? 0)),
                FotoIntercambio::LLANTA => ! empty($foto['posicion']),
                FotoIntercambio::PROBLEMA => ! empty($foto['unidad']),
                FotoIntercambio::RECORRIDO => ! empty($foto['vista']),
                default => true,
            };

            if (! $valida) {
                $validator->errors()->add('fotos', 'Hay una foto sin el punto, la llanta o la unidad a la que pertenece.');

                return;
            }
        }
    }
}
