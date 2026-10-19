# Módulo de intercambios: instrucciones de construcción

Este documento describe el módulo de intercambios que se construirá en la **app de intercambios**. Está escrito para que Claude (o cualquier desarrollador) lo construya sin tener acceso a la app de RH.

## 0. Antes de escribir código

1. **Revisa la app de intercambios completa**: stack, layout, componentes, cómo inician sesión los guardias (su tabla de usuarios), convenciones de nombres y carpetas.
2. **El módulo de entradas y salidas ya existe y es otro módulo.** No lo modifiques. Si comparte algo útil (layout, captura de fotos, firmas), reutilízalo.
3. **Usa el stack que ya tenga la app.** No agregues frameworks nuevos solo para este módulo.
4. **Construye por fases**, en el orden de la sección 10, y confirma cada fase con el usuario antes de pasar a la siguiente.
5. Toda la interfaz va en español.

## 1. Qué es un intercambio

Un intercambio es el registro del estado de una **caja** (remolque) y su **tractocamión** en el momento en que entran o salen del patio. Es la evidencia de en qué condiciones se entregó y se recibió la unidad, e incluye la revisión de seguridad **C-TPAT**.

Lo captura el **guardia de caseta** desde un celular o tableta, caminando alrededor de la unidad. Por eso es un **asistente por pasos** que guarda en cada paso y se puede retomar donde se quedó.

## 2. Arquitectura: dos bases de datos

| Base de datos | Qué contiene | Quién la administra |
|---|---|---|
| **Intercambios** (conexión por defecto de esta app) | Usuarios **guardias** de caseta y el módulo de entradas y salidas | Esta app |
| **RH** (conexión `rh`) | Los **operadores** (usuarios del área `OPERADOR`) y **todas las tablas del módulo de intercambios** | La app de RH |

**Reglas que no se pueden romper:**

- **Las tablas de intercambios ya existen en la base de RH**: se crean con migraciones de la app de RH. **No crees migraciones para ellas en esta app** y **nunca ejecutes `migrate` contra la conexión `rh`**.
- Todos los modelos de intercambios declaran `protected $connection = 'rh';`.
- **Los operadores son de solo lectura.** Esta app nunca crea, edita ni borra usuarios de RH.
- **El guardia vive en la base de intercambios.** En `intercambios` se guardan `guardia_id` (sin llave foránea, porque está en otra base) y `guardia_nombre`. No se pueden hacer `join` ni `whereHas` entre guardias y tablas de RH: son conexiones distintas.
- Las transacciones van sobre la conexión de RH: `DB::connection('rh')->transaction(...)`.

### Configuración de la conexión

En `config/database.php`, dentro de `connections`:

```php
'rh' => [
    'driver'    => 'mariadb',
    'host'      => env('RH_DB_HOST', '127.0.0.1'),
    'port'      => env('RH_DB_PORT', '3306'),
    'database'  => env('RH_DB_DATABASE', 'industrialhefesto'),
    'username'  => env('RH_DB_USERNAME'),
    'password'  => env('RH_DB_PASSWORD'),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'strict'    => true,
],
```

En `.env`: `RH_DB_HOST`, `RH_DB_PORT`, `RH_DB_DATABASE`, `RH_DB_USERNAME`, `RH_DB_PASSWORD`.

**Usuario de base de datos recomendado** (lo crea el administrador del servidor). No uses el usuario de la app de RH. Crea uno dedicado con:
- `SELECT` sobre `users`, `areas`, `info_usuarios`, `documento_operadors` y `tipo_licencias`;
- `SELECT, INSERT, UPDATE, DELETE` sobre las tablas del módulo (sección 3).

Así, un error en esta app no puede tocar expedientes, vacaciones ni nada más de RH.

## 3. Tablas del módulo (ya creadas en la base de RH)

Todas tienen `id`, `created_at` y `updated_at`. Los valores de tipo "enum" se guardan como texto.

