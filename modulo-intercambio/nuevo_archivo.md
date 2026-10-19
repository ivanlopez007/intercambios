# Módulo de intercambios: contexto técnico para mejorarlo

> **Para quien lea esto (IA o desarrollador):** este archivo describe el módulo de intercambios tal como funciona **hoy** (octubre 2026): las tablas que usa, qué hace cada una, cómo fluye un intercambio, dónde está el código y qué falta mejorar. Léelo completo antes de proponer cambios. Si algo de aquí contradice el código, **el código manda**: verifica antes de cambiar.
>
> El archivo `instrucciones-intercambios.md` de esta misma carpeta es la especificación original. Este archivo refleja lo que se construyó, incluidas las decisiones que cambiaron respecto a esa especificación (sección 3).

---

## 1. Qué es el módulo

Un **intercambio** es el registro del estado de una **caja** (remolque) y su **tractocamión** cuando **entran o salen** del patio de Industrial Hefesto (Saltillo, Coah.). Es evidencia de cómo se entregó y recibió la unidad, e incluye la revisión de seguridad **C-TPAT**.

Lo captura un guardia o administrador desde un **celular o tableta**, caminando alrededor de la unidad, en un **asistente de 10 pasos** que **funciona sin internet**.

- **Stack:** Laravel 13 (PHP 8.3), Blade, Alpine.js 3, Tailwind 4 (Vite), Chart.js, dompdf, MariaDB, Cloudflare R2 (S3). Idioma de la interfaz y del código: **español**.
- **Dónde está:** el asistente vive en **admin** (`/admin/intercambios/nuevo`) y por ahora solo lo usan usuarios con rol `admin`.
- **Pruebas:** PHPUnit, 87 pruebas, todas pasan (`php artisan test`).

---

## 2. Arquitectura

```
Celular (navegador / PWA)                      Servidor Laravel                     Almacenamiento
──────────────────────────                     ────────────────                     ──────────────
Asistente Alpine (intercambio.js)
  · borrador en IndexedDB ───────────────────► POST /admin/intercambios/fotos ───► R2: intercambios/{uuid}/...
  · fotos (Blob) en IndexedDB                  (cada foto, al tomarla si hay señal)
  · al completar → cola (cola-offline.js)
Service worker (public/sw.js)
  · sube fotos pendientes (3 a la vez) ──────► POST /admin/intercambios/fotos
  · envía el intercambio ────────────────────► POST /admin/intercambios ─────────► BD RH (db_externa): intercambio + detalle
                                                                                   BD local: bitacora_intercambios
```

**Dos bases de datos:**

| Conexión Laravel | Base | Qué contiene | Quién la administra |
|---|---|---|---|
| `db_externa` | `industrialhefesto_dev` (base de **RH**) | Tablas del módulo, catálogos y los operadores (usuarios de RH) | La **app de RH**: crea las tablas con sus migraciones |
| `mariadb` (por defecto) | `intercambios` (base **local**) | Usuarios que inician sesión (guardias y admins), bitácoras, sesiones y caché | Esta app (migraciones en `database/migrations`) |

**Archivos:** fotos y firmas en **Cloudflare R2** (disco `r2`, bucket privado). En la base solo se guarda la ruta. Se ven con **enlaces temporales de 5 minutos**.

---

## 3. Reglas que no se deben romper

