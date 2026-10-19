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
  Los usuarios desactivados pierden la sesión en su siguiente petición
  ([EnsureUserIsActive](app/Http/Middleware/EnsureUserIsActive.php)).
- Las URL anteriores (`/hefesto/...`, `/dashboard`, etc.) redirigen a las nuevas.

## Bases de datos

- **Local** (`DB_*`): solo la tabla `users` de esta aplicación (login y roles).
- **Externa** (`DB_EXTERNA_*`, conexión `db_externa`): empresas, visitas y demás tablas
  compartidas. **Esta aplicación no ejecuta migraciones sobre ella**; su esquema se administra
  por separado. En los tests se sustituye por SQLite en memoria
  ([tests/Concerns/UsaBaseExternaDePrueba.php](tests/Concerns/UsaBaseExternaDePrueba.php)).

## Instalación y despliegue

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build        # genera public/build (Tailwind, Alpine, FontAwesome, escáner QR)
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`npm run build` es obligatorio en cada despliegue: las vistas ya no cargan Tailwind ni
librerías desde CDN, sino desde `public/build`.

## Acceso del personal

Las pantallas y operaciones de la aplicación requieren iniciar sesión. No hay registro público.
Antes del primer despliegue, configura `ADMIN_NAME`, `ADMIN_EMAIL` y `ADMIN_PASSWORD` en el
entorno privado; usa una contraseña única y fuerte. El seeder crea o actualiza esa cuenta
administradora. No guardes las credenciales en el repositorio.

Para crear cuentas de prueba locales (un administrador y un usuario de caseta), ejecuta:

```bash
php artisan db:seed --class=DemoUsersSeeder
```

El seeder solo funciona en los entornos `local` y `testing`. Genera una contraseña aleatoria
para cada cuenta nueva y la muestra una sola vez en la consola. No uses estas cuentas en producción.

## Firmas (Cloudflare R2)

Las firmas se guardan como objetos privados en R2 y el historial muestra enlaces temporales
(5 minutos). Configura `AWS_ENDPOINT` y mantén el bucket **sin acceso público**. Si falla el
registro en la base de datos, la firma recién subida se borra.

## Intercambios

- Las tablas (`intercambios`, `cajas`, `tractocamiones`, `puntos_inspeccion`, etc.) están en la base
  de RH (`db_externa`) y se crean con las migraciones de la app de RH. Aquí solo están los modelos.
  Los operadores (usuarios activos de las áreas `OPERADORES` y `PERMISIONARIOS`, configurable con `INTERCAMBIOS_AREAS_OPERADORES`) son de solo lectura.
- Asistente: `/admin/intercambios/nuevo`. Funciona sin conexión:
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
