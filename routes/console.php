<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fotos de intercambios que nunca se registraron (requiere el cron de `php artisan schedule:run`).
Schedule::command('intercambios:limpiar-fotos')->dailyAt('03:30')->withoutOverlapping();

// Fotos de intercambios con más de config('intercambios.anios_conservacion_fotos') años.
Schedule::command('intercambios:depurar-fotos')->dailyAt('04:00')->withoutOverlapping();