1. **No crear migraciones para las tablas de RH en esta app** y **nunca ejecutar `migrate` contra `db_externa`**. Si hace falta una columna nueva en una tabla de RH, la migración va en la app de RH.
2. **Los operadores son de solo lectura.** Los modelos `Operador`, `InfoOperador`, `DocumentoOperador` y `TipoLicencia` usan el trait `SoloLectura`, que lanza excepción al guardar o borrar.
3. **No leer `rfc` ni `curp` de `info_usuarios`:** están cifrados con la llave de la app de RH.
4. **El guardia vive en la base local** (`users`). En `intercambios` se guardan `guardia_id` (sin llave foránea) y `guardia_nombre`. **No hay `join` posible entre bases.**
5. **Un intercambio registrado no se modifica.** El servidor lo garantiza: `subirFoto` responde 403 si el UUID ya está registrado.
6. **Los catálogos no se borran, se desactivan** (`activo = 0`), porque los intercambios los referencian.
7. **Las transacciones van sobre `db_externa`:** `DB::connection('db_externa')->transaction(...)`.
8. **El folio se genera en el servidor** con `lockForUpdate` sobre `folios_intercambio`. Nunca en el celular.
9. Toda la interfaz, los mensajes y los comentarios del código van en **español**.

**Decisiones que cambiaron respecto a la especificación original:**
- **Borradores solo en el celular.** El borrador **no** existe en el servidor: vive en IndexedDB y el servidor solo recibe intercambios **completos**. Por eso hoy todos los registros tienen `status = completado`; `borrador` y `cancelado` existen en el modelo pero no se usan.
- **Folio al llegar:** se asigna cuando el intercambio llega al servidor, no en el paso 1. Así los borradores descartados no gastan folios y funciona sin señal.
- **10 pasos en lugar de 9:** se agregó el paso "Fotos de la unidad", con 18 fotos obligatorias.
- **Conexión `db_externa`** en lugar de una nueva `rh`; ya existía y apunta a la misma base.
- **Llantas no obligatorias:** lo vacío se guarda como **4/32 y 100 PSI**.

---

## 4. Tablas

### 4.1 Base de RH (`db_externa`): tablas del módulo (lectura y escritura)

#### `intercambios`: un registro por intercambio
| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `folio` | varchar(20) único | `INT-AAAA-000001`, consecutivo por año |
| `status` | varchar(15) | `borrador` / `completado` / `cancelado`. Hoy siempre `completado` |
| `paso_actual` | tinyint | Hoy siempre `10` |
| `tipo_movimiento` | varchar(10) | `entrada` / `salida` |
| `fecha_hora` | datetime | Momento real del intercambio (lo captura el guardia; no puede ser futuro ni de hace más de 60 días) |
| `operador_id` | bigint FK → `users` (RH) | Obligatorio |
| `guardia_id`, `guardia_nombre` | bigint (sin FK), varchar | Usuario **local** que capturó. Si se capturó sin conexión, viene de `registrado_por` (id cifrado) |
| `caja_id` | FK → `cajas` | |
| `tractocamion_id` | FK → `tractocamiones` | |
| `odometro` | int | Opcional |
| `intercambio_relacionado_id` | FK → `intercambios` (self) | Último intercambio completado de la **misma caja** con el **movimiento contrario**. Se calcula al registrar |
| `estado_carga` | varchar(10) | `cargada` / `vacia` |
| `tipo_intercambio` | varchar(10) | `impo` / `expo` / `local` |
| `cliente_id` | FK → `clientes` | Cliente del viaje. Por defecto, el dueño de la caja |
| `origen_id`, `destino_id` | FK → `destinos` | |
| `referencia_carta_porte` | varchar(100) | |
| `numero_sello` | varchar(50) | Solo si va `cargada` |
| `comentarios_caja`, `comentarios_tracto`, `observaciones` | text | Descripción de daños (obligatoria si se marcó "tiene daños") y observaciones |
| `firma_operador_path`, `firma_guardia_path` | varchar | Ruta en R2 del PNG |
| `operador_nombre`, `operador_numero_empleado`, `operador_licencia`, `operador_licencia_vence`, `operador_apto_medico_vence` | | **Copia al completar** (no cambia aunque RH cambie después) |
| `caja_numero_economico`, `caja_placa`, `caja_cliente`, `tracto_numero_economico`, `tracto_placa`, `tracto_linea` | | **Copia al completar** |
| `completado_at`, `cancelado_at` | timestamp | |

