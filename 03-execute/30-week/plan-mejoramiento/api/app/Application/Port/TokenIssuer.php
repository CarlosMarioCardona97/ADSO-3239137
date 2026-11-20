<?php

namespace App\Application\Port;

use App\Domain\User;

/** Emite y verifica el token de sesión (JWT de 8 horas, plan D-11). */
interface TokenIssuer
{
    public const TTL_SECONDS = 28800;

    public function issue(User $user): string;

    /** @return array{sub: string, role: string}|null null si el token es inválido o venció. */
    public function decode(string $token): ?array;
}