### Catálogos

Los catálogos tienen `activo` (booleano). **Se desactivan, nunca se borran**, porque los intercambios completados los referencian.

**`clientes`**: dueños de las cajas.
- `nombre`, `rfc` (nullable), `activo`.

**`tipos_caja`**: precargado con Caja seca, Caja refrigerada, Plataforma y Cortina.
- `nombre` (único), `activo`.

**`cajas`**: remolques.
- `cliente_id` → `clientes`: el **dueño** de la caja (obligatorio).
- `tipo_caja_id` → `tipos_caja` (nullable).
- `numero_economico` (máx. 30).
- `placa`, `estado_placa` (estado que emitió la placa), `marca`, `modelo`, `vin` (17), `anio`, `tamano_pies` (todos nullable).
- `activo`.
- **Único por `(cliente_id, numero_economico)`**: el número económico lo asigna cada dueño y puede repetirse entre clientes distintos.

**`lineas_transportistas`**: líneas de los tractos de permisionarios.
- `nombre`, `rfc` (nullable), `activo`.

**`tractocamiones`**:
- `numero_economico`, `placa`, `estado_placa`, `marca`, `modelo`, `vin`, `anio`.
- `es_permisionario` (booleano).
- `linea_transportista_id` → `lineas_transportistas`: nullable, **solo si es permisionario**.
- `activo`.

**`destinos`**: ubicaciones; se usan tanto para el origen como para el destino.
- `nombre`, `activo`.

**`puntos_inspeccion`**: catálogo de la revisión C-TPAT, ya precargado.
- `seccion`: `ctpat` (18 puntos más 4 subpuntos), `olores` (5) o `residuos` (9).
- `nombre`.
- `parent_id` → `puntos_inspeccion`: los subpuntos. "Puertas interior/exterior" tiene Bisagras, Manijas, Cerrojos y Remaches.
- `tipo_respuesta`: `ok_falla` (sección ctpat) o `deteccion` (olores y residuos).
- `orden`, `activo`.

**`folios_intercambio`**: consecutivo por año.
- `anio` (llave primaria), `ultimo`.

### `intercambios`

| Columna | Paso | Notas |
|---|---|---|
| `folio` | 1 | Único. `INT-AAAA-000001` |
| `status` | — | `borrador` → `completado` / `cancelado` |
| `paso_actual` | — | 1 a 9: dónde retomar |
| `tipo_movimiento` | 1 | `entrada` / `salida` |
| `fecha_hora` | 1 | Momento real del intercambio |
| `operador_id` | 1 | → `users` (RH). Obligatorio |
| `guardia_id`, `guardia_nombre` | 1 | Guardia autenticado de esta app. Sin llave foránea |
| `caja_id` | 2 | → `cajas` |
| `intercambio_relacionado_id` | 2 | → `intercambios`. Último completado de la misma caja con el movimiento contrario |
| `tractocamion_id`, `odometro` | 3 | → `tractocamiones` |
| `estado_carga` | 4 | `cargada` / `vacia` |
| `tipo_intercambio` | 4 | `impo` / `expo` / `local` |
| `cliente_id` | 4 | → `clientes`. Por defecto, el dueño de la caja |
| `origen_id`, `destino_id` | 4 | → `destinos` |
| `referencia_carta_porte` | 4 | |
| `numero_sello` | 5 | Solo si la caja va cargada |
| `comentarios_caja`, `comentarios_tracto`, `observaciones` | 8 | |
| `firma_operador_path`, `firma_guardia_path` | 9 | Ruta del PNG |
| `operador_nombre`, `operador_numero_empleado`, `operador_licencia`, `operador_licencia_vence`, `operador_apto_medico_vence` | 9 | **Copia al completar** |
| `caja_numero_economico`, `caja_placa`, `caja_cliente`, `tracto_numero_economico`, `tracto_placa`, `tracto_linea` | 9 | **Copia al completar** |
| `completado_at`, `cancelado_at` | — | |