Índices: `folio` único; `(caja_id, status, fecha_hora)`, `(tractocamion_id, status, fecha_hora)`, `(status, fecha_hora)`, `guardia_id`. **Falta un índice solo por `fecha_hora`** (ver sección 10).

#### `llantas_intercambio`: 18 filas por intercambio
`id, intercambio_id (FK cascade), posicion (1-18), marca, profundidad_32 (0-32), psi (0-150), tipo (original|renovada), observaciones`. Único `(intercambio_id, posicion)`.
- **Posiciones:** 1-2 direccionales del tracto; 3-10 tracción del tracto (3,4 izq. del eje delantero; 5,6 der.; 7,8 izq. del trasero; 9,10 der.); 11-18 caja (eje delantero 11 izq. ext., 12 izq. int., 13 der. int., 14 der. ext.; eje trasero 15-18 igual).
- **Color:** direccionales con menos de 4/32 en rojo, 4-6 amarillo y más de 6 verde; resto con menos de 2 rojo, 2-4 amarillo y más de 4 verde (FMCSA 49 CFR 393.75). Lo calcula `LlantaIntercambio::color()` con `config/intercambios.php`.
- **Contrato de intercambio:** usa otra nomenclatura para las 8 de la caja: LFO(1)=11, LFI(2)=12, RFI(3)=13, RFO(4)=14, LRO(5)=15, LRI(6)=16, RRI(7)=17, RRO(8)=18 (`PdfIntercambio::LLANTAS_CONTRATO`).

#### `revision_puntos`: una fila por **cada** punto activo revisado (no solo los que fallan)
`id, intercambio_id (FK cascade), punto_inspeccion_id (FK), resultado, observaciones`. Único `(intercambio_id, punto_inspeccion_id)`.
- **`resultado` según `puntos_inspeccion.tipo_respuesta`:**
  - `ok_falla` → `cumple` | `no_cumple` | `no_aplica`
  - `deteccion` → `no_detectado` | `detectado`
  - `dano` → `sin_dano` | `golpe` | `corte` | `agujero` (letras G, C, A)
- **Observaciones obligatorias** si es `no_cumple` o `detectado` (`RevisionPunto::CON_HALLAZGO`).

#### `fotos_intercambio`: ruta de cada foto
`id, intercambio_id (FK cascade), tipo, revision_punto_id (FK, solo tipo punto), llanta_intercambio_id (FK, solo tipo llanta), unidad (caja|tracto, para problema y recorrido), path`.
- **`tipo`:**
  - `sello`: 1 a 2, obligatorias si va cargada;
  - `punto`: evidencia de un hallazgo;
  - `llanta`: opcional;
  - `problema`: fotos de daños de caja o tracto;
  - `recorrido`: las 18 fotos obligatorias de la unidad.
- **Ruta en R2:** `intercambios/{uuid_del_dispositivo}/{tipo}/{uuid_foto}.jpg`. Para `recorrido`, la **vista va como subcarpeta**: `intercambios/{uuid}/recorrido/{clave_vista}/{uuid_foto}.jpg`. `FotoIntercambio::getVistaAttribute()` la extrae de la ruta (no hay columna `vista`).
- **Firmas:** `intercambios/{uuid}/firmas/operador.png` y `guardia.png`.

#### `folios_intercambio`: contador por año
`anio (PK), ultimo`. `RegistrarIntercambio::siguienteFolio()` hace `insertOrIgnore` + `lockForUpdate` + `update`.

### 4.2 Base de RH: catálogos (lectura y escritura; se desactivan, no se borran)

