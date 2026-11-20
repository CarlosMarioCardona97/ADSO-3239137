<?php

namespace App\Application\Service;

use App\Application\Port\PasswordHasher;
use App\Application\Port\TokenIssuer;
use App\Application\Port\UserRepository;
use App\Domain\Exception\BusinessRuleViolation;

/** HU-01 · Iniciar sesión. */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {}

    /** @return array{access_token: string, token_type: string, expires_in: int, role: string} */
    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername(mb_strtolower(trim($username)));

        // CA-01.2: el mismo mensaje si falla el usuario o la clave.
        if ($user === null || ! $this->hasher->verify($password, $user->passwordHash)) {
            throw new BusinessRuleViolation('INVALID_CREDENTIALS', 'Usuario o contraseña incorrectos.');
        }

        return [
            'access_token' => $this->tokens->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => TokenIssuer::TTL_SECONDS,
            'role' => $user->role->value,
        ];
    }
}