Todo lo que se captura después del paso 1 es nullable en la base de datos. **Lo obligatorio se valida en la app**, al guardar cada paso y otra vez al completar.

### Detalle del intercambio

**`llantas_intercambio`**: una fila por llanta.
- `intercambio_id`.
- `posicion` (1 a 18).
- `marca`, `profundidad_32` (entero, en 32avos de pulgada), `psi`.
- `tipo`: `original` / `renovada`.
- `observaciones`.
- Única por `(intercambio_id, posicion)`.

**`revision_puntos`**: **una fila por cada punto revisado**, no solo los que fallan, porque es la evidencia de que se revisó.
- `intercambio_id`, `punto_inspeccion_id`.
- `resultado`:
  - para `ok_falla`: `cumple`, `no_cumple` o `no_aplica`;
  - para `deteccion`: `no_detectado` o `detectado`.
- `observaciones`.
- Única por `(intercambio_id, punto_inspeccion_id)`.

**`fotos_intercambio`**:
- `intercambio_id`.
- `tipo`: `sello`, `punto`, `llanta` o `problema`.
- `revision_punto_id`: solo cuando `tipo = punto`.
- `llanta_intercambio_id`: solo cuando `tipo = llanta`.
- `unidad`: `caja` o `tracto`, solo cuando `tipo = problema`.
- `path`: solo la ruta, nunca el archivo.

## 4. Operadores (base de RH, solo lectura)

Un operador es un usuario de RH **activo** (`users.deleted_at IS NULL`) cuya área se llama `OPERADOR` (`areas.area`). Pon el nombre del área en config (`config('intercambios.area_operadores')`, valor `OPERADOR`), no escrito en el código.

| Dato | Dónde está |
|---|---|
| Id | `users.id` |
| Número de empleado | `users.numero_empleado` |
| Nombre | `info_usuarios.nombre`, `apellido_p`, `apellido_m` (`info_usuarios.usuario_id = users.id`) |
| Licencia | `documento_operadors.numero_licencia` (`documento_operadors.operador_id = users.id`) |
| Tipo de licencia | `documento_operadors.tipo_licencia_id` → `tipo_licencias.tipo_licencia` |
| Vencimiento de la licencia | `documento_operadors.fecha_vencimiento_licencia` |
| Vencimiento del apto médico | `documento_operadors.fecha_vencimiento_apto` |

- **No leas `rfc` ni `curp` de `info_usuarios`**: están cifrados con la llave de la app de RH y aquí no se pueden descifrar.
- Puede haber operadores sin fila en `documento_operadors`: muéstralo como "sin licencia registrada".

Modelos sugeridos, todos de solo lectura y con `$connection = 'rh'`:
- `Operador`: tabla `users`, con `SoftDeletes` y un scope global que filtre el área de operadores.
- `InfoOperador`: tabla `info_usuarios`.
- `DocumentoOperador`: tabla `documento_operadors`.

## 5. Modelos del módulo

Todos con `protected $connection = 'rh';` y su `$table` explícito.

| Modelo | Tabla | Relaciones principales |
|---|---|---|
| `Cliente` | `clientes` | hasMany `Caja` |
| `TipoCaja` | `tipos_caja` | hasMany `Caja` |
| `Caja` | `cajas` | belongsTo `Cliente`, `TipoCaja` |
| `LineaTransportista` | `lineas_transportistas` | hasMany `Tractocamion` |
| `Tractocamion` | `tractocamiones` | belongsTo `LineaTransportista` |
| `Destino` | `destinos` | |
| `PuntoInspeccion` | `puntos_inspeccion` | belongsTo `parent`, hasMany `subpuntos` |
| `Intercambio` | `intercambios` | belongsTo `Operador`, `Caja`, `Tractocamion`, `Cliente`, `origen`/`destino` (`Destino`), `relacionado` (`Intercambio`); hasMany `LlantaIntercambio`, `RevisionPunto`, `FotoIntercambio` |
| `LlantaIntercambio` | `llantas_intercambio` | belongsTo `Intercambio`; hasMany `FotoIntercambio` |
| `RevisionPunto` | `revision_puntos` | belongsTo `Intercambio`, `PuntoInspeccion`; hasMany `FotoIntercambio` |
| `FotoIntercambio` | `fotos_intercambio` | belongsTo `Intercambio` |

