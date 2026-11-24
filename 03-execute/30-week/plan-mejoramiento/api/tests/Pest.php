<?php

/*
| Las pruebas de característica (Feature) arrancan Laravel y usan PostgreSQL real (base archive_test):
| RefreshDatabase corre las migraciones una vez y envuelve cada prueba en una transacción.
| Las pruebas unitarias del dominio y de la aplicación son PHP puro: no arrancan Laravel.
*/

use App\Application\Port\PasswordHasher;
use App\Application\Port\TokenIssuer;
use App\Application\Port\UserRepository;
use App\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

require_once __DIR__.'/Fakes/InMemory.php';

pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in('Feature');

const CATEGORY_CONTRACTS = '11111111-1111-4111-8111-111111111111';
const CATEGORY_RECORDS = '44444444-4444-4444-8444-444444444444';

/** Crea un usuario con el rol dado y devuelve los encabezados con su token. */
function headersFor(string $role, string $username = ''): array
{
    $username = $username !== '' ? $username : $role.'-'.Str::random(6);
    $user = User::restore((string) Str::uuid(), strtolower($username), app(PasswordHasher::class)->hash('Clave-segura-1'), $role);
    app(UserRepository::class)->add($user);

    return ['Authorization' => 'Bearer '.app(TokenIssuer::class)->issue($user), 'Accept' => 'application/json'];
}

/** Crea un expediente por la API y devuelve su JSON. */
function createDossier(array $admin, string $filing, int $units = 1, string $title = 'Contrato de obra 045'): array
{
    return test()->postJson('/api/v1/dossiers', [
        'title' => $title,
        'producing_office' => 'Secretaría de Infraestructura',
        'filing_number' => $filing,
        'category_id' => CATEGORY_CONTRACTS,
        'total_units' => $units,
    ], $admin)->assertCreated()->json();
}

/** Crea un funcionario por la API y devuelve su JSON. */
function createOfficial(array $headers, string $document, string $name = 'María Pérez'): array
{
    return test()->postJson('/api/v1/officials', ['full_name' => $name, 'document_number' => $document], $headers)
        ->assertCreated()->json();
}
