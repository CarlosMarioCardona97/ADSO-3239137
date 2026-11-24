<?php

namespace App\Providers;

use App\Application\Port\CategoryReader;
use App\Application\Port\Clock;
use App\Application\Port\CoverStorage;
use App\Application\Port\DossierRepository;
use App\Application\Port\IdGenerator;
use App\Application\Port\LoanRepository;
use App\Application\Port\OfficialRepository;
use App\Application\Port\PasswordHasher;
use App\Application\Port\ReportReader;
use App\Application\Port\TokenIssuer;
use App\Application\Port\UserRepository;
use App\Infrastructure\Persistence\DbCategoryReader;
use App\Infrastructure\Persistence\DbDossierRepository;
use App\Infrastructure\Persistence\DbLoanRepository;
use App\Infrastructure\Persistence\DbOfficialRepository;
use App\Infrastructure\Persistence\DbReportReader;
use App\Infrastructure\Persistence\DbUserRepository;
use App\Infrastructure\Security\BcryptPasswordHasher;
use App\Infrastructure\Security\JwtTokenIssuer;
use App\Infrastructure\Security\SystemClock;
use App\Infrastructure\Security\UuidGenerator;
use App\Infrastructure\Storage\LocalCoverStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

/**
 * ÚNICO punto de composición (artículo III): aquí, y solo aquí, cada puerto se une con su adaptador.
 * Cambiar un adaptador (por ejemplo, carátulas en S3) es cambiar una línea de este archivo (OCP).
 */
final class PortBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, fn () => new SystemClock((int) env('CLOCK_OFFSET_DAYS', 0)));
        $this->app->singleton(IdGenerator::class, UuidGenerator::class);
        $this->app->singleton(PasswordHasher::class, fn () => new BcryptPasswordHasher((int) env('BCRYPT_ROUNDS', 12)));
        $this->app->singleton(TokenIssuer::class, fn ($app) => new JwtTokenIssuer(
            (string) env('JWT_SECRET', ''),
            $app->make(Clock::class),
        ));
        $this->app->singleton(CoverStorage::class, fn () => new LocalCoverStorage(Storage::disk('local')));

        $db = fn ($app) => $app->make(ConnectionInterface::class);
        $this->app->singleton(CategoryReader::class, fn ($app) => new DbCategoryReader($db($app)));
        $this->app->singleton(UserRepository::class, fn ($app) => new DbUserRepository($db($app)));
        $this->app->singleton(DossierRepository::class, fn ($app) => new DbDossierRepository($db($app)));
        $this->app->singleton(OfficialRepository::class, fn ($app) => new DbOfficialRepository($db($app)));
        $this->app->singleton(LoanRepository::class, fn ($app) => new DbLoanRepository($db($app)));
        $this->app->singleton(ReportReader::class, fn ($app) => new DbReportReader($db($app)));
    }
}
