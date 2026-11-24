<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Protección: las pruebas de característica borran y recrean el esquema (RefreshDatabase).
     * Si la conexión no apunta a una base terminada en "_test", se detienen antes de tocar nada.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Las pruebas solo corren contra una base *_test; se recibió «{$database}».");
        }
    }
}
