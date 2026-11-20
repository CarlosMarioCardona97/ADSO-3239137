<?php

namespace App\Application\Port;

use App\Domain\User;

interface UserRepository
{
    public function findByUsername(string $username): ?User;

    public function usernameExists(string $username): bool;

    public function add(User $user): void;
}
