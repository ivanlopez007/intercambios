<?php

/*
|--------------------------------------------------------------------------
| Módulo de intercambios
|--------------------------------------------------------------------------
|
| Las tablas del módulo viven en la base de RH (conexión `db_externa`) y se crean con las
| migraciones de la app de RH. Esta app solo tiene los modelos.
|
*/

return [

    // Encabezado de los PDF (contrato de intercambio e inspección C-TPAT).
    'empresa' => [
        'nombre' => env('INTERCAMBIOS_EMPRESA', 'Industrial Hefesto'),
        'punto_intercambio' => env('INTERCAMBIOS_PUNTO', 'Saltillo, Coah.'),
        'logo' => public_path('icons/icon-192.png'),
    ],

    // Áreas (tabla `areas` de RH) cuyos usuarios activos son operadores, separadas por comas.
    // Los choferes de permisionarios también hacen intercambios.
    'areas_operadores' => array_values(array_filter(array_map('trim', explode(',',
        env('INTERCAMBIOS_AREAS_OPERADORES', 'OPERADORES,PERMISIONARIOS,OPERADOR')
    )))),

    // Días antes del vencimiento de licencia o apto médico en que se muestra la alerta amarilla.
    'dias_aviso_vencimiento' => 30,

    // Profundidad de llantas en 32avos de pulgada (FMCSA 49 CFR 393.75).
    // Menos de `minimo`: rojo. De `minimo` a `alerta`: amarillo. Más de `alerta`: verde.
    'llantas' => [
        'posiciones' => 18,
        'direccionales' => ['posiciones' => [1, 2], 'minimo' => 4, 'alerta' => 6],
        'resto' => ['minimo' => 2, 'alerta' => 4],
        'psi_maximo' => 150,
        // Valores con los que empieza cada llanta en un intercambio nuevo (el guardia los ajusta).
        'por_defecto' => ['profundidad_32' => 4, 'psi' => 100],
    ],

    'max_fotos_sello' => 2,

    // Recorrido de fotos de la unidad (paso "Fotos de la unidad"), en el orden en que el guardia
    // camina alrededor. Todas son obligatorias. La clave se guarda en la ruta de la foto
    // (intercambios/{uuid}/recorrido/{clave}/...), así que no se debe cambiar una vez en uso.
    'recorrido' => [
        ['clave' => 'trc_frontal', 'etiqueta' => 'TRC - Frontal', 'unidad' => 'tracto'],
        ['clave' => 'trc_llanta_frontal_izq', 'etiqueta' => 'TRC - Llanta frontal izquierda', 'unidad' => 'tracto'],
        ['clave' => 'trc_lado_izq', 'etiqueta' => 'TRC - Lado izquierdo', 'unidad' => 'tracto'],
        ['clave' => 'trc_llantas_izq_traseras', 'etiqueta' => 'TRC - Llantas izquierda trasera', 'unidad' => 'tracto'],
        ['clave' => 'caja_frontal', 'etiqueta' => 'CAJA - Frontal', 'unidad' => 'caja'],
        ['clave' => 'caja_izq_frontal', 'etiqueta' => 'CAJA - Izquierda frontal', 'unidad' => 'caja'],
        ['clave' => 'caja_izq_trasera', 'etiqueta' => 'CAJA - Izquierda trasera', 'unidad' => 'caja'],
        ['clave' => 'caja_llantas_izq_frontal', 'etiqueta' => 'CAJA - Llantas izquierda frontal', 'unidad' => 'caja'],
        ['clave' => 'caja_llantas_izq_trasera', 'etiqueta' => 'CAJA - Llantas izquierda trasera', 'unidad' => 'caja'],
        ['clave' => 'caja_trasera', 'etiqueta' => 'CAJA - Parte trasera (puertas)', 'unidad' => 'caja'],
        ['clave' => 'caja_der_trasera', 'etiqueta' => 'CAJA - Derecha trasera', 'unidad' => 'caja'],
        ['clave' => 'caja_llantas_der_trasera', 'etiqueta' => 'CAJA - Llantas derecha trasera', 'unidad' => 'caja'],
        ['clave' => 'caja_llantas_der_delantera', 'etiqueta' => 'CAJA - Llantas derecha delantera', 'unidad' => 'caja'],
        ['clave' => 'caja_frontal_der', 'etiqueta' => 'CAJA - Frontal derecha', 'unidad' => 'caja'],
        ['clave' => 'trc_trasera', 'etiqueta' => 'TRC - Parte trasera', 'unidad' => 'tracto'],
        ['clave' => 'trc_llantas_der_traseras', 'etiqueta' => 'TRC - Llantas derecha trasera', 'unidad' => 'tracto'],
        ['clave' => 'trc_lado_der', 'etiqueta' => 'TRC - Lado derecho', 'unidad' => 'tracto'],
        ['clave' => 'trc_llanta_frontal_der', 'etiqueta' => 'TRC - Llanta frontal derecha', 'unidad' => 'tracto'],
    ],

    // Compresión de fotos en el navegador antes de guardarlas y enviarlas.
    'fotos' => [
        'lado_maximo' => 1600,
        'calidad' => 0.7,
        'max_kb' => 5120,
    ],

    // Fotos subidas de intercambios que nunca se registraron (dispositivo perdido, borrador
    // descartado sin señal, etc.). El comando intercambios:limpiar-fotos las borra después de
    // estos días. Debe ser mayor que la antigüedad máxima que acepta el registro (60 días).
    'dias_fotos_huerfanas' => 70,

    // Las fotos de los intercambios se conservan estos años (fecha del intercambio); después el
    // comando intercambios:depurar-fotos las borra. Los datos, la revisión y las firmas se conservan.
    'anios_conservacion_fotos' => 5,

    // Patio: una caja que lleva más de estas horas dentro se marca con alerta de tiempo.
    'horas_alerta_patio' => 72,

];
