<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:255',
            'logo_url' => 'nullable|url',
            'sitio_web' => 'nullable|url',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la empresa es obligatorio.',
        ];
    }

    /**
     * La tabla externa no acepta nulos en los campos opcionales, así que se guardan como cadena vacía.
     */
    public function datosEmpresa(): array
    {
        return array_map(fn ($valor) => $valor ?? '', array_merge([
            'razon_social' => '',
            'categoria' => '',
            'logo_url' => '',
            'sitio_web' => '',
        ], $this->validated()));
    }
}
