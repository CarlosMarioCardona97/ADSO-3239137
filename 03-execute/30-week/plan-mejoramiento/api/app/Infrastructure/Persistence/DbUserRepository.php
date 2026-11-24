<?php

namespace App\Infrastructure\Persistence;

use App\Application\Port\UserRepository;
use App\Domain\User;
use Illuminate\Database\ConnectionInterface;

/** Q10 · Usuarios internos. `password_hash` nunca sale de este adaptador hacia HTTP. */
final class DbUserRepository implements UserRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function findByUsername(string $username): ?User
    {
        $row = $this->db->table('user')->where('username', $username)->first();

        return $row === null ? null : User::restore($row->id, $row->username, $row->password_hash, $row->role);
    }

    public function usernameExists(string $username): bool
    {
        return $this->db->table('user')->where('username', $username)->exists();
    }

    public function add(User $user): void
    {
        $this->db->table('user')->insert([
            'id' => $user->id,
            'username' => $user->username,
            'password_hash' => $user->passwordHash,
            'role' => $user->role->value,
        ]);
    }
}
