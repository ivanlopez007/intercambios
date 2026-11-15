<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * La app comparte la base con RH: las pruebas usan RefreshDatabase (migrate:fresh), así que
     * solo pueden correr contra SQLite en memoria. Si la configuración apunta a otra base, se
     * detienen antes de tocarla. El esquema de prueba está en tests/database/migrations.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $base = $app['config']->get("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $base !== ':memory:') {
            throw new RuntimeException(
                "Las pruebas solo corren con SQLite en memoria (phpunit.xml). Conexión actual: {$default} / {$base}."
            );
        }

        $app->make('migrator')->path(base_path('tests/database/migrations'));

        return $app;
    }
}
