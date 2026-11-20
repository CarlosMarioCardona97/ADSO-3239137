<?php

namespace App\Application\Service;

use App\Application\Port\IdGenerator;
use App\Application\Port\PasswordHasher;
use App\Application\Port\UserRepository;
use App\Domain\Exception\BusinessRuleViolation;
use App\Domain\User;

/** HU-02 · El admin da de alta archivistas. */
final class CreateArchivistService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly IdGenerator $ids,
    ) {}

    public function create(string $username, string $password, string $role): User
    {
        if (mb_strlen($password) < 8) {
            throw BusinessRuleViolation::validation('password', 'debe tener al menos 8 caracteres');
        }

        $user = User::createArchivist($this->ids->next(), $username, $this->hasher->hash($password), $role);

        if ($this->users->usernameExists($user->username)) {
            throw new BusinessRuleViolation('USERNAME_TAKEN', "El nombre de usuario {$user->username} ya existe.");
        }
        $this->users->add($user);

        return $user;
    }
}
