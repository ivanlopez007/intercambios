<?php

/*
|--------------------------------------------------------------------------
| Menús laterales
|--------------------------------------------------------------------------
|
| Cada elemento lleva una ruta ('route') o un submenú ('submenu'). 'active' es el patrón
| de nombre de ruta (request()->routeIs) que marca la opción como activa.
|
*/

return [

    'caseta' => [
        [
            'title' => 'Inicio',
            'icon' => 'fa-solid fa-house',
            'route' => 'caseta.menu',
            'active' => 'caseta.menu',
        ],
        [
            'title' => 'Entradas y Salidas',
            'icon' => 'fa-solid fa-right-left',
            'submenu' => [
                ['title' => 'Entrada', 'icon' => 'fa-solid fa-right-to-bracket', 'route' => 'caseta.entrada', 'active' => 'caseta.entrada'],
                ['title' => 'Salida', 'icon' => 'fa-solid fa-right-from-bracket', 'route' => 'caseta.salida', 'active' => 'caseta.salida'],
            ],
        ],
        [
            'title' => 'QR para Empresas',
            'icon' => 'fa-solid fa-qrcode',
            'route' => 'caseta.qr',
            'active' => 'caseta.qr*',
        ],
    ],

    'admin' => [
        [
            'title' => 'Dashboard',
            'icon' => 'fa-solid fa-chart-pie',
            'route' => 'admin.dashboard',
            'active' => 'admin.dashboard',
        ],
        [
            'title' => 'Intercambios',
            'icon' => 'fa-solid fa-rotate',
            'submenu' => [
                ['title' => 'Nuevo intercambio', 'route' => 'admin.intercambios.nuevo', 'active' => 'admin.intercambios.nuevo'],
                ['title' => 'Historial de Intercambios', 'route' => 'admin.intercambios.historial', 'active' => ['admin.intercambios.historial', 'admin.intercambios.show']],
                ['title' => 'Patio', 'route' => 'admin.intercambios.patio', 'active' => 'admin.intercambios.patio'],
            ],
        ],
        [
            'title' => 'Entradas y Salidas',
            'icon' => 'fa-solid fa-right-left',
            'submenu' => [
                ['title' => 'Historial de Visitas', 'route' => 'admin.entradas-salidas.historial', 'active' => 'admin.entradas-salidas.*'],
            ],
        ],
        [
            'title' => 'Catálogos',
            'icon' => 'fa-solid fa-folder',
            'submenu' => [
                ['title' => 'Empresas', 'route' => 'admin.empresas.index', 'active' => 'admin.empresas.*'],
                ['title' => 'Clientes', 'route' => 'admin.catalogos.index', 'params' => ['catalogo' => 'clientes'], 'active' => 'admin.catalogos.*'],
                ['title' => 'Cajas', 'route' => 'admin.catalogos.index', 'params' => ['catalogo' => 'cajas'], 'active' => 'admin.catalogos.*'],
                ['title' => 'Tipos de caja', 'route' => 'admin.catalogos.index', 'params' => ['catalogo' => 'tipos-caja'], 'active' => 'admin.catalogos.*'],
                ['title' => 'Tractocamiones', 'route' => 'admin.catalogos.index', 'params' => ['catalogo' => 'tractocamiones'], 'active' => 'admin.catalogos.*'],
                ['title' => 'Líneas transportistas', 'route' => 'admin.catalogos.index', 'params' => ['catalogo' => 'lineas-transportistas'], 'active' => 'admin.catalogos.*'],
                ['title' => 'Orígenes y destinos', 'route' => 'admin.catalogos.index', 'params' => ['catalogo' => 'destinos'], 'active' => 'admin.catalogos.*'],
                ['title' => 'Puntos de inspección', 'route' => 'admin.catalogos.index', 'params' => ['catalogo' => 'puntos-inspeccion'], 'active' => 'admin.catalogos.*'],
            ],
        ],
        [
            'title' => 'Usuarios',
            'icon' => 'fa-solid fa-users-gear',
            'route' => 'admin.usuarios.index',
            'active' => 'admin.usuarios.*',
        ],
        [
            'title' => 'Ir a Caseta',
            'icon' => 'fa-solid fa-mobile-screen',
            'route' => 'caseta.menu',
            'active' => 'caseta.*',
        ],
    ],

];
