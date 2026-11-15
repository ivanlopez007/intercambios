<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Perfil de una cuenta dentro de Caseta (tabla `usuarios_caseta`): rol y estado.
 * La sesión de Caseta siempre autentica este registro, así que su id es el que queda en
 * `intercambios.guardia_id` y en las bitácoras. Los ids no se reasignan nunca.
 *
 * - Vinculado (`user_id` con valor): es un empleado de RH. Su nombre, correo y contraseña
 *   vienen de RH; la columna `password` de aquí es un hash aleatorio que no se usa ni se cambia.
 * - Genérica (`user_id` NULL): cuenta que no es una persona (p. ej. "Caseta 1"); conserva
 *   su propia contraseña y se administra completa desde Caseta.
 */
#[Fillable([
    'user_id',
    'name',
    'email',
    'password',
    'rol',
    'activo',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios_caseta';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /** Empleado de RH al que pertenece el perfil (incluye dados de baja, para mostrar su nombre). */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(EmpleadoRh::class, 'user_id')->withTrashed();
    }

    public function esVinculado(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Nombre para mostrar y para guardar en registros nuevos: el de RH si es un empleado,
     * o el capturado en Caseta si es una cuenta genérica.
     */
    public function getNombreMostradoAttribute(): string
    {
        if ($this->esVinculado() && $this->empleado) {
            return $this->empleado->nombre_completo;
        }

        return (string) $this->name;
    }
}
