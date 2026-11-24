<?php

namespace App\Console\Commands;

use App\Application\Port\IdGenerator;
use App\Application\Port\PasswordHasher;
use App\Application\Port\UserRepository;
use App\Domain\User;
use Illuminate\Console\Command;

/**
 * T-17 · Crea el administrador inicial desde ADMIN_USERNAME y ADMIN_PASSWORD (plan D-10).
 * Nadie otorga el rol admin en ejecución por la API. Si ya existe, no hace nada.
 */
final class BootstrapAdminCommand extends Command
{
    protected $signature = 'archive:bootstrap-admin';

    protected $description = 'Crea el usuario admin inicial a partir de las variables de entorno';

    public function handle(UserRepository $users, PasswordHasher $hasher, IdGenerator $ids): int
    {
        $username = (string) env('ADMIN_USERNAME', '');
        $password = (string) env('ADMIN_PASSWORD', '');
        if ($username === '' || strlen($password) < 8) {
            $this->error('Defina ADMIN_USERNAME y ADMIN_PASSWORD (mínimo 8 caracteres) en el entorno.');

            return self::FAILURE;
        }

        $normalized = User::normalizeUsername($username);
        if ($users->usernameExists($normalized)) {
            $this->info("El admin «{$normalized}» ya existe.");

            return self::SUCCESS;
        }

        $users->add(User::bootstrapAdmin($ids->next(), $normalized, $hasher->hash($password)));
        $this->info("Admin «{$normalized}» creado.");   // la clave nunca se imprime (RNF-3)

        return self::SUCCESS;
    }
}