| Tabla | Columnas | Notas |
|---|---|---|
| `clientes` | `id, nombre, rfc, activo` | Dueños de cajas y clientes de viaje. Hoy: 1 ("Industrial Hefesto") |
| `tipos_caja` | `id, nombre (único), activo` | Caja seca, Caja refrigerada, Plataforma y Cortina |
| `cajas` | `id, cliente_id (FK), tipo_caja_id (FK null), numero_economico (30), placa, estado_placa, marca, modelo, vin (17), anio, tamano_pies, activo` | **Único `(cliente_id, numero_economico)`**: el número se repite entre dueños distintos. Hoy: 126 |
| `lineas_transportistas` | `id, nombre, rfc, activo` | Para tractos de permisionario. Hoy: 0 |
| `tractocamiones` | `id, numero_economico, placa, estado_placa, marca, modelo, vin, anio, es_permisionario, linea_transportista_id (FK, solo si permisionario), activo` | Hoy: 60, todos propios |
| `destinos` | `id, nombre, activo` | Sirven como origen y destino. Hoy: 20 |
| `puntos_inspeccion` | `id, seccion, nombre, parent_id (FK self), tipo_respuesta, orden, activo` | Ver abajo |

**`puntos_inspeccion` por sección (55 activos):**
| `seccion` | `tipo_respuesta` | Cuántos | Dónde se muestra |
|---|---|---|---|
| `ctpat` | `ok_falla` | 22: 18 puntos y 4 subpuntos de "Puertas interior/exterior" (Bisagras, Manijas, Cerrojos, Remaches) | Paso 7 |
| `olores` | `deteccion` | 5 | Paso 7 |
| `residuos` | `deteccion` | 9 | Paso 7 |
| `remolque` | `ok_falla` | 13: luces, marcadores, reflectores, loderas, manivela, birlos, conector 7 pines, cableado, topes, fuga de aire, manguera, conexiones, tubería | Paso 7, para el contrato |
| `danos_caja` | `dano` | 6 lados: Lado izquierdo (LS), Frente (F), Techo (T), Piso (FL), Lado derecho (RS), Trasera (R) | Paso 8, si la caja tiene daños |

Las secciones `remolque` y `danos_caja` las agregó esta app con `PuntosInspeccionContratoSeeder`. Las demás vienen de RH. Constantes en `PuntoInspeccion::SECCIONES` y `PuntoInspeccion::PUNTOS_CONTRATO`.

### 4.3 Base de RH: operadores (**solo lectura**)

| Tabla | Qué se lee |
|---|---|
| `users` | `id, numero_empleado, area_id, deleted_at`. Operador = activo (`deleted_at IS NULL`) y de un área de operadores |
| `areas` | `area`. Las áreas de operadores están en `config('intercambios.areas_operadores')`: hoy `OPERADORES` y `PERMISIONARIOS` (más `OPERADOR` por compatibilidad). **Hoy: 109 operadores** |
| `info_usuarios` | `usuario_id, nombre, apellido_p, apellido_m`. **No** `rfc`/`curp` |
| `documento_operadors` | `operador_id, numero_licencia, tipo_licencia_id, fecha_vencimiento_licencia, fecha_vencimiento_apto` (se usa el más reciente: `latestOfMany`) |
| `tipo_licencias` | `tipo_licencia` |

### 4.4 Base local (`intercambios`)

| Tabla | Para qué |
|---|---|
| `users` | Login: `name, email, password, rol (caseta|admin), activo`. El guardia del intercambio sale de aquí |
| `bitacora_intercambios` | `id_cliente (uuid único), intercambio_id, user_id, usuario_nombre`. **Idempotencia:** si el celular reenvía el mismo UUID, se devuelve el folio ya asignado sin duplicar |
| `sessions`, `cache`, `cache_locks` | Sesiones y el candado `intercambio:{uuid}`, que evita registrar dos veces en paralelo |

---

## 5. Flujo de un intercambio

