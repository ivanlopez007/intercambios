<?php

namespace App\Models;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Empleado de la app de RH (tabla `users`). Es la fuente única de la persona y de su
 * contraseña: Caseta solo la lee para validar el login y nunca escribe en ella.
 * Un empleado con `deleted_at` está dado de baja y no puede entrar.
 */
class EmpleadoRh extends Model
{
    use SoftDeletes, SoloLectura;

    /** Clave del sistema de Caseta en `sistemas` (se busca por clave, nunca por id). */
    public const SISTEMA = 'intercambios';

    protected $table = 'users';

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'must_change_password' => 'boolean',
    ];

    /**
     * Correos de relleno que RH captura cuando la persona no tiene uno real
     * (…@empresa.local, …@example.com, notiene@…, notiene1@…). Nunca sirven para entrar.
     */
    public static function esCorreoDeRelleno(string $correo): bool
    {
        $correo = mb_strtolower(trim($correo));

        return str_ends_with($correo, '@empresa.local')
            || str_ends_with($correo, '@example.com')
            || preg_match('/^notiene\d*@/', $correo) === 1;
    }

    /** Empleados activos cuyo correo coincide (sin distinguir mayúsculas ni espacios). */
    public function scopeConCorreo(Builder $query, string $correo): Builder
    {
        return $query->whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($correo))]);
    }

    // Nunca se seleccionan rfc ni curp: están cifrados con la llave de la app de RH.
    public function info(): HasOne
    {
        return $this->hasOne(InfoOperador::class, 'usuario_id')
            ->select(['id', 'usuario_id', 'nombre', 'apellido_p', 'apellido_m']);
    }

    /** Tiene marcado "Intercambios" en RH (Admin → Usuarios → Acceso a Sistemas) y el sistema está activo. */
    public function tieneAccesoACaseta(): bool
    {
        return DB::table('sistema_usuario as su')
            ->join('sistemas as s', 's.id', '=', 'su.sistema_id')
            ->where('su.user_id', $this->id)
            ->where('s.clave', self::SISTEMA)
            ->where('s.activo', true)
            ->exists();
    }

    public function getNombreCompletoAttribute(): string
    {
        $info = $this->info;

        return $info
            ? trim(preg_replace('/\s+/', ' ', "{$info->nombre} {$info->apellido_p} {$info->apellido_m}"))
            : "Empleado {$this->numero_empleado}";
    }
}