- Pon los valores de `status`, `tipo_movimiento`, `estado_carga`, `tipo_intercambio`, `resultado` y `tipo` de foto en constantes o enums de PHP, no como textos sueltos.
- El guardia no es una relación Eloquent normal (otra conexión). Si hace falta, resuélvelo con un accesor que lo busque en la conexión por defecto.

## 6. El asistente: 9 pasos

Encabezado fijo en todos los pasos:
- folio, tipo de movimiento, caja y tracto (cuando ya estén elegidos);
- indicador de pasos;
- botones "Anterior" y "Guardar y continuar".

Cada paso guarda al continuar y actualiza `paso_actual`. Al abrir un borrador se va a su `paso_actual`, pero los pasos anteriores se pueden revisar y corregir.

### Paso 1: Operador

- **Tipo de movimiento**: entrada o salida. Obligatorio.
- **Fecha y hora**: por defecto la actual, editable. No puede ser futura.
- **Operador**: búsqueda por nombre o número de empleado, solo entre los operadores activos (sección 4). Al elegirlo se muestra en solo lectura:
  - nombre, número de empleado, número y tipo de licencia;
  - vencimiento de la licencia y del apto médico, con indicador:
    - **rojo** si ya venció;
    - **amarillo** si vence en 30 días o menos (configurable);
    - **verde** si está vigente.
- **Documentos vencidos:** se muestra una advertencia clara, pero **no bloquea** el intercambio. Queda en la copia de datos al completar.

Al continuar, en una sola transacción sobre `rh`:
1. Se genera el folio (sección 7.3).
2. Se crea el intercambio con `status = borrador`, `paso_actual = 2`, `guardia_id` y `guardia_nombre` del guardia autenticado.

### Paso 2: Caja

- **Búsqueda por número económico.** Como el número puede repetirse entre dueños, los resultados muestran número y dueño.
- **Si la caja existe:** se llenan solos y en solo lectura dueño, tipo, placa, estado de la placa, marca, modelo, VIN, año y tamaño. Un botón "Corregir datos" permite editarlos.
- **Si no existe:** botón **"Registrar caja nueva"** con un formulario dentro del mismo paso:
  - **Dueño (cliente)**: selección con búsqueda y alta rápida de cliente (solo nombre y RFC opcional) sin salir del asistente.
  - Número económico, tipo de caja, placa, estado de la placa, marca, modelo, VIN, año y tamaño en pies.
  - **Obligatorios**: dueño, número económico y placa.
  - Validar que no exista ya ese número económico para ese dueño.

Al continuar:
- **Intercambio relacionado:** se busca el último intercambio **completado** de la misma caja con el **movimiento contrario** y se guarda en `intercambio_relacionado_id`.
- **Advertencias, sin bloquear:**
  - si el último movimiento completado de la caja es del **mismo** tipo (por ejemplo, dos entradas seguidas);
  - si la caja tiene **otro borrador abierto**.

### Paso 3: Tractocamión

Mismo comportamiento que el paso 2:
- **Búsqueda** por número económico.
- **Si existe**, se llenan solos placa, estado de la placa, marca, modelo, VIN, año, si es permisionario y su línea.
- **Si no existe**, botón "Registrar tractocamión nuevo":
  - Al marcar **permisionario**, la **línea transportista** se vuelve obligatoria, con selección y alta rápida de línea.
  - **Obligatorios**: número económico y placa.
- **Odómetro**: entero, opcional.