### 5.1 En el celular (`resources/js/intercambio.js`, vista `admin/intercambio/nuevo.blade.php`)
1. **Al abrir con señal**, se descarga `GET /admin/intercambios/datos`: operadores, catálogos, puntos, historial de cada caja (último intercambio por movimiento), llantas del último intercambio de cada caja y tracto, y configuración. Se guarda en IndexedDB `hefesto-intercambios` → store `datos`.
2. **El borrador** se guarda en IndexedDB (store `borradores`) en cada cambio, con las fotos como Blob.
3. **Pasos:**
   1. Operador, movimiento y fecha.
   2. Caja: buscar o dar de alta, con dueño nuevo si hace falta.
   3. Tracto: buscar o dar de alta, con línea si es permisionario.
   4. Viaje: carga, tipo, cliente, origen, destino y carta porte.
   5. Sello: se salta si va vacía.
   6. Llantas: diagrama SVG, todo opcional.
   7. Revisión: C-TPAT, olores, residuos y remolque.
   8. Comentarios y daños: ¿tiene daños? Sí/No; si sí, lados G/C/A, descripción y fotos.
   9. Fotos de la unidad: 18 obligatorias, **en orden**; las siguientes quedan bloqueadas hasta tomar la anterior.
   10. Resumen y firmas en canvas.
4. **Subida anticipada:** con señal, cada foto se sube en cuanto se toma (3 a la vez).
5. **Al completar**, el intercambio pasa a la cola compartida con caseta (IndexedDB `hefesto-caseta` → store `registros`, `tipo = 'intercambio'`, con `archivos[]`).

### 5.2 Service worker (`public/sw.js`, versión `v12`)
- Guarda la página `/admin/intercambios/nuevo` para abrirla sin señal.
- **Al procesar la cola**, para cada intercambio: sube las fotos que falten (3 a la vez) a `POST /admin/intercambios/fotos` y después envía `POST /admin/intercambios` con el JSON. El token CSRF lo pide a `GET /caseta/csrf`.
- **Respuestas:**
  - 2xx → borra el registro de la cola y avisa el folio a la página;
  - 409 → faltan fotos: las marca para resubir y reintenta;
  - 401/419 → sesión vencida;
  - 403/404/422 → queda con error, y el guardia puede **Corregir**, **Reintentar** o **Descartar**;
  - otros → reintenta después.
- **Al descartar** un intercambio rechazado, se encola `descartar-intercambio` → `POST /admin/intercambios/descartar`, que borra sus fotos de R2.

### 5.3 En el servidor
- `IntercambioController@subirFoto`: valida el tipo real (jpg, png o webp) y el tamaño (máx. 5 MB) y guarda en R2. Responde 403 si el UUID ya está registrado.
- `IntercambioController@store` → `GuardarIntercambioRequest` (valida **todas** las reglas de los pasos; las llantas vacías se completan con 4/32 y 100 PSI en `prepareForValidation`) → `RegistrarIntercambio::ejecutar()`. Dentro de una **transacción** sobre `db_externa`:
  - folio;
  - crea los catálogos nuevos (`firstOrCreate` por nombre o número para no duplicar si dos celulares los dieron de alta sin señal);
  - intercambio relacionado;
  - copia de datos;
  - llantas, revisión, fotos y firmas.
  - Después borra de R2 las fotos subidas que ya no se usan y anota la `bitacora_intercambios`.

---

## 6. Rutas (todas con middleware `auth` + `rol:admin`, prefijo `/admin`)

