<?php

namespace App\Domain;

use App\Domain\Exception\BusinessRuleViolation;

/** Operador interno (admin o archivista). Solo conoce el HASH de la clave, nunca la clave (artículo IX). */
final class User
{
    private function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly Role $role,
    ) {}

    /** CA-02.2: el nombre de usuario se guarda en minúsculas y recortado. */
    public static function normalizeUsername(string $raw): string
    {
        $username = mb_strtolower(trim($raw));
        if ($username === '' || mb_strlen($username) > 120) {
            throw BusinessRuleViolation::validation('username', 'es obligatorio y admite máximo 120 caracteres');
        }

        return $username;
    }

    /** CA-02.1 y CA-02.3: por la API solo se crean archivistas; pedir "admin" u otro rol es 422. */
    public static function createArchivist(string $id, string $username, string $passwordHash, string $requestedRole): self
    {
        if ($requestedRole !== Role::Archivist->value) {
            throw BusinessRuleViolation::validation('role', 'solo se permite el rol archivist');
        }

        return new self($id, self::normalizeUsername($username), $passwordHash, Role::Archivist);
    }

    /** El admin inicial lo crea el arranque desde el entorno (plan D-10), nunca la API. */
    public static function bootstrapAdmin(string $id, string $username, string $passwordHash): self
    {
        return new self($id, self::normalizeUsername($username), $passwordHash, Role::Admin);
    }

    public static function restore(string $id, string $username, string $passwordHash, string $role): self
    {
        return new self($id, $username, $passwordHash, Role::from($role));
    }
}