### Paso 4: Datos del viaje

- **Estado de carga**: cargada o vacía. Obligatorio.
- **Tipo de intercambio**: impo, expo o local. Obligatorio.
- **Cliente del viaje**: precargado con el dueño de la caja y editable, porque una caja de un cliente puede llevar mercancía de otro.
- **Origen y destino**: selección con búsqueda sobre `destinos`, con alta rápida.
- **Referencia o carta porte**: texto.

### Paso 5: Fotos del sello (solo si la caja va cargada)

- **Si `estado_carga = vacia`**, este paso se salta: el asistente pasa del 4 al 6.
  - Si antes estaba cargada y se cambió a vacía, se limpia `numero_sello` y se borran sus fotos de sello.
- **Si va cargada:**
  - **Número de sello**: obligatorio.
  - **Fotos del sello**: mínimo 1 y máximo 2 (`tipo = sello`). Deben verse claramente el código del sello y que está colocado.

### Paso 6: Llantas

- **Diagrama de la unidad con 18 posiciones:**
  - 1 y 2: direccionales del tracto;
  - 3 a 10: tracción del tracto;
  - 11 a 18: caja.
- **Al tocar una posición** se capturan:
  - **marca**;
  - **profundidad** en 32avos de pulgada (entero de 0 a 32);
  - **PSI** (entero de 0 a 150);
  - **tipo**: original o renovada;
  - observaciones y foto opcionales (`tipo = llanta`).
- **Precarga:** marca y tipo se traen del último intercambio completado del **mismo tracto** (posiciones 1 a 10) y de la **misma caja** (posiciones 11 a 18). La profundidad y el PSI siempre se miden de nuevo.
- **Color de cada llanta según la profundidad**, con umbrales en `config/intercambios.php`:

  | Posición | Rojo (bajo el mínimo legal) | Amarillo | Verde |
  |---|---|---|---|
  | 1–2 direccionales | menos de 4/32 | 4/32 a 6/32 | más de 6/32 |
  | 3–18 | menos de 2/32 | 2/32 a 4/32 | más de 4/32 |

  Los mínimos son los de FMCSA 49 CFR 393.75 (las unidades cruzan a EE. UU.). C-TPAT no fija una profundidad; revisa las llantas por alteraciones o compartimentos ocultos, y eso se cubre en el punto "Llantas" del paso 7.
- **Obligatorio para continuar**: profundidad y PSI de las 18 posiciones.
- **Una llanta en rojo no bloquea**, pero aparece en el resumen del paso 9.

### Paso 7: Revisión C-TPAT

- **Se dibuja completo desde `puntos_inspeccion`** (activos, ordenados por `orden`). No escribas puntos a mano en la vista.
  - **C-TPAT**: los 18 puntos en dos columnas en pantallas anchas y en una columna en celular. "Puertas interior/exterior" muestra sus 4 subpuntos debajo.
  - **Olores** y **Residuos**, debajo.
- **Valores iniciales**: los puntos C-TPAT empiezan en `cumple`; olores y residuos, en `no_detectado`.
- **Respuestas posibles:**
  - `ok_falla`: Cumple / No cumple / No aplica;
  - `deteccion`: No detectado / Detectado.
- **Al marcar "No cumple" o "Detectado":**
  - se abre el campo de observaciones, que es **obligatorio**;
  - se puede tomar una o más fotos (`tipo = punto`, con `revision_punto_id`).
- **Al guardar**, se escribe una fila en `revision_puntos` por **cada** punto activo (upsert por intercambio y punto).
- **Haz un solo componente de checklist** que reciba la sección y se reutilice para las tres listas.

### Paso 8: Comentarios y problemas

- **Si hay intercambio relacionado**, se muestran arriba, en solo lectura, sus comentarios y sus fotos de problemas, para comparar cómo salió y cómo regresó la unidad.
- **Campos:**
  - comentarios de la caja;
  - comentarios del tracto;
  - observaciones generales.

  Todos son opcionales.