| Método y ruta | Acción |
|---|---|
| `GET /` | `DashboardController` (cifras, gráficas, documentos por vencer y últimos intercambios) |
| `GET /intercambios/nuevo` | Asistente |
| `GET /intercambios/datos` | Paquete para trabajar sin conexión (JSON) |
| `POST /intercambios/fotos` | Subir una foto |
| `POST /intercambios` | Registrar un intercambio completo |
| `POST /intercambios/descartar` | Borrar las fotos de un intercambio descartado no registrado |
| `GET /intercambios/historial` | Listado con filtros |
| `GET /intercambios/{id}` | Detalle de solo lectura |
| `GET /intercambios/{id}/fotos/{foto}` | Redirección a un enlace temporal de R2 |
| `GET /intercambios/{id}/firmas/{operador|guardia}` | Ídem |
| `GET /intercambios/{id}/pdf/{contrato|ctpat}` | PDF (dompdf) |
| `GET /intercambios/patio` | `PatioController`: cajas cuyo último intercambio es una entrada |
| `GET|POST /catalogos/{catalogo}`, `PUT /catalogos/{catalogo}/{id}`, `PATCH /catalogos/{catalogo}/{id}/estado` | `CatalogoIntercambioController`, genérico (definición en `app/Support/CatalogosIntercambio.php`) |

---

## 7. Mapa del código

| Archivo | Qué hace |
|---|---|
| `config/intercambios.php` | **Toda la configuración:** empresa (PDF), áreas de operadores, umbrales de llantas, valores por defecto, máximo de fotos de sello, las **18 vistas del recorrido**, compresión de fotos, días de fotos huérfanas, **años de conservación (5)** y horas de alerta en patio (72) |
| `app/Models/*` | Un modelo por tabla. Constantes de valores en `Intercambio`, `RevisionPunto`, `FotoIntercambio`, `PuntoInspeccion` y `LlantaIntercambio` |
| `app/Models/Concerns/SoloLectura.php` | Impide escribir en los modelos de RH |
| `app/Http/Requests/GuardarIntercambioRequest.php` | Validación completa del intercambio |
| `app/Services/RegistrarIntercambio.php` | Registro transaccional, folio, catálogos nuevos, fotos y firmas |
| `app/Services/EstadoPatio.php` | Cálculo del patio (lo usan el patio y el dashboard) |
| `app/Services/PdfIntercambio.php` | Contrato de intercambio de remolque e inspección C-TPAT |
| `app/Http/Controllers/IntercambioController.php` | Asistente, paquete, fotos, registro, historial, detalle y PDF |
| `app/Http/Controllers/{Patio,Dashboard,CatalogoIntercambio}Controller.php` | Patio, dashboard y catálogos |
| `resources/js/intercambio.js` | Lógica del asistente (Alpine) |
| `resources/js/intercambio-almacen.js` | IndexedDB del asistente |
| `resources/js/cola-offline.js` | Cola compartida con caseta |
| `public/sw.js` | Service worker. **Al cambiarlo, subir `VERSION`** |
| `resources/views/admin/intercambio/*` | Vistas: `nuevo`, `historial`, `detalle`, `patio`; parciales de diagrama de llantas, checklist, punto, fotos y estado de subida |
| `resources/views/pdf/*` | Plantillas de los PDF |
| `app/Console/Commands/*` | `intercambios:limpiar-fotos` (huérfanas, diario 3:30), `intercambios:depurar-fotos` (más de 5 años, diario 4:00) y `intercambios:borrar-demo` |
| `database/seeders/*` | `PuntosInspeccionContratoSeeder`, `CargaInicialUnidadesSeeder` (cajas y tractos desde `data/`), `DemoIntercambiosSeeder` y `MasDemoIntercambiosSeeder` (**no usar en la base real**) |
| `tests/Feature/IntercambiosTest.php` | Pruebas del módulo. `tests/Concerns/UsaBaseExternaDePrueba.php` crea las tablas de RH en SQLite en memoria |

---

## 8. Cómo trabajar en este proyecto

- **Antes de terminar un cambio:** `php artisan test` (deben pasar las 87 pruebas), `./vendor/bin/pint` y `npm run build`.
- **En las pruebas**, `db_externa` es SQLite en memoria y R2 es `Storage::fake('r2')`. Al agregar una columna en RH, actualizar también `tests/Concerns/UsaBaseExternaDePrueba.php`.
- **R2 sin Cloudflare:** con `R2_DRIVER=local` el disco `r2` usa `storage/app/r2-local` (solo desarrollo).
- **Tailwind solo detecta clases escritas completas** en las vistas (`@source '../views'`): no armar nombres de clase con variables ni ponerlas solo en archivos JS.
- **Blade:** no mezclar `@php(...)` de una línea **antes** de un bloque `@php ... @endphp` en la misma vista (el compilador los confunde).
- **Al cambiar `public/sw.js`, subir `VERSION`** para que los celulares se actualicen.

