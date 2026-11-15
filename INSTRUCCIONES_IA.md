# Unificar en una sola base de datos: app de Caseta e Intercambios

## Contexto

La app de **Caseta e Intercambios** (Laravel 12, MariaDB) hoy usa dos conexiones:

| Conexión | Base | Contenido |
|---|---|---|
| `mariadb` (default) | `intercambios` | 3 tablas propias: `users`, `bitacora_visitas`, `bitacora_intercambios` |
| `db_externa` | `laravel` (base de la app principal / RH) | Todo lo demás: intercambios, fotos, llantas, revisiones, catálogos, visitas, empresas, operadores |

**Objetivo:** que todo funcione con **una sola base**, la principal (`laravel`). Hay que mover las 3 tablas locales a esa base, con sus datos y **conservando los mismos ids**, y quitar del código la conexión `db_externa`.

## Archivos de este paquete

| Archivo | Qué es |
|---|---|
| `01_estructura.sql` | `CREATE TABLE` de las 3 tablas, listas para la base principal |
| `migraciones/*.php` | Las mismas 3 tablas como migraciones de Laravel (usa estas o el `.sql`, no las dos) |
| `02_datos.sql` | Todos los datos: 10 usuarios, 2 registros de bitácora de visitas, 829 de bitácora de intercambios |

## Decisión clave: la tabla `users`

En la base principal **ya existe `users`** (885 empleados de RH, con otra estructura: `numero_empleado`, `rol_id`, `area_id`, `jornada_id`…). Los usuarios de la caseta **no** son esos empleados.

Por eso la tabla local `users` se mueve como **`usuarios_caseta`** y no se mezcla con `users`:

```
usuarios_caseta: id, name, email (unique), email_verified_at, password (bcrypt), rol ('admin'|'caseta'),
                 activo (bool), remember_token, created_at, updated_at
```

- Las contraseñas (hash bcrypt) se copian tal cual, así que los usuarios entran con la misma contraseña de siempre.
- `remember_token` va vacío, así que cada usuario tiene que iniciar sesión una vez.

### ⚠️ Los ids de `usuarios_caseta` NO se pueden cambiar

La tabla `intercambios` (que ya está en la base principal) tiene la columna **`guardia_id`**, que guarda el **id del usuario de caseta**. Esa columna no tiene llave foránea. Hoy usa los ids **4 y 5**. Además, `bitacora_visitas.user_id` y `bitacora_intercambios.user_id` apuntan a esos mismos ids. `02_datos.sql` inserta los ids explícitos (1–10). **No reasignar ids ni importar con autoincremento nuevo.**

Si algún día se mezclan con `users` de RH, habría que remapear `intercambios.guardia_id` y las dos bitácoras. **No hacerlo en esta tarea.**

## Paso 1: Base de datos (en la base principal)

1. Respaldar la base principal antes de empezar.
2. Verificar que no existan ya `usuarios_caseta`, `bitacora_visitas` ni `bitacora_intercambios` en la base principal. Hoy no existen.
3. Crear las tablas con **una** de estas opciones:
   - Copiar `migraciones/*.php` a `database/migrations/` de la app que use esa base y correr `php artisan migrate`. Revisar que no choquen con migraciones existentes.
   - O ejecutar `01_estructura.sql`.
4. Ejecutar `02_datos.sql`. Ya maneja `SET FOREIGN_KEY_CHECKS` y una transacción.
5. Verificar:
   ```sql
   SELECT COUNT(*) FROM usuarios_caseta;        -- 10
   SELECT COUNT(*) FROM bitacora_visitas;       -- 2
   SELECT COUNT(*) FROM bitacora_intercambios;  -- 829
   -- No debe regresar filas (todo guardia tiene usuario):
   SELECT DISTINCT guardia_id FROM intercambios WHERE guardia_id NOT IN (SELECT id FROM usuarios_caseta);
   -- No debe regresar filas (toda bitácora apunta a un intercambio existente):
   SELECT id FROM bitacora_intercambios WHERE intercambio_id NOT IN (SELECT id FROM intercambios);
   ```
6. Verificar que el `AUTO_INCREMENT` quedó arriba del id máximo: usuarios > 10, bitácora de visitas > 2, bitácora de intercambios > 829. MariaDB lo ajusta solo al insertar ids explícitos.

## Paso 2: Código (quitar la segunda conexión)

Aplica en el código de la app de Caseta e Intercambios, esté como app separada o ya integrada a la principal.

### Configuración
- `.env`: `DB_DATABASE` debe apuntar a la base principal (`laravel`). Borrar las variables `DB_EXTERNA_*`.
- `config/database.php`: eliminar la conexión `db_externa` (línea ~68) **al final**, después de quitar todos sus usos.
- `config/intercambios.php` (línea ~8): actualizar el comentario que menciona `db_externa`.

### Modelos: quitar `protected $connection = 'db_externa';` (22 modelos)
`AreaExterno`, `Caja`, `Cliente`, `Destino`, `DocumentoOperador`, `EmpresaExterno`, `FotoIntercambio`, `FotoVisitaExterno`, `InfoOperador`, `InfoUsuarioExterno`, `Intercambio`, `LineaTransportista`, `LlantaIntercambio`, `Operador`, `PuntoInspeccion`, `RequerimientoFotoVisitaExterno`, `RevisionPunto`, `TipoCaja`, `TipoLicencia`, `Tractocamion`, `UserExterno`, `VisitaExterno`.
Para localizarlos: `grep -rn "db_externa" app`.

