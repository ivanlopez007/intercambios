<?php

namespace App\Support;

use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\LineaTransportista;
use App\Models\PuntoInspeccion;
use App\Models\TipoCaja;
use App\Models\Tractocamion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Definición de las pantallas de catálogos del módulo de intercambios.
 * Los registros se activan o desactivan, nunca se borran (los intercambios los referencian).
 *
 * Cada campo: etiqueta, tipo (text, number, select, checkbox), reglas y, para select, opciones.
 * `reglas` puede ser un closure que recibe el registro que se edita (o null) y la petición.
 */
class CatalogosIntercambio
{
    public static function todos(): array
    {
        return [
            'clientes' => [
                'modelo' => Cliente::class,
                'titulo' => 'Clientes',
                'singular' => 'cliente',
                'icono' => 'fa-solid fa-building',
                'descripcion' => 'Dueños de las cajas y clientes de los viajes.',
                'buscar' => ['nombre', 'rfc'],
                'orden' => 'nombre',
                'campos' => [
                    'nombre' => ['etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:255']],
                    'rfc' => ['etiqueta' => 'RFC', 'reglas' => ['nullable', 'string', 'max:13']],
                ],
            ],
            'tipos-caja' => [
                'modelo' => TipoCaja::class,
                'titulo' => 'Tipos de caja',
                'singular' => 'tipo de caja',
                'icono' => 'fa-solid fa-shapes',
                'buscar' => ['nombre'],
                'orden' => 'nombre',
                'campos' => [
                    'nombre' => ['etiqueta' => 'Nombre', 'reglas' => fn (?Model $registro) => [
                        'required', 'string', 'max:255',
                        Rule::unique('db_externa.tipos_caja', 'nombre')->ignore($registro?->id),
                    ]],
                ],
            ],
            'cajas' => [
                'modelo' => Caja::class,
                'titulo' => 'Cajas',
                'singular' => 'caja',
                'icono' => 'fa-solid fa-trailer',
                'descripcion' => 'El número económico puede repetirse entre dueños distintos.',
                'buscar' => ['numero_economico', 'placa', 'vin'],
                'orden' => 'numero_economico',
                'con' => ['cliente:id,nombre', 'tipoCaja:id,nombre'],
                'campos' => [
                    'cliente_id' => [
                        'etiqueta' => 'Dueño', 'tipo' => 'select',
                        'opciones' => fn () => Cliente::orderBy('nombre')->pluck('nombre', 'id'),
                        'reglas' => ['required', 'integer', 'exists:db_externa.clientes,id'],
                    ],
                    'numero_economico' => ['etiqueta' => 'Número económico', 'reglas' => fn (?Model $registro, Request $request) => [
                        'required', 'string', 'max:30',
                        Rule::unique('db_externa.cajas', 'numero_economico')
                            ->where('cliente_id', $request->input('cliente_id'))
                            ->ignore($registro?->id),
                    ]],
                    'tipo_caja_id' => [
                        'etiqueta' => 'Tipo', 'tipo' => 'select',
                        'opciones' => fn () => TipoCaja::orderBy('nombre')->pluck('nombre', 'id'),
                        'reglas' => ['nullable', 'integer', 'exists:db_externa.tipos_caja,id'],
                    ],
                    'placa' => ['etiqueta' => 'Placa', 'reglas' => ['required', 'string', 'max:20']],
                    'estado_placa' => ['etiqueta' => 'Estado de la placa', 'reglas' => ['nullable', 'string', 'max:50'], 'tabla' => false],
                    'marca' => ['etiqueta' => 'Marca', 'reglas' => ['nullable', 'string', 'max:50']],
                    'modelo' => ['etiqueta' => 'Modelo', 'reglas' => ['nullable', 'string', 'max:50'], 'tabla' => false],
                    'vin' => ['etiqueta' => 'VIN', 'reglas' => ['nullable', 'string', 'max:17'], 'tabla' => false],
                    'anio' => ['etiqueta' => 'Año', 'tipo' => 'number', 'reglas' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)], 'tabla' => false],
                    'tamano_pies' => ['etiqueta' => 'Tamaño (pies)', 'tipo' => 'number', 'reglas' => ['nullable', 'integer', 'min:1', 'max:100'], 'tabla' => false],
                ],
            ],
            'lineas-transportistas' => [
                'modelo' => LineaTransportista::class,
                'titulo' => 'Líneas transportistas',
                'singular' => 'línea transportista',
                'icono' => 'fa-solid fa-route',
                'descripcion' => 'Líneas de los tractocamiones de permisionarios.',
                'buscar' => ['nombre', 'rfc'],
                'orden' => 'nombre',
                'campos' => [
                    'nombre' => ['etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:255']],
                    'rfc' => ['etiqueta' => 'RFC', 'reglas' => ['nullable', 'string', 'max:13']],
                ],
            ],
            'tractocamiones' => [
                'modelo' => Tractocamion::class,
                'titulo' => 'Tractocamiones',
                'singular' => 'tractocamión',
                'icono' => 'fa-solid fa-truck',
                'buscar' => ['numero_economico', 'placa', 'vin'],
                'orden' => 'numero_economico',
                'con' => ['lineaTransportista:id,nombre'],
                'campos' => [
                    'numero_economico' => ['etiqueta' => 'Número económico', 'reglas' => ['required', 'string', 'max:30']],
                    'placa' => ['etiqueta' => 'Placa', 'reglas' => ['required', 'string', 'max:20']],
                    'estado_placa' => ['etiqueta' => 'Estado de la placa', 'reglas' => ['nullable', 'string', 'max:50'], 'tabla' => false],
                    'marca' => ['etiqueta' => 'Marca', 'reglas' => ['nullable', 'string', 'max:50']],
                    'modelo' => ['etiqueta' => 'Modelo', 'reglas' => ['nullable', 'string', 'max:50'], 'tabla' => false],
                    'vin' => ['etiqueta' => 'VIN', 'reglas' => ['nullable', 'string', 'max:17'], 'tabla' => false],
                    'anio' => ['etiqueta' => 'Año', 'tipo' => 'number', 'reglas' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)], 'tabla' => false],
                    'es_permisionario' => ['etiqueta' => 'Es permisionario', 'tipo' => 'checkbox', 'reglas' => ['boolean']],
                    'linea_transportista_id' => [
                        'etiqueta' => 'Línea transportista', 'tipo' => 'select', 'visible_si' => 'es_permisionario',
                        'opciones' => fn () => LineaTransportista::orderBy('nombre')->pluck('nombre', 'id'),
                        'reglas' => fn (?Model $registro, Request $request) => [
                            Rule::requiredIf($request->boolean('es_permisionario')), 'nullable', 'integer',
                            'exists:db_externa.lineas_transportistas,id',
                        ],
                    ],
                ],
            ],
            'destinos' => [
                'modelo' => Destino::class,
                'titulo' => 'Orígenes y destinos',
                'singular' => 'ubicación',
                'icono' => 'fa-solid fa-location-dot',
                'buscar' => ['nombre'],
                'orden' => 'nombre',
                'campos' => [
                    'nombre' => ['etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:255']],
                ],
            ],
            'puntos-inspeccion' => [
                'modelo' => PuntoInspeccion::class,
                'titulo' => 'Puntos de inspección',
                'singular' => 'punto',
                'icono' => 'fa-solid fa-list-check',
                'descripcion' => 'Los puntos se precargan desde RH. Aquí solo se renombran, reordenan o desactivan.',
                'crear' => false,
                'buscar' => ['nombre', 'seccion'],
                'orden' => ['seccion', 'orden'],
                'campos' => [
                    'seccion' => ['etiqueta' => 'Sección', 'tipo' => 'select', 'opciones' => fn () => collect(PuntoInspeccion::SECCIONES), 'editable' => false],
                    'nombre' => ['etiqueta' => 'Nombre', 'reglas' => ['required', 'string', 'max:255']],
                    'orden' => ['etiqueta' => 'Orden', 'tipo' => 'number', 'reglas' => ['required', 'integer', 'min:0', 'max:1000']],
                ],
            ],
        ];
    }

    public static function obtener(string $clave): array
    {
        $catalogo = self::todos()[$clave] ?? abort(404);

        $catalogo['clave'] = $clave;
        $catalogo['crear'] ??= true;
        $catalogo['con'] ??= [];

        foreach ($catalogo['campos'] as $nombre => $campo) {
            $catalogo['campos'][$nombre] = $campo + ['tipo' => 'text', 'tabla' => true, 'editable' => true, 'reglas' => []];
        }

        return $catalogo;
    }
}
