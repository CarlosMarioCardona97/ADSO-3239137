<?php

namespace App\Application\Port;

/** Almacenamiento de las carátulas (plan D-08). La base solo guarda la clave opaca. */
interface CoverStorage
{
    /** Guarda el binario y devuelve una clave opaca nueva. */
    public function put(string $bytes, string $extension): string;

    public function get(string $key): ?string;

    public function delete(string $key): void;
}