- **Fotos de problemas** (`tipo = problema`): opcionales, cada una indicando si es de la caja o del tracto.

### Paso 9: Resumen y firmas

**Resumen de solo lectura:**
- operador, con alerta si tiene documentos vencidos;
- caja, tracto y datos del viaje;
- sello y sus fotos;
- llantas en rojo o amarillo;
- puntos que no cumplen y olores o residuos detectados, con sus observaciones y fotos;
- comentarios y fotos de problemas.

Cada bloque tiene un enlace "Editar" que lleva a su paso.

**Firmas:**
- **del operador** y **del guardia de caseta**, capturadas en pantalla con un canvas que funcione con el dedo;
- botón "Limpiar" en cada una;
- se guardan como PNG.

**Al pulsar "Completar intercambio"**, se valida el intercambio completo (todas las reglas de los pasos 1 a 9). Si pasa, en una transacción sobre `rh`:
1. Se guardan las firmas.
2. Se llenan las columnas de **copia de datos** con los valores actuales del operador, la caja y el tracto.
3. `status = completado`, `completado_at = now()`.

## 7. Reglas generales

### 7.1 Solo lectura al completar

Un intercambio `completado` **no se modifica**. Esto se valida **en el servidor**, en cada ruta que guarda datos o sube o borra fotos (por ejemplo con una policy o un middleware); no basta con ocultar botones. Si se intenta, se responde 403 con un mensaje claro.

### 7.2 Borradores

- Un guardia puede **retomar** cualquier borrador. No tiene que ser quien lo inició, porque hay cambios de turno.
- Un borrador se puede **cancelar**: `status = cancelado` y `cancelado_at`. Sus fotos se conservan.

### 7.3 Folio

`INT-AAAA-000001`, consecutivo por año, generado al crear el borrador:

```php
DB::connection('rh')->transaction(function () {
    $anio = now()->year;
    DB::connection('rh')->table('folios_intercambio')->insertOrIgnore(['anio' => $anio, 'ultimo' => 0]);
    $fila = DB::connection('rh')->table('folios_intercambio')->where('anio', $anio)->lockForUpdate()->first();
    $siguiente = $fila->ultimo + 1;
    DB::connection('rh')->table('folios_intercambio')->where('anio', $anio)->update(['ultimo' => $siguiente, 'updated_at' => now()]);
    $folio = sprintf('INT-%d-%06d', $anio, $siguiente);
    // ... crear el intercambio con $folio dentro de esta misma transacción
});
```

El `lockForUpdate` evita que dos casetas obtengan el mismo folio al mismo tiempo. Los folios de borradores cancelados no se reutilizan.

### 7.4 Fotos

- **Tomar la foto:** `<input type="file" accept="image/*" capture="environment">`, para que en el celular se abra la cámara trasera.
- **Comprimir en el navegador** antes de subir: lado mayor de 1600 px, JPEG con calidad 0.7.
- **Subir en el momento** en que se toma, no al guardar el paso, con `fetch`.
- **Mostrar el estado** de cada miniatura: subiendo, lista o error.
- **Reintentar** automáticamente si falla, porque en el patio la señal es mala.
- **Ruta:** `intercambios/{id}/{tipo}/{uuid}.jpg`, en un disco **privado**. Si la app ya usa Cloudflare R2, úsalo.
- **Validación en el servidor:** solo `jpg`, `jpeg`, `png` o `webp`, de máximo 5 MB.
- **Ver las fotos:** siempre con sesión, mediante una ruta que verifique permisos o un enlace temporal firmado. Nunca con una URL pública permanente.
- **Borrar:** en borrador se puede borrar una foto y tomarla de nuevo; en completado, no.

### 7.5 Firmas

Canvas → `toDataURL('image/png')` → se envía al servidor → se guarda como archivo en `intercambios/{id}/firmas/`. En la base solo va la ruta.

