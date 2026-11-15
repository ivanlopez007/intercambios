# Industrial Hefesto · Sistema de Caseta e Intercambios

Aplicación Laravel para el control de acceso en caseta: entradas y salidas de visitantes
(identificados por el QR de su empresa y con firma digital) y panel de administración.

## Estructura

| Área | URL | Roles | Uso |
| --- | --- | --- | --- |
| Caseta | `/caseta` | `caseta`, `admin` | Celulares de los guardias (PWA instalable) |
| Administración | `/admin` | `admin` | Historial de visitas, catálogo de empresas, dashboard |

- Rutas: [routes/web.php](routes/web.php), separadas en los grupos `caseta.*` y `admin.*`.
- Menús laterales: [config/menus.php](config/menus.php), dibujados con el componente
  [`<x-menu-lateral>`](resources/views/components/menu-lateral.blade.php).
- Roles: middleware `rol:` ([EnsureUserHasRole](app/Http/Middleware/EnsureUserHasRole.php)).
  Cada petición se revalida ([VerificarAccesoCaseta](app/Http/Middleware/VerificarAccesoCaseta.php)):
  perfil desactivado, o empleado dado de baja en RH, sin "Intercambios" o con la contraseña cambiada en RH,
  pierde la sesión en su siguiente petición.
- Las URL anteriores (`/hefesto/...`, `/dashboard`, etc.) redirigen a las nuevas.

## Base de datos (una sola, compartida con RH)

- La app usa **una sola base**: la de la app de RH (`DB_DATABASE=laravel`). Ahí están también sus
  tablas propias: `usuarios_caseta` (perfiles de acceso), `bitacora_visitas` y `bitacora_intercambios`.
- **El esquema lo administra la app de RH. Esta app no tiene migraciones y nunca debe correr
  `migrate`, `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` ni `db:wipe`**:
  la tabla `migrations` es la de RH.
- No escribe en las tablas de RH (`users`, `info_usuarios`, `sistemas`, `sistema_usuario`); solo las lee.
- Para no chocar con RH en la misma base: `CACHE_PREFIX=caseta-cache-`, `SESSION_COOKIE=caseta_session`,
  `DB_QUEUE=caseta` y `BCRYPT_ROUNDS=12` (los hashes de RH son bcrypt cost 12).
- Las pruebas corren solo con SQLite en memoria; [tests/TestCase.php](tests/TestCase.php) las detiene si la
  configuración apunta a otra base. Su esquema está en [tests/database/migrations](tests/database/migrations).

## Instalación y despliegue

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build        # genera public/build (Tailwind, Alpine, FontAwesome, escáner QR)
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`npm run build` es obligatorio en cada despliegue: las vistas ya no cargan Tailwind ni
librerías desde CDN, sino desde `public/build`.

## Acceso del personal

Las pantallas y operaciones de la aplicación requieren iniciar sesión con **correo + contraseña**.
No hay registro público. La lógica está en [AccesoCaseta](app/Services/AccesoCaseta.php):

- **Empleados de RH**: usan la **misma contraseña de la app de RH** (allá entran con su número de
  empleado). Necesitan correo real en RH y la casilla **Intercambios** marcada en RH; su perfil en
  Caseta se crea solo en su primer login, con rol `caseta`. En Caseta solo se les cambia rol y estado;
  nombre, correo y contraseña se administran desde RH. Los correos de relleno (`@empresa.local`,
  `@example.com`, `notiene…@`) nunca sirven para entrar.
- **Cuentas genéricas** (no son personas, p. ej. `caseta@…`): tienen su propia contraseña y se
  administran completas en Admin → Usuarios. Su correo no puede ser el de un empleado activo de RH.

`php artisan db:seed --class=AdminUserSeeder` crea o actualiza una cuenta **genérica** de
administrador con `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` (se niega si el correo es de un
empleado de RH). No guardes las credenciales en el repositorio.

## Firmas (Cloudflare R2)

Las firmas se guardan como objetos privados en R2 y el historial muestra enlaces temporales
(5 minutos). Configura `AWS_ENDPOINT` y mantén el bucket **sin acceso público**. Si falla el
registro en la base de datos, la firma recién subida se borra.

## Intercambios

- Las tablas (`intercambios`, `cajas`, `tractocamiones`, `puntos_inspeccion`, etc.) están en la base
  de RH y se crean con las migraciones de la app de RH. Aquí solo están los modelos.
  `intercambios.guardia_id` y las bitácoras guardan el id de `usuarios_caseta`, nunca el de `users`.
  Los operadores (usuarios activos de las áreas `OPERADORES` y `PERMISIONARIOS`, configurable con `INTERCAMBIOS_AREAS_OPERADORES`) son de solo lectura.
- Asistente: `/caseta/intercambios/nuevo` (caseta y admin). Funciona sin conexión:
  - catálogos y operadores se descargan a IndexedDB (`hefesto-intercambios`) cuando hay señal;
  - el borrador y sus fotos se guardan en el dispositivo en cada cambio;
  - al completarlo pasa a la cola de envío: el service worker sube cada foto y después el
    intercambio, que recibe su folio (`INT-AAAA-000001`) al llegar al servidor.
- `bitacora_intercambios` (base local) evita duplicados cuando un dispositivo reenvía un intercambio.
- Fotos y firmas: R2 privado, en `intercambios/{uuid del dispositivo}/...`, con enlaces temporales.
- Catálogos: `/admin/catalogos/{catalogo}`. Se activan o desactivan, nunca se borran.
- Configuración: [config/intercambios.php](config/intercambios.php).

## App de caseta (PWA)

- Manifest: [public/manifest.json](public/manifest.json) (inicio en `/caseta`, accesos directos
  a Entrada y Salida).
- Service worker: [public/sw.js](public/sw.js).
  - Archivos de `/build`: caché primero (carga instantánea).
  - Páginas de caseta (menú, entrada, salida, QR): red primero; sin señal muestra la última copia.
  - Páginas de administración y respuestas de datos: nunca se guardan.
  - Al cerrar sesión o llegar a `/login` se borran las páginas guardadas.
- Al modificar `sw.js`, sube la constante `VERSION` para que los celulares se actualicen.
- Requiere HTTPS en producción (los service workers y la cámara no funcionan en HTTP).

## Tests

```bash
php artisan test
```
