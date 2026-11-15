<?php

namespace App\Services;

use App\Models\EmpleadoRh;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login de Caseta con identidad y contraseña únicas con RH.
 *
 *   PASO 1. Empleado de RH (correo real, activo): valida contra users.password, exige acceso
 *           a "Intercambios" y que no deba cambiar su contraseña; resuelve (o crea) su perfil
 *           en usuarios_caseta.
 *   PASO 2. Cuenta genérica (usuarios_caseta.user_id NULL): valida su propia contraseña.
 *   PASO 3. Nada coincide: error genérico.
 *
 * Regla de oro: un perfil vinculado nunca entra con la contraseña local de usuarios_caseta.
 */
class AccesoCaseta
{
    public const ERROR_GENERICO = 'El correo o la contraseña no coinciden.';

    public const SIN_ACCESO = 'No tienes acceso a este sistema.';

    public const CAMBIAR_PASSWORD = 'Debes cambiar tu contraseña en la app de RH antes de entrar.';

    public const DESACTIVADA = 'Tu cuenta se encuentra desactivada. Contacta al administrador.';

    public const PASSWORD_CAMBIO = 'Tu contraseña cambió en la app de RH. Inicia sesión con la nueva.';

    public const SESION_EXPIRADA = 'Tu sesión expiró. Inicia sesión de nuevo.';

    /** Clave en la sesión (y nombre de la cookie de "recordarme") con la huella de la contraseña de RH. */
    public const HUELLA = 'caseta_pwd';

    /**
     * Devuelve el perfil que debe iniciar sesión o lanza un error de validación en `email`.
     *
     * @throws ValidationException
     */
    public function autenticar(string $correo, string $password): User
    {
        $correo = mb_strtolower(trim($correo));

        // PASO 1: ¿es una persona de RH?
        if (! EmpleadoRh::esCorreoDeRelleno($correo)) {
            $empleados = EmpleadoRh::conCorreo($correo)->limit(2)->get();

            if ($empleados->count() > 1) {
                // RH valida que no se repita; si pasara, no se adivina cuál es.
                Log::warning('Login de Caseta: correo repetido en users de RH.', ['correo' => $correo]);
                $this->rechazar(self::ERROR_GENERICO);
            }

            if ($empleado = $empleados->first()) {
                return $this->autenticarEmpleado($empleado, $correo, $password);
            }
        }

        // PASO 2: ¿es una cuenta genérica?
        $perfil = User::whereNull('user_id')->where('email', $correo)->first();

        if (! $perfil) {
            // PASO 3. Se calcula un hash igual para no revelar por el tiempo de respuesta qué correos existen.
            Hash::check($password, '$2y$12$'.str_repeat('.', 53));
            $this->rechazar(self::ERROR_GENERICO);
        }

        if (! Hash::check($password, $perfil->password)) {
            $this->rechazar(self::ERROR_GENERICO);
        }

        if (! $perfil->activo) {
            $this->rechazar(self::DESACTIVADA);
        }

        return $perfil;
    }

    private function autenticarEmpleado(EmpleadoRh $empleado, string $correo, string $password): User
    {
        // a) La contraseña es la de RH.
        if (! Hash::check($password, $empleado->password)) {
            $this->rechazar(self::ERROR_GENERICO);
        }

        // b) Tiene marcado "Intercambios" en RH.
        if (! $empleado->tieneAccesoACaseta()) {
            $this->rechazar(self::SIN_ACCESO);
        }

        // c) RH le pidió cambiar su contraseña (reseteo o contraseña inicial).
        if ($empleado->must_change_password) {
            $this->rechazar(self::CAMBIAR_PASSWORD);
        }

        // d) Su perfil en Caseta; se crea en su primer login.
        $perfil = $this->perfilDe($empleado, $correo);

        // e) El admin de Caseta puede bloquear el perfil.
        if (! $perfil->activo) {
            $this->rechazar(self::DESACTIVADA);
        }

        $perfil->setRelation('empleado', $empleado);

        return $perfil;
    }

    /**
     * Perfil del empleado en usuarios_caseta. RH es la fuente del correo: si cambió allá,
     * se actualiza aquí. Nunca se toma el perfil de otra cuenta por coincidir el correo.
     */
    private function perfilDe(EmpleadoRh $empleado, string $correo): User
    {
        $perfil = User::where('user_id', $empleado->id)->first();
        $ocupado = User::where('email', $correo)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $empleado->id))
            ->exists();

        if ($ocupado) {
            // RH ya impide guardar un correo que use otra cuenta de Caseta; esto es la última defensa.
            Log::warning('Login de Caseta: el correo del empleado lo usa otra cuenta de Caseta.', [
                'correo' => $correo,
                'empleado_id' => $empleado->id,
            ]);
            $this->rechazar('Ese correo ya lo usa otra cuenta de Caseta. Contacta al administrador.');
        }

        if (! $perfil) {
            return User::create([
                'user_id' => $empleado->id,
                'email' => $correo,
                'name' => $empleado->nombre_completo,
                'rol' => 'caseta',
                'activo' => true,
                // Hash aleatorio: un perfil vinculado nunca entra con contraseña local.
                'password' => Str::random(64),
            ]);
        }

        if ($perfil->email !== $correo) {
            $perfil->forceFill(['email' => $correo])->save();
        }

        return $perfil;
    }

    /**
     * Huella de la contraseña de RH del perfil, para detectar en cada petición si se cambió.
     * Es un HMAC con la llave de la app: no expone el hash aunque se lea la sesión.
     */
    public function huella(EmpleadoRh $empleado): string
    {
        return hash_hmac('sha256', (string) $empleado->password, (string) config('app.key'));
    }

    /**
     * Revalidación en cada petición. Devuelve el motivo por el que hay que cerrar la sesión,
     * o null si puede continuar. Para vinculados compara la huella guardada al iniciar sesión.
     */
    public function motivoParaCerrar(User $perfil, ?string $huellaGuardada): ?string
    {
        if (! $perfil->activo) {
            return self::DESACTIVADA;
        }

        if (! $perfil->esVinculado()) {
            return null;
        }

        // Sin withTrashed: un empleado dado de baja no se encuentra.
        $empleado = EmpleadoRh::with('info')->find($perfil->user_id);

        if (! $empleado || ! $empleado->tieneAccesoACaseta()) {
            return self::SIN_ACCESO;
        }

        if ($empleado->must_change_password) {
            return self::CAMBIAR_PASSWORD;
        }

        // Sesión sin huella (iniciada antes de este cambio o cookie de recordar sin ella).
        if ($huellaGuardada === null) {
            return self::SESION_EXPIRADA;
        }

        if (! hash_equals($this->huella($empleado), $huellaGuardada)) {
            return self::PASSWORD_CAMBIO;
        }

        $perfil->setRelation('empleado', $empleado);

        return null;
    }

    /** @throws ValidationException */
    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['email' => $mensaje]);
    }
}