### 7.6 Configuración

Crea `config/intercambios.php` con:
- `area_operadores` (`OPERADOR`);
- `dias_aviso_vencimiento` (30);
- los umbrales de llantas por grupo de posiciones (direccionales y resto: mínimo y alerta);
- `max_fotos_sello` (2);
- la calidad y el tamaño de compresión de las fotos.

## 8. Otras pantallas

- **Listado de intercambios**, con filtros por folio, número económico de la caja, cliente, tipo de movimiento, status y rango de fechas.
  - Los borradores muestran "Continuar".
  - Los completados muestran "Ver".
- **Detalle de un intercambio completado**, de solo lectura:
  - todo lo del resumen del paso 9, más las firmas;
  - enlace al intercambio relacionado.
- **Catálogos**: pantallas para dar de alta, editar y activar o desactivar clientes, tipos de caja, cajas, líneas transportistas, tractocamiones y destinos. Los puntos de inspección también se pueden desactivar o reordenar, pero no borrar.

**Segunda fase (no es necesaria para la primera entrega):** PDF del intercambio completado.

## 9. Pruebas

En las pruebas, define la conexión `rh` como SQLite en memoria. Crea sus tablas antes de cada prueba con copias de las migraciones de RH guardadas **solo para pruebas** (por ejemplo, `tests/Support/rh-migrations/`), ejecutadas sobre la conexión `rh`. También necesitas versiones mínimas de `users`, `areas`, `info_usuarios`, `documento_operadors` y `tipo_licencias`.

Casos mínimos:
- **Paso 1:**
  - solo aparecen operadores activos del área `OPERADOR`;
  - crea un borrador con folio consecutivo y el guardia autenticado.
- **Folio:** el consecutivo reinicia al cambiar de año.
- **Paso 2:**
  - la caja nueva se registra con su dueño;
  - se rechaza un número económico duplicado para el mismo dueño;
  - se detecta el intercambio relacionado.
- **Paso 5:**
  - se salta cuando la caja va vacía;
  - con caja cargada, exige número de sello y al menos una foto.
- **Paso 6:**
  - la precarga trae marca y tipo del último intercambio del mismo tracto o de la misma caja;
  - los umbrales de color son correctos.
- **Paso 7:**
  - guarda una fila por punto;
  - exige observaciones cuando no cumple o se detecta algo.
- **Completar:**
  - falla si falta algo obligatorio;
  - al completar llena las copias de datos y `completado_at`.
- **Solo lectura:** ninguna ruta de guardado modifica un intercambio completado (403).

## 10. Orden de construcción

Confirma cada fase con el usuario antes de pasar a la siguiente.

1. **Conexión `rh`, configuración y modelos** (sección 5), más los modelos de solo lectura de operadores (sección 4). Verifica que se lean operadores reales.
2. **Pantallas de catálogos** (sección 8).
3. **Estructura del asistente**: navegación entre pasos, guardado de borrador, retomar, cancelar y el listado.
4. **Pasos 1, 2 y 3**, con alta de caja y tracto nuevos y la detección del intercambio relacionado.
5. **Paso 4 y paso 5**, con el componente de fotos (compresión, subida inmediata y reintento).
6. **Paso 7**, con el componente de checklist.
7. **Paso 6**: diagrama de llantas.
8. **Paso 8.**
9. **Paso 9**: resumen, firmas, validación final y cierre.
10. **Vista de detalle** de un intercambio completado.

## 11. Decisiones pendientes

- **Umbrales de llantas para México.** Confirmar con el área de seguridad que la NOM-068-SCT-2 no sea más estricta que los de la sección 6.
- **Documentos vencidos del operador.** Hoy solo se advierte. Hay que decidir si una licencia vencida debe **bloquear** el intercambio.
- **Choferes de permisionarios.** Si llega uno que no es empleado, hoy no se puede registrar: hay que darlo de alta en RH en el área `OPERADOR`.
