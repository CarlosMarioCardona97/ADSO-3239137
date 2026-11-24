<?php

namespace App\Infrastructure\Security;

use App\Application\Port\Clock;
use App\Application\Port\TokenIssuer;
use App\Domain\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/** Adaptador JWT HS256 de 8 horas (plan D-11). Lleva solo `sub` y `role`: ningún dato personal. */
final class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(
        private readonly string $secret,
        private readonly Clock $clock,
    ) {}

    public function issue(User $user): string
    {
        $this->assertSecret();
        $now = $this->clock->now()->getTimestamp();

        return JWT::encode([
            'sub' => $user->id,
            'role' => $user->role->value,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ], $this->secret, 'HS256');
    }

    public function decode(string $token): ?array
    {
        $this->assertSecret();
        try {
            JWT::$timestamp = $this->clock->now()->getTimestamp();
            $claims = (array) JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (Throwable) {
            return null;   // firma inválida, token vencido o mal formado → 401
        }

        if (! is_string($claims['sub'] ?? null) || ! is_string($claims['role'] ?? null)) {
            return null;
        }

        return ['sub' => $claims['sub'], 'role' => $claims['role']];
    }

    /** El secreto viene del entorno (artículo IX) y debe ser largo para que HS256 sea seguro. */
    private function assertSecret(): void
    {
        if (strlen($this->secret) < 32) {
            throw new \RuntimeException('JWT_SECRET debe tener al menos 32 caracteres.');
        }
    }
}
