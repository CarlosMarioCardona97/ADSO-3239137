<?php

namespace App\Infrastructure\Persistence;

/** PostgreSQL rechaza con error un texto que no es UUID en una columna uuid; se filtra antes de consultar. */
final class Uuid
{
    public static function isValid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
