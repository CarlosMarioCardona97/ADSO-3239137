<?php

namespace App\Infrastructure\Security;

use App\Application\Port\PasswordHasher;

/** Adaptador de hash con bcrypt (plan D-09). La clave en claro nunca se guarda ni se registra. */
final class BcryptPasswordHasher implements PasswordHasher
{
    public function __construct(private readonly int $cost = 12) {}

    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => $this->cost]);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
