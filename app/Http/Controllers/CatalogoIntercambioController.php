<?php

namespace App\Http\Controllers;

use App\Support\CatalogosIntercambio;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Altas, edición y activación de los catálogos de intercambios (base de RH).
 * Ningún registro se borra: se desactiva, porque los intercambios completados lo referencian.
 */
class CatalogoIntercambioController extends Controller
{
    public function index(Request $request, string $catalogo)
    {
        $config = CatalogosIntercambio::obtener($catalogo);
        $query = $config['modelo']::query()->with($config['con']);

        if ($request->filled('buscar')) {
            $buscar = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->input('buscar')).'%';
            $query->where(function ($q) use ($config, $buscar) {
                foreach ($config['buscar'] as $columna) {
                    $q->orWhereRaw("{$columna} LIKE ? ESCAPE '!'", [$buscar]);
                }
            });
        }

        if ($request->input('estado') === 'activos') {
            $query->where('activo', true);
        } elseif ($request->input('estado') === 'inactivos') {
            $query->where('activo', false);
        }

        foreach ((array) $config['orden'] as $columna) {
            $query->orderBy($columna);
        }

        $opciones = collect($config['campos'])
            ->filter(fn ($campo) => $campo['tipo'] === 'select')
            ->map(fn ($campo) => ($campo['opciones'])());

        return view('admin.catalogos.intercambio', [
            'config' => $config,
            'catalogos' => CatalogosIntercambio::todos(),
            'registros' => $query->paginate(20)->withQueryString(),
            'opciones' => $opciones,
        ]);
    }

    public function store(Request $request, string $catalogo): RedirectResponse
    {
        $config = CatalogosIntercambio::obtener($catalogo);
        abort_unless($config['crear'], 404);

        $config['modelo']::create($this->validar($request, $config, null) + ['activo' => true]);

        return back()->with('success', ucfirst($config['singular']).' registrado correctamente.');
    }

    public function update(Request $request, string $catalogo, int $id): RedirectResponse
    {
        $config = CatalogosIntercambio::obtener($catalogo);
        $registro = $config['modelo']::findOrFail($id);

        $registro->update($this->validar($request, $config, $registro));

        return back()->with('success', ucfirst($config['singular']).' actualizado correctamente.');
    }

    public function estado(string $catalogo, int $id): RedirectResponse
    {
        $config = CatalogosIntercambio::obtener($catalogo);
        $registro = $config['modelo']::findOrFail($id);

        $registro->forceFill(['activo' => ! $registro->activo])->save();

        return back()->with('success', ucfirst($config['singular']).($registro->activo ? ' activado.' : ' desactivado.'));
    }

    private function validar(Request $request, array $config, ?Model $registro): array
    {
        $campos = collect($config['campos'])->filter(fn ($campo) => $campo['editable']);

        // Las casillas no se envían cuando están desmarcadas.
        foreach ($campos as $nombre => $campo) {
            if ($campo['tipo'] === 'checkbox') {
                $request->merge([$nombre => $request->boolean($nombre)]);
            }
        }

        $reglas = $campos->map(fn ($campo) => $campo['reglas'] instanceof Closure
            ? ($campo['reglas'])($registro, $request)
            : $campo['reglas'])->all();

        $datos = $request->validate($reglas, [], $campos->map(fn ($campo) => mb_strtolower($campo['etiqueta']))->all());

        $datos = array_map(fn ($valor) => is_string($valor) && trim($valor) === '' ? null : (is_string($valor) ? trim($valor) : $valor), $datos);

        // Solo los permisionarios llevan línea transportista.
        if (array_key_exists('es_permisionario', $datos) && ! $datos['es_permisionario']) {
            $datos['linea_transportista_id'] = null;
        }

        return $datos;
    }
}
