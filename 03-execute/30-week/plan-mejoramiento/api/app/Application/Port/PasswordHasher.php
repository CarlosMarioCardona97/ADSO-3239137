<?php

namespace App\Application\Port;

/** Hash de contraseñas (plan D-09). El dominio nunca ve la clave en claro. */
interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;
}
