<?php

use App\Application\Port\DossierRepository;
use App\Application\Port\OfficialRepository;
use App\Application\Port\UserRepository;
use App\Domain\Dossier;
use App\Domain\Official;
use App\Domain\User;
use App\Domain\ValueObject\FilingNumber;
use App\Domain\ValueObject\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// T-12 · Repositorios de expediente, funcionario y usuario contra PostgreSQL real.

function newDossier(string $filing, string $title = 'Acta de concejo 12'): Dossier
{
    return Dossier::register((string) Str::uuid(), $title, 'Secretaría General', FilingNumber::fromString($filing), CATEGORY_RECORDS, 'Actas', Quantity::of(2));
}

it('guarda y recupera un expediente con su categoría', function () {
    $repo = app(DossierRepository::class);
    $dossier = newDossier('2025-000001');
    $repo->add($dossier);

    $found = $repo->findActive($dossier->id);
    expect($found->title())->toBe('Acta de concejo 12')
        ->and($found->categoryName())->toBe('Actas')
        ->and($found->availableUnits())->toBe(2)
        ->and($found->version())->toBe(1);
});

it('busca solo activos, por texto parcial y en orden por título (Q1)', function () {
    $repo = app(DossierRepository::class);
    $repo->add(newDossier('2025-000002', 'Acta B'));
    $repo->add(newDossier('2025-000003', 'Acta A'));
    $retired = newDossier('2025-000004', 'Acta C');
    $repo->add($retired);
    $retired->retire(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $repo->saveRetirement($retired);

    $page = $repo->search('acta', null, 1, 20);
    expect($page->total)->toBe(2)
        ->and(array_map(fn (Dossier $d) => $d->title(), $page->items))->toBe(['Acta A', 'Acta B'])
        ->and($repo->findActive($retired->id))->toBeNull()
        ->and($repo->find($retired->id))->not->toBeNull();
});

it('permite reutilizar el radicado de un expediente dado de baja (índice parcial, ADR-003)', function () {
    $repo = app(DossierRepository::class);
    $first = newDossier('2025-000005');
    $repo->add($first);
    expect($repo->filingNumberInUse('2025-000005'))->toBeTrue();

    $first->retire(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $repo->saveRetirement($first);
    expect($repo->filingNumberInUse('2025-000005'))->toBeFalse();
    $repo->add(newDossier('2025-000005'));
    expect(DB::table('dossier')->where('filing_number', '2025-000005')->count())->toBe(2);
});

it('guarda funcionarios y usuarios sin exponer la clave', function () {
    $officials = app(OfficialRepository::class);
    $official = Official::register((string) Str::uuid(), 'Juan Gómez', '1075000099');
    $officials->add($official);
    expect($officials->find($official->id)->fullName)->toBe('Juan Gómez')
        ->and($officials->documentInUse('1075000099'))->toBeTrue()
        ->and($officials->openItemsCount($official->id))->toBe(0);

    $users = app(UserRepository::class);
    $users->add(User::createArchivist((string) Str::uuid(), 'Ana', password_hash('x', PASSWORD_BCRYPT), 'archivist'));
    expect($users->findByUsername('ana')->passwordHash)->toStartWith('$2y$');
});

it('no tiene ninguna columna con DEFAULT (T-02)', function () {
    $defaults = DB::selectOne(
        "SELECT count(*) AS total FROM information_schema.columns
          WHERE table_schema = 'archive' AND column_default IS NOT NULL"
    );
    expect((int) $defaults->total)->toBe(0)
        ->and(DB::table('category')->orderBy('name')->pluck('name')->all())
        ->toBe(['Actas', 'Contratos', 'Jurídico', 'Personal', 'Predial']);
});