---

## 9. Estado de los datos hoy (base `industrialhefesto_dev`)

- 1 intercambio real registrado. Los de prueba se borraron el 14/10/2026 (respaldo en `storage/app/respaldos/`).
- 1 cliente (Industrial Hefesto), 126 cajas, 60 tractocamiones, 20 destinos, 0 líneas transportistas, 55 puntos de inspección y 109 operadores.
- **Errores conocidos en las cajas** (se cargaron tal como venían):
  - **VIN que no cuadra con su dígito verificador:** IHM5330 (mismo VIN que IHM5329), IHM5334, IHM5338, IH5366, IH5376 (16 caracteres) e IH5303.
  - **IH5318112** sin VIN.
  - **Placas probablemente erróneas:** IH5318111 y 75995.
  - **IH5317:** año 2006 contra 2007 según el VIN.
- **Tracto IH99:** placa probable `34BH9U`.
- **Operadores:** 34 con licencia vencida y ninguno con fecha de apto médico.

---

## 10. Mejoras pendientes (priorizadas)

**Seguridad (de la revisión del 13/10/2026):**
1. Producción: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` y **usuario de base de datos dedicado** en lugar de `root`:
   - `SELECT` en las 5 tablas de operadores;
   - `SELECT, INSERT, UPDATE, DELETE` en las 12 del módulo y catálogos.
2. Middleware de encabezados de seguridad: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` y HSTS.
3. `composer update` de `laravel/framework`, `league/commonmark` y `league/flysystem` (avisos de seguridad); `npm audit fix` (`shell-quote`, solo de desarrollo).
4. `registrado_por` (id cifrado del guardia para registros sin conexión) **no caduca** y acepta usuarios desactivados: agregar fecha y validarla.
5. Límite de peticiones para `POST /intercambios/fotos` y `POST /intercambios`.
6. "Recordarme" dura 5 años: bajarlo a unos 30 días.
7. El bucket de R2 `gateops-storage` parece compartido con otra app: usar un bucket propio o una clave limitada a `intercambios/`.

**Rendimiento (volumen esperado: 1,800 intercambios al mes):**
8. `intercambios:limpiar-fotos` recorre todas las carpetas de R2 y hace una consulta por carpeta: consultar `bitacora_intercambios` por lotes.
9. Caché de unos minutos para `GET /intercambios/datos` (hoy recalcula el historial de todas las cajas en cada carga).
10. Pedir a la app de RH un índice en `intercambios.fecha_hora`.
11. Compresión por tipo de foto: sello a 1600 px y calidad 0.8; recorrido a 1280 px y 0.6 (hoy todas a 1600 px y 0.7, unos 6 a 10 MB por intercambio).

**Funcionalidad:**
12. Ubicación de la caja en el patio (zona o cajón) y ocupación: requiere una columna o tabla nueva en RH.
13. Columna `vista` en `fotos_intercambio` en lugar de guardarla en la ruta: requiere una migración en RH.
14. Hora pico en el dashboard.
15. Que el rol `caseta` pueda usar el asistente (hoy solo `admin`). Hay una ruta vieja `/caseta/intercambios/entrada` con un formulario de prueba que se puede retirar.
16. `navigator.storage.persist()` para que iOS no borre borradores sin enviar, y no duplicar los Blob entre el borrador y la cola (hoy ocupan el doble mientras esperan señal).
17. Corregir en el catálogo los VIN y placas con error de la sección 9.