### Modelo `User` (usuarios de caseta)
- `app/Models/User.php`: agregar `protected $table = 'usuarios_caseta';`.
- **Si se integra a la app principal**, ahí ya existe un `App\Models\User` para `users` de RH. En ese caso crear un modelo nuevo, por ejemplo `App\Models\UsuarioCaseta` (tabla `usuarios_caseta`, mismos fillable, hidden y casts que el `User` actual), y:
  - Agregar en `config/auth.php` un provider `usuarios_caseta` (eloquent, `UsuarioCaseta::class`) y un guard propio (por ejemplo `caseta`, driver `session`), para no romper el login de RH.
  - Hacer que `AuthController`, los middleware `EnsureUserHasRole` y `EnsureUserIsActive`, y las rutas de caseta/admin usen ese guard (`Auth::guard('caseta')`, `auth:caseta`, `$request->user('caseta')`).
  - Reemplazar los usos de `App\Models\User` en: `IntercambioController`, `VisitaExternoController`, `UsuarioController`, `UsuarioRequest`, `RegistrarIntercambio`, `BitacoraVisita` (relación `usuario()`), `ReporteIntercambios` (`auth()->user()`).
- Las relaciones `user_id` de las bitácoras y `intercambios.guardia_id` apuntan a `usuarios_caseta`, **nunca** a `users` de RH. `Intercambio.php` (línea ~29) dice "el guardia es un usuario de la base local": actualizar el comentario.

### Reglas de validación con conexión explícita: quitar el prefijo `db_externa.`
- `app/Http/Controllers/VisitaExternoController.php:141` y `:291`: `exists:db_externa.empresas,id` → `exists:empresas,id`
- `app/Http/Requests/GuardarIntercambioRequest.php:103` y `:108`: `exists:db_externa.tipos_caja,id` → `exists:tipos_caja,id`
- `app/Support/CatalogosIntercambio.php:64, 81, 85, 92, 138`: `db_externa.tipos_caja`, `db_externa.clientes`, `db_externa.cajas`, `db_externa.lineas_transportistas` → sin prefijo
- `app/Http/Requests/UsuarioRequest.php:34`: `Rule::unique('users', 'email')` → `Rule::unique('usuarios_caseta', 'email')`. **Importante:** si no se cambia, validaría contra los empleados de RH.

### Consultas y transacciones con conexión explícita
- `app/Services/RegistrarIntercambio.php:80`: `DB::connection('db_externa')->transaction(...)` → `DB::transaction(...)`
- `app/Services/RegistrarIntercambio.php:220`: `DB::connection('db_externa')->table('folios_intercambio')` → `DB::table('folios_intercambio')`
- `app/Http/Controllers/VisitaExternoController.php:195`: `DB::connection('db_externa')->transaction(...)` → `DB::transaction(...)`

### Mejora que permite tener una sola base (opcional, recomendada)
Antes, el intercambio (base externa) y su `bitacora_intercambios` (base local) se guardaban en transacciones separadas, porque eran dos bases. Ahora pueden ir en **la misma transacción**. En `IntercambioController::store` (~línea 243–250), el `BitacoraIntercambio::create(...)` puede quedar dentro de la misma `DB::transaction` que crea el intercambio. Lo mismo para `BitacoraVisita` en `VisitaExternoController::anotarEnBitacora` (~línea 394). Así no puede quedar un intercambio sin bitácora ni una bitácora sin intercambio.

### Comentarios y docs
Actualizar los que dicen "base local" o "base externa": `BitacoraVisita.php`, `BitacoraIntercambio.php`, `Intercambio.php`, `IntercambioController.php:57`, el `README.md` y las migraciones viejas en `database/migrations/` (las 3 de la base `intercambios`, que ya no aplican).

## Paso 3: Pruebas manuales

1. Login con un usuario admin y con el usuario `caseta@…` (id 4), con sus contraseñas actuales.
2. Admin → Usuarios: listar, crear, editar, desactivar. Que el email duplicado se valide contra `usuarios_caseta`.
3. Caseta → registrar una entrada y una salida de visitante. Revisar que se cree la fila en `bitacora_visitas` con `user_id` del guardia.
4. Caseta → nuevo intercambio (con y sin conexión). Revisar `intercambios.guardia_id`, el folio y la fila en `bitacora_intercambios`.
5. Reenviar el mismo intercambio guardado offline: no debe duplicarse (lo evita `id_cliente`).
6. Admin → historial y detalle de intercambios, PDFs (contrato y CTPAT), patio, dashboard y exportar reportes.
7. `php artisan intercambios:limpiar-fotos` (consulta `bitacora_intercambios`) sin errores.
8. `grep -rn "db_externa" app config routes` → sin resultados.

## Qué NO hacer
- No mezclar `usuarios_caseta` con `users` de RH ni cambiar sus ids.
- No borrar la base `intercambios` vieja hasta confirmar que todo funciona. Dejarla como respaldo un tiempo.
- No modificar las tablas de RH (`users`, `info_usuarios`, etc.). La app solo las lee para operadores y empleados.
