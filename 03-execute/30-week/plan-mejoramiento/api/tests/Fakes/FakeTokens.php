<?php

namespace Tests\Fakes;

use App\Application\Port\TokenIssuer;
use App\Domain\User;

/** Fake del emisor de tokens: el token es legible y no se firma. */
final class FakeTokens implements TokenIssuer
{
    public function issue(User $user): string
    {
        return "token|{$user->id}|{$user->role->value}";
    }

    public function decode(string $token): ?array
    {
        $parts = explode('|', $token);

        return count($parts) === 3 && $parts[0] === 'token' ? ['sub' => $parts[1], 'role' => $parts[2]] : null;
    }
}
