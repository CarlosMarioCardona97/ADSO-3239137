<?php

namespace App\Application\Port;

/** Lectura de las cinco categorías sembradas (no tienen mantenimiento). */
interface CategoryReader
{
    /** @return list<array{id: string, name: string}> en orden por nombre. */
    public function all(): array;

    public function nameOf(string $id): ?string;
}
