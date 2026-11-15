<?php

namespace App\Http\Requests;

use App\Models\EmpleadoRh;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UsuarioRequest extends FormRequest
{
    public const ROLES = ['caseta', 'admin'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'activo' => $this->boolean('activo'),
        ]);
    }

    /**
     * - Perfil vinculado a un empleado de RH: en Caseta solo se editan rol y activo
     *   (nombre, correo y contraseña se administran desde RH).
     * - Cuenta genérica: nombre, correo, contraseña, rol y activo. Su correo no puede ser
     *   de un empleado activo de RH (a ellos se les da acceso desde RH).
     */
    public function rules(): array
    {
        /** @var User|null $usuario */
        $usuario = $this->route('usuario');

        $reglas = [
            'rol' => ['required', Rule::in(self::ROLES)],
            'activo' => ['boolean'],
        ];

        if ($usuario?->esVinculado()) {
            return $reglas;
        }

        return $reglas + [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('usuarios_caseta', 'email')->ignore($usuario),
                function (string $atributo, mixed $valor, \Closure $fallar) {
                    if (EmpleadoRh::conCorreo((string) $valor)->exists()) {
                        $fallar('Ese correo es de un empleado; dale acceso a Intercambios desde la app de RH.');
                    }
                },
            ],
            // Al editar, la contraseña es opcional: si se deja vacía no cambia.
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    /**
     * Un administrador no puede quitarse el rol ni desactivarse a sí mismo
     * (evita quedarse sin acceso al panel).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $usuario = $this->route('usuario');

                if (! $usuario || ! $usuario->is($this->user())) {
                    return;
                }

                if ($this->input('rol') !== 'admin') {
                    $validator->errors()->add('rol', 'No puedes quitarte el rol de administrador a ti mismo.');
                }

                if (! $this->boolean('activo')) {
                    $validator->errors()->add('activo', 'No puedes desactivar tu propia cuenta.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'rol.required' => 'Selecciona un rol.',
            'rol.in' => 'El rol debe ser caseta o administrador.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
