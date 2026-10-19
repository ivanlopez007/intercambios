<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoIntercambioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaExternoController;
use App\Http\Controllers\IntercambioController;
use App\Http\Controllers\PatioController;
use App\Http\Controllers\QrEmpresaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VisitaExternoController;
use App\Support\CatalogosIntercambio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('login.store');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Cada rol aterriza en su propio menú.
    Route::get('/', function (Request $request) {
        return $request->user()->rol === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('caseta.menu');
    })->name('home');

    /*
    |--------------------------------------------------------------------------
    | Caseta (uso en celulares; también accesible para administradores)
    |--------------------------------------------------------------------------
    */
    Route::middleware('rol:caseta,admin')
        ->prefix('caseta')
        ->name('caseta.')
        ->group(function () {
            Route::view('/', 'caseta.menu')->name('menu');

            // Entradas y salidas de visitantes
            Route::view('/entrada', 'caseta.entradas-salidas.entrada')->name('entrada');
            Route::view('/salida', 'caseta.entradas-salidas.salida')->name('salida');
            Route::post('/visitas/entrada', [VisitaExternoController::class, 'store'])
                ->name('visitas.entrada');
            Route::post('/visitas/salida', [VisitaExternoController::class, 'registrarSalida'])
                ->name('visitas.salida');
            Route::get('/visitas/activas', [VisitaExternoController::class, 'activas'])
                ->name('visitas.activas');

            // Token vigente para que el service worker envíe los registros guardados sin conexión.
            Route::get('/csrf', fn () => response()
                ->json(['token' => csrf_token()])
                ->header('Cache-Control', 'no-store'))
                ->name('csrf');

            // QR de empresas
            Route::get('/qr', [QrEmpresaController::class, 'index'])->name('qr');
            Route::get('/empresas', [QrEmpresaController::class, 'catalogo'])->name('empresas');
            Route::get('/qr/{empresa}.svg', [QrEmpresaController::class, 'show'])
                ->whereNumber('empresa')
                ->name('qr.show');

            // Intercambios (formulario en desarrollo)
            Route::view('/intercambios/entrada', 'caseta.intercambios.entrada')
                ->name('intercambios.entrada');
        });

    /*
    |--------------------------------------------------------------------------
    | Administración
    |--------------------------------------------------------------------------
    */
    Route::middleware('rol:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');

            Route::get('/entradas-salidas', [VisitaExternoController::class, 'historial'])
                ->name('entradas-salidas.historial');

            // Intercambios. El asistente (nuevo) funciona sin conexión: guarda el borrador en el
            // dispositivo y el service worker envía las fotos y el intercambio al volver la señal.
            Route::get('/intercambios/historial', [IntercambioController::class, 'index'])
                ->name('intercambios.historial');
            Route::get('/intercambios/nuevo', [IntercambioController::class, 'create'])
                ->name('intercambios.nuevo');
            Route::get('/intercambios/datos', [IntercambioController::class, 'datos'])
                ->name('intercambios.datos');
            Route::post('/intercambios', [IntercambioController::class, 'store'])
                ->name('intercambios.store');
            Route::post('/intercambios/fotos', [IntercambioController::class, 'subirFoto'])
                ->name('intercambios.fotos.store');
            Route::post('/intercambios/descartar', [IntercambioController::class, 'descartar'])
                ->name('intercambios.descartar');
            Route::get('/intercambios/{intercambio}', [IntercambioController::class, 'show'])
                ->whereNumber('intercambio')
                ->name('intercambios.show');
            Route::get('/intercambios/{intercambio}/fotos/{foto}', [IntercambioController::class, 'foto'])
                ->whereNumber(['intercambio', 'foto'])
                ->name('intercambios.foto');
            Route::get('/intercambios/{intercambio}/pdf/{documento}', [IntercambioController::class, 'pdf'])
                ->whereNumber('intercambio')
                ->whereIn('documento', ['contrato', 'ctpat'])
                ->name('intercambios.pdf');
            Route::get('/intercambios/{intercambio}/firmas/{quien}', [IntercambioController::class, 'firma'])
                ->whereNumber('intercambio')
                ->whereIn('quien', ['operador', 'guardia'])
                ->name('intercambios.firma');
            Route::get('/intercambios/patio', [PatioController::class, 'index'])
                ->name('intercambios.patio');

            // Catálogos de intercambios (se activan o desactivan, nunca se borran).
            Route::prefix('catalogos/{catalogo}')
                ->whereIn('catalogo', array_keys(CatalogosIntercambio::todos()))
                ->name('catalogos.')
                ->controller(CatalogoIntercambioController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                    Route::patch('/{id}/estado', 'estado')->whereNumber('id')->name('estado');
                });

            Route::resource('empresas', EmpresaExternoController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::resource('usuarios', UsuarioController::class)
                ->except(['show', 'destroy']);
            Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])
                ->name('usuarios.estado');
        });
});

/*
|--------------------------------------------------------------------------
| URL anteriores (marcadores y accesos directos guardados en los celulares)
|--------------------------------------------------------------------------
*/

Route::permanentRedirect('/hefesto', '/caseta');
Route::permanentRedirect('/hefesto/menu', '/caseta');
Route::permanentRedirect('/hefesto/entrada', '/caseta/entrada');
Route::permanentRedirect('/hefesto/salida', '/caseta/salida');
Route::permanentRedirect('/hefesto/generar-qr', '/caseta/qr');
Route::permanentRedirect('/hefesto/intercambio', '/caseta/intercambios/entrada');
Route::permanentRedirect('/dashboard', '/admin');
Route::permanentRedirect('/historial-entradas-salidas', '/admin/entradas-salidas');
Route::permanentRedirect('/historial', '/admin/intercambios/historial');
Route::permanentRedirect('/patio', '/admin/intercambios/patio');
Route::permanentRedirect('/empresas', '/admin/empresas');
